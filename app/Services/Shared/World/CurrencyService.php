<?php

declare(strict_types=1);

namespace App\Services\Shared\World;

use App\Exports\Shared\World\CurrenciesExport;
use App\Exports\Shared\World\WorldTemplateExport;
use App\Imports\Shared\World\CurrenciesImport;
use App\Models\Shared\Currency;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Manages shared ISO currency reference data for the landlord World API.
 *
 * Domain: central-database currency catalog (nnjeim/world). This is not tenant payment configuration.
 *
 * Invariants:
 * - Destroy is a soft delete; restore requires a trashed row.
 * - Export uses the same filter and search as the index listing.
 *
 * Side effects: creates, updates, soft-deletes, restores, imports, and exports {@see Currency} records.
 */
class CurrencyService
{
    /**
     * Paginate currencies using model filter, search, and include scopes.
     *
     * @return LengthAwarePaginator<int, Currency>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return Currency::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->withIncludes($request->query('include'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString();
    }

    /**
     * Paginate currency select options as label/value pairs.
     *
     * @return LengthAwarePaginator<int, array{label: string, value: int}>
     */
    public function options(Request $request): LengthAwarePaginator
    {
        return Currency::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (Currency $currency): array => [
                'label' => $currency->code.' — '.$currency->name,
                'value' => $currency->id,
            ]);
    }

    /**
     * Load a currency with optional allowed relationships.
     */
    public function show(Currency $currency, Request $request): Currency
    {
        return $currency->loadAllowedIncludes($request->query('include'));
    }

    /**
     * Create a world currency.
     *
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Currency
    {
        return Currency::query()->create($data);
    }

    /**
     * Update a world currency.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Currency $currency, array $data): Currency
    {
        $currency->update($data);

        return $currency->refresh();
    }

    /**
     * Soft delete a world currency.
     */
    public function destroy(Currency $currency): void
    {
        $currency->delete();
    }

    /**
     * Restore a soft-deleted world currency.
     *
     * @throws HttpException When the currency is not trashed (404).
     */
    public function restore(Currency $currency): Currency
    {
        abort_unless($currency->trashed(), 404);

        $currency->restore();

        return $currency->refresh();
    }

    /**
     * Soft delete many world currencies.
     *
     * @param  list<int>  $ids
     */
    public function destroyMany(array $ids): void
    {
        Currency::query()->whereKey($ids)->delete();
    }

    /**
     * Restore many soft-deleted world currencies.
     *
     * @param  list<int>  $ids
     */
    public function restoreMany(array $ids): void
    {
        Currency::onlyTrashed()->whereKey($ids)->restore();
    }

    /**
     * Import world currencies from a spreadsheet.
     */
    public function import(UploadedFile $file): void
    {
        Excel::import(new CurrenciesImport, $file);
    }

    /**
     * Download an empty import template with world currency headings.
     */
    public function template(): BinaryFileResponse
    {
        return Excel::download(new WorldTemplateExport(CurrenciesExport::columns()), 'currencies-template.xlsx');
    }

    /**
     * Export world currencies using the same filter and search as the index.
     */
    public function export(Request $request): BinaryFileResponse
    {
        return Excel::download(new CurrenciesExport($request), 'currencies.xlsx');
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
