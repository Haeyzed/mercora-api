<?php

declare(strict_types=1);

namespace App\Services\Landlord\Plans;

use App\Enums\Landlord\PlanInterval;
use App\Enums\Landlord\PlanStatus;
use App\Models\Landlord\Plan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Manages the landlord subscription plan catalog.
 *
 * Domain: sellable plans with pricing, intervals, and marketing feature highlights.
 *
 * Invariants:
 * - Plans are soft-deletable; restore requires a trashed row.
 * - Plan mutations do not automatically change existing subscription snapshots.
 *
 * Side effects: creates, updates, soft-deletes, and restores {@see Plan} records.
 */
class PlanService
{
    /**
     * Paginate plans using model filter, search, and ordered scopes.
     *
     * @return LengthAwarePaginator<int, Plan>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return Plan::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->withIncludes($request->query('include'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString();
    }

    /**
     * Load a plan with optional allowed relationships.
     */
    public function show(Plan $plan, Request $request): Plan
    {
        return $plan->loadAllowedIncludes($request->query('include'));
    }

    /**
     * Paginate plan select options as label/value pairs.
     *
     * @return LengthAwarePaginator<int, array{label: string, value: int}>
     */
    public function options(Request $request): LengthAwarePaginator
    {
        return Plan::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (Plan $plan): array => [
                'label' => $plan->name,
                'value' => $plan->id,
            ]);
    }

    /**
     * Create a plan.
     *
     * @param  array{name: string, price: int, currency: string, interval: PlanInterval|string, description?: string|null, trial_days?: int, status?: PlanStatus|string, feature_highlights?: list<string>|null}  $data
     */
    public function store(array $data): Plan
    {
        return Plan::query()->create($data);
    }

    /**
     * Update a plan.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Plan $plan, array $data): Plan
    {
        $plan->update($data);

        return $plan->refresh();
    }

    /**
     * Soft delete a plan.
     */
    public function destroy(Plan $plan): void
    {
        $plan->delete();
    }

    /**
     * Restore a soft-deleted plan.
     *
     * @throws HttpException When the plan is not trashed (404).
     */
    public function restore(Plan $plan): Plan
    {
        abort_unless($plan->trashed(), 404);

        $plan->restore();

        return $plan->refresh();
    }

    /**
     * Soft delete many plans.
     *
     * @param  list<int>  $ids
     */
    public function destroyMany(array $ids): void
    {
        Plan::query()->whereKey($ids)->delete();
    }

    /**
     * Restore many soft-deleted plans.
     *
     * @param  list<int>  $ids
     */
    public function restoreMany(array $ids): void
    {
        Plan::onlyTrashed()->whereKey($ids)->restore();
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
