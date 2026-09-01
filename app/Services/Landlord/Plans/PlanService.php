<?php

declare(strict_types=1);

namespace App\Services\Landlord\Plans;

use App\Models\Landlord\Plan;
use App\Models\Landlord\Subscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Manages the landlord subscription plan catalog.
 *
 * Domain: sellable plans with marketing metadata; billing amounts live on {@see PlanPrice}.
 *
 * Invariants:
 * - Plans are soft-deletable; restore requires a trashed row.
 * - Plan mutations do not automatically change existing subscription snapshots.
 *
 * Side effects: creates, updates, soft-deletes, and restores {@see Plan} records.
 */
class PlanService
{
    public function __construct(private EntitlementService $entitlementService) {}

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
            ->with('primaryPrice')
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString();
    }

    /**
     * Load a plan with optional allowed relationships.
     */
    public function show(Plan $plan, Request $request): Plan
    {
        return $plan
            ->loadAllowedIncludes($request->query('include'))
            ->loadMissing('primaryPrice');
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
     * Create a plan and its initial active price.
     *
     * @param  array{name: string, price: array{amount: int, currency: string, interval: string, interval_count?: int, trial_days?: int}, description?: string|null, status?: string, feature_highlights?: list<string>|null}  $data
     */
    public function store(array $data): Plan
    {
        return DB::transaction(function () use ($data): Plan {
            $priceData = $data['price'];
            unset($data['price']);

            $plan = Plan::query()->create($data);

            $plan->prices()->create([
                'currency' => $priceData['currency'],
                'amount' => $priceData['amount'],
                'interval' => $priceData['interval'],
                'interval_count' => $priceData['interval_count'] ?? 1,
                'trial_days' => $priceData['trial_days'] ?? 0,
                'is_active' => true,
            ]);

            return $plan->load('primaryPrice');
        });
    }

    /**
     * Update catalog fields on a plan. Billing terms are managed via plan prices.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Plan $plan, array $data): Plan
    {
        $plan->update($data);

        return $plan->refresh()->load('primaryPrice');
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

        return $plan->refresh()->load('primaryPrice');
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

    /**
     * Replace entitlement features attached to a plan.
     *
     * @param  list<array{feature_id: int, value: mixed}>  $features
     */
    public function syncFeatures(Plan $plan, array $features): Plan
    {
        $sync = [];

        foreach ($features as $feature) {
            $sync[(int) $feature['feature_id']] = ['value' => $feature['value']];
        }

        $plan->features()->sync($sync);

        Subscription::query()
            ->where('plan_id', $plan->id)
            ->current()
            ->with('tenant')
            ->get()
            ->each(function (Subscription $subscription): void {
                if ($subscription->tenant !== null) {
                    $this->entitlementService->forget($subscription->tenant);
                }
            });

        return $plan->load('features');
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
