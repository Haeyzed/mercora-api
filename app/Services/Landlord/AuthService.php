<?php

declare(strict_types=1);

namespace App\Services\Landlord;

use App\Enums\Media\MediaCollection;
use App\Http\Resources\Landlord\Auth\LoginPayload;
use App\Models\Landlord\User;
use App\Notifications\Landlord\ResetPasswordNotification;
use App\Services\Media\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Authenticates landlord users and manages their account profile.
 *
 * Domain: landlord panel login, password recovery, profile, and avatar.
 *
 * Invariants:
 * - Only active users with valid credentials receive a token.
 * - Logout revokes only the token used for the current request.
 * - Forgot password never reveals whether an email exists.
 * - Password reset and change revoke all issued API tokens.
 *
 * Side effects: creates and deletes Sanctum tokens; stores avatar media; sends mail;
 * reads {@see SettingService} for token revocation policy.
 */
class AuthService
{
    public function __construct(
        private MediaService $mediaService,
        private SettingService $settings,
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
}
