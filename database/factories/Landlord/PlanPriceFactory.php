<?php

declare(strict_types=1);

namespace Database\Factories\Landlord;

use App\Enums\Landlord\PlanInterval;
use App\Models\Landlord\Plan;
use App\Models\Landlord\PlanPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanPrice>
 */
class PlanPriceFactory extends Factory
{
    protected $model = PlanPrice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory()->active(),
            'currency' => 'USD',
            'amount' => 2900,
            'interval' => PlanInterval::Monthly,
            'interval_count' => 1,
            'trial_days' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
