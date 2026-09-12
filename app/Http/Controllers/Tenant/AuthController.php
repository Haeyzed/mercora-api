<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Auth\ChangePasswordRequest;
use App\Http\Requests\Tenant\Auth\ForgotPasswordRequest;
use App\Http\Requests\Tenant\Auth\LoginRequest;
use App\Http\Requests\Tenant\Auth\ResetPasswordRequest;
use App\Http\Requests\Tenant\Auth\UpdateProfileRequest;
use App\Http\Resources\Tenant\Auth\LoginResource;
use App\Http\Resources\Tenant\Auth\UserResource;
use App\Models\Tenant\User;
use App\Services\Tenant\AuthService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;

#[Group('Tenant Auth')]
class AuthController extends Controller
{
    public function __construct(private AuthService $authService) {}

    /**
     * Log in a tenant user and issue an API token.
     *
     * @unauthenticated
     */
    #[Endpoint(operationId: 'loginTenant', title: 'Log in')]
    public function login(LoginRequest $request): LoginResource
    {
        return new LoginResource($this->authService->login($request->credentials()));
    }

    /**
     * Request a password reset link.
     *
     * @unauthenticated
     */
    #[Endpoint(operationId: 'forgotTenantPassword', title: 'Forgot password')]
    #[Response(204)]
    public function forgotPassword(ForgotPasswordRequest $request): HttpResponse
    {
        $this->authService->forgotPassword($request->validated('email'));

        return response()->noContent();
    }

    /**
     * Reset a password using a reset token.
     *
     * @unauthenticated
     */
    #[Endpoint(operationId: 'resetTenantPassword', title: 'Reset password')]
    #[Response(204)]
    public function resetPassword(ResetPasswordRequest $request): HttpResponse
    {
        $this->authService->resetPassword($request->validated());

        return response()->noContent();
    }

    /**
     * Return the authenticated tenant user.
     */
    #[Endpoint(operationId: 'tenantMe', title: 'Current user')]
    public function me(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        return new UserResource($user->loadMissing('roles'));
    }

    /**
     * Update the authenticated tenant user's profile.
     */
    #[Endpoint(operationId: 'updateTenantProfile', title: 'Update profile')]
    public function updateProfile(UpdateProfileRequest $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        $updated = $this->authService->updateProfile($user, $request->safe()->only(['name', 'email', 'phone']));

        return new UserResource($updated);
    }

    /**
     * Change the authenticated tenant user's password.
     */
    #[Endpoint(operationId: 'changeTenantPassword', title: 'Change password')]
    #[Response(204)]
    public function changePassword(ChangePasswordRequest $request): HttpResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authService->changePassword($user, $request->validated());

        return response()->noContent();
    }

    /**
     * Log out by revoking the current API token.
     */
    #[Endpoint(operationId: 'logoutTenant', title: 'Log out')]
    #[Response(204)]
    public function logout(Request $request): HttpResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authService->logout($user);

        return response()->noContent();
    }
}
