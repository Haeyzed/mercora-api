<?php

use App\Enums\Landlord\TenantStatus;
use App\Jobs\Landlord\ProvisionTenantJob;
use App\Models\Landlord\Domain;
use App\Models\Landlord\Invoice;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\Tenant;
use App\Services\Landlord\Settings\SettingService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(LazilyRefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function tenantPayload(array $overrides = []): array
{
    return [
        'name' => 'Acme Stores',
        'domain' => 'acme.example.com',
        ...$overrides,
    ];
}

describe('index', function () {
    it('returns a paginated list of tenants', function () {
        Tenant::factory()->create(['name' => 'Acme Stores']);
        Tenant::factory()->create(['name' => 'Beta Retail']);

        $this->getJson('/api/landlord/tenants')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'name', 'slug', 'status'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);
    });

    it('paginates tenants using the per_page query parameter', function () {
        Tenant::factory()->count(3)->create();

        $this->getJson('/api/landlord/tenants?per_page=2')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(2, 'data');
    });

    it('filters tenants by a partial name', function () {
        Tenant::factory()->create(['name' => 'Acme Stores']);
        Tenant::factory()->create(['name' => 'Beta Retail']);

        $this->getJson('/api/landlord/tenants?filter[name]=cme')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Acme Stores');
    });

    it('filters tenants by status', function () {
        Tenant::factory()->active()->create(['name' => 'Acme Stores']);
        Tenant::factory()->create(['name' => 'Beta Retail']);

        $this->getJson('/api/landlord/tenants?filter[status]=active')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Acme Stores')
            ->assertJsonPath('data.0.status', 'active');
    });

    it('searches tenants across name and slug', function (string $term) {
        Tenant::factory()->create(['name' => 'Acme Stores']);
        Tenant::factory()->create(['name' => 'Beta Retail']);

        $this->getJson('/api/landlord/tenants?search='.$term)
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Acme Stores');
    })->with([
        'name' => 'Acme',
        'slug' => 'acme-stores',
    ]);

    it('returns all tenants when search is blank', function () {
        Tenant::factory()->create(['name' => 'Acme Stores']);
        Tenant::factory()->create(['name' => 'Beta Retail']);

        $this->getJson('/api/landlord/tenants?search=')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    });

    it('ignores unknown filters instead of querying arbitrary columns', function () {
        Tenant::factory()->create(['name' => 'Acme Stores']);

        $this->getJson('/api/landlord/tenants?filter[password]=secret')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    });

    it('includes domains when requested', function () {
        $tenant = Tenant::factory()->create(['name' => 'Acme Stores']);
        Domain::factory()->for($tenant)->create(['domain' => 'acme.example.com']);

        $this->getJson('/api/landlord/tenants?include=domains')
            ->assertOk()
            ->assertJsonPath('data.0.domains.0.domain', 'acme.example.com');
    });
});

describe('options', function () {
    it('returns tenant options as label and value pairs', function () {
        $tenant = Tenant::factory()->create(['name' => 'Acme Stores']);

        $this->getJson('/api/landlord/tenants/options')
            ->assertOk()
            ->assertJsonPath('data.0.label', 'Acme Stores')
            ->assertJsonPath('data.0.value', $tenant->id);
    });

    it('searches tenant options by a single term', function () {
        Tenant::factory()->create(['name' => 'Acme Stores']);
        Tenant::factory()->create(['name' => 'Beta Retail']);

        $this->getJson('/api/landlord/tenants/options?search=Acme')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.label', 'Acme Stores');
    });
});

describe('store', function () {
    it('creates a tenant with its first domain and provisions it in testing', function () {
        $this->postJson('/api/landlord/tenants', tenantPayload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Acme Stores')
            ->assertJsonPath('data.slug', 'acme-stores')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.domains.0.domain', 'acme.example.com')
            ->assertJsonMissingPath('data.data')
            ->assertJsonMissingPath('data.provision_error');

        $this->assertDatabaseHas('tenants', [
            'name' => 'Acme Stores',
            'slug' => 'acme-stores',
            'status' => TenantStatus::Active->value,
        ]);
        $this->assertDatabaseHas('domains', [
            'domain' => 'acme.example.com',
        ]);
        $this->assertNotNull(Tenant::query()->where('name', 'Acme Stores')->value('provisioned_at'));
    });

    it('leaves the tenant provisioning when the job is queued', function () {
        Bus::fake([ProvisionTenantJob::class]);

        $this->postJson('/api/landlord/tenants', tenantPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'provisioning');

        $this->assertDatabaseHas('tenants', [
            'name' => 'Acme Stores',
            'status' => TenantStatus::Provisioning->value,
        ]);

        Bus::assertDispatched(ProvisionTenantJob::class);
    });

    it('skips provisioning when auto provision is disabled', function () {
        Bus::fake([ProvisionTenantJob::class]);

        app(SettingService::class)->updateDomain('registration', [
            'registration.auto_provision_tenant' => false,
        ]);

        $this->postJson('/api/landlord/tenants', tenantPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        Bus::assertNotDispatched(ProvisionTenantJob::class);
    });

    it('does not activate a tenant from a client-supplied status', function () {
        Bus::fake([ProvisionTenantJob::class]);

        $this->postJson('/api/landlord/tenants', tenantPayload([
            'status' => 'active',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'provisioning');
    });

    it('does not persist a client-supplied slug', function () {
        $this->postJson('/api/landlord/tenants', tenantPayload([
            'slug' => 'injected-slug',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.slug', 'acme-stores');

        $this->assertDatabaseMissing('tenants', [
            'slug' => 'injected-slug',
        ]);
    });

    it('returns 422 when required tenant fields are missing', function () {
        $this->postJson('/api/landlord/tenants', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'domain']);
    });

    it('returns 422 when the domain is a central domain', function (string $domain) {
        $this->postJson('/api/landlord/tenants', tenantPayload([
            'domain' => $domain,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['domain']);
    })->with([
        'localhost' => 'localhost',
        'loopback' => '127.0.0.1',
    ]);

    it('returns 422 when the domain is listed as a central domain', function () {
        config(['tenancy.central_domains' => ['mercora.test']]);

        $this->postJson('/api/landlord/tenants', tenantPayload([
            'domain' => 'mercora.test',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['domain']);
    });

    it('returns 422 when the domain is already taken', function () {
        $tenant = Tenant::factory()->create();
        Domain::factory()->for($tenant)->create(['domain' => 'acme.example.com']);

        $this->postJson('/api/landlord/tenants', tenantPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['domain']);
    });
});

describe('show', function () {
    it('returns a single tenant', function () {
        $tenant = Tenant::factory()->create(['name' => 'Acme Stores']);

        $this->getJson("/api/landlord/tenants/{$tenant->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Acme Stores')
            ->assertJsonPath('data.slug', 'acme-stores')
            ->assertJsonMissingPath('data.data')
            ->assertJsonMissingPath('data.domains');
    });

    it('returns 404 when the tenant does not exist', function () {
        $this->getJson('/api/landlord/tenants/9d8f0a1e-2b3c-4d5e-8f70-1234567890ab')
            ->assertNotFound();
    });

    it('includes domains when requested', function () {
        $tenant = Tenant::factory()->create(['name' => 'Acme Stores']);
        Domain::factory()->for($tenant)->create(['domain' => 'acme.example.com']);

        $this->getJson("/api/landlord/tenants/{$tenant->id}?include=domains")
            ->assertOk()
            ->assertJsonPath('data.domains.0.domain', 'acme.example.com');
    });

    it('includes subscriptions when requested', function () {
        $tenant = Tenant::factory()->create(['name' => 'Acme Stores']);
        Subscription::factory()->for($tenant)->create();

        $this->getJson("/api/landlord/tenants/{$tenant->id}?include=subscriptions")
            ->assertOk()
            ->assertJsonPath('data.subscriptions.0.tenant_id', $tenant->id);
    });

    it('includes invoices when requested', function () {
        $tenant = Tenant::factory()->create();
        $subscription = Subscription::factory()->for($tenant)->create();
        $invoice = Invoice::factory()->for($subscription)->create();

        $this->getJson("/api/landlord/tenants/{$tenant->id}?include=invoices")
            ->assertOk()
            ->assertJsonPath('data.invoices.0.id', $invoice->id)
            ->assertJsonPath('data.invoices.0.tenant_id', $tenant->id);
    });
});

describe('update', function () {
    it('updates a tenant and regenerates the slug from the name', function () {
        $tenant = Tenant::factory()->create(['name' => 'Acme Stores']);

        $this->putJson("/api/landlord/tenants/{$tenant->id}", [
            'name' => 'Acme Marketplace',
            'status' => 'active',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Acme Marketplace')
            ->assertJsonPath('data.slug', 'acme-marketplace')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'Acme Marketplace',
            'slug' => 'acme-marketplace',
            'status' => TenantStatus::Pending->value,
        ]);
    });
});

describe('destroy', function () {
    it('soft deletes a tenant', function () {
        $tenant = Tenant::factory()->create(['name' => 'Acme Stores']);

        $this->deleteJson("/api/landlord/tenants/{$tenant->id}")
            ->assertNoContent();

        $this->assertSoftDeleted($tenant);
    });

    it('returns 404 when showing a soft-deleted tenant', function () {
        $tenant = Tenant::factory()->create(['name' => 'Acme Stores']);
        $tenant->delete();

        $this->getJson("/api/landlord/tenants/{$tenant->id}")
            ->assertNotFound();
    });
});

describe('restore', function () {
    it('restores a soft-deleted tenant', function () {
        $tenant = Tenant::factory()->create(['name' => 'Acme Stores']);
        $tenant->delete();

        $this->postJson("/api/landlord/tenants/{$tenant->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.name', 'Acme Stores');

        $this->assertNotSoftDeleted($tenant);
    });

    it('returns 404 when the tenant is not soft deleted', function () {
        $tenant = Tenant::factory()->create(['name' => 'Acme Stores']);

        $this->postJson("/api/landlord/tenants/{$tenant->id}/restore")
            ->assertNotFound();
    });

    it('returns 404 when the tenant does not exist', function () {
        $this->postJson('/api/landlord/tenants/9d8f0a1e-2b3c-4d5e-8f70-1234567890ab/restore')
            ->assertNotFound();
    });
});

describe('destroyMany', function () {
    it('soft deletes the given tenants', function () {
        $acme = Tenant::factory()->create(['name' => 'Acme Stores']);
        $beta = Tenant::factory()->create(['name' => 'Beta Retail']);

        $this->deleteJson('/api/landlord/tenants/destroy-many', [
            'ids' => [$acme->id, $beta->id],
        ])->assertNoContent();

        $this->assertSoftDeleted($acme);
        $this->assertSoftDeleted($beta);
    });

    it('returns 422 when ids are missing', function () {
        $this->deleteJson('/api/landlord/tenants/destroy-many', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids']);
    });

    it('returns 422 when an id is not a uuid', function () {
        $this->deleteJson('/api/landlord/tenants/destroy-many', [
            'ids' => [1],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids.0']);
    });

    it('returns 422 when an id does not exist', function () {
        $this->deleteJson('/api/landlord/tenants/destroy-many', [
            'ids' => ['9d8f0a1e-2b3c-4d5e-8f70-1234567890ab'],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids.0']);
    });
});

describe('restoreMany', function () {
    it('restores the given soft-deleted tenants', function () {
        $acme = Tenant::factory()->create(['name' => 'Acme Stores']);
        $beta = Tenant::factory()->create(['name' => 'Beta Retail']);
        $acme->delete();
        $beta->delete();

        $this->postJson('/api/landlord/tenants/restore-many', [
            'ids' => [$acme->id, $beta->id],
        ])->assertNoContent();

        $this->assertNotSoftDeleted($acme);
        $this->assertNotSoftDeleted($beta);
    });

    it('returns 422 when an id is not soft deleted', function () {
        $tenant = Tenant::factory()->create(['name' => 'Acme Stores']);

        $this->postJson('/api/landlord/tenants/restore-many', [
            'ids' => [$tenant->id],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['ids.0']);
    });
});
