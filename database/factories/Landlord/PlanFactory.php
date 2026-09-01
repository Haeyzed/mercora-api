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
            'status' => PlanStatus::Draft,
            'feature_highlights' => ['Online store', 'Basic reports'],
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Plan $plan): void {
            if ($plan->prices()->exists()) {
                return;
            }

            PlanPriceFactory::new()->for($plan)->create();
        });
    }

    public function withoutPrices(): static
    {
        return $this->afterCreating(function (Plan $plan): void {
            $plan->prices()->delete();
        });
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
        return $this->withPrice(['interval' => PlanInterval::Yearly]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function withPrice(array $overrides = []): static
    {
        return $this->afterCreating(function (Plan $plan) use ($overrides): void {
            $price = $plan->prices()->first();

            if ($price === null) {
                PlanPriceFactory::new()->for($plan)->create($overrides);

                return;
            }

            $price->update($overrides);
        });
    }
}
