<?php

declare(strict_types=1);

namespace App\Services\Landlord\Plans;

use App\Models\Landlord\Plan;
use App\Models\Landlord\PlanPrice;
use App\Services\Concerns\PaginatesRequests;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Manages currency-specific prices for catalog plans.
 *
 * Domain: billable price variants attached to a {@see Plan}.
 *
 * Invariants:
 * - Prices belong to exactly one plan.
 * - Financial fields (amount, currency, interval, interval_count) are immutable once referenced by a subscription.
 * - Deactivation is preferred over editing prices that are already in use.
 *
 * Side effects: creates, updates, activates, deactivates, and soft-deletes {@see PlanPrice} records.
 */
class PlanPriceService
{
    use PaginatesRequests;

    /**
     * Paginate prices for a plan.
     *
     * @return LengthAwarePaginator<int, PlanPrice>
     */
    public function paginate(Plan $plan, Request $request): LengthAwarePaginator
    {
        return $plan->prices()
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString();
    }

    /**
     * Load a plan price that belongs to the given plan.
     *
     * @throws ValidationException When the price does not belong to the plan.
     */
    public function show(Plan $plan, PlanPrice $planPrice): PlanPrice
    {
        $this->ensureBelongsToPlan($plan, $planPrice);

        return $planPrice;
    }

    /**
     * Create a price for a plan.
     *
     * @param  array{currency: string, amount: int, interval: string, interval_count?: int, trial_days?: int, is_active?: bool}  $data
     */
    public function store(Plan $plan, array $data): PlanPrice
    {
        return $plan->prices()->create([
            'currency' => $data['currency'],
            'amount' => $data['amount'],
            'interval' => $data['interval'],
            'interval_count' => $data['interval_count'] ?? 1,
            'trial_days' => $data['trial_days'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Update a plan price. Financial fields are blocked when subscriptions reference the price.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException When financial fields change on a price in use.
     */
    public function update(Plan $plan, PlanPrice $planPrice, array $data): PlanPrice
    {
        $this->ensureBelongsToPlan($plan, $planPrice);
        $this->ensureMutableFinancials($planPrice, $data);

        unset($data['is_active']);

        $planPrice->update($data);

        return $planPrice->refresh();
    }

    /**
     * Mark a plan price as active.
     */
    public function activate(Plan $plan, PlanPrice $planPrice): PlanPrice
    {
        $this->ensureBelongsToPlan($plan, $planPrice);

        $planPrice->update(['is_active' => true]);

        return $planPrice->refresh();
    }

    /**
     * Mark a plan price as inactive without deleting historical billing references.
     */
    public function deactivate(Plan $plan, PlanPrice $planPrice): PlanPrice
    {
        $this->ensureBelongsToPlan($plan, $planPrice);

        $planPrice->update(['is_active' => false]);

        return $planPrice->refresh();
    }

    /**
     * Soft delete a plan price that has never been used by a subscription.
     *
     * @throws ValidationException When subscriptions reference the price.
     */
    public function destroy(Plan $plan, PlanPrice $planPrice): void
    {
        $this->ensureBelongsToPlan($plan, $planPrice);

        if ($planPrice->subscriptions()->exists()) {
            throw ValidationException::withMessages([
                'plan_price_id' => 'A price that has been used by subscriptions cannot be deleted. Deactivate it instead.',
            ]);
        }

        $planPrice->delete();
    }

    /**
     * @throws ValidationException When the price does not belong to the plan.
     */
    private function ensureBelongsToPlan(Plan $plan, PlanPrice $planPrice): void
    {
        if ((int) $planPrice->plan_id === (int) $plan->id) {
            return;
        }

        throw ValidationException::withMessages([
            'plan_price_id' => 'The price does not belong to this plan.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException When financial fields change on a price in use.
     */
    private function ensureMutableFinancials(PlanPrice $planPrice, array $data): void
    {
        if (! $planPrice->subscriptions()->exists()) {
            return;
        }

        $immutable = ['currency', 'amount', 'interval', 'interval_count'];

        foreach ($immutable as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $incoming = $data[$field];
            $current = $planPrice->getAttribute($field);

            if ($incoming instanceof \BackedEnum) {
                $incoming = $incoming->value;
            }

            if ($current instanceof \BackedEnum) {
                $current = $current->value;
            }

            if ($incoming != $current) {
                throw ValidationException::withMessages([
                    $field => 'This price has been used by subscriptions and its billing terms cannot be changed. Deactivate it and create a new price instead.',
                ]);
            }
        }
    }
}
