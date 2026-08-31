<?php

/**
 * Real tenant database provisioning integration tests.
 *
 * These tests create and drop actual tenant databases. They are skipped by
 * default in the normal test suite to avoid destructive side effects on
 * developer machines using SQLite in-memory or shared databases.
 *
 * To run:
 *   RUN_TENANT_PROVISIONING_INTEGRATION=true php artisan test tests/Integration/TenantProvisioningIntegrationTest.php
 *
 * Requirements:
 * - A database driver that supports Stancl tenant database creation (MySQL recommended)
 * - TENANT_PROVISIONING_TEST_DATABASE configured in phpunit.xml or environment
 */

use App\Enums\Landlord\TenantStatus;
use App\Jobs\Landlord\ProvisionTenantJob;
use App\Models\Landlord\Tenant;
use App\Services\Landlord\Tenants\TenantProvisioningVerifier;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    if (! filter_var(env('RUN_TENANT_PROVISIONING_INTEGRATION', false), FILTER_VALIDATE_BOOL)) {
        $this->markTestSkipped('Set RUN_TENANT_PROVISIONING_INTEGRATION=true to run tenant provisioning integration tests.');
    }
});

afterEach(function (): void {
    if (! isset($this->provisionedTenant) || ! $this->provisionedTenant instanceof Tenant) {
        return;
    }

    $tenant = $this->provisionedTenant->fresh();

    if ($tenant === null) {
        return;
    }

    try {
        $tenant->forceDelete();
    } catch (Throwable $exception) {
        report($exception);
    }
});

it('provisions a tenant database and runs tenant migrations', function () {
    $tenant = Tenant::factory()->create([
        'status' => TenantStatus::Provisioning,
    ]);

    $tenant->createDomain('integration-'.uniqid().'.test');

    ProvisionTenantJob::dispatchSync($tenant);

    $tenant->refresh();
    $this->provisionedTenant = $tenant;

    expect($tenant->status)->toBe(TenantStatus::Active)
        ->and($tenant->provisioned_at)->not->toBeNull()
        ->and(app(TenantProvisioningVerifier::class)->isProvisioned($tenant))->toBeTrue();

    $databaseName = $tenant->database()->getName();

    expect($tenant->database()->manager()->databaseExists($databaseName))->toBeTrue();

    $tenant->run(function (): void {
        expect(Schema::hasTable('migrations'))->toBeTrue();
    });
});
