<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared\World;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\World\DestroyManyRequest;
use App\Http\Requests\Shared\World\ImportWorldRequest;
use App\Http\Requests\Shared\World\RestoreManyRequest;
use App\Http\Requests\Shared\World\StoreTimezoneRequest;
use App\Http\Requests\Shared\World\UpdateTimezoneRequest;
use App\Http\Resources\Shared\World\OptionResource;
use App\Http\Resources\Shared\World\TimezoneResource;
use App\Models\Shared\Timezone;
use App\Services\Shared\World\TimezoneService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

#[Group('Shared World')]
class TimezoneController extends Controller
{
    public function __construct(private TimezoneService $timezoneService) {}

    /**
     * List timezones.
     *
     * @return AnonymousResourceCollection<int, TimezoneResource>
     */
    #[Endpoint(operationId: 'listSharedWorldTimezones', title: 'List timezones')]
    #[QueryParameter('filter[name]', description: 'Partial match on timezone name.', type: 'string')]
    #[QueryParameter('filter[country_id]', description: 'Exact country id.', type: 'int')]
    #[QueryParameter('search', description: 'Partial match on timezone name.', type: 'string')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: country.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Timezone::class);

        return TimezoneResource::collection($this->timezoneService->paginate($request));
    }

    /**
     * List timezone options for selects.
     *
     * @return AnonymousResourceCollection<int, OptionResource>
     */
    #[Endpoint(operationId: 'listSharedWorldTimezoneOptions', title: 'List timezone options')]
    #[QueryParameter('filter[name]', description: 'Partial match on timezone name.', type: 'string')]
    #[QueryParameter('filter[country_id]', description: 'Exact country id.', type: 'int')]
    #[QueryParameter('search', description: 'Partial match on timezone name.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function options(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Timezone::class);

        return OptionResource::collection($this->timezoneService->options($request));
    }

    /**
     * Create a timezone.
     */
    #[Endpoint(operationId: 'storeSharedWorldTimezone', title: 'Create a timezone')]
    #[Response(201)]
    public function store(StoreTimezoneRequest $request): JsonResponse
    {
        $this->authorize('create', Timezone::class);

        return $this->timezoneService
            ->store($request->validated())
            ->toResource(TimezoneResource::class)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a timezone.
     */
    #[Endpoint(operationId: 'showSharedWorldTimezone', title: 'Show a timezone')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: country.', type: 'string')]
    public function show(Request $request, Timezone $timezone): TimezoneResource
    {
        $this->authorize('view', $timezone);

        return $this->timezoneService
            ->show($timezone, $request)
            ->toResource(TimezoneResource::class);
    }

    /**
     * Update a timezone.
     */
    #[Endpoint(operationId: 'updateSharedWorldTimezone', title: 'Update a timezone')]
    public function update(UpdateTimezoneRequest $request, Timezone $timezone): TimezoneResource
    {
        $this->authorize('update', $timezone);

        return $this->timezoneService
            ->update($timezone, $request->validated())
            ->toResource(TimezoneResource::class);
    }

    /**
     * Soft delete a timezone.
     */
    #[Endpoint(operationId: 'destroySharedWorldTimezone', title: 'Delete a timezone')]
    public function destroy(Timezone $timezone): HttpResponse
    {
        $this->authorize('delete', $timezone);

        $this->timezoneService->destroy($timezone);

        return response()->noContent();
    }

    /**
     * Restore a soft-deleted timezone.
     */
    #[Endpoint(operationId: 'restoreSharedWorldTimezone', title: 'Restore a timezone')]
    public function restore(Timezone $timezone): TimezoneResource
    {
        $this->authorize('restore', $timezone);

        return $this->timezoneService
            ->restore($timezone)
            ->toResource(TimezoneResource::class);
    }

    /**
     * Soft delete many timezones.
     */
    #[Endpoint(operationId: 'destroyManySharedWorldTimezones', title: 'Delete many timezones')]
    public function destroyMany(DestroyManyRequest $request): HttpResponse
    {
        $this->authorize('delete', Timezone::class);

        $this->timezoneService->destroyMany($request->ids());

        return response()->noContent();
    }

    /**
     * Restore many soft-deleted timezones.
     */
    #[Endpoint(operationId: 'restoreManySharedWorldTimezones', title: 'Restore many timezones')]
    public function restoreMany(RestoreManyRequest $request): HttpResponse
    {
        $this->authorize('restore', Timezone::class);

        $this->timezoneService->restoreMany($request->ids());

        return response()->noContent();
    }

    /**
     * Import timezones from a spreadsheet.
     */
    #[Endpoint(operationId: 'importSharedWorldTimezones', title: 'Import timezones')]
    public function import(ImportWorldRequest $request): HttpResponse
    {
        $this->authorize('create', Timezone::class);

        $this->timezoneService->import($request->uploadedFile());

        return response()->noContent();
    }

    /**
     * Download a timezone import template.
     */
    #[Endpoint(operationId: 'templateSharedWorldTimezones', title: 'Download timezone import template')]
    public function template(): BinaryFileResponse
    {
        $this->authorize('viewAny', Timezone::class);

        return $this->timezoneService->template();
    }

    /**
     * Export timezones to a spreadsheet.
     */
    #[Endpoint(operationId: 'exportSharedWorldTimezones', title: 'Export timezones')]
    #[QueryParameter('filter[name]', description: 'Partial match on timezone name.', type: 'string')]
    #[QueryParameter('filter[country_id]', description: 'Exact country id.', type: 'int')]
    #[QueryParameter('search', description: 'Partial match on timezone name.', type: 'string')]
    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Timezone::class);

        return $this->timezoneService->export($request);
    }
}
