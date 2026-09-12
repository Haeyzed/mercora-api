<?php

declare(strict_types=1);

use App\Enums\Landlord\TenantStatus;
use App\Http\Middleware\InitializeTenancyByDomainOrHeader;
use App\Models\Landlord\Domain;
use App\Models\Landlord\Tenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Stancl\Tenancy\Tenancy;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    config([
        'tenancy.central_domains' => ['mercora.test', 'localhost', '127.0.0.1'],
        // Identification tests do not need a provisioned tenant database.
        'tenancy.bootstrappers' => [],
    ]);
});

afterEach(function (): void {
    $tenancy = app(Tenancy::class);

    if ($tenancy->initialized) {
        $tenancy->end();
    }
});

/**
 * @return array{0: Tenant, 1: string}
 */
function makeTenantWithDomain(TenantStatus $status = TenantStatus::Active): array
{
    $tenant = Tenant::factory()->create([
        'status' => $status,
        'provisioned_at' => $status === TenantStatus::Active || $status === TenantStatus::Suspended
            ? now()
            : null,
    ]);

    $domain = 'shop-'.uniqid().'.example.test';
    Domain::factory()->for($tenant)->create(['domain' => $domain]);

    return [$tenant->fresh(), $domain];
}

it('initializes tenancy from the request host and returns health', function (): void {
    [$tenant, $domain] = makeTenantWithDomain();

    $this->getJson('https://'.$domain.'/api/tenant/health')
        ->assertOk()
        ->assertJsonPath('data.id', $tenant->id)
        ->assertJsonPath('data.status', TenantStatus::Active->value);
});

it('blocks central host without X-Tenant-Domain', function (): void {
    makeTenantWithDomain();

    $this->getJson('https://mercora.test/api/tenant/health')
        ->assertNotFound();
});

it('initializes tenancy from X-Tenant-Domain on a central host', function (): void {
    [$tenant, $domain] = makeTenantWithDomain();

    $this->getJson('https://mercora.test/api/tenant/health', [
        InitializeTenancyByDomainOrHeader::HEADER => $domain,
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $tenant->id)
        ->assertJsonPath('data.status', TenantStatus::Active->value);
});

it('returns not found for an unknown tenant domain', function (): void {
    $this->getJson('https://unknown.example.test/api/tenant/health')
        ->assertNotFound();
});

it('returns not found for an unknown X-Tenant-Domain header', function (): void {
    $this->getJson('https://mercora.test/api/tenant/health', [
        InitializeTenancyByDomainOrHeader::HEADER => 'missing.example.test',
    ])
        ->assertNotFound();
});

it('blocks suspended tenants on the tenant health endpoint', function (): void {
    [, $domain] = makeTenantWithDomain(TenantStatus::Suspended);

    $this->getJson('https://'.$domain.'/api/tenant/health')
        ->assertForbidden()
        ->assertJsonPath('message', 'This tenant account has been suspended.');
});
