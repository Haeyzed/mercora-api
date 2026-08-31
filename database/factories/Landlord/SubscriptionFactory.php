<?php

declare(strict_types=1);

namespace Database\Factories\Landlord;

use App\Enums\Landlord\PlanInterval;
use App\Enums\Landlord\SubscriptionStatus;
use App\Models\Landlord\Plan;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now();

        return [
            'tenant_id' => Tenant::factory(),
            'plan_id' => Plan::factory()->active(),
            'price' => 2900,
            'currency' => 'USD',
            'interval' => PlanInterval::Monthly,
            'status' => SubscriptionStatus::Active,
            'is_current' => 1,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMonth(),
            'trial_ends_at' => null,
            'canceled_at' => null,
        ];
    }

    public function trialing(): static
    {
        return $this->state(function (array $attributes): array {
            $startsAt = $attributes['starts_at'] ?? now();

            return [
                'status' => SubscriptionStatus::Trialing,
                'trial_ends_at' => $startsAt->copy()->addDays(14),
            ];
        });
    }

    public function canceled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubscriptionStatus::Canceled,
            'canceled_at' => now(),
            'is_current' => null,
        ]);
    }
}
