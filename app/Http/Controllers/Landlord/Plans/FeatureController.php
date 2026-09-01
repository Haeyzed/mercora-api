<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\Plans;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Plans\DestroyManyFeaturesRequest;
use App\Http\Requests\Landlord\Plans\RestoreManyFeaturesRequest;
use App\Http\Requests\Landlord\Plans\StoreFeatureRequest;
use App\Http\Requests\Landlord\Plans\UpdateFeatureRequest;
use App\Http\Resources\Landlord\Plans\FeatureResource;
use App\Http\Resources\Shared\World\OptionResource;
use App\Models\Landlord\Feature;
use App\Services\Landlord\Plans\FeatureService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;

#[Group('Landlord Plans')]
class FeatureController extends Controller
{
    public function __construct(private FeatureService $featureService) {}

    /**
     * List entitlement features.
     *
     * @return AnonymousResourceCollection<int, FeatureResource>
     */
    #[Endpoint(operationId: 'listLandlordFeatures', title: 'List features')]
    #[QueryParameter('filter[key]', description: 'Exact feature key.', type: 'string')]
    #[QueryParameter('filter[type]', description: 'Exact feature type.', type: 'string')]
    #[QueryParameter('filter[is_active]', description: 'Whether the feature is active.', type: 'bool')]
    #[QueryParameter('search', description: 'Partial match across name, key, and description.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Feature::class);

        return FeatureResource::collection($this->featureService->paginate($request));
    }

    /**
     * List feature options for selects.
     *
     * @return AnonymousResourceCollection<int, OptionResource>
     */
    #[Endpoint(operationId: 'listLandlordFeatureOptions', title: 'List feature options')]
    #[QueryParameter('search', description: 'Partial match across name, key, and description.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function options(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Feature::class);

        return OptionResource::collection($this->featureService->options($request));
    }

    /**
     * Create a feature.
     */
    #[Endpoint(operationId: 'storeLandlordFeature', title: 'Create a feature')]
    #[Response(201)]
    public function store(StoreFeatureRequest $request): JsonResponse
    {
        $this->authorize('create', Feature::class);

        return $this->featureService
            ->store($request->validated())
            ->toResource(FeatureResource::class)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a feature.
     */
    #[Endpoint(operationId: 'showLandlordFeature', title: 'Show a feature')]
    public function show(Feature $feature): FeatureResource
    {
        $this->authorize('view', $feature);

        return $this->featureService
            ->show($feature)
            ->toResource(FeatureResource::class);
    }

    /**
     * Update a feature.
     */
    #[Endpoint(operationId: 'updateLandlordFeature', title: 'Update a feature')]
    public function update(UpdateFeatureRequest $request, Feature $feature): FeatureResource
    {
        $this->authorize('update', $feature);

        return $this->featureService
            ->update($feature, $request->validated())
            ->toResource(FeatureResource::class);
    }

    /**
     * Soft delete a feature.
     */
    #[Endpoint(operationId: 'destroyLandlordFeature', title: 'Delete a feature')]
    public function destroy(Feature $feature): HttpResponse
    {
        $this->authorize('delete', $feature);

        $this->featureService->destroy($feature);

        return response()->noContent();
    }

    /**
     * Restore a soft-deleted feature.
     */
    #[Endpoint(operationId: 'restoreLandlordFeature', title: 'Restore a feature')]
    public function restore(Feature $feature): FeatureResource
    {
        $this->authorize('restore', $feature);

        return $this->featureService
            ->restore($feature)
            ->toResource(FeatureResource::class);
    }

    /**
     * Soft delete many features.
     */
    #[Endpoint(operationId: 'destroyManyLandlordFeatures', title: 'Delete many features')]
    public function destroyMany(DestroyManyFeaturesRequest $request): HttpResponse
    {
        $this->authorize('delete', Feature::class);

        $this->featureService->destroyMany($request->ids());

        return response()->noContent();
    }

    /**
     * Restore many soft-deleted features.
     */
    #[Endpoint(operationId: 'restoreManyLandlordFeatures', title: 'Restore many features')]
    public function restoreMany(RestoreManyFeaturesRequest $request): HttpResponse
    {
        $this->authorize('restore', Feature::class);

        $this->featureService->restoreMany($request->ids());

        return response()->noContent();
    }
}
