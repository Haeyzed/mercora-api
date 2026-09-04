<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Users\StoreUserRequest;
use App\Http\Requests\Landlord\Users\SyncRolesRequest;
use App\Http\Requests\Landlord\Users\UpdateUserRequest;
use App\Http\Resources\Landlord\Users\UserResource;
use App\Http\Resources\Shared\World\OptionResource;
use App\Models\Landlord\User;
use App\Services\Landlord\UserService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;

#[Group('Landlord Users')]
class UserController extends Controller
{
    public function __construct(private UserService $userService) {}

    /**
     * List landlord users.
     *
     * @return AnonymousResourceCollection<int, UserResource>
     */
    #[Endpoint(operationId: 'listLandlordUsers', title: 'List users')]
    #[QueryParameter('filter[name]', description: 'Partial match on user name.', type: 'string')]
    #[QueryParameter('filter[email]', description: 'Exact email.', type: 'string')]
    #[QueryParameter('filter[is_active]', description: 'Active flag.', type: 'bool')]
    #[QueryParameter('search', description: 'Partial match across name and email.', type: 'string')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: roles.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        return UserResource::collection($this->userService->paginate($request));
    }

    /**
     * List user options for selects.
     *
     * @return AnonymousResourceCollection<int, OptionResource>
     */
    #[Endpoint(operationId: 'listLandlordUserOptions', title: 'List user options')]
    #[QueryParameter('search', description: 'Partial match across name and email.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function options(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        return OptionResource::collection($this->userService->options($request));
    }

    /**
     * Create a landlord user.
     */
    #[Endpoint(operationId: 'storeLandlordUser', title: 'Create a user')]
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
     * Show a landlord user.
     */
    #[Endpoint(operationId: 'showLandlordUser', title: 'Show a user')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: roles.', type: 'string')]
    public function show(Request $request, User $user): UserResource
    {
        $this->authorize('view', $user);

        return $this->userService
            ->show($user, $request)
            ->toResource(UserResource::class);
    }

    /**
     * Update a landlord user.
     */
    #[Endpoint(operationId: 'updateLandlordUser', title: 'Update a user')]
    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        return $this->userService
            ->update($user, $request->validated())
            ->toResource(UserResource::class);
    }

    /**
     * Delete a landlord user.
     */
    #[Endpoint(operationId: 'destroyLandlordUser', title: 'Delete a user')]
    public function destroy(User $user): HttpResponse
    {
        $this->authorize('delete', $user);

        $this->userService->destroy($user);

        return response()->noContent();
    }

    /**
     * Activate a landlord user.
     */
    #[Endpoint(operationId: 'activateLandlordUser', title: 'Activate a user')]
    public function activate(User $user): UserResource
    {
        $this->authorize('activate', $user);

        return $this->userService
            ->activate($user)
            ->toResource(UserResource::class);
    }

    /**
     * Deactivate a landlord user.
     */
    #[Endpoint(operationId: 'deactivateLandlordUser', title: 'Deactivate a user')]
    public function deactivate(User $user): UserResource
    {
        $this->authorize('deactivate', $user);

        return $this->userService
            ->deactivate($user)
            ->toResource(UserResource::class);
    }

    /**
     * Replace the roles assigned to a landlord user.
     */
    #[Endpoint(operationId: 'syncLandlordUserRoles', title: 'Assign user roles')]
    public function syncRoles(SyncRolesRequest $request, User $user): UserResource
    {
        return $this->userService
            ->syncRoles($user, $request->roles())
            ->toResource(UserResource::class);
    }
}
