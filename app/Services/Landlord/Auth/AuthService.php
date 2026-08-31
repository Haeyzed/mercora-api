<?php

declare(strict_types=1);

namespace App\Services\Landlord\Auth;

use App\Models\Landlord\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Authenticates landlord users via Laravel Sanctum personal access tokens.
 *
 * Domain: landlord panel login and session teardown.
 *
 * Invariants:
 * - Only active users with valid credentials receive a token.
 * - Logout revokes only the token used for the current request.
 *
 * Side effects: creates and deletes Sanctum {@see PersonalAccessToken} records.
 */
class AuthService
{
    /**
     * Authenticate a landlord user and issue an API token.
     *
     * @param  array{email: string, password: string, device_name?: string}  $credentials
     * @return array{user: User, token: string}
     *
     * @throws ValidationException When credentials are invalid or the user is inactive.
     */
    public function login(array $credentials): array
    {
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! $user->is_active || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        return [
            'user' => $user,
            'token' => $user->createToken($credentials['device_name'] ?? 'landlord')->plainTextToken,
        ];
    }

    /**
     * Revoke the token used for the current request.
     *
     * No-op when the authenticated guard is not backed by a {@see PersonalAccessToken}.
     */
    public function logout(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }
}
