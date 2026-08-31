<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared\World;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\World\DestroyManyRequest;
use App\Http\Requests\Shared\World\ImportWorldRequest;
use App\Http\Requests\Shared\World\RestoreManyRequest;
use App\Http\Requests\Shared\World\StoreCountryRequest;
use App\Http\Requests\Shared\World\UpdateCountryRequest;
use App\Http\Resources\Shared\World\CountryResource;
use App\Http\Resources\Shared\World\OptionResource;
use App\Models\Shared\Country;
use App\Services\Shared\World\CountryService;
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
class CountryController extends Controller
{
    public function __construct(private CountryService $countryService) {}

    /**
     * List countries.
     *
     * @return AnonymousResourceCollection<int, CountryResource>
     */
    #[Endpoint(operationId: 'listSharedWorldCountries', title: 'List countries')]
    #[QueryParameter('filter[name]', description: 'Partial match on country name.', type: 'string')]
    #[QueryParameter('filter[iso2]', description: 'Exact ISO 3166-1 alpha-2 code.', type: 'string')]
    #[QueryParameter('filter[iso3]', description: 'Exact ISO 3166-1 alpha-3 code.', type: 'string')]
    #[QueryParameter('filter[region]', description: 'Exact region name.', type: 'string')]
    #[QueryParameter('filter[status]', description: 'Exact status flag.', type: 'int')]
    #[QueryParameter('search', description: 'Partial match across name, iso2, iso3, native, and phone_code.', type: 'string')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: states, cities, timezones, currency.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Country::class);

        return CountryResource::collection($this->countryService->paginate($request));
    }

    /**
     * List country options for selects.
     *
     * @return AnonymousResourceCollection<int, OptionResource>
     */
    #[Endpoint(operationId: 'listSharedWorldCountryOptions', title: 'List country options')]
    #[QueryParameter('filter[name]', description: 'Partial match on country name.', type: 'string')]
    #[QueryParameter('filter[iso2]', description: 'Exact ISO 3166-1 alpha-2 code.', type: 'string')]
    #[QueryParameter('search', description: 'Partial match across name, iso2, iso3, native, and phone_code.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function options(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Country::class);

        return OptionResource::collection($this->countryService->options($request));
    }

    /**
     * Create a country.
     */
    #[Endpoint(operationId: 'storeSharedWorldCountry', title: 'Create a country')]
    #[Response(201)]
    public function store(StoreCountryRequest $request): JsonResponse
    {
        $this->authorize('create', Country::class);

        return $this->countryService
            ->store($request->validated())
            ->toResource(CountryResource::class)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a country.
     */
    #[Endpoint(operationId: 'showSharedWorldCountry', title: 'Show a country')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: states, cities, timezones, currency.', type: 'string')]
    public function show(Request $request, Country $country): CountryResource
    {
        $this->authorize('view', $country);

        return $this->countryService
            ->show($country, $request)
            ->toResource(CountryResource::class);
    }

    /**
     * Update a country.
     */
    #[Endpoint(operationId: 'updateSharedWorldCountry', title: 'Update a country')]
    public function update(UpdateCountryRequest $request, Country $country): CountryResource
    {
        $this->authorize('update', $country);

        return $this->countryService
            ->update($country, $request->validated())
            ->toResource(CountryResource::class);
    }

    /**
     * Soft delete a country.
     */
    #[Endpoint(operationId: 'destroySharedWorldCountry', title: 'Delete a country')]
    public function destroy(Country $country): HttpResponse
    {
        $this->authorize('delete', $country);

        $this->countryService->destroy($country);

        return response()->noContent();
    }

    /**
     * Restore a soft-deleted country.
     */
    #[Endpoint(operationId: 'restoreSharedWorldCountry', title: 'Restore a country')]
    public function restore(Country $country): CountryResource
    {
        $this->authorize('restore', $country);

        return $this->countryService
            ->restore($country)
            ->toResource(CountryResource::class);
    }

    /**
     * Soft delete many countries.
     */
    #[Endpoint(operationId: 'destroyManySharedWorldCountries', title: 'Delete many countries')]
    public function destroyMany(DestroyManyRequest $request): HttpResponse
    {
        $this->authorize('delete', Country::class);

        $this->countryService->destroyMany($request->ids());

        return response()->noContent();
    }

    /**
     * Restore many soft-deleted countries.
     */
    #[Endpoint(operationId: 'restoreManySharedWorldCountries', title: 'Restore many countries')]
    public function restoreMany(RestoreManyRequest $request): HttpResponse
    {
        $this->authorize('restore', Country::class);

        $this->countryService->restoreMany($request->ids());

        return response()->noContent();
    }

    /**
     * Import countries from a spreadsheet.
     */
    #[Endpoint(operationId: 'importSharedWorldCountries', title: 'Import countries')]
    public function import(ImportWorldRequest $request): HttpResponse
    {
        $this->authorize('create', Country::class);

        $this->countryService->import($request->uploadedFile());

        return response()->noContent();
    }

    /**
     * Download a country import template.
     */
    #[Endpoint(operationId: 'templateSharedWorldCountries', title: 'Download country import template')]
    public function template(): BinaryFileResponse
    {
        $this->authorize('viewAny', Country::class);

        return $this->countryService->template();
    }

    /**
     * Export countries to a spreadsheet.
     */
    #[Endpoint(operationId: 'exportSharedWorldCountries', title: 'Export countries')]
    #[QueryParameter('filter[name]', description: 'Partial match on country name.', type: 'string')]
    #[QueryParameter('filter[iso2]', description: 'Exact ISO 3166-1 alpha-2 code.', type: 'string')]
    #[QueryParameter('filter[iso3]', description: 'Exact ISO 3166-1 alpha-3 code.', type: 'string')]
    #[QueryParameter('filter[region]', description: 'Exact region name.', type: 'string')]
    #[QueryParameter('filter[status]', description: 'Exact status flag.', type: 'int')]
    #[QueryParameter('search', description: 'Partial match across name, iso2, iso3, native, and phone_code.', type: 'string')]
    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Country::class);

        return $this->countryService->export($request);
    }
}
