<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\Plans;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Plans\DestroyManyRequest;
use App\Http\Requests\Landlord\Plans\RestoreManyRequest;
use App\Http\Requests\Landlord\Plans\StorePlanRequest;
use App\Http\Requests\Landlord\Plans\UpdatePlanRequest;
use App\Http\Resources\Landlord\Plans\PlanResource;
use App\Http\Resources\Shared\World\OptionResource;
use App\Models\Landlord\Plan;
use App\Services\Landlord\Plans\PlanService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;

#[Group('Landlord Plans')]
class PlanController extends Controller
{
    public function __construct(private PlanService $planService) {}

    /**
     * List plans.
     *
     * @return AnonymousResourceCollection<int, PlanResource>
     */
    #[Endpoint(operationId: 'listLandlordPlans', title: 'List plans')]
    #[QueryParameter('filter[name]', description: 'Partial match on plan name.', type: 'string')]
    #[QueryParameter('filter[slug]', description: 'Exact plan slug.', type: 'string')]
    #[QueryParameter('filter[status]', description: 'Exact plan status.', type: 'string')]
    #[QueryParameter('filter[interval]', description: 'Exact billing interval.', type: 'string')]
    #[QueryParameter('filter[currency]', description: 'Exact ISO 4217 currency code.', type: 'string')]
    #[QueryParameter('search', description: 'Partial match across name, slug, and description.', type: 'string')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: subscriptions.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Plan::class);

        return PlanResource::collection($this->planService->paginate($request));
    }

    /**
     * List plan options for selects.
     *
     * @return AnonymousResourceCollection<int, OptionResource>
     */
    #[Endpoint(operationId: 'listLandlordPlanOptions', title: 'List plan options')]
    #[QueryParameter('filter[name]', description: 'Partial match on plan name.', type: 'string')]
    #[QueryParameter('filter[status]', description: 'Exact plan status.', type: 'string')]
    #[QueryParameter('search', description: 'Partial match across name, slug, and description.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function options(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Plan::class);

        return OptionResource::collection($this->planService->options($request));
    }

    /**
     * Create a plan.
     */
    #[Endpoint(operationId: 'storeLandlordPlan', title: 'Create a plan')]
    #[Response(201)]
    public function store(StorePlanRequest $request): JsonResponse
    {
        $this->authorize('create', Plan::class);

        return $this->planService
            ->store($request->validated())
            ->toResource(PlanResource::class)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a plan.
     */
    #[Endpoint(operationId: 'showLandlordPlan', title: 'Show a plan')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: subscriptions.', type: 'string')]
    public function show(Request $request, Plan $plan): PlanResource
    {
        $this->authorize('view', $plan);

        return $this->planService
            ->show($plan, $request)
            ->toResource(PlanResource::class);
    }

    /**
     * Update a plan.
     */
    #[Endpoint(operationId: 'updateLandlordPlan', title: 'Update a plan')]
    public function update(UpdatePlanRequest $request, Plan $plan): PlanResource
    {
        $this->authorize('update', $plan);

        return $this->planService
            ->update($plan, $request->validated())
            ->toResource(PlanResource::class);
    }

    /**
     * Soft delete a plan.
     */
    #[Endpoint(operationId: 'destroyLandlordPlan', title: 'Delete a plan')]
    public function destroy(Plan $plan): HttpResponse
    {
        $this->authorize('delete', $plan);

        $this->planService->destroy($plan);

        return response()->noContent();
    }

    /**
     * Restore a soft-deleted plan.
     */
    #[Endpoint(operationId: 'restoreLandlordPlan', title: 'Restore a plan')]
    public function restore(Plan $plan): PlanResource
    {
        $this->authorize('restore', $plan);

        return $this->planService
            ->restore($plan)
            ->toResource(PlanResource::class);
    }

    /**
     * Soft delete many plans.
     */
    #[Endpoint(operationId: 'destroyManyLandlordPlans', title: 'Delete many plans')]
    public function destroyMany(DestroyManyRequest $request): HttpResponse
    {
        $this->authorize('delete', Plan::class);

        $this->planService->destroyMany($request->ids());

        return response()->noContent();
    }

    /**
     * Restore many soft-deleted plans.
     */
    #[Endpoint(operationId: 'restoreManyLandlordPlans', title: 'Restore many plans')]
    public function restoreMany(RestoreManyRequest $request): HttpResponse
    {
        $this->authorize('restore', Plan::class);

        $this->planService->restoreMany($request->ids());

        return response()->noContent();
    }
}
