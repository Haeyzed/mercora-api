<?php

use App\Enums\Landlord\TenantStatus;
use App\Http\Middleware\EnsureTenantNotSuspended;
use App\Models\Landlord\Tenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Stancl\Tenancy\Tenancy;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    actingAsLandlord();
});

afterEach(function (): void {
    app(Tenancy::class)->tenant = null;
});

function bindTenantForMiddleware(Tenant $tenant): void
{
    app(Tenancy::class)->tenant = $tenant;
}

it('allows active tenants to continue through tenant middleware', function () {
    $tenant = Tenant::factory()->create(['status' => TenantStatus::Active]);
    bindTenantForMiddleware($tenant);

    $middleware = new EnsureTenantNotSuspended;
    $response = $middleware->handle(Request::create('/'), fn () => response('ok', 200));

    expect($response->getStatusCode())->toBe(200);
});

it('blocks suspended tenants with a 403 response', function () {
    $tenant = Tenant::factory()->create(['status' => TenantStatus::Suspended]);
    bindTenantForMiddleware($tenant);

    $middleware = new EnsureTenantNotSuspended;
    $response = $middleware->handle(Request::create('/'), fn () => response('ok', 200));

    expect($response->getStatusCode())->toBe(403)
        ->and($response->getContent())->toContain('suspended');
});

it('allows reactivated tenants after suspension is lifted', function () {
    $tenant = Tenant::factory()->create(['status' => TenantStatus::Suspended]);
    bindTenantForMiddleware($tenant);

    $middleware = new EnsureTenantNotSuspended;
    $response = $middleware->handle(Request::create('/'), fn () => response('ok', 200));

    expect($response->getStatusCode())->toBe(403);

    $tenant->update(['status' => TenantStatus::Active]);
    bindTenantForMiddleware($tenant->fresh());

    $response = $middleware->handle(Request::create('/'), fn () => response('ok', 200));

    expect($response->getStatusCode())->toBe(200);
});

it('does not block landlord APIs for suspended tenants', function () {
    $tenant = Tenant::factory()->create([
        'status' => TenantStatus::Suspended,
        'name' => 'Suspended Tenant',
    ]);

    $this->getJson("/api/landlord/tenants/{$tenant->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'suspended');
});

it('still resolves tenant context when bound for middleware', function () {
    $tenant = Tenant::factory()->create(['status' => TenantStatus::Active]);
    bindTenantForMiddleware($tenant);

    expect(tenant('id'))->toBe($tenant->id);
});
