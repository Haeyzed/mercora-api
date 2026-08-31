<?php

declare(strict_types=1);

namespace Database\Factories\Landlord;

use App\Enums\Landlord\TenantStatus;
use App\Models\Landlord\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'status' => TenantStatus::Pending,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TenantStatus::Active,
            'provisioned_at' => now(),
            'provision_error' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TenantStatus::Failed,
            'provision_error' => 'Tenant provisioning failed.',
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TenantStatus::Suspended,
            'provisioned_at' => now(),
        ]);
    }
}
