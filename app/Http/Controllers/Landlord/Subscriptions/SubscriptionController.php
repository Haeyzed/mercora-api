<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\Subscriptions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Subscriptions\ChangeSubscriptionPlanRequest;
use App\Http\Requests\Landlord\Subscriptions\StoreSubscriptionRequest;
use App\Http\Resources\Landlord\Subscriptions\SubscriptionResource;
use App\Models\Landlord\Subscription;
use App\Services\Landlord\Subscriptions\SubscriptionService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Landlord Subscriptions')]
class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $subscriptionService) {}

    /**
     * List subscriptions.
     *
     * @return AnonymousResourceCollection<int, SubscriptionResource>
     */
    #[Endpoint(operationId: 'listLandlordSubscriptions', title: 'List subscriptions')]
    #[QueryParameter('filter[tenant_id]', description: 'Exact tenant id.', type: 'string')]
    #[QueryParameter('filter[plan_id]', description: 'Exact plan id.', type: 'int')]
    #[QueryParameter('filter[status]', description: 'Exact subscription status.', type: 'string')]
    #[QueryParameter('search', description: 'Partial match across tenant and plan name or slug.', type: 'string')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: tenant, plan, invoices.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Subscription::class);

        return SubscriptionResource::collection($this->subscriptionService->paginate($request));
    }

    /**
     * Create a subscription.
     */
    #[Endpoint(operationId: 'storeLandlordSubscription', title: 'Create a subscription')]
    #[Response(201)]
    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        $this->authorize('create', Subscription::class);

        return $this->subscriptionService
            ->store($request->validated())
            ->toResource(SubscriptionResource::class)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a subscription.
     */
    #[Endpoint(operationId: 'showLandlordSubscription', title: 'Show a subscription')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: tenant, plan, invoices.', type: 'string')]
    public function show(Request $request, Subscription $subscription): SubscriptionResource
    {
        $this->authorize('view', $subscription);

        return $this->subscriptionService
            ->show($subscription, $request)
            ->toResource(SubscriptionResource::class);
    }

    /**
     * Change the plan on a subscription.
     */
    #[Endpoint(operationId: 'changeLandlordSubscriptionPlan', title: 'Change subscription plan')]
    public function changePlan(ChangeSubscriptionPlanRequest $request, Subscription $subscription): SubscriptionResource
    {
        $this->authorize('changePlan', $subscription);

        return $this->subscriptionService
            ->changePlan($subscription, $request->validated())
            ->toResource(SubscriptionResource::class);
    }

    /**
     * Cancel a subscription.
     */
    #[Endpoint(operationId: 'cancelLandlordSubscription', title: 'Cancel a subscription')]
    public function cancel(Subscription $subscription): SubscriptionResource
    {
        $this->authorize('cancel', $subscription);

        return $this->subscriptionService
            ->cancel($subscription)
            ->toResource(SubscriptionResource::class);
    }

    /**
     * Renew a subscription for the next billing period.
     */
    #[Endpoint(operationId: 'renewLandlordSubscription', title: 'Renew a subscription')]
    public function renew(Subscription $subscription): SubscriptionResource
    {
        $this->authorize('renew', $subscription);

        return $this->subscriptionService
            ->requestRenewal($subscription)
            ->toResource(SubscriptionResource::class);
    }
}
