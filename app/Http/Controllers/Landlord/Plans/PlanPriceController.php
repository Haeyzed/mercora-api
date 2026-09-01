<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\Plans;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Plans\StorePlanPriceRequest;
use App\Http\Requests\Landlord\Plans\UpdatePlanPriceRequest;
use App\Http\Resources\Landlord\Plans\PlanPriceResource;
use App\Models\Landlord\Plan;
use App\Models\Landlord\PlanPrice;
use App\Services\Landlord\Plans\PlanPriceService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;

#[Group('Landlord Plans')]
class PlanPriceController extends Controller
{
    public function __construct(private PlanPriceService $planPriceService) {}

    /**
     * List prices for a plan.
     *
     * @return AnonymousResourceCollection<int, PlanPriceResource>
     */
    #[Endpoint(operationId: 'listLandlordPlanPrices', title: 'List plan prices')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function index(Request $request, Plan $plan): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PlanPrice::class);

        return PlanPriceResource::collection($this->planPriceService->paginate($plan, $request));
    }

    /**
     * Create a plan price.
     */
    #[Endpoint(operationId: 'storeLandlordPlanPrice', title: 'Create a plan price')]
    #[Response(201)]
    public function store(StorePlanPriceRequest $request, Plan $plan): JsonResponse
    {
        $this->authorize('create', PlanPrice::class);

        return $this->planPriceService
            ->store($plan, $request->validated())
            ->toResource(PlanPriceResource::class)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a plan price.
     */
    #[Endpoint(operationId: 'showLandlordPlanPrice', title: 'Show a plan price')]
    public function show(Plan $plan, PlanPrice $planPrice): PlanPriceResource
    {
        $this->authorize('view', $planPrice);

        return $this->planPriceService
            ->show($plan, $planPrice)
            ->toResource(PlanPriceResource::class);
    }

    /**
     * Update a plan price.
     */
    #[Endpoint(operationId: 'updateLandlordPlanPrice', title: 'Update a plan price')]
    public function update(UpdatePlanPriceRequest $request, Plan $plan, PlanPrice $planPrice): PlanPriceResource
    {
        $this->authorize('update', $planPrice);

        return $this->planPriceService
            ->update($plan, $planPrice, $request->validated())
            ->toResource(PlanPriceResource::class);
    }

    /**
     * Activate a plan price.
     */
    #[Endpoint(operationId: 'activateLandlordPlanPrice', title: 'Activate a plan price')]
    public function activate(Plan $plan, PlanPrice $planPrice): PlanPriceResource
    {
        $this->authorize('activate', $planPrice);

        return $this->planPriceService
            ->activate($plan, $planPrice)
            ->toResource(PlanPriceResource::class);
    }

    /**
     * Deactivate a plan price.
     */
    #[Endpoint(operationId: 'deactivateLandlordPlanPrice', title: 'Deactivate a plan price')]
    public function deactivate(Plan $plan, PlanPrice $planPrice): PlanPriceResource
    {
        $this->authorize('deactivate', $planPrice);

        return $this->planPriceService
            ->deactivate($plan, $planPrice)
            ->toResource(PlanPriceResource::class);
    }

    /**
     * Delete an unused plan price.
     */
    #[Endpoint(operationId: 'destroyLandlordPlanPrice', title: 'Delete a plan price')]
    public function destroy(Plan $plan, PlanPrice $planPrice): HttpResponse
    {
        $this->authorize('delete', $planPrice);

        $this->planPriceService->destroy($plan, $planPrice);

        return response()->noContent();
    }
}
