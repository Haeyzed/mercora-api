<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared\World;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\World\DestroyManyRequest;
use App\Http\Requests\Shared\World\ImportWorldRequest;
use App\Http\Requests\Shared\World\RestoreManyRequest;
use App\Http\Requests\Shared\World\StoreCurrencyRequest;
use App\Http\Requests\Shared\World\UpdateCurrencyRequest;
use App\Http\Resources\Shared\World\CurrencyResource;
use App\Http\Resources\Shared\World\OptionResource;
use App\Models\Shared\Currency;
use App\Services\Shared\World\CurrencyService;
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
class CurrencyController extends Controller
{
    public function __construct(private CurrencyService $currencyService) {}

    /**
     * List world currencies.
     *
     * @return AnonymousResourceCollection<int, CurrencyResource>
     */
    #[Endpoint(operationId: 'listSharedWorldCurrencies', title: 'List world currencies')]
    #[QueryParameter('filter[name]', description: 'Partial match on currency name.', type: 'string')]
    #[QueryParameter('filter[code]', description: 'Exact ISO currency code.', type: 'string')]
    #[QueryParameter('filter[country_id]', description: 'Exact country id.', type: 'int')]
    #[QueryParameter('search', description: 'Partial match across name, code, and symbol.', type: 'string')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: country.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Currency::class);

        return CurrencyResource::collection($this->currencyService->paginate($request));
    }

    /**
     * List currency options for selects.
     *
     * @return AnonymousResourceCollection<int, OptionResource>
     */
    #[Endpoint(operationId: 'listSharedWorldCurrencyOptions', title: 'List currency options')]
    #[QueryParameter('filter[name]', description: 'Partial match on currency name.', type: 'string')]
    #[QueryParameter('filter[code]', description: 'Exact ISO currency code.', type: 'string')]
    #[QueryParameter('search', description: 'Partial match across name, code, and symbol.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function options(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Currency::class);

        return OptionResource::collection($this->currencyService->options($request));
    }

    /**
     * Create a world currency.
     */
    #[Endpoint(operationId: 'storeSharedWorldCurrency', title: 'Create a world currency')]
    #[Response(201)]
    public function store(StoreCurrencyRequest $request): JsonResponse
    {
        $this->authorize('create', Currency::class);

        return $this->currencyService
            ->store($request->validated())
            ->toResource(CurrencyResource::class)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a world currency.
     */
    #[Endpoint(operationId: 'showSharedWorldCurrency', title: 'Show a world currency')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: country.', type: 'string')]
    public function show(Request $request, Currency $currency): CurrencyResource
    {
        $this->authorize('view', $currency);

        return $this->currencyService
            ->show($currency, $request)
            ->toResource(CurrencyResource::class);
    }

    /**
     * Update a world currency.
     */
    #[Endpoint(operationId: 'updateSharedWorldCurrency', title: 'Update a world currency')]
    public function update(UpdateCurrencyRequest $request, Currency $currency): CurrencyResource
    {
        $this->authorize('update', $currency);

        return $this->currencyService
            ->update($currency, $request->validated())
            ->toResource(CurrencyResource::class);
    }

    /**
     * Soft delete a world currency.
     */
    #[Endpoint(operationId: 'destroySharedWorldCurrency', title: 'Delete a world currency')]
    public function destroy(Currency $currency): HttpResponse
    {
        $this->authorize('delete', $currency);

        $this->currencyService->destroy($currency);

        return response()->noContent();
    }

    /**
     * Restore a soft-deleted world currency.
     */
    #[Endpoint(operationId: 'restoreSharedWorldCurrency', title: 'Restore a world currency')]
    public function restore(Currency $currency): CurrencyResource
    {
        $this->authorize('restore', $currency);

        return $this->currencyService
            ->restore($currency)
            ->toResource(CurrencyResource::class);
    }

    /**
     * Soft delete many world currencies.
     */
    #[Endpoint(operationId: 'destroyManySharedWorldCurrencies', title: 'Delete many world currencies')]
    public function destroyMany(DestroyManyRequest $request): HttpResponse
    {
        $this->authorize('delete', Currency::class);

        $this->currencyService->destroyMany($request->ids());

        return response()->noContent();
    }

    /**
     * Restore many soft-deleted world currencies.
     */
    #[Endpoint(operationId: 'restoreManySharedWorldCurrencies', title: 'Restore many world currencies')]
    public function restoreMany(RestoreManyRequest $request): HttpResponse
    {
        $this->authorize('restore', Currency::class);

        $this->currencyService->restoreMany($request->ids());

        return response()->noContent();
    }

    /**
     * Import world currencies from a spreadsheet.
     */
    #[Endpoint(operationId: 'importSharedWorldCurrencies', title: 'Import world currencies')]
    public function import(ImportWorldRequest $request): HttpResponse
    {
        $this->authorize('create', Currency::class);

        $this->currencyService->import($request->uploadedFile());

        return response()->noContent();
    }

    /**
     * Download a world currency import template.
     */
    #[Endpoint(operationId: 'templateSharedWorldCurrencies', title: 'Download world currency import template')]
    public function template(): BinaryFileResponse
    {
        $this->authorize('viewAny', Currency::class);

        return $this->currencyService->template();
    }

    /**
     * Export world currencies to a spreadsheet.
     */
    #[Endpoint(operationId: 'exportSharedWorldCurrencies', title: 'Export world currencies')]
    #[QueryParameter('filter[name]', description: 'Partial match on currency name.', type: 'string')]
    #[QueryParameter('filter[code]', description: 'Exact ISO currency code.', type: 'string')]
    #[QueryParameter('filter[country_id]', description: 'Exact country id.', type: 'int')]
    #[QueryParameter('search', description: 'Partial match across name, code, and symbol.', type: 'string')]
    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Currency::class);

        return $this->currencyService->export($request);
    }
}
