<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Auth\ChangePasswordRequest;
use App\Http\Requests\Landlord\Auth\ForgotPasswordRequest;
use App\Http\Requests\Landlord\Auth\LoginRequest;
use App\Http\Requests\Landlord\Auth\ResetPasswordRequest;
use App\Http\Requests\Landlord\Auth\StoreAvatarRequest;
use App\Http\Requests\Landlord\Auth\UpdateProfileRequest;
use App\Http\Resources\Landlord\Auth\LoginResource;
use App\Http\Resources\Landlord\Auth\UserResource;
use App\Http\Resources\Media\MediaResource;
use App\Models\Landlord\User;
use App\Services\Landlord\AuthService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
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
     * Request a password reset link.
     *
     * @unauthenticated
     */
    #[Endpoint(operationId: 'forgotLandlordPassword', title: 'Forgot password')]
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
    #[Endpoint(operationId: 'resetLandlordPassword', title: 'Reset password')]
    #[Response(204)]
    public function resetPassword(ResetPasswordRequest $request): HttpResponse
    {
        $this->authService->resetPassword($request->validated());

        return response()->noContent();
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
     * Update the authenticated landlord user's profile.
     */
    #[Endpoint(operationId: 'updateLandlordProfile', title: 'Update profile')]
    public function updateProfile(UpdateProfileRequest $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        $updated = $this->authService->updateProfile(
            $user,
            $request->safe()->except(['avatar']),
            $request->file('avatar'),
        );

        return new UserResource($updated);
    }

    /**
     * Upload or replace the authenticated user's avatar.
     */
    #[Endpoint(operationId: 'storeLandlordAvatar', title: 'Replace avatar')]
    #[Response(201)]
    public function storeAvatar(StoreAvatarRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return (new MediaResource($this->authService->replaceAvatar($user, $request->file('avatar'))))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Remove the authenticated user's avatar.
     */
    #[Endpoint(operationId: 'destroyLandlordAvatar', title: 'Remove avatar')]
    #[Response(204)]
    public function destroyAvatar(Request $request): HttpResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authService->removeAvatar($user);

        return response()->noContent();
    }

    /**
     * Change the authenticated landlord user's password.
     */
    #[Endpoint(operationId: 'changeLandlordPassword', title: 'Change password')]
    #[Response(204)]
    public function changePassword(ChangePasswordRequest $request): HttpResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authService->changePassword($user, $request->validated());

        return response()->noContent();
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
