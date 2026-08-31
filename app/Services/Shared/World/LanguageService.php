<?php

declare(strict_types=1);

namespace App\Services\Shared\World;

use App\Exports\Shared\World\LanguagesExport;
use App\Exports\Shared\World\WorldTemplateExport;
use App\Imports\Shared\World\LanguagesImport;
use App\Models\Shared\Language;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Manages shared language reference data for the landlord World API.
 *
 * Domain: central-database language catalog (nnjeim/world); not duplicated in tenant databases.
 *
 * Invariants:
 * - Destroy is a soft delete; restore requires a trashed row.
 * - Export uses the same filter and search as the index listing.
 *
 * Side effects: creates, updates, soft-deletes, restores, imports, and exports {@see Language} records.
 */
class LanguageService
{
    /**
     * Paginate languages using model filter and search scopes.
     *
     * @return LengthAwarePaginator<int, Language>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return Language::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString();
    }

    /**
     * Paginate language select options as label/value pairs.
     *
     * @return LengthAwarePaginator<int, array{label: string, value: int}>
     */
    public function options(Request $request): LengthAwarePaginator
    {
        return Language::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (Language $language): array => [
                'label' => $language->name,
                'value' => $language->id,
            ]);
    }

    /**
     * Return a language.
     */
    public function show(Language $language): Language
    {
        return $language;
    }

    /**
     * Create a language.
     *
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Language
    {
        return Language::query()->create($data);
    }

    /**
     * Update a language.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Language $language, array $data): Language
    {
        $language->update($data);

        return $language->refresh();
    }

    /**
     * Soft delete a language.
     */
    public function destroy(Language $language): void
    {
        $language->delete();
    }

    /**
     * Restore a soft-deleted language.
     *
     * @throws HttpException When the language is not trashed (404).
     */
    public function restore(Language $language): Language
    {
        abort_unless($language->trashed(), 404);

        $language->restore();

        return $language->refresh();
    }

    /**
     * Soft delete many languages.
     *
     * @param  list<int>  $ids
     */
    public function destroyMany(array $ids): void
    {
        Language::query()->whereKey($ids)->delete();
    }

    /**
     * Restore many soft-deleted languages.
     *
     * @param  list<int>  $ids
     */
    public function restoreMany(array $ids): void
    {
        Language::onlyTrashed()->whereKey($ids)->restore();
    }

    /**
     * Import languages from a spreadsheet.
     */
    public function import(UploadedFile $file): void
    {
        Excel::import(new LanguagesImport, $file);
    }

    /**
     * Download an empty import template with language headings.
     */
    public function template(): BinaryFileResponse
    {
        return Excel::download(new WorldTemplateExport(LanguagesExport::columns()), 'languages-template.xlsx');
    }

    /**
     * Export languages using the same filter and search as the index.
     */
    public function export(Request $request): BinaryFileResponse
    {
        return Excel::download(new LanguagesExport($request), 'languages.xlsx');
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
