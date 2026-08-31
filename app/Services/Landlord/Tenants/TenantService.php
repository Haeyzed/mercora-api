<?php

declare(strict_types=1);

namespace App\Services\Landlord\Tenants;

use App\Enums\Landlord\TenantStatus;
use App\Jobs\Landlord\ProvisionTenantJob;
use App\Models\Landlord\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Landlord tenant records, first hostname, and lifecycle.
 */
class TenantService
{
    /**
     * Paginate tenants using model filter, search, and include scopes.
     *
     * @return LengthAwarePaginator<int, Tenant>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return Tenant::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->withIncludes($request->query('include'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString();
    }

    /**
     * Paginate tenant select options as label/value pairs.
     *
     * @return LengthAwarePaginator<int, array{label: string, value: string}>
     */
    public function options(Request $request): LengthAwarePaginator
    {
        return Tenant::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (Tenant $tenant): array => [
                'label' => $tenant->name,
                'value' => $tenant->id,
            ]);
    }

    /**
     * Load a tenant with optional allowed relationships.
     */
    public function show(Tenant $tenant, Request $request): Tenant
    {
        return $tenant->loadAllowedIncludes($request->query('include'));
    }

    /**
     * Create a tenant and its first domain.
     *
     * @param  array{name: string, domain: string}  $data
     */
    public function store(array $data): Tenant
    {
        $tenant = DB::transaction(function () use ($data): Tenant {
            $tenant = Tenant::query()->create([
                'name' => $data['name'],
                'status' => TenantStatus::Pending,
            ]);

            $tenant->createDomain($data['domain']);

            return $tenant->load('domains');
        });

        $this->dispatchProvisioning($tenant);

        return $tenant->fresh(['domains']) ?? $tenant;
    }

    /**
     * Update a tenant. Lifecycle status is not client-writable.
     *
     * @param  array{name?: string}  $data
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        unset($data['status'], $data['provisioned_at'], $data['provision_error']);

        $tenant->update($data);

        return $tenant->refresh();
    }

    /**
     * Dispatch provisioning for a pending or failed tenant.
     */
    public function provision(Tenant $tenant): Tenant
    {
        if (! in_array($tenant->status, TenantStatus::provisionable(), true)) {
            throw ValidationException::withMessages([
                'status' => 'The tenant cannot be provisioned in its current state.',
            ]);
        }

        $this->dispatchProvisioning($tenant);

        return $tenant->fresh() ?? $tenant;
    }

    /**
     * Activate a tenant that already completed provisioning.
     */
    public function activate(Tenant $tenant): Tenant
    {
        if (! $tenant->status->canActivate()) {
            throw ValidationException::withMessages([
                'status' => 'The tenant cannot be activated in its current state.',
            ]);
        }

        if ($tenant->provisioned_at === null) {
            throw ValidationException::withMessages([
                'status' => 'The tenant has not been provisioned.',
            ]);
        }

        $tenant->update([
            'status' => TenantStatus::Active,
            'provision_error' => null,
        ]);

        return $tenant->refresh();
    }

    /**
     * Suspend an active tenant.
     */
    public function suspend(Tenant $tenant): Tenant
    {
        if (! $tenant->status->canSuspend()) {
            throw ValidationException::withMessages([
                'status' => 'The tenant cannot be suspended in its current state.',
            ]);
        }

        $tenant->update([
            'status' => TenantStatus::Suspended,
        ]);

        return $tenant->refresh();
    }

    /**
     * Return a suspended tenant to active.
     */
    public function reactivate(Tenant $tenant): Tenant
    {
        if (! $tenant->status->canReactivate()) {
            throw ValidationException::withMessages([
                'status' => 'The tenant cannot be reactivated in its current state.',
            ]);
        }

        $tenant->update([
            'status' => TenantStatus::Active,
        ]);

        return $tenant->refresh();
    }

    public function markProvisioning(Tenant $tenant): void
    {
        $tenant->update([
            'status' => TenantStatus::Provisioning,
            'provision_error' => null,
        ]);
    }

    public function completeProvisioning(Tenant $tenant): void
    {
        $tenant->update([
            'status' => TenantStatus::Active,
            'provisioned_at' => $tenant->provisioned_at ?? now(),
            'provision_error' => null,
        ]);
    }

    public function failProvisioning(Tenant $tenant, string $message): void
    {
        $tenant->update([
            'status' => TenantStatus::Failed,
            'provision_error' => $message,
        ]);
    }

    /**
     * Soft delete a tenant without dropping its database.
     */
    public function destroy(Tenant $tenant): void
    {
        $tenant->delete();
    }

    /**
     * Force delete a tenant and its database.
     */
    public function forceDelete(Tenant $tenant): void
    {
        $tenant->forceDelete();
    }

    /**
     * Restore a soft-deleted tenant.
     */
    public function restore(Tenant $tenant): Tenant
    {
        abort_unless($tenant->trashed(), 404);

        $tenant->restore();

        return $tenant->refresh();
    }

    /**
     * Soft delete many tenants.
     *
     * @param  list<string>  $ids
     */
    public function destroyMany(array $ids): void
    {
        Tenant::query()->whereKey($ids)->delete();
    }

    /**
     * Restore many soft-deleted tenants.
     *
     * @param  list<string>  $ids
     */
    public function restoreMany(array $ids): void
    {
        Tenant::onlyTrashed()->whereKey($ids)->restore();
    }

    private function dispatchProvisioning(Tenant $tenant): void
    {
        $tenant->update([
            'status' => TenantStatus::Provisioning,
            'provision_error' => null,
        ]);

        ProvisionTenantJob::dispatch($tenant);
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
