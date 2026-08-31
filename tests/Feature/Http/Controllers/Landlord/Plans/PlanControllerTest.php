<?php

use App\Enums\Landlord\PlanInterval;
use App\Enums\Landlord\PlanStatus;
use App\Models\Landlord\Plan;
use App\Models\Landlord\Subscription;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function planPayload(array $overrides = []): array
{
    return [
        'name' => 'Starter Plan',
        'description' => 'For new stores',
        'price' => 2900,
        'currency' => 'USD',
        'interval' => 'monthly',
        'trial_days' => 14,
        'status' => 'active',
        'feature_highlights' => ['Online store', 'Basic reports'],
        ...$overrides,
    ];
}

describe('index', function () {
    it('returns a paginated list of plans', function () {
        Plan::factory()->create(['name' => 'Starter Plan']);
        Plan::factory()->create(['name' => 'Growth Plan']);

        $this->getJson('/api/landlord/plans')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'name', 'slug', 'price', 'currency', 'interval', 'status'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);
    });

    it('paginates plans using the per_page query parameter', function () {
        Plan::factory()->count(3)->create();

        $this->getJson('/api/landlord/plans?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(2, 'data');
    });

    it('filters plans by a partial name', function () {
        Plan::factory()->create(['name' => 'Starter Plan']);
        Plan::factory()->create(['name' => 'Growth Plan']);

        $this->getJson('/api/landlord/plans?filter[name]=tart')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Starter Plan');
    });

    it('filters plans by status', function () {
        Plan::factory()->active()->create(['name' => 'Starter Plan']);
        Plan::factory()->create(['name' => 'Growth Plan']);

        $this->getJson('/api/landlord/plans?filter[status]=active')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Starter Plan')
            ->assertJsonPath('data.0.status', 'active');
    });

    it('filters plans by interval', function () {
        Plan::factory()->yearly()->create(['name' => 'Starter Plan']);
        Plan::factory()->create(['name' => 'Growth Plan']);

        $this->getJson('/api/landlord/plans?filter[interval]=yearly')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Starter Plan')
            ->assertJsonPath('data.0.interval', 'yearly');
    });

    it('searches plans across name and slug', function (string $term) {
        Plan::factory()->create(['name' => 'Starter Plan']);
        Plan::factory()->create(['name' => 'Growth Plan']);

        $this->getJson('/api/landlord/plans?search='.$term)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Starter Plan');
    })->with([
        'name' => 'Starter',
        'slug' => 'starter-plan',
    ]);

    it('returns all plans when search is blank', function () {
        Plan::factory()->create(['name' => 'Starter Plan']);
        Plan::factory()->create(['name' => 'Growth Plan']);

        $this->getJson('/api/landlord/plans?search=')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    });

    it('ignores unknown filters instead of querying arbitrary columns', function () {
        Plan::factory()->create(['name' => 'Starter Plan']);

        $this->getJson('/api/landlord/plans?filter[password]=secret')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    });
});

describe('options', function () {
    it('returns plan options as label and value pairs', function () {
        $plan = Plan::factory()->create(['name' => 'Starter Plan']);

        $this->getJson('/api/landlord/plans/options')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Starter Plan')
            ->assertJsonPath('data.0.value', $plan->id);
    });

    it('searches plan options by a single term', function () {
        Plan::factory()->create(['name' => 'Starter Plan']);
        Plan::factory()->create(['name' => 'Growth Plan']);

        $this->getJson('/api/landlord/plans/options?search=Starter')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.label', 'Starter Plan');
    });
});

describe('store', function () {
    it('creates a plan', function () {
        $this->postJson('/api/landlord/plans', planPayload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Starter Plan')
            ->assertJsonPath('data.slug', 'starter-plan')
            ->assertJsonPath('data.price', 2900)
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.interval', 'monthly')
            ->assertJsonPath('data.trial_days', 14)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.feature_highlights.0', 'Online store');

        $this->assertDatabaseHas('plans', [
            'name' => 'Starter Plan',
            'slug' => 'starter-plan',
            'price' => 2900,
            'currency' => 'USD',
            'interval' => PlanInterval::Monthly->value,
            'status' => PlanStatus::Active->value,
        ]);
    });

    it('defaults status to draft when omitted', function () {
        $payload = planPayload();
        unset($payload['status']);

        $this->postJson('/api/landlord/plans', $payload)
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');
    });

    it('does not persist a client-supplied slug', function () {
        $this->postJson('/api/landlord/plans', planPayload([
            'slug' => 'injected-slug',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.slug', 'starter-plan');

        $this->assertDatabaseMissing('plans', [
            'slug' => 'injected-slug',
        ]);
    });

    it('returns 422 when required plan fields are missing', function () {
        $this->postJson('/api/landlord/plans', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'price', 'currency', 'interval']);
    });

    it('returns 422 when the currency is not an iso code', function () {
        $this->postJson('/api/landlord/plans', planPayload([
            'currency' => 'us',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['currency']);
    });

    it('returns 422 when the interval is invalid', function () {
        $this->postJson('/api/landlord/plans', planPayload([
            'interval' => 'weekly',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['interval']);
    });
});

describe('show', function () {
    it('returns a single plan', function () {
        $plan = Plan::factory()->create(['name' => 'Starter Plan']);

        $this->getJson("/api/landlord/plans/{$plan->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Starter Plan')
            ->assertJsonPath('data.slug', 'starter-plan');
    });

    it('returns 404 when the plan does not exist', function () {
        $this->getJson('/api/landlord/plans/999')
            ->assertNotFound();
    });

    it('includes subscriptions when requested', function () {
        $plan = Plan::factory()->active()->create(['name' => 'Starter Plan']);
        Subscription::factory()->for($plan)->create();

        $this->getJson("/api/landlord/plans/{$plan->id}?include=subscriptions")
            ->assertOk()
            ->assertJsonPath('data.subscriptions.0.plan_id', $plan->id);
    });
});

describe('update', function () {
    it('updates a plan and regenerates the slug from the name', function () {
        $plan = Plan::factory()->create(['name' => 'Starter Plan']);

        $this->putJson("/api/landlord/plans/{$plan->id}", [
            'name' => 'Growth Plan',
            'price' => 7900,
            'status' => 'active',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Growth Plan')
            ->assertJsonPath('data.slug', 'growth-plan')
            ->assertJsonPath('data.price', 7900)
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'name' => 'Growth Plan',
            'slug' => 'growth-plan',
            'price' => 7900,
            'status' => PlanStatus::Active->value,
        ]);
    });
});

describe('destroy', function () {
    it('soft deletes a plan', function () {
        $plan = Plan::factory()->create(['name' => 'Starter Plan']);

        $this->deleteJson("/api/landlord/plans/{$plan->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($plan);
    });

    it('returns 404 when showing a soft-deleted plan', function () {
        $plan = Plan::factory()->create(['name' => 'Starter Plan']);
        $plan->delete();

        $this->getJson("/api/landlord/plans/{$plan->id}")
            ->assertNotFound();
    });
});

describe('restore', function () {
    it('restores a soft-deleted plan', function () {
        $plan = Plan::factory()->create(['name' => 'Starter Plan']);
        $plan->delete();

        $this->postJson("/api/landlord/plans/{$plan->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.name', 'Starter Plan');

        $this->assertNotSoftDeleted($plan);
    });

    it('returns 404 when the plan is not soft deleted', function () {
        $plan = Plan::factory()->create(['name' => 'Starter Plan']);

        $this->postJson("/api/landlord/plans/{$plan->id}/restore")
            ->assertNotFound();
    });

    it('returns 404 when the plan does not exist', function () {
        $this->postJson('/api/landlord/plans/999/restore')
            ->assertNotFound();
    });
});

describe('destroyMany', function () {
    it('soft deletes the given plans', function () {
        $starter = Plan::factory()->create(['name' => 'Starter Plan']);
        $growth = Plan::factory()->create(['name' => 'Growth Plan']);

        $this->deleteJson('/api/landlord/plans/destroy-many', [
            'ids' => [$starter->id, $growth->id],
        ])->assertNoContent();

        $this->assertSoftDeleted($starter);
        $this->assertSoftDeleted($growth);
    });

    it('returns 422 when ids are missing', function () {
        $this->deleteJson('/api/landlord/plans/destroy-many', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids']);
    });

    it('returns 422 when an id does not exist', function () {
        $this->deleteJson('/api/landlord/plans/destroy-many', [
            'ids' => [999],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids.0']);
    });
});

describe('restoreMany', function () {
    it('restores the given soft-deleted plans', function () {
        $starter = Plan::factory()->create(['name' => 'Starter Plan']);
        $growth = Plan::factory()->create(['name' => 'Growth Plan']);
        $starter->delete();
        $growth->delete();

        $this->postJson('/api/landlord/plans/restore-many', [
            'ids' => [$starter->id, $growth->id],
        ])->assertNoContent();

        $this->assertNotSoftDeleted($starter);
        $this->assertNotSoftDeleted($growth);
    });

    it('returns 422 when an id is not soft deleted', function () {
        $plan = Plan::factory()->create(['name' => 'Starter Plan']);

        $this->postJson('/api/landlord/plans/restore-many', [
            'ids' => [$plan->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids.0']);
    });
});
