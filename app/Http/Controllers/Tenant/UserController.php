<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Users\StoreUserRequest;
use App\Http\Requests\Tenant\Users\UpdateUserRequest;
use App\Http\Resources\Tenant\Users\UserResource;
use App\Models\Tenant\User;
use App\Services\Tenant\UserService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;

#[Group('Tenant Users')]
class UserController extends Controller
{
    public function __construct(private UserService $userService) {}

    /**
     * List tenant staff users.
     *
     * @return AnonymousResourceCollection<int, UserResource>
     */
    #[Endpoint(operationId: 'listTenantUsers', title: 'List users')]
    #[QueryParameter('filter[name]', description: 'Partial match on user name.', type: 'string')]
    #[QueryParameter('filter[email]', description: 'Exact email.', type: 'string')]
    #[QueryParameter('filter[is_active]', description: 'Active flag.', type: 'bool')]
    #[QueryParameter('search', description: 'Partial match across name and email.', type: 'string')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: roles.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function index(Request $request): AnonymousResourceCollection
    {
        return UserResource::collection($this->userService->paginate($request));
    }

    /**
     * Create a tenant staff user.
     */
    #[Endpoint(operationId: 'storeTenantUser', title: 'Create a user')]
    #[Response(201)]
    public function store(StoreUserRequest $request): JsonResponse
    {
        return $this->userService
            ->store($request->validated())
            ->toResource(UserResource::class)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a tenant staff user.
     */
    #[Endpoint(operationId: 'showTenantUser', title: 'Show a user')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: roles.', type: 'string')]
    public function show(Request $request, User $user): UserResource
    {
        return $this->userService
            ->show($user, $request)
            ->toResource(UserResource::class);
    }

    /**
     * Update a tenant staff user.
     */
    #[Endpoint(operationId: 'updateTenantUser', title: 'Update a user')]
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        return $this->userService
            ->update($user, $request->validated())
            ->toResource(UserResource::class);
    }

    /**
     * Delete a tenant staff user.
     */
    #[Endpoint(operationId: 'destroyTenantUser', title: 'Delete a user')]
    public function destroy(User $user): HttpResponse
    {
        $this->userService->destroy($user);

        return response()->noContent();
    }
}
