<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared\World;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\World\DestroyManyRequest;
use App\Http\Requests\Shared\World\ImportWorldRequest;
use App\Http\Requests\Shared\World\RestoreManyRequest;
use App\Http\Requests\Shared\World\StoreCityRequest;
use App\Http\Requests\Shared\World\UpdateCityRequest;
use App\Http\Resources\Shared\World\CityResource;
use App\Http\Resources\Shared\World\OptionResource;
use App\Models\Shared\City;
use App\Services\Shared\World\CityService;
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
class CityController extends Controller
{
    public function __construct(private CityService $cityService) {}

    /**
     * List cities.
     *
     * @return AnonymousResourceCollection<int, CityResource>
     */
    #[Endpoint(operationId: 'listSharedWorldCities', title: 'List cities')]
    #[QueryParameter('filter[name]', description: 'Partial match on city name.', type: 'string')]
    #[QueryParameter('filter[country_id]', description: 'Exact country id.', type: 'int')]
    #[QueryParameter('filter[state_id]', description: 'Exact state id.', type: 'int')]
    #[QueryParameter('filter[country_code]', description: 'Exact country ISO code stored on the city.', type: 'string')]
    #[QueryParameter('filter[state_code]', description: 'Exact state code stored on the city.', type: 'string')]
    #[QueryParameter('search', description: 'Partial match across name, country_code, and state_code.', type: 'string')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: country, state.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', City::class);

        return CityResource::collection($this->cityService->paginate($request));
    }

    /**
     * List city options for selects.
     *
     * @return AnonymousResourceCollection<int, OptionResource>
     */
    #[Endpoint(operationId: 'listSharedWorldCityOptions', title: 'List city options')]
    #[QueryParameter('filter[name]', description: 'Partial match on city name.', type: 'string')]
    #[QueryParameter('filter[country_id]', description: 'Exact country id.', type: 'int')]
    #[QueryParameter('filter[state_id]', description: 'Exact state id.', type: 'int')]
    #[QueryParameter('search', description: 'Partial match across name, country_code, and state_code.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function options(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', City::class);

        return OptionResource::collection($this->cityService->options($request));
    }

    /**
     * Create a city.
     */
    #[Endpoint(operationId: 'storeSharedWorldCity', title: 'Create a city')]
    #[Response(201)]
    public function store(StoreCityRequest $request): JsonResponse
    {
        $this->authorize('create', City::class);

        return $this->cityService
            ->store($request->validated())
            ->toResource(CityResource::class)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a city.
     */
    #[Endpoint(operationId: 'showSharedWorldCity', title: 'Show a city')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: country, state.', type: 'string')]
    public function show(Request $request, City $city): CityResource
    {
        $this->authorize('view', $city);

        return $this->cityService
            ->show($city, $request)
            ->toResource(CityResource::class);
    }

    /**
     * Update a city.
     */
    #[Endpoint(operationId: 'updateSharedWorldCity', title: 'Update a city')]
    public function update(UpdateCityRequest $request, City $city): CityResource
    {
        $this->authorize('update', $city);

        return $this->cityService
            ->update($city, $request->validated())
            ->toResource(CityResource::class);
    }

    /**
     * Soft delete a city.
     */
    #[Endpoint(operationId: 'destroySharedWorldCity', title: 'Delete a city')]
    public function destroy(City $city): HttpResponse
    {
        $this->authorize('delete', $city);

        $this->cityService->destroy($city);

        return response()->noContent();
    }

    /**
     * Restore a soft-deleted city.
     */
    #[Endpoint(operationId: 'restoreSharedWorldCity', title: 'Restore a city')]
    public function restore(City $city): CityResource
    {
        $this->authorize('restore', $city);

        return $this->cityService
            ->restore($city)
            ->toResource(CityResource::class);
    }

    /**
     * Soft delete many cities.
     */
    #[Endpoint(operationId: 'destroyManySharedWorldCities', title: 'Delete many cities')]
    public function destroyMany(DestroyManyRequest $request): HttpResponse
    {
        $this->authorize('delete', City::class);

        $this->cityService->destroyMany($request->ids());

        return response()->noContent();
    }

    /**
     * Restore many soft-deleted cities.
     */
    #[Endpoint(operationId: 'restoreManySharedWorldCities', title: 'Restore many cities')]
    public function restoreMany(RestoreManyRequest $request): HttpResponse
    {
        $this->authorize('restore', City::class);

        $this->cityService->restoreMany($request->ids());

        return response()->noContent();
    }

    /**
     * Import cities from a spreadsheet.
     */
    #[Endpoint(operationId: 'importSharedWorldCities', title: 'Import cities')]
    public function import(ImportWorldRequest $request): HttpResponse
    {
        $this->authorize('create', City::class);

        $this->cityService->import($request->uploadedFile());

        return response()->noContent();
    }

    /**
     * Download a city import template.
     */
    #[Endpoint(operationId: 'templateSharedWorldCities', title: 'Download city import template')]
    public function template(): BinaryFileResponse
    {
        $this->authorize('viewAny', City::class);

        return $this->cityService->template();
    }

    /**
     * Export cities to a spreadsheet.
     */
    #[Endpoint(operationId: 'exportSharedWorldCities', title: 'Export cities')]
    #[QueryParameter('filter[name]', description: 'Partial match on city name.', type: 'string')]
    #[QueryParameter('filter[country_id]', description: 'Exact country id.', type: 'int')]
    #[QueryParameter('filter[state_id]', description: 'Exact state id.', type: 'int')]
    #[QueryParameter('filter[country_code]', description: 'Exact country ISO code stored on the city.', type: 'string')]
    #[QueryParameter('filter[state_code]', description: 'Exact state code stored on the city.', type: 'string')]
    #[QueryParameter('search', description: 'Partial match across name, country_code, and state_code.', type: 'string')]
    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', City::class);

        return $this->cityService->export($request);
    }
}
