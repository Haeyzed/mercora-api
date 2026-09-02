<?php

declare(strict_types=1);

namespace App\Http\Resources\Landlord\Auth;

use App\Models\Landlord\User;

/**
 * Login response payload wrapped by {@see LoginResource}.
 */
final readonly class LoginPayload
{
    public function __construct(
        public User $user,
        public string $token,
    ) {}
}
