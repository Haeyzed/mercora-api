<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Notifications\DestroyManyRequest;
use App\Http\Requests\Landlord\Notifications\RestoreManyRequest;
use App\Http\Requests\Landlord\Notifications\StoreNoticeRequest;
use App\Http\Requests\Landlord\Notifications\UpdateNoticeRequest;
use App\Http\Resources\Landlord\Notifications\NotificationResource;
use App\Models\Landlord\Notice;
use App\Services\Landlord\Notifications\NoticeService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;

#[Group('Landlord Notifications')]
class NotificationController extends Controller
{
    public function __construct(private NoticeService $noticeService) {}

    /**
     * List notices.
     *
     * @return AnonymousResourceCollection<int, NotificationResource>
     */
    #[Endpoint(operationId: 'listLandlordNotifications', title: 'List notifications')]
    #[QueryParameter('filter[user_id]', description: 'Exact recipient user id.', type: 'int')]
    #[QueryParameter('filter[status]', description: 'Exact notice status.', type: 'string')]
    #[QueryParameter('filter[channel]', description: 'Exact notice channel.', type: 'string')]
    #[QueryParameter('search', description: 'Partial match across title, body, and recipient name or email.', type: 'string')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: user.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Notice::class);

        return NotificationResource::collection($this->noticeService->paginate($request));
    }

    /**
     * Create a notice.
     */
    #[Endpoint(operationId: 'storeLandlordNotification', title: 'Create a notification')]
    #[Response(201)]
    public function store(StoreNoticeRequest $request): JsonResponse
    {
        $this->authorize('create', Notice::class);

        return $this->noticeService
            ->store($request->validated())
            ->toResource(NotificationResource::class)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a notice.
     */
    #[Endpoint(operationId: 'showLandlordNotification', title: 'Show a notification')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: user.', type: 'string')]
    public function show(Request $request, Notice $notice): NotificationResource
    {
        $this->authorize('view', $notice);

        return $this->noticeService
            ->show($notice, $request)
            ->toResource(NotificationResource::class);
    }

    /**
     * Update an unread notice.
     */
    #[Endpoint(operationId: 'updateLandlordNotification', title: 'Update a notification')]
    public function update(UpdateNoticeRequest $request, Notice $notice): NotificationResource
    {
        $this->authorize('update', $notice);

        return $this->noticeService
            ->update($notice, $request->validated())
            ->toResource(NotificationResource::class);
    }

    /**
     * Mark a notice as read.
     */
    #[Endpoint(operationId: 'readLandlordNotification', title: 'Read a notification')]
    public function read(Notice $notice): NotificationResource
    {
        $this->authorize('read', $notice);

        return $this->noticeService
            ->read($notice)
            ->toResource(NotificationResource::class);
    }

    /**
     * Mark all unread notices for the authenticated user as read.
     */
    #[Endpoint(operationId: 'readAllLandlordNotifications', title: 'Read all notifications')]
    public function readAll(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Notice::class);

        $count = $this->noticeService->readAll($request->user()->id);

        return response()->json(['read' => $count]);
    }

    /**
     * Soft delete a notice.
     */
    #[Endpoint(operationId: 'destroyLandlordNotification', title: 'Delete a notification')]
    public function destroy(Notice $notice): HttpResponse
    {
        $this->authorize('delete', $notice);

        $this->noticeService->destroy($notice);

        return response()->noContent();
    }

    /**
     * Restore a soft-deleted notice.
     */
    #[Endpoint(operationId: 'restoreLandlordNotification', title: 'Restore a notification')]
    public function restore(Notice $notice): NotificationResource
    {
        $this->authorize('restore', $notice);

        return $this->noticeService
            ->restore($notice)
            ->toResource(NotificationResource::class);
    }

    /**
     * Soft delete many notices.
     */
    #[Endpoint(operationId: 'destroyManyLandlordNotifications', title: 'Delete many notifications')]
    public function destroyMany(DestroyManyRequest $request): HttpResponse
    {
        $this->authorize('delete', Notice::class);

        $this->noticeService->destroyMany($request->ids());

        return response()->noContent();
    }

    /**
     * Restore many soft-deleted notices.
     */
    #[Endpoint(operationId: 'restoreManyLandlordNotifications', title: 'Restore many notifications')]
    public function restoreMany(RestoreManyRequest $request): HttpResponse
    {
        $this->authorize('restore', Notice::class);

        $this->noticeService->restoreMany($request->ids());

        return response()->noContent();
    }
}
