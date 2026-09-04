<?php

declare(strict_types=1);

namespace App\Services\Shared\World;

use App\Exports\Shared\World\TimezonesExport;
use App\Exports\Shared\World\WorldTemplateExport;
use App\Imports\Shared\World\TimezonesImport;
use App\Models\Shared\Timezone;
use App\Services\Concerns\PaginatesRequests;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Manages shared IANA timezone reference data for the landlord World API.
 *
 * Domain: central-database timezone catalog (nnjeim/world); not duplicated in tenant databases.
 *
 * Invariants:
 * - Destroy is a soft delete; restore requires a trashed row.
 * - Export uses the same filter and search as the index listing.
 *
 * Side effects: creates, updates, soft-deletes, restores, imports, and exports {@see Timezone} records.
 */
class TimezoneService
{
    use PaginatesRequests;

    /**
     * Paginate timezones using model filter, search, and include scopes.
     *
     * @return LengthAwarePaginator<int, Timezone>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return Timezone::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->withIncludes($request->query('include'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString();
    }

    /**
     * Paginate timezone select options as label/value pairs.
     *
     * @return LengthAwarePaginator<int, array{label: string, value: int}>
     */
    public function options(Request $request): LengthAwarePaginator
    {
        return Timezone::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (Timezone $timezone): array => [
                'label' => $timezone->name,
                'value' => $timezone->id,
            ]);
    }

    /**
     * Load a timezone with optional allowed relationships.
     */
    public function show(Timezone $timezone, Request $request): Timezone
    {
        return $timezone->loadAllowedIncludes($request->query('include'));
    }

    /**
     * Create a timezone.
     *
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Timezone
    {
        return Timezone::query()->create($data);
    }

    /**
     * Update a timezone.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Timezone $timezone, array $data): Timezone
    {
        $timezone->update($data);

        return $timezone->refresh();
    }

    /**
     * Soft delete a timezone.
     */
    public function destroy(Timezone $timezone): void
    {
        $timezone->delete();
    }

    /**
     * Restore a soft-deleted timezone.
     *
     * @throws HttpException When the timezone is not trashed (404).
     */
    public function restore(Timezone $timezone): Timezone
    {
        abort_unless($timezone->trashed(), 404);

        $timezone->restore();

        return $timezone->refresh();
    }

    /**
     * Soft delete many timezones.
     *
     * @param  list<int>  $ids
     */
    public function destroyMany(array $ids): void
    {
        Timezone::query()->whereKey($ids)->delete();
    }

    /**
     * Restore many soft-deleted timezones.
     *
     * @param  list<int>  $ids
     */
    public function restoreMany(array $ids): void
    {
        Timezone::onlyTrashed()->whereKey($ids)->restore();
    }

    /**
     * Import timezones from a spreadsheet.
     */
    public function import(UploadedFile $file): void
    {
        Excel::import(new TimezonesImport, $file);
    }

    /**
     * Download an empty import template with timezone headings.
     */
    public function template(): BinaryFileResponse
    {
        return Excel::download(new WorldTemplateExport(TimezonesExport::columns()), 'timezones-template.xlsx');
    }

    /**
     * Export timezones using the same filter and search as the index.
     */
    public function export(Request $request): BinaryFileResponse
    {
        return Excel::download(new TimezonesExport($request), 'timezones.xlsx');
    }
}
