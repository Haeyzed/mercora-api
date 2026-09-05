<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Notifications\PreviewNotificationTemplateRequest;
use App\Http\Requests\Landlord\Notifications\StoreNotificationTemplateRequest;
use App\Http\Requests\Landlord\Notifications\UpdateNotificationTemplateRequest;
use App\Http\Resources\Landlord\Notifications\NotificationTemplateResource;
use App\Http\Resources\Shared\World\OptionResource;
use App\Models\Landlord\NotificationTemplate;
use App\Services\Landlord\Notifications\NotificationTemplateService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;

#[Group('Landlord Notification Templates')]
class NotificationTemplateController extends Controller
{
    public function __construct(private NotificationTemplateService $templates) {}

    /**
     * List notification templates.
     *
     * @return AnonymousResourceCollection<int, NotificationTemplateResource>
     */
    #[Endpoint(operationId: 'listLandlordNotificationTemplates', title: 'List notification templates')]
    #[QueryParameter('filter[key]', description: 'Exact template key.', type: 'string')]
    #[QueryParameter('filter[is_active]', description: 'Whether the template is active.', type: 'bool')]
    #[QueryParameter('search', description: 'Partial match across key, name, and description.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', NotificationTemplate::class);

        return NotificationTemplateResource::collection($this->templates->paginate($request));
    }

    /**
     * List notification template options for selects.
     *
     * @return AnonymousResourceCollection<int, OptionResource>
     */
    #[Endpoint(operationId: 'listLandlordNotificationTemplateOptions', title: 'List notification template options')]
    #[QueryParameter('search', description: 'Partial match across key, name, and description.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function options(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', NotificationTemplate::class);

        return OptionResource::collection($this->templates->options($request));
    }

    /**
     * Create a notification template.
     */
    #[Endpoint(operationId: 'storeLandlordNotificationTemplate', title: 'Create a notification template')]
    #[Response(201)]
    public function store(StoreNotificationTemplateRequest $request): JsonResponse
    {
        $this->authorize('create', NotificationTemplate::class);

        return $this->templates
            ->store($request->validated())
            ->toResource(NotificationTemplateResource::class)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a notification template.
     */
    #[Endpoint(operationId: 'showLandlordNotificationTemplate', title: 'Show a notification template')]
    public function show(NotificationTemplate $notificationTemplate): NotificationTemplateResource
    {
        $this->authorize('view', $notificationTemplate);

        return $this->templates
            ->show($notificationTemplate)
            ->toResource(NotificationTemplateResource::class);
    }

    /**
     * Update a notification template.
     */
    #[Endpoint(operationId: 'updateLandlordNotificationTemplate', title: 'Update a notification template')]
    public function update(
        UpdateNotificationTemplateRequest $request,
        NotificationTemplate $notificationTemplate,
    ): NotificationTemplateResource {
        $this->authorize('update', $notificationTemplate);

        return $this->templates
            ->update($notificationTemplate, $request->validated())
            ->toResource(NotificationTemplateResource::class);
    }

    /**
     * Delete a notification template.
     */
    #[Endpoint(operationId: 'destroyLandlordNotificationTemplate', title: 'Delete a notification template')]
    public function destroy(NotificationTemplate $notificationTemplate): HttpResponse
    {
        $this->authorize('delete', $notificationTemplate);

        $this->templates->destroy($notificationTemplate);

        return response()->noContent();
    }

    /**
     * Preview rendered template content without sending.
     */
    #[Endpoint(operationId: 'previewLandlordNotificationTemplate', title: 'Preview a notification template')]
    public function preview(
        PreviewNotificationTemplateRequest $request,
        NotificationTemplate $notificationTemplate,
    ): JsonResponse {
        $this->authorize('preview', $notificationTemplate);

        return response()->json([
            'data' => $this->templates->preview(
                $notificationTemplate,
                $request->validated('data') ?? [],
            ),
        ]);
    }
}
