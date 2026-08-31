<?php

declare(strict_types=1);

namespace App\Services\Shared\World;

use App\Exports\Shared\World\CitiesExport;
use App\Exports\Shared\World\WorldTemplateExport;
use App\Imports\Shared\World\CitiesImport;
use App\Models\Shared\City;
use App\Models\Shared\Country;
use App\Models\Shared\State;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Shared city reference data for the landlord World API.
 */
class CityService
{
    /**
     * Paginate cities using model filter, search, and include scopes.
     *
     * @return LengthAwarePaginator<int, City>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return City::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->withIncludes($request->query('include'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString();
    }

    /**
     * Paginate city select options as label/value pairs.
     *
     * @return LengthAwarePaginator<int, array{label: string, value: int}>
     */
    public function options(Request $request): LengthAwarePaginator
    {
        return City::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (City $city): array => [
                'label' => $city->name,
                'value' => $city->id,
            ]);
    }

    /**
     * Load a city with optional allowed relationships.
     */
    public function show(City $city, Request $request): City
    {
        return $city->loadAllowedIncludes($request->query('include'));
    }

    /**
     * Create a city.
     *
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): City
    {
        return City::query()->create($this->withLocationCodes($data));
    }

    /**
     * Update a city.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(City $city, array $data): City
    {
        $city->update($this->withLocationCodes($data));

        return $city->refresh();
    }

    /**
     * Soft delete a city.
     */
    public function destroy(City $city): void
    {
        $city->delete();
    }

    /**
     * Restore a soft-deleted city.
     */
    public function restore(City $city): City
    {
        abort_unless($city->trashed(), 404);

        $city->restore();

        return $city->refresh();
    }

    /**
     * Soft delete many cities.
     *
     * @param  list<int>  $ids
     */
    public function destroyMany(array $ids): void
    {
        City::query()->whereKey($ids)->delete();
    }

    /**
     * Restore many soft-deleted cities.
     *
     * @param  list<int>  $ids
     */
    public function restoreMany(array $ids): void
    {
        City::onlyTrashed()->whereKey($ids)->restore();
    }

    /**
     * Import cities from a spreadsheet.
     */
    public function import(UploadedFile $file): void
    {
        Excel::import(new CitiesImport, $file);
    }

    /**
     * Download an empty import template with city headings.
     */
    public function template(): BinaryFileResponse
    {
        return Excel::download(new WorldTemplateExport(CitiesExport::columns()), 'cities-template.xlsx');
    }

    /**
     * Export cities using the same filter and search as the index.
     */
    public function export(Request $request): BinaryFileResponse
    {
        return Excel::download(new CitiesExport($request), 'cities.xlsx');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withLocationCodes(array $data): array
    {
        if (! isset($data['country_code']) && isset($data['country_id'])) {
            $data['country_code'] = Country::query()->whereKey($data['country_id'])->value('iso2');
        }

        if (! isset($data['state_code']) && isset($data['state_id'])) {
            $data['state_code'] = State::query()->whereKey($data['state_id'])->value('state_code');
        }

        return $data;
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
