<?php

use App\Models\Landlord\Domain;
use App\Models\Landlord\Tenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('index', function () {
    it('returns a paginated list of domains for the tenant', function () {
        $tenant = Tenant::factory()->create(['name' => 'Acme Stores']);
        Domain::factory()->for($tenant)->create(['domain' => 'acme.example.com']);
        Domain::factory()->for($tenant)->create(['domain' => 'shop.acme.example.com']);
        Domain::factory()->create(['domain' => 'other.example.com']);

        $this->getJson("/api/landlord/tenants/{$tenant->id}/domains")
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'domain', 'tenant_id'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);
    });

    it('searches domains by hostname', function () {
        $tenant = Tenant::factory()->create(['name' => 'Acme Stores']);
        Domain::factory()->for($tenant)->create(['domain' => 'acme.example.com']);
        Domain::factory()->for($tenant)->create(['domain' => 'shop.acme.example.com']);

        $this->getJson("/api/landlord/tenants/{$tenant->id}/domains?search=shop")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.domain', 'shop.acme.example.com');
    });
});

describe('store', function () {
    it('creates a domain for the tenant', function () {
        $tenant = Tenant::factory()->create(['name' => 'Acme Stores']);

        $this->postJson("/api/landlord/tenants/{$tenant->id}/domains", [
            'domain' => 'shop.acme.example.com',
        ])
            ->assertCreated()
            ->assertJsonPath('data.domain', 'shop.acme.example.com')
            ->assertJsonPath('data.tenant_id', $tenant->id);

        $this->assertDatabaseHas('domains', [
            'domain' => 'shop.acme.example.com',
            'tenant_id' => $tenant->id,
        ]);
    });

    it('returns 422 when the domain is missing', function () {
        $tenant = Tenant::factory()->create(['name' => 'Acme Stores']);

        $this->postJson("/api/landlord/tenants/{$tenant->id}/domains", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['domain']);
    });

    it('returns 422 when the domain is a central domain', function (string $domain) {
        $tenant = Tenant::factory()->create(['name' => 'Acme Stores']);

        $this->postJson("/api/landlord/tenants/{$tenant->id}/domains", [
            'domain' => $domain,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['domain']);
    })->with([
        'localhost' => 'localhost',
        'loopback' => '127.0.0.1',
    ]);

    it('returns 422 when the domain is already taken', function () {
        $tenant = Tenant::factory()->create(['name' => 'Acme Stores']);
        Domain::factory()->for($tenant)->create(['domain' => 'acme.example.com']);

        $this->postJson("/api/landlord/tenants/{$tenant->id}/domains", [
            'domain' => 'acme.example.com',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['domain']);
    });
});

describe('destroy', function () {
    it('permanently deletes a domain', function () {
        $tenant = Tenant::factory()->create(['name' => 'Acme Stores']);
        $domain = Domain::factory()->for($tenant)->create(['domain' => 'acme.example.com']);

        $this->deleteJson("/api/landlord/tenants/{$tenant->id}/domains/{$domain->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('domains', [
            'id' => $domain->id,
        ]);
    });

    it('returns 404 when the domain belongs to another tenant', function () {
        $tenant = Tenant::factory()->create(['name' => 'Acme Stores']);
        $other = Tenant::factory()->create(['name' => 'Beta Retail']);
        $domain = Domain::factory()->for($other)->create(['domain' => 'beta.example.com']);

        $this->deleteJson("/api/landlord/tenants/{$tenant->id}/domains/{$domain->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('domains', [
            'id' => $domain->id,
            'domain' => 'beta.example.com',
        ]);
    });
});
