<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\Roles;

use App\Http\Controllers\Controller;
use App\Http\Resources\Landlord\Roles\PermissionResource;
use App\Services\Landlord\PermissionService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\Permission\Models\Role;

#[Group('Landlord Roles')]
class PermissionController extends Controller
{
    public function __construct(private PermissionService $permissionService) {}

    /**
     * List the seeded landlord permission catalog.
     *
     * @return AnonymousResourceCollection<int, PermissionResource>
     */
    #[Endpoint(operationId: 'listLandlordPermissions', title: 'List permissions')]
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Role::class);

        return PermissionResource::collection($this->permissionService->all());
    }
}
