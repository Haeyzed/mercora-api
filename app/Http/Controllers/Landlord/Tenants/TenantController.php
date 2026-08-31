<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\Tenants;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Tenants\DestroyManyRequest;
use App\Http\Requests\Landlord\Tenants\RestoreManyRequest;
use App\Http\Requests\Landlord\Tenants\StoreTenantRequest;
use App\Http\Requests\Landlord\Tenants\UpdateTenantRequest;
use App\Http\Resources\Landlord\Tenants\TenantResource;
use App\Http\Resources\Shared\World\OptionResource;
use App\Models\Landlord\Tenant;
use App\Services\Landlord\Tenants\TenantService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;

#[Group('Landlord Tenants')]
class TenantController extends Controller
{
    public function __construct(private TenantService $tenantService) {}

    /**
     * List tenants.
     *
     * @return AnonymousResourceCollection<int, TenantResource>
     */
    #[Endpoint(operationId: 'listLandlordTenants', title: 'List tenants')]
    #[QueryParameter('filter[name]', description: 'Partial match on tenant name.', type: 'string')]
    #[QueryParameter('filter[slug]', description: 'Exact tenant slug.', type: 'string')]
    #[QueryParameter('filter[status]', description: 'Exact tenant status.', type: 'string')]
    #[QueryParameter('search', description: 'Partial match across name and slug.', type: 'string')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: domains, subscriptions, invoices.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Tenant::class);

        return TenantResource::collection($this->tenantService->paginate($request));
    }

    /**
     * List tenant options for selects.
     *
     * @return AnonymousResourceCollection<int, OptionResource>
     */
    #[Endpoint(operationId: 'listLandlordTenantOptions', title: 'List tenant options')]
    #[QueryParameter('filter[name]', description: 'Partial match on tenant name.', type: 'string')]
    #[QueryParameter('filter[slug]', description: 'Exact tenant slug.', type: 'string')]
    #[QueryParameter('filter[status]', description: 'Exact tenant status.', type: 'string')]
    #[QueryParameter('search', description: 'Partial match across name and slug.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function options(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Tenant::class);

        return OptionResource::collection($this->tenantService->options($request));
    }

    /**
     * Create a tenant.
     */
    #[Endpoint(operationId: 'storeLandlordTenant', title: 'Create a tenant')]
    #[Response(201)]
    public function store(StoreTenantRequest $request): JsonResponse
    {
        $this->authorize('create', Tenant::class);

        return $this->tenantService
            ->store($request->validated())
            ->toResource(TenantResource::class)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a tenant.
     */
    #[Endpoint(operationId: 'showLandlordTenant', title: 'Show a tenant')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: domains, subscriptions, invoices.', type: 'string')]
    public function show(Request $request, Tenant $tenant): TenantResource
    {
        $this->authorize('view', $tenant);

        return $this->tenantService
            ->show($tenant, $request)
            ->toResource(TenantResource::class);
    }

    /**
     * Update a tenant.
     */
    #[Endpoint(operationId: 'updateLandlordTenant', title: 'Update a tenant')]
    public function update(UpdateTenantRequest $request, Tenant $tenant): TenantResource
    {
        $this->authorize('update', $tenant);

        return $this->tenantService
            ->update($tenant, $request->validated())
            ->toResource(TenantResource::class);
    }

    /**
     * Soft delete a tenant.
     */
    #[Endpoint(operationId: 'destroyLandlordTenant', title: 'Delete a tenant')]
    public function destroy(Tenant $tenant): HttpResponse
    {
        $this->authorize('delete', $tenant);

        $this->tenantService->destroy($tenant);

        return response()->noContent();
    }

    /**
     * Restore a soft-deleted tenant.
     */
    #[Endpoint(operationId: 'restoreLandlordTenant', title: 'Restore a tenant')]
    public function restore(Tenant $tenant): TenantResource
    {
        $this->authorize('restore', $tenant);

        return $this->tenantService
            ->restore($tenant)
            ->toResource(TenantResource::class);
    }

    /**
     * Soft delete many tenants.
     */
    #[Endpoint(operationId: 'destroyManyLandlordTenants', title: 'Delete many tenants')]
    public function destroyMany(DestroyManyRequest $request): HttpResponse
    {
        $this->authorize('delete', Tenant::class);

        $this->tenantService->destroyMany($request->ids());

        return response()->noContent();
    }

    /**
     * Restore many soft-deleted tenants.
     */
    #[Endpoint(operationId: 'restoreManyLandlordTenants', title: 'Restore many tenants')]
    public function restoreMany(RestoreManyRequest $request): HttpResponse
    {
        $this->authorize('restore', Tenant::class);

        $this->tenantService->restoreMany($request->ids());

        return response()->noContent();
    }

    /**
     * Retry tenant database provisioning.
     */
    #[Endpoint(operationId: 'provisionLandlordTenant', title: 'Provision a tenant')]
    public function provision(Tenant $tenant): TenantResource
    {
        $this->authorize('provision', $tenant);

        return $this->tenantService
            ->provision($tenant)
            ->toResource(TenantResource::class);
    }

    /**
     * Activate a provisioned tenant.
     */
    #[Endpoint(operationId: 'activateLandlordTenant', title: 'Activate a tenant')]
    public function activate(Tenant $tenant): TenantResource
    {
        $this->authorize('activate', $tenant);

        return $this->tenantService
            ->activate($tenant)
            ->toResource(TenantResource::class);
    }

    /**
     * Suspend an active tenant.
     */
    #[Endpoint(operationId: 'suspendLandlordTenant', title: 'Suspend a tenant')]
    public function suspend(Tenant $tenant): TenantResource
    {
        $this->authorize('suspend', $tenant);

        return $this->tenantService
            ->suspend($tenant)
            ->toResource(TenantResource::class);
    }

    /**
     * Reactivate a suspended tenant.
     */
    #[Endpoint(operationId: 'reactivateLandlordTenant', title: 'Reactivate a tenant')]
    public function reactivate(Tenant $tenant): TenantResource
    {
        $this->authorize('reactivate', $tenant);

        return $this->tenantService
            ->reactivate($tenant)
            ->toResource(TenantResource::class);
    }

    /**
     * Permanently delete a tenant and its database.
     */
    #[Endpoint(operationId: 'forceDestroyLandlordTenant', title: 'Force delete a tenant')]
    public function forceDestroy(Tenant $tenant): HttpResponse
    {
        $this->authorize('forceDelete', $tenant);

        $this->tenantService->forceDelete($tenant);

        return response()->noContent();
    }
}
