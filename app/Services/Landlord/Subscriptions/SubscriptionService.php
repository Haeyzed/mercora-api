<?php

declare(strict_types=1);

namespace App\Services\Landlord\Subscriptions;

use App\Enums\Landlord\PlanInterval;
use App\Enums\Landlord\SubscriptionStatus;
use App\Models\Landlord\Plan;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\Tenant;
use App\Services\Landlord\Billing\InvoiceService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Landlord tenant subscriptions to catalog plans.
 */
class SubscriptionService
{
    public function __construct(private InvoiceService $invoiceService) {}

    /**
     * Paginate subscriptions using model filter, search, and include scopes.
     *
     * @return LengthAwarePaginator<int, Subscription>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return Subscription::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->withIncludes($request->query('include'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString();
    }

    /**
     * Paginate subscription select options as label/value pairs.
     *
     * @return LengthAwarePaginator<int, array{label: string, value: int}>
     */
    public function options(Request $request): LengthAwarePaginator
    {
        return Subscription::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->with(['tenant', 'plan'])
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (Subscription $subscription): array => [
                'label' => ($subscription->tenant?->name ?? $subscription->tenant_id).' — '.($subscription->plan?->name ?? (string) $subscription->plan_id),
                'value' => $subscription->id,
            ]);
    }

    /**
     * Load a subscription with optional allowed relationships.
     */
    public function show(Subscription $subscription, Request $request): Subscription
    {
        return $subscription->loadAllowedIncludes($request->query('include'));
    }

    /**
     * Subscribe a tenant to a plan and snapshot the catalog terms.
     *
     * @param  array{tenant_id: string, plan_id: int, starts_at?: string|CarbonInterface}  $data
     */
    public function store(array $data): Subscription
    {
        return DB::transaction(function () use ($data): Subscription {
            Tenant::query()->whereKey($data['tenant_id'])->lockForUpdate()->firstOrFail();

            $hasCurrent = Subscription::query()
                ->where('tenant_id', $data['tenant_id'])
                ->current()
                ->exists();

            if ($hasCurrent) {
                throw ValidationException::withMessages([
                    'tenant_id' => 'The tenant already has a current subscription.',
                ]);
            }

            $plan = Plan::query()->findOrFail($data['plan_id']);
            $startsAt = isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : now();

            try {
                return Subscription::query()->create([
                    'tenant_id' => $data['tenant_id'],
                    'is_current' => 1,
                    ...$this->termsFromPlan($plan, $startsAt),
                ])->load(['tenant', 'plan']);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'tenant_id' => 'The tenant already has a current subscription.',
                ]);
            }
        });
    }

    /**
     * Change the plan on a current subscription and re-snapshot terms.
     *
     * @param  array{plan_id?: int}  $data
     */
    public function update(Subscription $subscription, array $data): Subscription
    {
        if ($subscription->status === SubscriptionStatus::Canceled) {
            throw ValidationException::withMessages([
                'status' => 'The subscription is already canceled.',
            ]);
        }

        if (isset($data['plan_id'])) {
            $plan = Plan::query()->findOrFail($data['plan_id']);
            $startsAt = $subscription->starts_at ?? now();

            $subscription->fill($this->termsFromPlan($plan, $startsAt, $subscription->status));
        }

        $subscription->save();

        return $subscription->refresh();
    }

    /**
     * Cancel a current subscription.
     */
    public function cancel(Subscription $subscription): Subscription
    {
        if ($subscription->status === SubscriptionStatus::Canceled) {
            throw ValidationException::withMessages([
                'status' => 'The subscription is already canceled.',
            ]);
        }

        $subscription->update([
            'status' => SubscriptionStatus::Canceled,
            'canceled_at' => now(),
            'is_current' => null,
        ]);

        return $subscription->refresh();
    }

    /**
     * Advance the billing period and issue an invoice for the next period.
     */
    public function renew(Subscription $subscription): Subscription
    {
        return DB::transaction(function () use ($subscription): Subscription {
            $subscription = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            if (in_array($subscription->status, [SubscriptionStatus::Canceled, SubscriptionStatus::Expired], true)) {
                throw ValidationException::withMessages([
                    'status' => 'The subscription cannot be renewed.',
                ]);
            }

            $periodStart = $subscription->ends_at ?? now();
            $periodEnd = $subscription->interval === PlanInterval::Yearly
                ? $periodStart->copy()->addYear()
                : $periodStart->copy()->addMonth();

            $this->invoiceService->issueFor($subscription, $periodStart, $periodEnd);

            $subscription->update([
                'status' => SubscriptionStatus::Active,
                'ends_at' => $periodEnd,
                'is_current' => 1,
            ]);

            return $subscription->refresh()->load(['tenant', 'plan', 'invoices']);
        });
    }

    /**
     * Apply due trial conversions, renewals, and expirations. Idempotent.
     */
    public function processDue(): void
    {
        Subscription::query()
            ->current()
            ->whereNotNull('trial_ends_at')
            ->where('status', SubscriptionStatus::Trialing)
            ->where('trial_ends_at', '<=', now())
            ->where('ends_at', '>', now())
            ->each(function (Subscription $subscription): void {
                $subscription->update(['status' => SubscriptionStatus::Active]);
            });

        Subscription::query()
            ->current()
            ->where('ends_at', '<=', now())
            ->whereIn('status', [SubscriptionStatus::Trialing, SubscriptionStatus::Active])
            ->each(function (Subscription $subscription): void {
                $this->renew($subscription);
            });
    }

    /**
     * Soft delete a subscription.
     */
    public function destroy(Subscription $subscription): void
    {
        $subscription->delete();
    }

    /**
     * Restore a soft-deleted subscription.
     */
    public function restore(Subscription $subscription): Subscription
    {
        abort_unless($subscription->trashed(), 404);

        $subscription->restore();

        return $subscription->refresh();
    }

    /**
     * Soft delete many subscriptions.
     *
     * @param  list<int>  $ids
     */
    public function destroyMany(array $ids): void
    {
        Subscription::query()->whereKey($ids)->delete();
    }

    /**
     * Restore many soft-deleted subscriptions.
     *
     * @param  list<int>  $ids
     */
    public function restoreMany(array $ids): void
    {
        Subscription::onlyTrashed()->whereKey($ids)->restore();
    }

    /**
     * @return array{plan_id: int, price: int, currency: string, interval: PlanInterval, status: SubscriptionStatus, starts_at: CarbonInterface, ends_at: CarbonInterface, trial_ends_at: CarbonInterface|null, canceled_at: null}
     */
    private function termsFromPlan(Plan $plan, CarbonInterface $startsAt, ?SubscriptionStatus $status = null): array
    {
        $trialEndsAt = $plan->trial_days > 0 ? $startsAt->copy()->addDays($plan->trial_days) : null;
        $periodStart = $trialEndsAt ?? $startsAt;
        $endsAt = $plan->interval === PlanInterval::Yearly
            ? $periodStart->copy()->addYear()
            : $periodStart->copy()->addMonth();

        return [
            'plan_id' => $plan->id,
            'price' => $plan->price,
            'currency' => $plan->currency,
            'interval' => $plan->interval,
            'status' => $status ?? ($trialEndsAt instanceof CarbonInterface
                ? SubscriptionStatus::Trialing
                : SubscriptionStatus::Active),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'trial_ends_at' => $trialEndsAt,
            'canceled_at' => null,
        ];
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
