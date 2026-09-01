<?php

declare(strict_types=1);

namespace App\Http\Controllers\Landlord\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\Settings\DestroyManyRequest;
use App\Http\Requests\Landlord\Settings\RestoreManyRequest;
use App\Http\Requests\Landlord\Settings\StoreSettingRequest;
use App\Http\Requests\Landlord\Settings\UpdateSettingRequest;
use App\Http\Resources\Landlord\Settings\SettingResource;
use App\Models\Landlord\Setting;
use App\Services\Landlord\Settings\SettingService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response as HttpResponse;

#[Group('Landlord Settings')]
class SettingController extends Controller
{
    public function __construct(private SettingService $settingService) {}

    /**
     * List settings.
     *
     * @return AnonymousResourceCollection<int, SettingResource>
     */
    #[Endpoint(operationId: 'listLandlordSettings', title: 'List settings')]
    #[QueryParameter('filter[group]', description: 'Exact setting group.', type: 'string')]
    #[QueryParameter('filter[key]', description: 'Exact setting key.', type: 'string')]
    #[QueryParameter('filter[type]', description: 'Exact setting type.', type: 'string')]
    #[QueryParameter('search', description: 'Partial match across key, group, and description.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Setting::class);

        return SettingResource::collection($this->settingService->paginate($request));
    }

    /**
     * Create a setting.
     */
    #[Endpoint(operationId: 'storeLandlordSetting', title: 'Create a setting')]
    #[Response(201)]
    public function store(StoreSettingRequest $request): JsonResponse
    {
        $this->authorize('create', Setting::class);

        return $this->settingService
            ->store($request->validated())
            ->toResource(SettingResource::class)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a setting.
     */
    #[Endpoint(operationId: 'showLandlordSetting', title: 'Show a setting')]
    public function show(Setting $setting): SettingResource
    {
        $this->authorize('view', $setting);

        return $setting->toResource(SettingResource::class);
    }

    /**
     * Update a setting.
     */
    #[Endpoint(operationId: 'updateLandlordSetting', title: 'Update a setting')]
    public function update(UpdateSettingRequest $request, Setting $setting): SettingResource
    {
        $this->authorize('update', $setting);

        return $this->settingService
            ->update($setting, $request->validated())
            ->toResource(SettingResource::class);
    }

    /**
     * Soft delete a setting.
     */
    #[Endpoint(operationId: 'destroyLandlordSetting', title: 'Delete a setting')]
    public function destroy(Setting $setting): HttpResponse
    {
        $this->authorize('delete', $setting);

        $this->settingService->destroy($setting);

        return response()->noContent();
    }

    /**
     * Restore a soft-deleted setting.
     */
    #[Endpoint(operationId: 'restoreLandlordSetting', title: 'Restore a setting')]
    public function restore(Setting $setting): SettingResource
    {
        $this->authorize('restore', $setting);

        return $this->settingService
            ->restore($setting)
            ->toResource(SettingResource::class);
    }

    /**
     * Soft delete many settings.
     */
    #[Endpoint(operationId: 'destroyManyLandlordSettings', title: 'Delete many settings')]
    public function destroyMany(DestroyManyRequest $request): HttpResponse
    {
        $this->authorize('delete', Setting::class);

        $this->settingService->destroyMany($request->ids());

        return response()->noContent();
    }

    /**
     * Restore many soft-deleted settings.
     */
    #[Endpoint(operationId: 'restoreManyLandlordSettings', title: 'Restore many settings')]
    public function restoreMany(RestoreManyRequest $request): HttpResponse
    {
        $this->authorize('restore', Setting::class);

        $this->settingService->restoreMany($request->ids());

        return response()->noContent();
    }
}
