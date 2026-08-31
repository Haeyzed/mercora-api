<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Auth\LoginRequest;
use App\Http\Resources\Landlord\Auth\LoginResource;
use App\Http\Resources\Landlord\Auth\UserResource;
use App\Models\Landlord\User;
use App\Services\Landlord\Auth\AuthService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

#[Group('Landlord Auth')]
class AuthController extends Controller
{
    public function __construct(private AuthService $authService) {}

    /**
     * Log in a landlord user and issue an API token.
     *
     * @unauthenticated
     */
    #[Endpoint(operationId: 'loginLandlord', title: 'Log in')]
    public function login(LoginRequest $request): LoginResource
    {
        return new LoginResource($this->authService->login($request->credentials()));
    }

    /**
     * Return the authenticated landlord user.
     */
    #[Endpoint(operationId: 'showLandlordMe', title: 'Show the current user')]
    public function me(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user->loadMissing('roles'));
    }

    /**
     * Revoke the current landlord API token.
     */
    #[Endpoint(operationId: 'logoutLandlord', title: 'Log out')]
    public function logout(Request $request): HttpResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authService->logout($user);

        return response()->noContent();
    }
}
