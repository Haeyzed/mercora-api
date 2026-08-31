<?php

use App\Enums\Landlord\PlanInterval;
use App\Enums\Landlord\SubscriptionStatus;
use App\Models\Landlord\Invoice;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(LazilyRefreshDatabase::class);

describe('renew', function () {
    it('advances the billing period and issues an invoice', function () {
        $this->travelTo('2026-08-29 20:00:00');

        $subscription = Subscription::factory()->create([
            'interval' => PlanInterval::Monthly,
            'price' => 2900,
            'ends_at' => now(),
        ]);

        $this->postJson("/api/landlord/subscriptions/{$subscription->id}/renew")
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.ends_at', '2026-09-29T20:00:00.000000Z');

        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseHas('invoices', [
            'subscription_id' => $subscription->id,
            'amount' => 2900,
        ]);
    });

    it('issues a new period invoice when an admin renews twice', function () {
        $this->travelTo('2026-08-29 20:00:00');

        $subscription = Subscription::factory()->create([
            'interval' => PlanInterval::Monthly,
            'ends_at' => now(),
        ]);

        $this->postJson("/api/landlord/subscriptions/{$subscription->id}/renew")->assertOk();
        $endsAt = $subscription->fresh()->ends_at;

        $this->postJson("/api/landlord/subscriptions/{$subscription->id}/renew")->assertOk();

        expect($subscription->fresh()->ends_at->ne($endsAt))->toBeTrue();
        $this->assertDatabaseCount('invoices', 2);
    });

    it('returns 422 when a canceled subscription is renewed', function () {
        $subscription = Subscription::factory()->canceled()->create();

        $this->postJson("/api/landlord/subscriptions/{$subscription->id}/renew")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });
});

describe('scheduled processing', function () {
    it('converts an ended trial to active without renewing an open period', function () {
        $this->travelTo('2026-09-12 20:00:00');

        $subscription = Subscription::factory()->trialing()->create([
            'trial_ends_at' => now()->subMinute(),
            'ends_at' => now()->addMonth(),
        ]);

        Artisan::call('landlord:process-subscriptions');

        expect($subscription->fresh()->status)->toBe(SubscriptionStatus::Active);
        $this->assertDatabaseCount('invoices', 0);
    });

    it('renews a current subscription whose period has ended', function () {
        $this->travelTo('2026-09-29 20:00:00');

        $subscription = Subscription::factory()->create([
            'interval' => PlanInterval::Monthly,
            'ends_at' => now(),
        ]);

        Artisan::call('landlord:process-subscriptions');

        expect($subscription->fresh()->ends_at->toIso8601String())->toBe('2026-10-29T20:00:00+00:00')
            ->and(Invoice::query()->where('subscription_id', $subscription->id)->count())->toBe(1);

        Artisan::call('landlord:process-subscriptions');

        $this->assertDatabaseCount('invoices', 1);
    });
});

describe('concurrency', function () {
    it('rejects a second current subscription at the database', function () {
        $tenant = Tenant::factory()->create();
        Subscription::factory()->for($tenant)->create(['is_current' => 1]);

        expect(fn () => Subscription::factory()->for($tenant)->create(['is_current' => 1]))
            ->toThrow(QueryException::class);
    });
});
