<?php

declare(strict_types=1);

namespace Database\Factories\Landlord;

use App\Enums\Landlord\PlanInterval;
use App\Enums\Landlord\PlanStatus;
use App\Models\Landlord\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' Plan',
            'description' => fake()->sentence(),
            'price' => 2900,
            'currency' => 'USD',
            'interval' => PlanInterval::Monthly,
            'trial_days' => 0,
            'status' => PlanStatus::Draft,
            'features' => ['Online store', 'Basic reports'],
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PlanStatus::Active,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PlanStatus::Archived,
        ]);
    }

    public function yearly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'interval' => PlanInterval::Yearly,
        ]);
    }
}
