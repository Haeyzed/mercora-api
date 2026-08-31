<?php

declare(strict_types=1);

namespace Database\Factories\Landlord;

use App\Enums\Landlord\ApiKeyStatus;
use App\Models\Landlord\ApiKey;
use App\Models\Landlord\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = 'mrc_'.Str::random(40);

        return [
            'user_id' => User::factory(),
            'name' => 'CI deploy',
            'prefix' => substr($token, 0, 12),
            'key_hash' => hash('sha256', $token),
            'status' => ApiKeyStatus::Active,
            'last_used_at' => null,
            'expires_at' => null,
            'revoked_at' => null,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ApiKeyStatus::Revoked,
            'revoked_at' => now(),
        ]);
    }
}
