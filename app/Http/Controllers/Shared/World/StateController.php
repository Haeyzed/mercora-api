<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared\World;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shared\World\DestroyManyRequest;
use App\Http\Requests\Shared\World\ImportWorldRequest;
use App\Http\Requests\Shared\World\RestoreManyRequest;
use App\Http\Requests\Shared\World\StoreStateRequest;
use App\Http\Requests\Shared\World\UpdateStateRequest;
use App\Http\Resources\Shared\World\OptionResource;
use App\Http\Resources\Shared\World\StateResource;
use App\Models\Shared\State;
use App\Services\Shared\World\StateService;
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
class StateController extends Controller
{
    public function __construct(private StateService $stateService) {}

    /**
     * List states.
     *
     * @return AnonymousResourceCollection<int, StateResource>
     */
    #[Endpoint(operationId: 'listSharedWorldStates', title: 'List states')]
    #[QueryParameter('filter[name]', description: 'Partial match on state name.', type: 'string')]
    #[QueryParameter('filter[country_id]', description: 'Exact country id.', type: 'int')]
    #[QueryParameter('filter[country_code]', description: 'Exact country ISO code stored on the state.', type: 'string')]
    #[QueryParameter('filter[state_code]', description: 'Exact state code.', type: 'string')]
    #[QueryParameter('search', description: 'Partial match across name, country_code, and state_code.', type: 'string')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: country, cities.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', State::class);

        return StateResource::collection($this->stateService->paginate($request));
    }

    /**
     * List state options for selects.
     *
     * @return AnonymousResourceCollection<int, OptionResource>
     */
    #[Endpoint(operationId: 'listSharedWorldStateOptions', title: 'List state options')]
    #[QueryParameter('filter[name]', description: 'Partial match on state name.', type: 'string')]
    #[QueryParameter('filter[country_id]', description: 'Exact country id.', type: 'int')]
    #[QueryParameter('filter[country_code]', description: 'Exact country ISO code stored on the state.', type: 'string')]
    #[QueryParameter('search', description: 'Partial match across name, country_code, and state_code.', type: 'string')]
    #[QueryParameter('page', description: 'Page number.', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page. Maximum 100.', type: 'int')]
    public function options(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', State::class);

        return OptionResource::collection($this->stateService->options($request));
    }

    /**
     * Create a state.
     */
    #[Endpoint(operationId: 'storeSharedWorldState', title: 'Create a state')]
    #[Response(201)]
    public function store(StoreStateRequest $request): JsonResponse
    {
        $this->authorize('create', State::class);

        return $this->stateService
            ->store($request->validated())
            ->toResource(StateResource::class)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Show a state.
     */
    #[Endpoint(operationId: 'showSharedWorldState', title: 'Show a state')]
    #[QueryParameter('include', description: 'Comma-separated relationships. Allowed: country, cities.', type: 'string')]
    public function show(Request $request, State $state): StateResource
    {
        $this->authorize('view', $state);

        return $this->stateService
            ->show($state, $request)
            ->toResource(StateResource::class);
    }

    /**
     * Update a state.
     */
    #[Endpoint(operationId: 'updateSharedWorldState', title: 'Update a state')]
    public function update(UpdateStateRequest $request, State $state): StateResource
    {
        $this->authorize('update', $state);

        return $this->stateService
            ->update($state, $request->validated())
            ->toResource(StateResource::class);
    }

    /**
     * Soft delete a state.
     */
    #[Endpoint(operationId: 'destroySharedWorldState', title: 'Delete a state')]
    public function destroy(State $state): HttpResponse
    {
        $this->authorize('delete', $state);

        $this->stateService->destroy($state);

        return response()->noContent();
    }

    /**
     * Restore a soft-deleted state.
     */
    #[Endpoint(operationId: 'restoreSharedWorldState', title: 'Restore a state')]
    public function restore(State $state): StateResource
    {
        $this->authorize('restore', $state);

        return $this->stateService
            ->restore($state)
            ->toResource(StateResource::class);
    }

    /**
     * Soft delete many states.
     */
    #[Endpoint(operationId: 'destroyManySharedWorldStates', title: 'Delete many states')]
    public function destroyMany(DestroyManyRequest $request): HttpResponse
    {
        $this->authorize('delete', State::class);

        $this->stateService->destroyMany($request->ids());

        return response()->noContent();
    }

    /**
     * Restore many soft-deleted states.
     */
    #[Endpoint(operationId: 'restoreManySharedWorldStates', title: 'Restore many states')]
    public function restoreMany(RestoreManyRequest $request): HttpResponse
    {
        $this->authorize('restore', State::class);

        $this->stateService->restoreMany($request->ids());

        return response()->noContent();
    }

    /**
     * Import states from a spreadsheet.
     */
    #[Endpoint(operationId: 'importSharedWorldStates', title: 'Import states')]
    public function import(ImportWorldRequest $request): HttpResponse
    {
        $this->authorize('create', State::class);

        $this->stateService->import($request->uploadedFile());

        return response()->noContent();
    }

    /**
     * Download a state import template.
     */
    #[Endpoint(operationId: 'templateSharedWorldStates', title: 'Download state import template')]
    public function template(): BinaryFileResponse
    {
        $this->authorize('viewAny', State::class);

        return $this->stateService->template();
    }

    /**
     * Export states to a spreadsheet.
     */
    #[Endpoint(operationId: 'exportSharedWorldStates', title: 'Export states')]
    #[QueryParameter('filter[name]', description: 'Partial match on state name.', type: 'string')]
    #[QueryParameter('filter[country_id]', description: 'Exact country id.', type: 'int')]
    #[QueryParameter('filter[country_code]', description: 'Exact country ISO code stored on the state.', type: 'string')]
    #[QueryParameter('filter[state_code]', description: 'Exact state code.', type: 'string')]
    #[QueryParameter('search', description: 'Partial match across name, country_code, and state_code.', type: 'string')]
    public function export(Request $request): BinaryFileResponse
    {
        $this->authorize('viewAny', State::class);

        return $this->stateService->export($request);
    }
}
