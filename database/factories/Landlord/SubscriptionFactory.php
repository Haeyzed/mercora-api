<?php

declare(strict_types=1);

namespace Database\Factories\Landlord;

use App\Enums\Landlord\PlanInterval;
use App\Enums\Landlord\SubscriptionStatus;
use App\Models\Landlord\Plan;
use App\Models\Landlord\PlanPrice;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (Subscription $subscription): void {
            if ($subscription->plan_price_id !== null) {
                return;
            }

            $plan = $subscription->plan()->first();

            if ($plan === null) {
                return;
            }

            $planPrice = $plan->prices()->first() ?? PlanPrice::factory()->for($plan)->create([
                'amount' => $subscription->price,
                'currency' => $subscription->currency,
                'interval' => $subscription->interval,
            ]);

            $subscription->update([
                'plan_price_id' => $planPrice->id,
                'plan_name' => $plan->name,
                'interval_count' => $planPrice->interval_count,
            ]);
        });
    }

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
