<?php

use App\Enums\Landlord\RoleName;
use App\Enums\Landlord\TenantStatus;
use App\Jobs\Landlord\ProvisionTenantJob;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\User;
use App\Services\Landlord\Tenants\TenantService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(LazilyRefreshDatabase::class);

describe('provision', function () {
    it('retries provisioning for a failed tenant', function () {
        $tenant = Tenant::factory()->failed()->create();

        $this->postJson("/api/landlord/tenants/{$tenant->id}/provision")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'status' => TenantStatus::Active->value,
        ]);
        $this->assertNotNull($tenant->fresh()->provisioned_at);
    });

    it('returns 422 when an active tenant is provisioned again', function () {
        $tenant = Tenant::factory()->active()->create();

        $this->postJson("/api/landlord/tenants/{$tenant->id}/provision")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });
});

describe('activate', function () {
    it('returns 422 when the tenant has not been provisioned', function () {
        $tenant = Tenant::factory()->create();

        $this->postJson("/api/landlord/tenants/{$tenant->id}/activate")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });
});

describe('suspend and reactivate', function () {
    it('suspends an active tenant and reactivates it', function () {
        $tenant = Tenant::factory()->active()->create();

        $this->postJson("/api/landlord/tenants/{$tenant->id}/suspend")
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this->postJson("/api/landlord/tenants/{$tenant->id}/reactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    });

    it('returns 422 when a pending tenant is suspended', function () {
        $tenant = Tenant::factory()->create();

        $this->postJson("/api/landlord/tenants/{$tenant->id}/suspend")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });

    it('returns 422 when an active tenant is reactivated', function () {
        $tenant = Tenant::factory()->active()->create();

        $this->postJson("/api/landlord/tenants/{$tenant->id}/reactivate")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    });
});

describe('force delete', function () {
    it('permanently deletes a tenant', function () {
        $tenant = Tenant::factory()->active()->create();

        $this->deleteJson("/api/landlord/tenants/{$tenant->id}/force")
            ->assertNoContent();

        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
    });

    it('returns 403 when the user cannot force delete tenants', function () {
        $user = User::factory()->create();
        actingAsLandlord($user, superAdmin: false);
        $user->assignRole(RoleName::Operator->value);

        $tenant = Tenant::factory()->active()->create();

        $this->deleteJson("/api/landlord/tenants/{$tenant->id}/force")
            ->assertForbidden();

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
    });
});

describe('provisioning failure', function () {
    it('marks the tenant failed and does not activate it', function () {
        Bus::fake([ProvisionTenantJob::class]);

        $tenant = Tenant::factory()->create();
        app(TenantService::class)->failProvisioning($tenant, 'Tenant provisioning failed.');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'status' => TenantStatus::Failed->value,
        ]);
        $this->assertNull($tenant->fresh()->provisioned_at);
    });
});
