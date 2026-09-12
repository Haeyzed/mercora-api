<?php

declare(strict_types=1);

namespace App\Jobs\Landlord;

use App\Enums\Landlord\TenantStatus;
use App\Models\Landlord\Tenant;
use App\Services\Landlord\Tenants\TenantService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Database\DatabaseManager;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;
use Stancl\Tenancy\Jobs\SeedDatabase;
use Throwable;

/**
 * Provision tenant infrastructure after subscription signup or payment.
 *
 * Idempotent: unique per tenant for one hour ({@see UniqueFor}). Skips work when
 * the tenant is already active. Creates the tenant database, migrates, seeds RBAC,
 * finalizes the first Admin from pending_provision, then marks provisioning complete.
 */
#[UniqueFor(3600)]
class ProvisionTenantJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * @param  Tenant  $tenant  Landlord tenant record to provision.
     */
    public function __construct(public Tenant $tenant) {}

    /**
     * Queue uniqueness key preventing concurrent provisioning for the same tenant.
     */
    public function uniqueId(): string
    {
        return $this->tenant->getKey();
    }

    /**
     * Create tenant database, migrate, seed, finalize admin, and activate.
     *
     * No-op when the tenant is missing or already active. Infrastructure steps are
     * skipped in the testing environment unless RUN_TENANT_PROVISIONING_INTEGRATION.
     *
     * @throws Throwable When database creation, seed, or finalize fails irrecoverably.
     */
    public function handle(TenantService $tenantService): void
    {
        $tenant = $this->tenant->fresh();

        if ($tenant === null || $tenant->status === TenantStatus::Active) {
            return;
        }

        $tenantService->markProvisioning($tenant);

        try {
            if ($this->shouldProvisionInfrastructure()) {
                $this->provisionInfrastructure($tenant);
            }

            $tenantService->completeProvisioning($tenant->fresh() ?? $tenant);
        } catch (Throwable $exception) {
            Log::error('Tenant provisioning failed.', [
                'tenant_id' => $tenant->id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $tenantService->failProvisioning($tenant, 'Tenant provisioning failed.');

            throw $exception;
        }
    }

    private function shouldProvisionInfrastructure(): bool
    {
        if (! app()->environment('testing')) {
            return true;
        }

        return filter_var(env('RUN_TENANT_PROVISIONING_INTEGRATION', false), FILTER_VALIDATE_BOOL);
    }

    /**
     * @throws Throwable
     */
    private function provisionInfrastructure(Tenant $tenant): void
    {
        try {
            (new CreateDatabase($tenant))->handle(app(DatabaseManager::class));
        } catch (Throwable $exception) {
            if (! str_contains(strtolower($exception->getMessage()), 'already')) {
                throw $exception;
            }
        }

        (new MigrateDatabase($tenant))->handle();
        (new SeedDatabase($tenant))->handle();
        (new FinalizeTenantProvision($tenant))->handle();
    }
}
