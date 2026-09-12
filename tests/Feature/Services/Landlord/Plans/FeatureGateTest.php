<?php

use App\Enums\Landlord\FeatureType;
use App\Models\Landlord\Feature;
use App\Models\Landlord\Plan;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\Tenant;
use App\Services\Landlord\Plans\FeatureGate;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(LazilyRefreshDatabase::class);

function gateTenantWithFeature(string $key, FeatureType $type, mixed $value): Tenant
{
    $plan = Plan::factory()->active()->create();
    $feature = Feature::factory()->create(['key' => $key, 'type' => $type, 'is_active' => true]);
    $plan->features()->attach($feature->id, ['value' => $value]);

    $tenant = Tenant::factory()->create();
    Subscription::factory()->for($tenant)->for($plan)->create();

    return $tenant;
}

it('asserts allowed features without throwing', function () {
    $tenant = gateTenantWithFeature('custom_domain', FeatureType::Boolean, true);

    app(FeatureGate::class)->assert($tenant, 'custom_domain');

    expect(app(FeatureGate::class)->allows($tenant, 'custom_domain'))->toBeTrue();
});

it('throws when asserting a missing feature', function () {
    $tenant = Tenant::factory()->create();
    Subscription::factory()->for($tenant)->create();

    app(FeatureGate::class)->assert($tenant, 'missing.feature');
})->throws(ValidationException::class);

it('allows usage under an integer limit', function () {
    $tenant = gateTenantWithFeature('users.max', FeatureType::Integer, 2);

    expect(app(FeatureGate::class)->canUse($tenant, 'users.max', 1))->toBeTrue();
});

it('rejects usage at an integer limit', function () {
    $tenant = gateTenantWithFeature('users.max', FeatureType::Integer, 2);

    app(FeatureGate::class)->assertCanUse($tenant, 'users.max', 2);
})->throws(ValidationException::class);

it('treats unlimited features as usable without a numeric limit', function () {
    $tenant = gateTenantWithFeature('products', FeatureType::Unlimited, 'unlimited');
    $gate = app(FeatureGate::class);

    expect($gate->isUnlimited($tenant, 'products'))->toBeTrue()
        ->and($gate->limit($tenant, 'products'))->toBeNull()
        ->and($gate->canUse($tenant, 'products', 999))->toBeTrue();
});

it('denies inactive catalog features even when attached', function () {
    $plan = Plan::factory()->active()->create();
    $feature = Feature::factory()->create([
        'key' => 'api.access',
        'type' => FeatureType::Boolean,
        'is_active' => false,
    ]);
    $plan->features()->attach($feature->id, ['value' => true]);

    $tenant = Tenant::factory()->create();
    Subscription::factory()->for($tenant)->for($plan)->create();

    expect(app(FeatureGate::class)->allows($tenant, 'api.access'))->toBeFalse();
});
