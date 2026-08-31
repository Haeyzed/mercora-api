<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\Audit;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Audit\DestroyManyRequest;
use App\Http\Resources\Landlord\Audit\ActivityResource;
use App\Http\Resources\Shared\World\OptionResource;
use App\Models\Landlord\Activity;
use App\Services\Landlord\Audit\ActivityService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;

#[Group('Landlord Audit')]
class ActivityController extends Controller
{
    public function __construct(private ActivityService $activityService) {}

    /**
     * List activities.
     *
     * @return AnonymousResourceCollection<int, ActivityResource>
     */
    #[Endpoint(operationId: 'listLandlordActivities', title: 'List activities')]
    #[QueryParameter('filter[event]', description: 'Exact activity event.', type: 'string')]
    #[QueryParameter('filter[log_name]', description: 'Exact log name.', type: 'string')]
    #[QueryParameter('filter[subject_type]', description: 'Exact subject morph type.', type: 'string')]
    #[QueryParameter('filter[subject_id]', description: 'Exact subject id.', type: 'string')]
    #[QueryParameter('filter[causer_id]', description: 'Exact causer id.', type: 'int')]
    #[QueryParameter('search', description: 'Partial match on the activity description.', type: 'string')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: causer, subject.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Activity::class);

        return ActivityResource::collection($this->activityService->paginate($request));
    }

    /**
     * List activity options for selects.
     *
     * @return AnonymousResourceCollection<int, OptionResource>
     */
    #[Endpoint(operationId: 'listLandlordActivityOptions', title: 'List activity options')]
    #[QueryParameter('filter[event]', description: 'Exact activity event.', type: 'string')]
    #[QueryParameter('filter[log_name]', description: 'Exact log name.', type: 'string')]
    #[QueryParameter('search', description: 'Partial match on the activity description.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function options(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Activity::class);

        return OptionResource::collection($this->activityService->options($request));
    }

    /**
     * Show an activity.
     */
    #[Endpoint(operationId: 'showLandlordActivity', title: 'Show an activity')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: causer, subject.', type: 'string')]
    public function show(Request $request, Activity $activity): ActivityResource
    {
        $this->authorize('view', $activity);

        return $this->activityService
            ->show($activity, $request)
            ->toResource(ActivityResource::class);
    }

    /**
     * Permanently delete an activity.
     */
    #[Endpoint(operationId: 'destroyLandlordActivity', title: 'Delete an activity')]
    public function destroy(Activity $activity): HttpResponse
    {
        $this->authorize('delete', $activity);

        $this->activityService->destroy($activity);

        return response()->noContent();
    }

    /**
     * Permanently delete many activities.
     */
    #[Endpoint(operationId: 'destroyManyLandlordActivities', title: 'Delete many activities')]
    public function destroyMany(DestroyManyRequest $request): HttpResponse
    {
        $this->authorize('delete', Activity::class);

        $this->activityService->destroyMany($request->ids());

        return response()->noContent();
    }
}
