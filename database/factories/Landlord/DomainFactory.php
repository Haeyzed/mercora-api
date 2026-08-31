<?php

declare(strict_types=1);

namespace Database\Factories\Landlord;

use App\Models\Landlord\Domain;
use App\Models\Landlord\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'domain' => fake()->unique()->domainName(),
            'tenant_id' => Tenant::factory(),
        ];
    }
}
