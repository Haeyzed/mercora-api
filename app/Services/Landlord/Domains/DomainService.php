<?php

declare(strict_types=1);

namespace App\Services\Landlord\Domains;

use App\Models\Landlord\Domain;
use App\Models\Landlord\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Hostnames attached to a landlord tenant.
 */
class DomainService
{
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
     */
    public function store(Tenant $tenant, array $data): Domain
    {
        return $tenant->createDomain($data['domain']);
    }

    /**
     * Permanently delete a hostname.
     */
    public function destroy(Domain $domain): void
    {
        $domain->delete();
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
