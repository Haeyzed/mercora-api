<?php

declare(strict_types=1);

namespace App\Services\Shared\World;

use App\Exports\Shared\World\CountriesExport;
use App\Exports\Shared\World\WorldTemplateExport;
use App\Imports\Shared\World\CountriesImport;
use App\Models\Shared\Country;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Manages shared country reference data for the landlord World API.
 *
 * Domain: central-database geographic reference data (nnjeim/world); not duplicated in tenant databases.
 *
 * Invariants:
 * - Destroy is a soft delete; restore requires a trashed row.
 * - Export uses the same filter and search as the index listing.
 *
 * Side effects: creates, updates, soft-deletes, restores, imports, and exports {@see Country} records.
 */
class CountryService
{
    /**
     * Paginate countries using model filter, search, and include scopes.
     *
     * @return LengthAwarePaginator<int, Country>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return Country::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->withIncludes($request->query('include'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString();
    }

    /**
     * Paginate country select options as label/value pairs.
     *
     * @return LengthAwarePaginator<int, array{label: string, value: int}>
     */
    public function options(Request $request): LengthAwarePaginator
    {
        return Country::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (Country $country): array => [
                'label' => $country->name,
                'value' => $country->id,
            ]);
    }

    /**
     * Load a country with optional allowed relationships.
     */
    public function show(Country $country, Request $request): Country
    {
        return $country->loadAllowedIncludes($request->query('include'));
    }

    /**
     * Create a country.
     *
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Country
    {
        return Country::query()->create($data);
    }

    /**
     * Update a country.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Country $country, array $data): Country
    {
        $country->update($data);

        return $country->refresh();
    }

    /**
     * Soft delete a country.
     */
    public function destroy(Country $country): void
    {
        $country->delete();
    }

    /**
     * Restore a soft-deleted country.
     *
     * @throws HttpException When the country is not trashed (404).
     */
    public function restore(Country $country): Country
    {
        abort_unless($country->trashed(), 404);

        $country->restore();

        return $country->refresh();
    }

    /**
     * Soft delete many countries.
     *
     * @param  list<int>  $ids
     */
    public function destroyMany(array $ids): void
    {
        Country::query()->whereKey($ids)->delete();
    }

    /**
     * Restore many soft-deleted countries.
     *
     * @param  list<int>  $ids
     */
    public function restoreMany(array $ids): void
    {
        Country::onlyTrashed()->whereKey($ids)->restore();
    }

    /**
     * Import countries from a spreadsheet.
     */
    public function import(UploadedFile $file): void
    {
        Excel::import(new CountriesImport, $file);
    }

    /**
     * Download an empty import template with country headings.
     */
    public function template(): BinaryFileResponse
    {
        return Excel::download(new WorldTemplateExport(CountriesExport::columns()), 'countries-template.xlsx');
    }

    /**
     * Export countries using the same filter and search as the index.
     */
    public function export(Request $request): BinaryFileResponse
    {
        return Excel::download(new CountriesExport($request), 'countries.xlsx');
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
