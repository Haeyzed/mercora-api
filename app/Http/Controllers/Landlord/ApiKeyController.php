<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\ApiKeys\DestroyManyRequest;
use App\Http\Requests\Landlord\ApiKeys\RestoreManyRequest;
use App\Http\Requests\Landlord\ApiKeys\StoreApiKeyRequest;
use App\Http\Requests\Landlord\ApiKeys\UpdateApiKeyRequest;
use App\Http\Resources\Landlord\ApiKeys\ApiKeyResource;
use App\Models\Landlord\ApiKey;
use App\Services\Landlord\ApiKeyService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;

#[Group('Landlord API Keys')]
class ApiKeyController extends Controller
{
    public function __construct(private ApiKeyService $apiKeyService) {}

    /**
     * List API keys.
     *
     * @return AnonymousResourceCollection<int, ApiKeyResource>
     */
    #[Endpoint(operationId: 'listLandlordApiKeys', title: 'List API keys')]
    #[QueryParameter('filter[user_id]', description: 'Exact owner user id.', type: 'int')]
    #[QueryParameter('filter[status]', description: 'Exact API key status.', type: 'string')]
    #[QueryParameter('filter[prefix]', description: 'Exact displayed key prefix.', type: 'string')]
    #[QueryParameter('search', description: 'Partial match across name, prefix, and owner name or email.', type: 'string')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: user.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ApiKey::class);

        return ApiKeyResource::collection($this->apiKeyService->paginate($request));
    }

    /**
     * Create an API key.
     */
    #[Endpoint(operationId: 'storeLandlordApiKey', title: 'Create an API key')]
    #[Response(201)]
    public function store(StoreApiKeyRequest $request): JsonResponse
    {
        $this->authorize('create', ApiKey::class);

        return $this->apiKeyService
            ->store($request->validated())
            ->toResource(ApiKeyResource::class)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show an API key.
     */
    #[Endpoint(operationId: 'showLandlordApiKey', title: 'Show an API key')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: user.', type: 'string')]
    public function show(Request $request, ApiKey $apiKey): ApiKeyResource
    {
        $this->authorize('view', $apiKey);

        return $this->apiKeyService
            ->show($apiKey, $request)
            ->toResource(ApiKeyResource::class);
    }

    /**
     * Update an active API key.
     */
    #[Endpoint(operationId: 'updateLandlordApiKey', title: 'Update an API key')]
    public function update(UpdateApiKeyRequest $request, ApiKey $apiKey): ApiKeyResource
    {
        $this->authorize('update', $apiKey);

        return $this->apiKeyService
            ->update($apiKey, $request->validated())
            ->toResource(ApiKeyResource::class);
    }

    /**
     * Revoke an API key.
     */
    #[Endpoint(operationId: 'revokeLandlordApiKey', title: 'Revoke an API key')]
    public function revoke(ApiKey $apiKey): ApiKeyResource
    {
        $this->authorize('revoke', $apiKey);

        return $this->apiKeyService
            ->revoke($apiKey)
            ->toResource(ApiKeyResource::class);
    }

    /**
     * Soft delete an API key.
     */
    #[Endpoint(operationId: 'destroyLandlordApiKey', title: 'Delete an API key')]
    public function destroy(ApiKey $apiKey): HttpResponse
    {
        $this->authorize('delete', $apiKey);

        $this->apiKeyService->destroy($apiKey);

        return response()->noContent();
    }

    /**
     * Restore a soft-deleted API key.
     */
    #[Endpoint(operationId: 'restoreLandlordApiKey', title: 'Restore an API key')]
    public function restore(ApiKey $apiKey): ApiKeyResource
    {
        $this->authorize('restore', $apiKey);

        return $this->apiKeyService
            ->restore($apiKey)
            ->toResource(ApiKeyResource::class);
    }

    /**
     * Soft delete many API keys.
     */
    #[Endpoint(operationId: 'destroyManyLandlordApiKeys', title: 'Delete many API keys')]
    public function destroyMany(DestroyManyRequest $request): HttpResponse
    {
        $this->authorize('delete', ApiKey::class);

        $this->apiKeyService->destroyMany($request->ids());

        return response()->noContent();
    }

    /**
     * Restore many soft-deleted API keys.
     */
    #[Endpoint(operationId: 'restoreManyLandlordApiKeys', title: 'Restore many API keys')]
    public function restoreMany(RestoreManyRequest $request): HttpResponse
    {
        $this->authorize('restore', ApiKey::class);

        $this->apiKeyService->restoreMany($request->ids());

        return response()->noContent();
    }
}
