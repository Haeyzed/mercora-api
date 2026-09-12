<?php

declare(strict_types=1);

use App\Enums\Landlord\TenantStatus;
use App\Models\Landlord\Domain;
use App\Models\Landlord\Tenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    config([
        'tenancy.central_domains' => ['mercora.test', 'localhost', '127.0.0.1'],
    ]);
});

/**
 * @return array{0: Tenant, 1: string}
 */
function makePublicResolveTenant(TenantStatus $status = TenantStatus::Active): array
{
    $tenant = Tenant::factory()->create([
        'status' => $status,
        'name' => 'ABC Store',
        'provisioned_at' => $status === TenantStatus::Active || $status === TenantStatus::Suspended
            ? now()
            : null,
    ]);

    $domain = ($tenant->slug).'.example.test';
    Domain::factory()->for($tenant)->create(['domain' => $domain]);

    return [$tenant->fresh(['domains']), $domain];
}

it('resolves a public tenant by domain without authentication', function (): void {
    [$tenant, $domain] = makePublicResolveTenant();

    $this->getJson('/api/public/tenant?domain='.$domain)
        ->assertOk()
        ->assertJsonPath('data.id', $tenant->id)
        ->assertJsonPath('data.name', 'ABC Store')
        ->assertJsonPath('data.slug', $tenant->slug)
        ->assertJsonPath('data.status', TenantStatus::Active->value)
        ->assertJsonPath('data.allows_login', true)
        ->assertJsonPath('data.domains.0.domain', $domain)
        ->assertJsonMissingPath('data.provision_error');
});

it('returns not found for an unknown domain', function (): void {
    $this->getJson('/api/public/tenant?domain=unknown.example.test')
        ->assertNotFound();
});

it('resolves a suspended tenant but does not allow login', function (): void {
    [$tenant, $domain] = makePublicResolveTenant(TenantStatus::Suspended);

    $this->getJson('/api/public/tenant?domain='.$domain)
        ->assertOk()
        ->assertJsonPath('data.id', $tenant->id)
        ->assertJsonPath('data.status', TenantStatus::Suspended->value)
        ->assertJsonPath('data.allows_login', false);
});

it('rejects central domains as public tenants', function (): void {
    $this->getJson('/api/public/tenant?domain=mercora.test')
        ->assertNotFound();
});
