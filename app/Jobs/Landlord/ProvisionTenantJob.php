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
use Throwable;

#[UniqueFor(3600)]
class ProvisionTenantJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(public Tenant $tenant) {}

    public function uniqueId(): string
    {
        return $this->tenant->getKey();
    }

    public function handle(TenantService $tenantService): void
    {
        $tenant = $this->tenant->fresh();

        if ($tenant === null || $tenant->status === TenantStatus::Active) {
            return;
        }

        $tenantService->markProvisioning($tenant);

        try {
            if (! app()->environment('testing')) {
                $this->provisionInfrastructure($tenant);
            }

            $tenantService->completeProvisioning($tenant);
        } catch (Throwable $exception) {
            Log::error('Tenant provisioning failed.', [
                'tenant_id' => $tenant->id,
                'exception' => $exception::class,
            ]);

            $tenantService->failProvisioning($tenant, 'Tenant provisioning failed.');

            throw $exception;
        }
    }

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
    }
}
