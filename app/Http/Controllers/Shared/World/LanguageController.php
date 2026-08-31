<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared\World;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\World\DestroyManyRequest;
use App\Http\Requests\Shared\World\ImportWorldRequest;
use App\Http\Requests\Shared\World\RestoreManyRequest;
use App\Http\Requests\Shared\World\StoreLanguageRequest;
use App\Http\Requests\Shared\World\UpdateLanguageRequest;
use App\Http\Resources\Shared\World\LanguageResource;
use App\Http\Resources\Shared\World\OptionResource;
use App\Models\Shared\Language;
use App\Services\Shared\World\LanguageService;
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
class LanguageController extends Controller
{
    public function __construct(private LanguageService $languageService) {}

    /**
     * List languages.
     *
     * @return AnonymousResourceCollection<int, LanguageResource>
     */
    #[Endpoint(operationId: 'listSharedWorldLanguages', title: 'List languages')]
    #[QueryParameter('filter[name]', description: 'Partial match on language name.', type: 'string')]
    #[QueryParameter('filter[name_native]', description: 'Partial match on native language name.', type: 'string')]
    #[QueryParameter('filter[code]', description: 'Exact language code.', type: 'string')]
    #[QueryParameter('filter[dir]', description: 'Exact text direction, such as ltr or rtl.', type: 'string')]
    #[QueryParameter('search', description: 'Partial match across name, name_native, and code.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Language::class);

        return LanguageResource::collection($this->languageService->paginate($request));
    }

    /**
     * List language options for selects.
     *
     * @return AnonymousResourceCollection<int, OptionResource>
     */
    #[Endpoint(operationId: 'listSharedWorldLanguageOptions', title: 'List language options')]
    #[QueryParameter('filter[name]', description: 'Partial match on language name.', type: 'string')]
    #[QueryParameter('filter[code]', description: 'Exact language code.', type: 'string')]
    #[QueryParameter('search', description: 'Partial match across name, name_native, and code.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function options(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Language::class);

        return OptionResource::collection($this->languageService->options($request));
    }

    /**
     * Create a language.
     */
    #[Endpoint(operationId: 'storeSharedWorldLanguage', title: 'Create a language')]
    #[Response(201)]
    public function store(StoreLanguageRequest $request): JsonResponse
    {
        $this->authorize('create', Language::class);

        return $this->languageService
            ->store($request->validated())
            ->toResource(LanguageResource::class)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a language.
     */
    #[Endpoint(operationId: 'showSharedWorldLanguage', title: 'Show a language')]
    public function show(Language $language): LanguageResource
    {
        $this->authorize('view', $language);

        return $this->languageService
            ->show($language)
            ->toResource(LanguageResource::class);
    }

    /**
     * Update a language.
     */
    #[Endpoint(operationId: 'updateSharedWorldLanguage', title: 'Update a language')]
    public function update(UpdateLanguageRequest $request, Language $language): LanguageResource
    {
        $this->authorize('update', $language);

        return $this->languageService
            ->update($language, $request->validated())
            ->toResource(LanguageResource::class);
    }

    /**
     * Soft delete a language.
     */
    #[Endpoint(operationId: 'destroySharedWorldLanguage', title: 'Delete a language')]
    public function destroy(Language $language): HttpResponse
    {
        $this->authorize('delete', $language);

        $this->languageService->destroy($language);

        return response()->noContent();
    }

    /**
     * Restore a soft-deleted language.
     */
    #[Endpoint(operationId: 'restoreSharedWorldLanguage', title: 'Restore a language')]
    public function restore(Language $language): LanguageResource
    {
        $this->authorize('restore', $language);

        return $this->languageService
            ->restore($language)
            ->toResource(LanguageResource::class);
    }

    /**
     * Soft delete many languages.
     */
    #[Endpoint(operationId: 'destroyManySharedWorldLanguages', title: 'Delete many languages')]
    public function destroyMany(DestroyManyRequest $request): HttpResponse
    {
        $this->authorize('delete', Language::class);

        $this->languageService->destroyMany($request->ids());

        return response()->noContent();
    }

    /**
     * Restore many soft-deleted languages.
     */
    #[Endpoint(operationId: 'restoreManySharedWorldLanguages', title: 'Restore many languages')]
    public function restoreMany(RestoreManyRequest $request): HttpResponse
    {
        $this->authorize('restore', Language::class);

        $this->languageService->restoreMany($request->ids());

        return response()->noContent();
    }

    /**
     * Import languages from a spreadsheet.
     */
    #[Endpoint(operationId: 'importSharedWorldLanguages', title: 'Import languages')]
    public function import(ImportWorldRequest $request): HttpResponse
    {
        $this->authorize('create', Language::class);

        $this->languageService->import($request->uploadedFile());

        return response()->noContent();
    }

    /**
     * Download a language import template.
     */
    #[Endpoint(operationId: 'templateSharedWorldLanguages', title: 'Download language import template')]
    public function template(): BinaryFileResponse
    {
        $this->authorize('viewAny', Language::class);

        return $this->languageService->template();
    }

    /**
     * Export languages to a spreadsheet.
     */
    #[Endpoint(operationId: 'exportSharedWorldLanguages', title: 'Export languages')]
    #[QueryParameter('filter[name]', description: 'Partial match on language name.', type: 'string')]
    #[QueryParameter('filter[name_native]', description: 'Partial match on native language name.', type: 'string')]
    #[QueryParameter('filter[code]', description: 'Exact language code.', type: 'string')]
    #[QueryParameter('filter[dir]', description: 'Exact text direction, such as ltr or rtl.', type: 'string')]
    #[QueryParameter('search', description: 'Partial match across name, name_native, and code.', type: 'string')]
    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', Language::class);

        return $this->languageService->export($request);
    }
}
