<?php

declare(strict_types=1);

namespace App\Services\Landlord\Tenants;

use App\Enums\Landlord\TenantStatus;
use App\Jobs\Landlord\ProvisionTenantJob;
use App\Models\Landlord\Tenant;
use App\Services\Concerns\PaginatesRequests;
use App\Services\Landlord\Settings\SettingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Manages landlord tenant records, first hostname, and lifecycle transitions.
 *
 * Domain: multi-tenant customers with isolated databases and domains.
 *
 * Invariants:
 * - New tenants start as pending with one initial domain.
 * - Lifecycle status, provisioned_at, and provision_error are not client-writable on update.
 * - Activation requires successful provisioning per {@see TenantProvisioningVerifier}.
 * - Soft delete does not drop the tenant database; force delete does.
 *
 * Side effects: creates tenants and domains, dispatches {@see ProvisionTenantJob},
 * updates tenant status, and soft-deletes or force-deletes tenant records;
 * reads {@see SettingService} for the provisioning queue.
 */
class TenantService
{
    use PaginatesRequests;

    public function __construct(
        private TenantProvisioningVerifier $provisioningVerifier,
        private SettingService $settings,
    ) {}

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
     * Create a tenant and its first domain, then optionally dispatch provisioning.
     *
     * @param  array{name: string, domain: string}  $data
     *
     * @throws ValidationException When the domain violates tenancy policy or provisioning is at capacity.
     */
    public function store(array $data): Tenant
    {
        $this->ensureDomainAllowed($data['domain']);

        $tenant = DB::transaction(function () use ($data): Tenant {
            $tenant = Tenant::query()->create([
                'name' => $data['name'],
                'status' => TenantStatus::Pending,
            ]);

            $tenant->createDomain($data['domain']);

            return $tenant->load('domains');
        });

        if ($this->settings->value('registration.auto_provision_tenant', true)) {
            $this->dispatchProvisioning($tenant);
        }

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
     *
     * @throws ValidationException When the tenant is not in a provisionable status.
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
     *
     * @throws ValidationException When the tenant cannot be activated or is not provisioned.
     */
    public function activate(Tenant $tenant): Tenant
    {
        if (! $tenant->status->canActivate()) {
            throw ValidationException::withMessages([
                'status' => 'The tenant cannot be activated in its current state.',
            ]);
        }

        if ($tenant->provisioned_at === null || ! $this->provisioningVerifier->isProvisioned($tenant)) {
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
     *
     * @throws ValidationException When the tenant cannot be suspended.
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
     *
     * @throws ValidationException When the tenant cannot be reactivated.
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

    /**
     * Mark a tenant as provisioning. Called by the provisioning job at start.
     */
    public function markProvisioning(Tenant $tenant): void
    {
        $tenant->update([
            'status' => TenantStatus::Provisioning,
            'provision_error' => null,
        ]);
    }

    /**
     * Mark provisioning complete and activate the tenant. Called by the provisioning job on success.
     */
    public function completeProvisioning(Tenant $tenant): void
    {
        $tenant->update([
            'status' => TenantStatus::Active,
            'provisioned_at' => $tenant->provisioned_at ?? now(),
            'provision_error' => null,
        ]);
    }

    /**
     * Record a provisioning failure. Called by the provisioning job on error.
     */
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
     *
     * @throws HttpException When the tenant is not trashed (404).
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

    /**
     * Transition the tenant to provisioning and dispatch the async provisioning job.
     *
     * Uses {@see tenancy.provisioning_queue} when configured.
     *
     * @throws ValidationException When too many tenants are already provisioning.
     */
    private function dispatchProvisioning(Tenant $tenant): void
    {
        $this->ensureWithinConcurrentProvisionLimit();

        $tenant->update([
            'status' => TenantStatus::Provisioning,
            'provision_error' => null,
        ]);

        $queue = (string) $this->settings->value('tenancy.provisioning_queue', 'default');

        ProvisionTenantJob::dispatch($tenant)->onQueue($queue !== '' ? $queue : 'default');
    }

    /**
     * @throws ValidationException When the hostname violates subdomain or custom-domain policy.
     */
    private function ensureDomainAllowed(string $domain): void
    {
        $normalized = strtolower(trim($domain));
        $suffix = $this->settings->value('tenancy.default_domain_suffix');
        $isPlatformSubdomain = is_string($suffix)
            && $suffix !== ''
            && (str_ends_with($normalized, '.'.strtolower($suffix)) || $normalized === strtolower($suffix));

        if ($isPlatformSubdomain) {
            if ($this->settings->value('tenancy.allow_subdomains', true)) {
                return;
            }

            throw ValidationException::withMessages([
                'domain' => ['Platform subdomains are disabled.'],
            ]);
        }

        if ($this->settings->value('tenancy.allow_custom_domains', true)) {
            return;
        }

        throw ValidationException::withMessages([
            'domain' => ['Custom domains are disabled.'],
        ]);
    }

    /**
     * @throws ValidationException When the concurrent provisioning cap is reached.
     */
    private function ensureWithinConcurrentProvisionLimit(): void
    {
        $max = max(1, (int) $this->settings->value('tenancy.max_concurrent_provisions', 5));
        $count = Tenant::query()->where('status', TenantStatus::Provisioning)->count();

        if ($count < $max) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => ["At most {$max} tenants may be provisioning at once."],
        ]);
    }
}
