<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\Roles;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Roles\StoreRoleRequest;
use App\Http\Requests\Landlord\Roles\UpdateRoleRequest;
use App\Http\Resources\Landlord\Roles\RoleResource;
use App\Services\Landlord\Roles\RoleService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;
use Spatie\Permission\Models\Role;

#[Group('Landlord Roles')]
class RoleController extends Controller
{
    public function __construct(private RoleService $roleService) {}

    /**
     * List landlord roles.
     *
     * @return AnonymousResourceCollection<int, RoleResource>
     */
    #[Endpoint(operationId: 'listLandlordRoles', title: 'List roles')]
    #[QueryParameter('search', description: 'Partial match on role name.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Role::class);

        return RoleResource::collection($this->roleService->paginate($request));
    }

    /**
     * Create a landlord role.
     */
    #[Endpoint(operationId: 'storeLandlordRole', title: 'Create a role')]
    #[Response(201)]
    public function store(StoreRoleRequest $request): JsonResponse
    {
        return $this->roleService
            ->store($request->validated())
            ->toResource(RoleResource::class)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a landlord role.
     */
    #[Endpoint(operationId: 'showLandlordRole', title: 'Show a role')]
    public function show(Role $role): RoleResource
    {
        $this->authorize('view', $role);

        return $this->roleService
            ->show($role)
            ->toResource(RoleResource::class);
    }

    /**
     * Update a landlord role.
     */
    #[Endpoint(operationId: 'updateLandlordRole', title: 'Update a role')]
    public function update(UpdateRoleRequest $request, Role $role): RoleResource
    {
        return $this->roleService
            ->update($role, $request->validated())
            ->toResource(RoleResource::class);
    }

    /**
     * Delete a landlord role.
     */
    #[Endpoint(operationId: 'destroyLandlordRole', title: 'Delete a role')]
    public function destroy(Role $role): HttpResponse
    {
        $this->authorize('delete', $role);

        $this->roleService->destroy($role);

        return response()->noContent();
    }
}
