<?php

use App\Models\Landlord\Plan;
use App\Models\Landlord\PlanPrice;
use App\Models\Landlord\Subscription;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('store', function () {
    it('creates a plan price', function () {
        $plan = Plan::factory()->create();

        $this->postJson("/api/landlord/plans/{$plan->id}/prices", [
            'currency' => 'NGN',
            'amount' => 4900,
            'interval' => 'yearly',
            'interval_count' => 1,
            'trial_days' => 7,
        ])
            ->assertCreated()
            ->assertJsonPath('data.amount', 4900)
            ->assertJsonPath('data.currency', 'NGN')
            ->assertJsonPath('data.is_active', true);
    });
});

describe('update', function () {
    it('blocks financial changes when subscriptions reference the price', function () {
        $plan = Plan::factory()->create();
        $price = $plan->prices()->firstOrFail();
        Subscription::factory()->create(['plan_price_id' => $price->id]);

        $this->putJson("/api/landlord/plans/{$plan->id}/prices/{$price->id}", [
            'amount' => 3900,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    });

    it('allows trial day updates on a price in use', function () {
        $plan = Plan::factory()->create();
        $price = $plan->prices()->firstOrFail();
        Subscription::factory()->create(['plan_price_id' => $price->id]);

        $this->putJson("/api/landlord/plans/{$plan->id}/prices/{$price->id}", [
            'trial_days' => 14,
        ])
            ->assertOk()
            ->assertJsonPath('data.trial_days', 14);
    });
});

describe('deactivate', function () {
    it('deactivates an active plan price', function () {
        $plan = Plan::factory()->create();
        $price = $plan->prices()->firstOrFail();

        $this->postJson("/api/landlord/plans/{$plan->id}/prices/{$price->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    });
});

describe('destroy', function () {
    it('rejects deleting a price referenced by subscriptions', function () {
        $plan = Plan::factory()->create();
        $price = $plan->prices()->firstOrFail();
        Subscription::factory()->create(['plan_price_id' => $price->id]);

        $this->deleteJson("/api/landlord/plans/{$plan->id}/prices/{$price->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_price_id']);
    });
});
