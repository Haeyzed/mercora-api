<?php

use App\Enums\Landlord\PlanInterval;
use App\Enums\Landlord\SubscriptionStatus;
use App\Models\Landlord\Invoice;
use App\Models\Landlord\Plan;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\Tenant;
use App\Services\Landlord\SettingService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('index', function () {
    it('returns a paginated list of subscriptions', function () {
        Subscription::factory()->count(2)->create();

        $this->getJson('/api/landlord/subscriptions')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'tenant_id', 'plan_id', 'price', 'currency', 'interval', 'status'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);
    });

    it('paginates subscriptions using the per_page query parameter', function () {
        Subscription::factory()->count(3)->create();

        $this->getJson('/api/landlord/subscriptions?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(2, 'data');
    });

    it('filters subscriptions by tenant id', function () {
        $tenant = Tenant::factory()->create(['name' => 'Acme Stores']);
        Subscription::factory()->for($tenant)->create();
        Subscription::factory()->create();

        $this->getJson('/api/landlord/subscriptions?filter[tenant_id]='.$tenant->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.tenant_id', $tenant->id);
    });

    it('filters subscriptions by status', function () {
        Subscription::factory()->canceled()->create();
        Subscription::factory()->create();

        $this->getJson('/api/landlord/subscriptions?filter[status]=canceled')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'canceled');
    });

    it('searches subscriptions by tenant name', function () {
        $tenant = Tenant::factory()->create(['name' => 'Acme Stores']);
        Subscription::factory()->for($tenant)->create();
        Subscription::factory()->for(Tenant::factory()->create(['name' => 'Beta Retail']))->create();

        $this->getJson('/api/landlord/subscriptions?search=Acme')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.tenant_id', $tenant->id);
    });

    it('returns all subscriptions when search is blank', function () {
        Subscription::factory()->count(2)->create();

        $this->getJson('/api/landlord/subscriptions?search=')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    });

    it('ignores unknown filters instead of querying arbitrary columns', function () {
        Subscription::factory()->create();

        $this->getJson('/api/landlord/subscriptions?filter[password]=secret')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    });

    it('includes the tenant and plan when requested', function () {
        $tenant = Tenant::factory()->create(['name' => 'Acme Stores']);
        $plan = Plan::factory()->active()->create(['name' => 'Starter Plan']);
        Subscription::factory()->for($tenant)->for($plan)->create();

        $this->getJson('/api/landlord/subscriptions?include=tenant,plan')
            ->assertOk()
            ->assertJsonPath('data.0.tenant.name', 'Acme Stores')
            ->assertJsonPath('data.0.plan.name', 'Starter Plan');
    });
});

describe('store', function () {
    it('subscribes a tenant to an active plan and snapshots the catalog terms', function () {
        $this->travelTo('2026-08-29 20:00:00');

        $tenant = Tenant::factory()->create(['name' => 'Acme Stores']);
        $plan = Plan::factory()->active()->withPrice([
            'amount' => 2900,
            'currency' => 'USD',
            'interval' => PlanInterval::Monthly,
            'trial_days' => 0,
        ])->create(['name' => 'Starter Plan']);

        $this->postJson('/api/landlord/subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonPath('data.plan_id', $plan->id)
            ->assertJsonPath('data.price', 2900)
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.interval', 'monthly')
            ->assertJsonPath('data.status', 'pending_payment')
            ->assertJsonPath('data.starts_at', '2026-08-29T20:00:00.000000Z')
            ->assertJsonPath('data.ends_at', '2026-09-29T20:00:00.000000Z')
            ->assertJsonPath('data.trial_ends_at', null)
            ->assertJsonPath('data.tenant.name', 'Acme Stores')
            ->assertJsonPath('data.plan.name', 'Starter Plan');

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'price' => 2900,
            'status' => SubscriptionStatus::PendingPayment->value,
        ]);
    });

    it('starts a trial when the plan has trial days', function () {
        $this->travelTo('2026-08-29 20:00:00');

        $tenant = Tenant::factory()->create();
        $plan = Plan::factory()->active()->withPrice([
            'trial_days' => 14,
            'interval' => PlanInterval::Monthly,
        ])->create();

        $this->postJson('/api/landlord/subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'trialing')
            ->assertJsonPath('data.trial_ends_at', '2026-09-12T20:00:00.000000Z')
            ->assertJsonPath('data.ends_at', '2026-10-12T20:00:00.000000Z');
    });

    it('does not persist client-supplied price or status', function () {
        $tenant = Tenant::factory()->create();
        $plan = Plan::factory()->active()->withPrice([
            'amount' => 2900,
            'trial_days' => 0,
        ])->create();

        $this->postJson('/api/landlord/subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'price' => 1,
            'status' => 'canceled',
        ])
            ->assertCreated()
            ->assertJsonPath('data.price', 2900)
            ->assertJsonPath('data.status', 'pending_payment');
    });

    it('returns 422 when required subscription fields are missing', function () {
        $this->postJson('/api/landlord/subscriptions', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tenant_id', 'plan_id']);
    });

    it('returns 422 when the plan is not active', function () {
        $tenant = Tenant::factory()->create();
        $plan = Plan::factory()->create();

        $this->postJson('/api/landlord/subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_id']);
    });

    it('returns 422 when the tenant already has a current subscription', function () {
        $tenant = Tenant::factory()->create();
        $plan = Plan::factory()->active()->create();
        Subscription::factory()->for($tenant)->for($plan)->create();

        $this->postJson('/api/landlord/subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tenant_id']);
    });

    it('allows a new subscription after the previous one is canceled', function () {
        $tenant = Tenant::factory()->create();
        $plan = Plan::factory()->active()->create();
        Subscription::factory()->for($tenant)->for($plan)->canceled()->create();

        $this->postJson('/api/landlord/subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
        ])->assertCreated();
    });
});

describe('show', function () {
    it('returns a single subscription', function () {
        $subscription = Subscription::factory()->create();

        $this->getJson("/api/landlord/subscriptions/{$subscription->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $subscription->id)
            ->assertJsonMissingPath('data.tenant')
            ->assertJsonMissingPath('data.plan')
            ->assertJsonMissingPath('data.invoices');
    });

    it('includes invoices when requested', function () {
        $subscription = Subscription::factory()->create();
        $invoice = Invoice::factory()->for($subscription)->create();

        $this->getJson("/api/landlord/subscriptions/{$subscription->id}?include=invoices")
            ->assertOk()
            ->assertJsonPath('data.invoices.0.id', $invoice->id)
            ->assertJsonPath('data.invoices.0.subscription_id', $subscription->id);
    });

    it('returns 404 when the subscription does not exist', function () {
        $this->getJson('/api/landlord/subscriptions/999')
            ->assertNotFound();
    });
});

describe('changePlan', function () {
    it('changes the plan and re-snapshots the catalog terms', function () {
        $subscription = Subscription::factory()->create([
            'price' => 2900,
            'currency' => 'USD',
        ]);
        $plan = Plan::factory()->active()->withPrice([
            'amount' => 7900,
            'currency' => 'NGN',
            'interval' => PlanInterval::Yearly,
            'trial_days' => 0,
        ])->create(['name' => 'Growth Plan']);

        $this->postJson("/api/landlord/subscriptions/{$subscription->id}/change-plan", [
            'plan_id' => $plan->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.plan_id', $plan->id)
            ->assertJsonPath('data.price', 7900)
            ->assertJsonPath('data.currency', 'NGN')
            ->assertJsonPath('data.interval', 'yearly');
    });

    it('returns 422 when changing the plan on a canceled subscription', function () {
        $subscription = Subscription::factory()->canceled()->create();
        $plan = Plan::factory()->active()->create();

        $this->postJson("/api/landlord/subscriptions/{$subscription->id}/change-plan", [
            'plan_id' => $plan->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });

    it('returns 422 when plan changes are disabled', function () {
        app(SettingService::class)->updateDomain('subscriptions', [
            'subscriptions.allow_plan_changes' => false,
        ]);

        $subscription = Subscription::factory()->create();
        $plan = Plan::factory()->active()->withPrice()->create();

        $this->postJson("/api/landlord/subscriptions/{$subscription->id}/change-plan", [
            'plan_id' => $plan->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plan_id']);
    });
});

describe('cancel', function () {
    it('schedules cancellation at period end by default', function () {
        $this->travelTo('2026-08-29 20:00:00');

        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
            'ends_at' => '2026-09-29 20:00:00',
        ]);

        $this->postJson("/api/landlord/subscriptions/{$subscription->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.canceled_at', '2026-08-29T20:00:00.000000Z');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => SubscriptionStatus::Active->value,
            'is_current' => 1,
        ]);
    });

    it('cancels immediately when period-end cancel is disabled and immediate cancel is allowed', function () {
        app(SettingService::class)->updateDomain('subscriptions', [
            'subscriptions.cancel_at_period_end' => false,
            'subscriptions.allow_immediate_cancel' => true,
        ]);

        $this->travelTo('2026-08-29 20:00:00');

        $subscription = Subscription::factory()->create();

        $this->postJson("/api/landlord/subscriptions/{$subscription->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'canceled')
            ->assertJsonPath('data.canceled_at', '2026-08-29T20:00:00.000000Z');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => SubscriptionStatus::Canceled->value,
        ]);
    });

    it('returns 422 when the subscription is already canceled', function () {
        $subscription = Subscription::factory()->canceled()->create();

        $this->postJson("/api/landlord/subscriptions/{$subscription->id}/cancel")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });
});

describe('destroy', function () {
    it('does not expose a delete endpoint', function () {
        $subscription = Subscription::factory()->create();

        $this->deleteJson("/api/landlord/subscriptions/{$subscription->id}")
            ->assertMethodNotAllowed();

        $this->assertNotSoftDeleted($subscription);
    });
});

describe('restore', function () {
    it('does not expose a restore endpoint', function () {
        $subscription = Subscription::factory()->create();
        $subscription->delete();

        $this->postJson("/api/landlord/subscriptions/{$subscription->id}/restore")
            ->assertNotFound();
    });
});

describe('destroyMany', function () {
    it('does not expose a bulk delete endpoint', function () {
        $first = Subscription::factory()->create();
        $second = Subscription::factory()->create();

        $this->deleteJson('/api/landlord/subscriptions/destroy-many', [
            'ids' => [$first->id, $second->id],
        ])->assertMethodNotAllowed();

        $this->assertNotSoftDeleted($first);
        $this->assertNotSoftDeleted($second);
    });
});

describe('restoreMany', function () {
    it('does not expose a bulk restore endpoint', function () {
        $subscription = Subscription::factory()->create();
        $subscription->delete();

        $this->postJson('/api/landlord/subscriptions/restore-many', [
            'ids' => [$subscription->id],
        ])->assertMethodNotAllowed();
    });
});
