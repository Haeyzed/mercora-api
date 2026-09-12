<?php

declare(strict_types=1);

use App\Enums\Landlord\SubscriptionStatus;
use App\Http\Middleware\EnsureTenantHttps;
use App\Http\Middleware\EnsureTenantNotSuspended;
use App\Http\Middleware\InitializeTenancyByDomainOrHeader;
use App\Http\Middleware\PreventAccessFromCentralDomainsUnlessTenantHeader;
use App\Models\Landlord\Subscription;
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
    ])->get('/api/tenant/__test/subscription-probe', fn () => response()->json(['ok' => true]));
});

afterEach(function (): void {
    if (isset($this->tenantContext) && $this->tenantContext instanceof TenantTestContext) {
        $this->tenantContext->tearDown();
    }
});

it('allows product routes when the subscription is active', function (): void {
    $context = $this->tenantContext = provisionTenantContext();
    Subscription::factory()->for($context->tenant)->create([
        'status' => SubscriptionStatus::Active,
        'is_current' => 1,
    ]);

    [, $token] = loginTenantStaff($context);

    $this->withToken($token)
        ->getJson($context->url('/api/tenant/__test/subscription-probe'))
        ->assertOk()
        ->assertJsonPath('ok', true);
});

it('allows product routes when the subscription is trialing', function (): void {
    $context = $this->tenantContext = provisionTenantContext();
    Subscription::factory()->for($context->tenant)->trialing()->create();

    [, $token] = loginTenantStaff($context);

    $this->withToken($token)
        ->getJson($context->url('/api/tenant/__test/subscription-probe'))
        ->assertOk()
        ->assertJsonPath('ok', true);
});

it('returns 402 when the subscription is past due', function (): void {
    $context = $this->tenantContext = provisionTenantContext();
    Subscription::factory()->for($context->tenant)->pastDue()->create();

    [, $token] = loginTenantStaff($context);

    $this->withToken($token)
        ->getJson($context->url('/api/tenant/__test/subscription-probe'))
        ->assertPaymentRequired()
        ->assertJsonPath('message', 'An active subscription is required.');
});

it('returns 402 when the subscription is pending payment', function (): void {
    $context = $this->tenantContext = provisionTenantContext();
    Subscription::factory()->for($context->tenant)->pendingPayment()->create();

    [, $token] = loginTenantStaff($context);

    $this->withToken($token)
        ->getJson($context->url('/api/tenant/__test/subscription-probe'))
        ->assertPaymentRequired()
        ->assertJsonPath('message', 'An active subscription is required.');
});

it('returns 402 when the tenant has no subscription', function (): void {
    $context = $this->tenantContext = provisionTenantContext();

    [, $token] = loginTenantStaff($context);

    $this->withToken($token)
        ->getJson($context->url('/api/tenant/__test/subscription-probe'))
        ->assertPaymentRequired()
        ->assertJsonPath('message', 'An active subscription is required.');
});

it('still allows auth me when the subscription is past due', function (): void {
    $context = $this->tenantContext = provisionTenantContext();
    Subscription::factory()->for($context->tenant)->pastDue()->create();

    [, $token] = loginTenantStaff($context);

    $this->withToken($token)
        ->getJson($context->url('/api/tenant/auth/me'))
        ->assertOk()
        ->assertJsonPath('data.email', 'staff@acme.test');
});
