<?php

declare(strict_types=1);

namespace App\Services\Landlord\Tenants;

use App\Models\Landlord\Tenant;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Verifies that a tenant has completed database provisioning before activation.
 *
 * Domain: Stancl Tenancy tenant readiness checks.
 *
 * Invariants:
 * - A tenant is considered provisioned only when {@see Tenant::$provisioned_at} is set and the tenant database exists.
 * - In the testing environment, database existence is not checked once provisioned_at is set.
 * - Activation additionally requires a migrations table in the tenant database (non-testing).
 *
 * Side effects: runs tenant-context schema checks; aborts HTTP requests when assertions fail.
 */
class TenantProvisioningVerifier
{
    /**
     * Determine whether the tenant has completed provisioning.
     *
     * Returns false when provisioned_at is null, the database is missing, or the existence check throws.
     */
    public function isProvisioned(Tenant $tenant): bool
    {
        if ($tenant->provisioned_at === null) {
            return false;
        }

        if (app()->environment('testing') && ! $this->integrationTestsEnabled()) {
            return $tenant->provisioned_at !== null;
        }

        try {
            return $tenant->database()->manager()->databaseExists($tenant->database()->getName());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Assert the tenant is provisioned and its database has run migrations.
     *
     * @throws HttpException With 422 when not provisioned or migrations are missing.
     */
    public function assertProvisioned(Tenant $tenant): void
    {
        if (! $this->isProvisioned($tenant)) {
            abort(422, 'The tenant has not completed provisioning.');
        }

        if (! app()->environment('testing') || $this->integrationTestsEnabled()) {
            if (! $this->hasMigrationsTable($tenant)) {
                abort(422, 'The tenant database is not ready.');
            }
        }
    }

    /**
     * Whether tenant provisioning integration tests are enabled.
     */
    private function integrationTestsEnabled(): bool
    {
        return filter_var(env('RUN_TENANT_PROVISIONING_INTEGRATION', false), FILTER_VALIDATE_BOOL);
    }

    /**
     * Check whether the tenant database contains a migrations table.
     */
    private function hasMigrationsTable(Tenant $tenant): bool
    {
        return $tenant->run(function (): bool {
            return Schema::hasTable('migrations');
        });
    }
}
