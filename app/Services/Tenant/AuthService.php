<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Http\Resources\Tenant\Auth\LoginPayload;
use App\Models\Tenant\User;
use App\Notifications\Tenant\ResetPasswordNotification;
use App\Services\Landlord\SettingService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Tenant staff authentication, profile, and password workflows.
 */
class AuthService
{
    /**
     * Authenticate a tenant user and issue an API token.
     *
     * @param  array{email: string, password: string, device_name?: string}  $credentials
     *
     * @throws ValidationException
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
        $timeout = $this->sessionTimeoutMinutes();

        if ($timeout > 0) {
            $expiresAt = now()->addMinutes($timeout);
        }

        return new LoginPayload(
            user: $user->loadMissing('roles'),
            token: $user->createToken(
                $credentials['device_name'] ?? 'tenant',
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
     * Send a password reset notification when the email exists.
     *
     * Never reveals whether the account exists.
     */
    public function forgotPassword(string $email): void
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! $user->is_active) {
            return;
        }

        $token = Password::broker('tenant_users')->createToken($user);
        $user->notify(new ResetPasswordNotification($token));
    }

    /**
     * Reset a tenant user's password using the password broker.
     *
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $data
     *
     * @throws ValidationException
     */
    public function resetPassword(array $data): void
    {
        $user = User::query()->where('email', $data['email'])->first();

        if ($user !== null && ! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $status = Password::broker('tenant_users')->reset(
            $data,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                ])->save();

                $user->tokens()->delete();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }

    /**
     * Update the authenticated tenant user's profile.
     *
     * @param  array{name?: string, email?: string, phone?: string|null}  $data
     */
    public function updateProfile(User $user, array $data): User
    {
        $user->fill($data);
        $user->save();

        return $user->refresh();
    }

    /**
     * Change the authenticated tenant user's password.
     *
     * @param  array{current_password: string, password: string}  $data
     *
     * @throws ValidationException
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

        $user->tokens()->delete();
    }

    private function sessionTimeoutMinutes(): int
    {
        return max(0, (int) $this->centralSetting('security.session_timeout_minutes', 120));
    }

    private function centralSetting(string $key, mixed $default = null): mixed
    {
        return tenancy()->central(function () use ($key, $default): mixed {
            try {
                if (! Schema::hasTable('settings')) {
                    return $default;
                }

                return app(SettingService::class)->value($key, $default);
            } catch (\Throwable) {
                return $default;
            }
        });
    }
}
