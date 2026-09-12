<?php

declare(strict_types=1);

use App\Enums\Landlord\FeatureType;
use App\Enums\Landlord\SubscriptionStatus;
use App\Http\Middleware\EnsureTenantHttps;
use App\Http\Middleware\EnsureTenantNotSuspended;
use App\Http\Middleware\InitializeTenancyByDomainOrHeader;
use App\Http\Middleware\PreventAccessFromCentralDomainsUnlessTenantHeader;
use App\Models\Landlord\Feature;
use App\Models\Landlord\Plan;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\Tenant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\Support\TenantTestContext;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    config([
        'tenancy.central_domains' => ['mercora.test', 'localhost', '127.0.0.1'],
    ]);

    RateLimiter::for('tenant-auth', fn (): Limit => Limit::none());
    RateLimiter::for('tenant-api', fn (): Limit => Limit::none());

    Route::middleware([
        'api',
        InitializeTenancyByDomainOrHeader::class,
        PreventAccessFromCentralDomainsUnlessTenantHeader::class,
        EnsureTenantHttps::class,
        EnsureTenantNotSuspended::class,
        'tenant.guard',
        'auth:tenant',
        'subscription.active',
        'feature:custom_domain',
    ])->get('/api/tenant/__test/feature-probe', fn () => response()->json(['ok' => true]));
});

afterEach(function (): void {
    if (isset($this->tenantContext) && $this->tenantContext instanceof TenantTestContext) {
        $this->tenantContext->tearDown();
    }
});

/**
 * Attach an active subscription with an optional boolean feature on the plan.
 */
function subscribeTenantWithOptionalFeature(Tenant $tenant, bool $withFeature, SubscriptionStatus $status = SubscriptionStatus::Active): void
{
    $plan = Plan::factory()->active()->create();

    if ($withFeature) {
        $feature = Feature::factory()->create([
            'key' => 'custom_domain',
            'type' => FeatureType::Boolean,
            'is_active' => true,
        ]);
        $plan->features()->attach($feature->id, ['value' => true]);
    }

    $factory = Subscription::factory()->for($tenant)->for($plan);

    if ($status === SubscriptionStatus::PastDue) {
        $factory->pastDue()->create();

        return;
    }

    $factory->create([
        'status' => $status,
        'is_current' => 1,
    ]);
}

it('allows the feature probe when the plan includes custom_domain', function (): void {
    $context = $this->tenantContext = provisionTenantContext();
    subscribeTenantWithOptionalFeature($context->tenant, withFeature: true);

    [, $token] = loginTenantStaff($context);

    $this->withToken($token)
        ->getJson($context->url('/api/tenant/__test/feature-probe'))
        ->assertOk()
        ->assertJsonPath('ok', true);
});

it('returns 403 when the plan does not include custom_domain', function (): void {
    $context = $this->tenantContext = provisionTenantContext();
    subscribeTenantWithOptionalFeature($context->tenant, withFeature: false);

    [, $token] = loginTenantStaff($context);

    $this->withToken($token)
        ->getJson($context->url('/api/tenant/__test/feature-probe'))
        ->assertForbidden()
        ->assertJsonPath('message', 'Your current plan does not include this feature.');
});

it('returns 402 before feature checks when the subscription is past due', function (): void {
    $context = $this->tenantContext = provisionTenantContext();
    subscribeTenantWithOptionalFeature($context->tenant, withFeature: true, status: SubscriptionStatus::PastDue);

    [, $token] = loginTenantStaff($context);

    $this->withToken($token)
        ->getJson($context->url('/api/tenant/__test/feature-probe'))
        ->assertPaymentRequired()
        ->assertJsonPath('message', 'An active subscription is required.');
});

it('still allows auth me without the feature entitlement', function (): void {
    $context = $this->tenantContext = provisionTenantContext();
    subscribeTenantWithOptionalFeature($context->tenant, withFeature: false);

    [, $token] = loginTenantStaff($context);

    $this->withToken($token)
        ->getJson($context->url('/api/tenant/auth/me'))
        ->assertOk()
        ->assertJsonPath('data.email', 'staff@acme.test');
});
