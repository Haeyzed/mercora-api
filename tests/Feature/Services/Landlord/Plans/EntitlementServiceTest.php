<?php

use App\Enums\Landlord\FeatureType;
use App\Models\Landlord\Feature;
use App\Models\Landlord\Plan;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\Tenant;
use App\Services\Landlord\Plans\EntitlementService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function tenantWithPlanFeature(Plan $plan, Feature $feature, mixed $value): Tenant
{
    $plan->features()->attach($feature->id, ['value' => $value]);

    $tenant = Tenant::factory()->create();
    Subscription::factory()->for($tenant)->for($plan)->create();

    return $tenant;
}

it('allows enabled boolean features', function () {
    $plan = Plan::factory()->active()->create();
    $feature = Feature::factory()->create(['key' => 'custom_domain', 'type' => FeatureType::Boolean]);
    $tenant = tenantWithPlanFeature($plan, $feature, true);

    expect(app(EntitlementService::class)->allows($tenant, 'custom_domain'))->toBeTrue();
});

it('denies disabled boolean features', function () {
    $plan = Plan::factory()->active()->create();
    $feature = Feature::factory()->create(['key' => 'custom_domain', 'type' => FeatureType::Boolean]);
    $tenant = tenantWithPlanFeature($plan, $feature, false);

    expect(app(EntitlementService::class)->allows($tenant, 'custom_domain'))->toBeFalse();
});

it('returns numeric limits for integer features', function () {
    $plan = Plan::factory()->active()->create();
    $feature = Feature::factory()->create(['key' => 'users.max', 'type' => FeatureType::Integer]);
    $tenant = tenantWithPlanFeature($plan, $feature, 10);

    expect(app(EntitlementService::class)->value($tenant, 'users.max'))->toBe(10);
});

it('represents unlimited features explicitly', function () {
    $plan = Plan::factory()->active()->create();
    $feature = Feature::factory()->create(['key' => 'products', 'type' => FeatureType::Unlimited]);
    $tenant = tenantWithPlanFeature($plan, $feature, 'unlimited');

    expect(app(EntitlementService::class)->value($tenant, 'products'))->toBe('unlimited')
        ->and(app(EntitlementService::class)->allows($tenant, 'products'))->toBeTrue();
});

it('returns null for missing features', function () {
    $tenant = Tenant::factory()->create();
    Subscription::factory()->for($tenant)->create();

    expect(app(EntitlementService::class)->value($tenant, 'missing.feature'))->toBeNull()
        ->and(app(EntitlementService::class)->allows($tenant, 'missing.feature'))->toBeFalse();
});

it('ignores inactive features not attached to the plan', function () {
    $plan = Plan::factory()->active()->create();
    Feature::factory()->create(['key' => 'api.access', 'type' => FeatureType::Boolean, 'is_active' => false]);

    $tenant = Tenant::factory()->create();
    Subscription::factory()->for($tenant)->for($plan)->create();

    expect(app(EntitlementService::class)->allows($tenant, 'api.access'))->toBeFalse();
});

it('resolves feature values from plan features not plan names', function () {
    $plan = Plan::factory()->active()->create(['name' => 'Enterprise']);
    $feature = Feature::factory()->create(['key' => 'warehouses', 'type' => FeatureType::Integer]);
    $tenant = tenantWithPlanFeature($plan, $feature, 5);

    expect(app(EntitlementService::class)->value($tenant, 'warehouses'))->toBe(5);
});

it('invalidates cached entitlements when forget bumps the version', function () {
    $plan = Plan::factory()->active()->create();
    $feature = Feature::factory()->create(['key' => 'custom_domain', 'type' => FeatureType::Boolean]);
    $tenant = tenantWithPlanFeature($plan, $feature, true);
    $entitlements = app(EntitlementService::class);

    expect($entitlements->allows($tenant, 'custom_domain'))->toBeTrue();

    $plan->features()->detach($feature->id);

    expect($entitlements->allows($tenant, 'custom_domain'))->toBeTrue();

    $entitlements->forget($tenant);

    expect($entitlements->allows($tenant, 'custom_domain'))->toBeFalse();
});
