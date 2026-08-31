<?php

declare(strict_types=1);

namespace App\Services\Shared\World;

use App\Exports\Shared\World\StatesExport;
use App\Exports\Shared\World\WorldTemplateExport;
use App\Imports\Shared\World\StatesImport;
use App\Models\Shared\Country;
use App\Models\Shared\State;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Shared state/region reference data for the landlord World API.
 */
class StateService
{
    /**
     * Paginate states using model filter, search, and include scopes.
     *
     * @return LengthAwarePaginator<int, State>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return State::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->withIncludes($request->query('include'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString();
    }

    /**
     * Paginate state select options as label/value pairs.
     *
     * @return LengthAwarePaginator<int, array{label: string, value: int}>
     */
    public function options(Request $request): LengthAwarePaginator
    {
        return State::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (State $state): array => [
                'label' => $state->name,
                'value' => $state->id,
            ]);
    }

    /**
     * Load a state with optional allowed relationships.
     */
    public function show(State $state, Request $request): State
    {
        return $state->loadAllowedIncludes($request->query('include'));
    }

    /**
     * Create a state.
     *
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): State
    {
        return State::query()->create($this->withCountryCode($data));
    }

    /**
     * Update a state.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(State $state, array $data): State
    {
        $state->update($this->withCountryCode($data));

        return $state->refresh();
    }

    /**
     * Soft delete a state.
     */
    public function destroy(State $state): void
    {
        $state->delete();
    }

    /**
     * Restore a soft-deleted state.
     */
    public function restore(State $state): State
    {
        abort_unless($state->trashed(), 404);

        $state->restore();

        return $state->refresh();
    }

    /**
     * Soft delete many states.
     *
     * @param  list<int>  $ids
     */
    public function destroyMany(array $ids): void
    {
        State::query()->whereKey($ids)->delete();
    }

    /**
     * Restore many soft-deleted states.
     *
     * @param  list<int>  $ids
     */
    public function restoreMany(array $ids): void
    {
        State::onlyTrashed()->whereKey($ids)->restore();
    }

    /**
     * Import states from a spreadsheet.
     */
    public function import(UploadedFile $file): void
    {
        Excel::import(new StatesImport, $file);
    }

    /**
     * Download an empty import template with state headings.
     */
    public function template(): BinaryFileResponse
    {
        return Excel::download(new WorldTemplateExport(StatesExport::columns()), 'states-template.xlsx');
    }

    /**
     * Export states using the same filter and search as the index.
     */
    public function export(Request $request): BinaryFileResponse
    {
        return Excel::download(new StatesExport($request), 'states.xlsx');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withCountryCode(array $data): array
    {
        if (! isset($data['country_code']) && isset($data['country_id'])) {
            $data['country_code'] = Country::query()->whereKey($data['country_id'])->value('iso2');
        }

        return $data;
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
