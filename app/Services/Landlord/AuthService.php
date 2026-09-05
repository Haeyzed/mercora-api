<?php

declare(strict_types=1);

namespace App\Services\Landlord;

use App\Enums\Landlord\ApiKeyStatus;
use App\Enums\Landlord\PlanStatus;
use App\Enums\Landlord\RoleName;
use App\Enums\Media\MediaCollection;
use App\Http\Resources\Landlord\Auth\LoginPayload;
use App\Models\Landlord\ApiKey;
use App\Models\Landlord\Notice;
use App\Models\Landlord\Plan;
use App\Models\Landlord\User;
use App\Notifications\Landlord\ResetPasswordNotification;
use App\Services\Landlord\Notifications\NotificationDispatcher;
use App\Services\Landlord\Tenants\TenantService;
use App\Services\Media\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Activitylog\Models\Activity;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * Authenticates landlord users and manages their account profile.
 *
 * Domain: landlord panel login, registration, password recovery, profile, avatar, and GDPR self-service.
 *
 * Invariants:
 * - Only active users with valid credentials receive a token.
 * - Logout revokes only the token used for the current request.
 * - Forgot password never reveals whether an email exists.
 * - Password reset and change revoke all issued API tokens.
 * - Public registration respects registration.* settings.
 * - Personal data export/erase respect compliance.* flags.
 *
 * Side effects: creates users/tenants/subscriptions; creates and deletes Sanctum tokens;
 * stores avatar media; sends mail; soft-deletes users; reads SettingService.
 */
class AuthService
{
    public function __construct(
        private MediaService $mediaService,
        private SettingService $settings,
        private TenantService $tenants,
        private SubscriptionService $subscriptions,
        private NotificationDispatcher $notifications,
    ) {}

    /**
     * Authenticate a landlord user and issue an API token.
     *
     * @param  array{email: string, password: string, device_name?: string}  $credentials
     *
     * @throws ValidationException When credentials are invalid or the user is inactive.
     */
    public function login(array $credentials): LoginPayload
    {
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! $user->is_active || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $expiresAt = null;
        $timeout = max(0, (int) $this->settings->value('security.session_timeout_minutes', 120));

        if ($timeout > 0) {
            $expiresAt = now()->addMinutes($timeout);
        }

        return new LoginPayload(
            user: $user,
            token: $user->createToken(
                $credentials['device_name'] ?? 'landlord',
                ['*'],
                $expiresAt,
            )->plainTextToken,
        );
    }

    /**
     * Register a landlord user with a new tenant (and optional default plan).
     *
     * @param  array{
     *     name: string,
     *     email: string,
     *     password: string,
     *     tenant_name: string,
     *     domain: string,
     *     terms_accepted?: bool,
     *     device_name?: string
     * }  $data
     *
     * @throws ValidationException When registration is disabled or validation policy fails.
     * @throws Throwable
     */
    public function register(array $data): LoginPayload
    {
        if (! (bool) $this->settings->value('registration.tenant_registration_enabled', true)) {
            throw ValidationException::withMessages([
                'email' => ['Tenant registration is disabled.'],
            ]);
        }

        if ((bool) $this->settings->value('registration.require_terms_acceptance', true) && empty($data['terms_accepted'])) {
            throw ValidationException::withMessages([
                'terms_accepted' => ['Terms must be accepted.'],
            ]);
        }

        $this->ensureEmailDomainAllowed($data['email']);

        $user = DB::transaction(function () use ($data): User {
            $requiresVerification = (bool) $this->settings->value('registration.require_email_verification', true);

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
                'email_verified_at' => $requiresVerification ? null : now(),
            ]);

            $operator = Role::findOrCreate(RoleName::Operator->value, 'web');
            $user->assignRole($operator);

            $tenant = $this->tenants->store([
                'name' => $data['tenant_name'],
                'domain' => $data['domain'],
            ]);

            $planSlug = $this->settings->value('registration.default_plan_slug');

            if (is_string($planSlug) && $planSlug !== '') {
                $plan = Plan::query()
                    ->where('status', PlanStatus::Active)
                    ->where('slug', $planSlug)
                    ->first();

                if ($plan !== null) {
                    $this->subscriptions->store([
                        'tenant_id' => $tenant->id,
                        'plan_id' => $plan->id,
                        'trial_days' => (int) $this->settings->value('registration.trial_days', 14),
                    ]);
                }
            }

            return $user;
        });

        if ((bool) $this->settings->value('registration.send_welcome_email', true)) {
            $this->sendWelcomeNotices($user);
        }

        return $this->login([
            'email' => $data['email'],
            'password' => $data['password'],
            'device_name' => $data['device_name'] ?? 'landlord',
        ]);
    }

    /**
     * Revoke the token used for the current request.
     */
    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }

    /**
     * Send a password reset link without revealing whether the email exists.
     */
    public function forgotPassword(string $email): void
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! $user->is_active) {
            return;
        }

        $token = Password::broker('users')->createToken($user);

        $user->notify(new ResetPasswordNotification($token));
    }

    /**
     * Reset a landlord user's password using the password broker.
     *
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $data
     *
     * @throws ValidationException When the token or email is invalid.
     */
    public function resetPassword(array $data): void
    {
        $user = User::query()->where('email', $data['email'])->first();

        if ($user !== null && ! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $status = Password::broker('users')->reset(
            $data,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                ])->save();

                if ($this->settings->value('security.revoke_tokens_on_password_change', true)) {
                    $user->tokens()->delete();
                }
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }

    /**
     * Update the authenticated landlord user's profile and optional avatar.
     *
     * @param  array{name?: string, email?: string}  $data
     *
     * @throws Throwable
     */
    public function updateProfile(User $user, array $data, ?UploadedFile $avatar = null): User
    {
        $user->fill($data);
        $user->save();

        if ($avatar !== null) {
            $this->mediaService->replace($user, $avatar, MediaCollection::Avatar);
        }

        return $user->refresh()->loadMissing('roles');
    }

    /**
     * Replace the authenticated user's avatar.
     */
    public function replaceAvatar(User $user, UploadedFile $avatar): Media
    {
        return $this->mediaService->replace($user, $avatar, MediaCollection::Avatar);
    }

    /**
     * Remove the authenticated user's avatar.
     */
    public function removeAvatar(User $user): void
    {
        $this->mediaService->removeCollection($user, MediaCollection::Avatar);
    }

    /**
     * Change the authenticated landlord user's password.
     *
     * Revokes all API tokens when {@see security.revoke_tokens_on_password_change} is enabled.
     *
     * @param  array{current_password: string, password: string}  $data
     *
     * @throws ValidationException When the current password is incorrect.
     */
    public function changePassword(User $user, array $data): void
    {
        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('auth.password')],
            ]);
        }

        $user->forceFill([
            'password' => $data['password'],
        ])->save();

        if ($this->settings->value('security.revoke_tokens_on_password_change', true)) {
            $user->tokens()->delete();
        }
    }

    /**
     * Export the authenticated user's personal data package.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException When export is disabled.
     */
    public function exportPersonalData(User $user): array
    {
        if (! (bool) $this->settings->value('compliance.export_personal_data_enabled', true)) {
            throw ValidationException::withMessages([
                'export' => ['Personal data export is disabled.'],
            ]);
        }

        return [
            'exported_at' => now()->toIso8601String(),
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'roles' => $user->getRoleNames()->values()->all(),
            'notices' => Notice::query()
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->get(['id', 'title', 'body', 'channel', 'status', 'created_at'])
                ->toArray(),
            'api_keys' => ApiKey::query()
                ->where('user_id', $user->id)
                ->get(['id', 'name', 'prefix', 'status', 'expires_at', 'revoked_at', 'last_used_at', 'created_at'])
                ->toArray(),
            'activities' => Activity::query()
                ->where('causer_type', $user->getMorphClass())
                ->where('causer_id', $user->getKey())
                ->orderByDesc('id')
                ->limit(200)
                ->get(['id', 'event', 'description', 'created_at'])
                ->toArray(),
        ];
    }

    /**
     * Anonymize and soft-delete the authenticated user's personal data.
     *
     * @throws ValidationException When erase is disabled or the user is the last Super Admin.
     */
    public function erasePersonalData(User $user): void
    {
        if (! (bool) $this->settings->value('compliance.erase_personal_data_enabled', true)) {
            throw ValidationException::withMessages([
                'erase' => ['Personal data erasure is disabled.'],
            ]);
        }

        if ($user->hasRole(RoleName::SuperAdmin->value)
            && User::query()->role(RoleName::SuperAdmin->value)->where('is_active', true)->count() <= 1
        ) {
            throw ValidationException::withMessages([
                'erase' => ['The last Super Admin cannot erase their account.'],
            ]);
        }

        DB::transaction(function () use ($user): void {
            $this->mediaService->removeCollection($user, MediaCollection::Avatar);
            $user->tokens()->delete();

            ApiKey::query()->where('user_id', $user->id)->each(function (ApiKey $key): void {
                if ($key->revoked_at === null) {
                    $key->update([
                        'status' => ApiKeyStatus::Revoked,
                        'revoked_at' => now(),
                    ]);
                }
            });

            $user->forceFill([
                'name' => 'Deleted User',
                'email' => 'deleted+'.$user->id.'@erased.invalid',
                'password' => Str::password(32),
                'is_active' => false,
                'email_verified_at' => null,
                'remember_token' => null,
            ])->save();

            $user->delete();
        });
    }

    /**
     * @throws ValidationException
     */
    private function ensureEmailDomainAllowed(string $email): void
    {
        $allowed = $this->settings->value('registration.allowed_email_domains', []);

        if (! is_array($allowed) || $allowed === []) {
            return;
        }

        $domain = Str::lower(Str::after($email, '@'));
        $normalized = array_map(
            static fn (mixed $value): string => Str::lower((string) $value),
            $allowed,
        );

        if (! in_array($domain, $normalized, true)) {
            throw ValidationException::withMessages([
                'email' => ['This email domain is not allowed to register.'],
            ]);
        }
    }

    private function sendWelcomeNotices(User $user): void
    {
        $this->notifications->send($user, 'auth.welcome');
    }
}
