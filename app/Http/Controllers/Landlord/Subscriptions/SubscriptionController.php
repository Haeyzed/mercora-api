<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\Subscriptions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Subscriptions\DestroyManyRequest;
use App\Http\Requests\Landlord\Subscriptions\RestoreManyRequest;
use App\Http\Requests\Landlord\Subscriptions\StoreSubscriptionRequest;
use App\Http\Requests\Landlord\Subscriptions\UpdateSubscriptionRequest;
use App\Http\Resources\Landlord\Subscriptions\SubscriptionResource;
use App\Http\Resources\Shared\World\OptionResource;
use App\Models\Landlord\Subscription;
use App\Services\Landlord\Subscriptions\SubscriptionService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;

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
     * List subscription options for selects.
     *
     * @return AnonymousResourceCollection<int, OptionResource>
     */
    #[Endpoint(operationId: 'listLandlordSubscriptionOptions', title: 'List subscription options')]
    #[QueryParameter('filter[tenant_id]', description: 'Exact tenant id.', type: 'string')]
    #[QueryParameter('filter[status]', description: 'Exact subscription status.', type: 'string')]
    #[QueryParameter('search', description: 'Partial match across tenant and plan name or slug.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function options(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Subscription::class);

        return OptionResource::collection($this->subscriptionService->options($request));
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
    #[Endpoint(operationId: 'updateLandlordSubscription', title: 'Update a subscription')]
    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): SubscriptionResource
    {
        $this->authorize('update', $subscription);

        return $this->subscriptionService
            ->update($subscription, $request->validated())
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
            ->renew($subscription)
            ->toResource(SubscriptionResource::class);
    }

    /**
     * Soft delete a subscription.
     */
    #[Endpoint(operationId: 'destroyLandlordSubscription', title: 'Delete a subscription')]
    public function destroy(Subscription $subscription): HttpResponse
    {
        $this->authorize('delete', $subscription);

        $this->subscriptionService->destroy($subscription);

        return response()->noContent();
    }

    /**
     * Restore a soft-deleted subscription.
     */
    #[Endpoint(operationId: 'restoreLandlordSubscription', title: 'Restore a subscription')]
    public function restore(Subscription $subscription): SubscriptionResource
    {
        $this->authorize('restore', $subscription);

        return $this->subscriptionService
            ->restore($subscription)
            ->toResource(SubscriptionResource::class);
    }

    /**
     * Soft delete many subscriptions.
     */
    #[Endpoint(operationId: 'destroyManyLandlordSubscriptions', title: 'Delete many subscriptions')]
    public function destroyMany(DestroyManyRequest $request): HttpResponse
    {
        $this->authorize('delete', Subscription::class);

        $this->subscriptionService->destroyMany($request->ids());

        return response()->noContent();
    }

    /**
     * Restore many soft-deleted subscriptions.
     */
    #[Endpoint(operationId: 'restoreManyLandlordSubscriptions', title: 'Restore many subscriptions')]
    public function restoreMany(RestoreManyRequest $request): HttpResponse
    {
        $this->authorize('restore', Subscription::class);

        $this->subscriptionService->restoreMany($request->ids());

        return response()->noContent();
    }
}
