<?php

declare(strict_types=1);

namespace App\Services\Landlord\Plans;

use App\Models\Landlord\Feature;
use App\Services\Concerns\PaginatesRequests;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Manages the landlord entitlement feature catalog.
 *
 * Domain: reusable capability definitions attached to plans through plan_features.
 *
 * Invariants:
 * - Feature keys are unique across soft-deleted rows.
 * - Entitlements are resolved through plan assignments, not feature names alone.
 *
 * Side effects: creates, updates, soft-deletes, and restores {@see Feature} records.
 */
class FeatureService
{
    use PaginatesRequests;

    /**
     * Paginate features using model filter and search scopes.
     *
     * @return LengthAwarePaginator<int, Feature>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return Feature::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString();
    }

    /**
     * Paginate feature select options as label/value pairs.
     *
     * @return LengthAwarePaginator<int, array{label: string, value: int}>
     */
    public function options(Request $request): LengthAwarePaginator
    {
        return Feature::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (Feature $feature): array => [
                'label' => $feature->name,
                'value' => $feature->id,
            ]);
    }

    /**
     * Load a feature.
     */
    public function show(Feature $feature): Feature
    {
        return $feature;
    }

    /**
     * Create a catalog feature.
     *
     * @param  array{key: string, name: string, description?: string|null, type: string, is_active?: bool}  $data
     */
    public function store(array $data): Feature
    {
        return Feature::query()->create($data);
    }

    /**
     * Update a catalog feature.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Feature $feature, array $data): Feature
    {
        $feature->update($data);

        return $feature->refresh();
    }

    /**
     * Soft delete a feature.
     */
    public function destroy(Feature $feature): void
    {
        $feature->delete();
    }

    /**
     * Restore a soft-deleted feature.
     *
     * @throws HttpException When the feature is not trashed (404).
     */
    public function restore(Feature $feature): Feature
    {
        abort_unless($feature->trashed(), 404);

        $feature->restore();

        return $feature->refresh();
    }

    /**
     * Soft delete many features.
     *
     * @param  list<int>  $ids
     */
    public function destroyMany(array $ids): void
    {
        Feature::query()->whereKey($ids)->delete();
    }

    /**
     * Restore many soft-deleted features.
     *
     * @param  list<int>  $ids
     */
    public function restoreMany(array $ids): void
    {
        Feature::onlyTrashed()->whereKey($ids)->restore();
    }
}
