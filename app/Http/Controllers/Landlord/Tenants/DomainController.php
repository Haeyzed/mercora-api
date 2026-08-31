<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\Tenants;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Tenants\StoreDomainRequest;
use App\Http\Resources\Landlord\Tenants\DomainResource;
use App\Models\Landlord\Domain;
use App\Models\Landlord\Tenant;
use App\Services\Landlord\Domains\DomainService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;

#[Group('Landlord Tenants')]
class DomainController extends Controller
{
    public function __construct(private DomainService $domainService) {}

    /**
     * List domains for a tenant.
     *
     * @return AnonymousResourceCollection<int, DomainResource>
     */
    #[Endpoint(operationId: 'listLandlordTenantDomains', title: 'List tenant domains')]
    #[QueryParameter('search', description: 'Partial match on hostname.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function index(Request $request, Tenant $tenant): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Domain::class);

        return DomainResource::collection($this->domainService->paginate($tenant, $request));
    }

    /**
     * Create a domain for a tenant.
     */
    #[Endpoint(operationId: 'storeLandlordTenantDomain', title: 'Create a tenant domain')]
    #[Response(201)]
    public function store(StoreDomainRequest $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('create', Domain::class);

        return $this->domainService
            ->store($tenant, $request->validated())
            ->toResource(DomainResource::class)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Permanently delete a tenant domain.
     */
    #[Endpoint(operationId: 'destroyLandlordTenantDomain', title: 'Delete a tenant domain')]
    public function destroy(Tenant $tenant, Domain $domain): HttpResponse
    {
        $this->authorize('delete', $domain);

        $this->domainService->destroy($domain);

        return response()->noContent();
    }
}
