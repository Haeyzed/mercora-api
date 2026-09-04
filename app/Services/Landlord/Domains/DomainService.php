<?php

declare(strict_types=1);

namespace App\Services\Landlord\Domains;

use App\Models\Landlord\Domain;
use App\Models\Landlord\Tenant;
use App\Services\Concerns\PaginatesRequests;
use App\Services\Landlord\Settings\SettingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Manages hostnames attached to landlord tenants.
 *
 * Domain: Stancl Tenancy domain records scoped to a {@see Tenant}.
 *
 * Invariants:
 * - Domains belong to exactly one tenant.
 * - Platform subdomains respect {@see tenancy.allow_subdomains} and {@see tenancy.default_domain_suffix}.
 * - Custom domains respect {@see tenancy.allow_custom_domains} and {@see tenancy.max_domains_per_tenant}.
 * - Deletion is permanent (no soft deletes).
 *
 * Side effects: creates and permanently deletes {@see Domain} records via the tenant relationship;
 * reads {@see SettingService} for tenancy policy.
 */
class DomainService
{
    use PaginatesRequests;

    public function __construct(private SettingService $settings) {}

    /**
     * Paginate domains that belong to the given tenant.
     *
     * @return LengthAwarePaginator<int, Domain>
     */
    public function paginate(Tenant $tenant, Request $request): LengthAwarePaginator
    {
        return $tenant->domains()
            ->search($request->query('search'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString();
    }

    /**
     * Attach a hostname to a tenant.
     *
     * @param  array{domain: string}  $data
     *
     * @throws ValidationException When domain policy is violated or the tenant is at its domain limit.
     */
    public function store(Tenant $tenant, array $data): Domain
    {
        $this->ensureDomainTypeAllowed($data['domain']);
        $this->ensureWithinDomainLimit($tenant);

        return $tenant->createDomain($data['domain']);
    }

    /**
     * Permanently delete a hostname.
     */
    public function destroy(Domain $domain): void
    {
        $domain->delete();
    }

    /**
     * @throws ValidationException When the hostname type is disabled by settings.
     */
    private function ensureDomainTypeAllowed(string $domain): void
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
     * @throws ValidationException When the tenant already has the maximum number of domains.
     */
    private function ensureWithinDomainLimit(Tenant $tenant): void
    {
        $max = max(1, (int) $this->settings->value('tenancy.max_domains_per_tenant', 5));

        if ($tenant->domains()->count() < $max) {
            return;
        }

        throw ValidationException::withMessages([
            'domain' => ["This tenant may have at most {$max} domains."],
        ]);
    }
}
