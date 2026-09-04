<?php

declare(strict_types=1);

namespace App\Services\Landlord;

use App\Enums\Landlord\InvoiceStatus;
use App\Enums\Landlord\PaymentStatus;
use App\Enums\Landlord\PlanInterval;
use App\Enums\Landlord\PlanStatus;
use App\Enums\Landlord\SubscriptionStatus;
use App\Models\Landlord\Invoice;
use App\Models\Landlord\Payment;
use App\Models\Landlord\Plan;
use App\Models\Landlord\PlanPrice;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\Tenant;
use App\Services\Concerns\PaginatesRequests;
use App\Services\Landlord\Plans\EntitlementService;
use App\Services\Landlord\Tenants\TenantService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Manages tenant subscriptions to catalog plans and their billing lifecycle.
 *
 * Domain: landlord subscription records with snapshotted plan terms.
 *
 * Invariants:
 * - A tenant may have at most one current subscription.
 * - Terms (price, currency, interval) are snapshotted from an active {@see PlanPrice} at create/change time.
 * - Non-trialing subscriptions issue an invoice on creation; renewals issue invoices without auto-payment.
 * - Immediate cancel clears {@see Subscription::$is_current}; period-end cancel keeps access until {@see Subscription::$ends_at}.
 * - {@see renewAfterPayment()} advances the period only when the invoice period end exceeds the current end.
 * - Successful payment renewals reactivate suspended tenants and invalidate entitlement cache.
 * - Immediate / finalized cancel voids open invoices and abandons pending payments.
 *
 * Side effects: creates, updates, cancels, and soft-deletes {@see Subscription} records;
 * delegates invoice issuance to {@see InvoiceService}; may suspend or reactivate tenants;
 * invalidates {@see EntitlementService} cache; reads {@see SettingService} for subscription policy.
 */
class SubscriptionService
{
    use PaginatesRequests;

    public function __construct(
        private InvoiceService $invoiceService,
        private SettingService $settings,
        private TenantService $tenants,
        private EntitlementService $entitlements,
    ) {}

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
     * Load a subscription with optional allowed relationships.
     */
    public function show(Subscription $subscription, Request $request): Subscription
    {
        return $subscription->loadAllowedIncludes($request->query('include'));
    }

    /**
     * Create a subscription for a tenant and plan.
     *
     * Locks the tenant row and issues an initial invoice when not trialing.
     *
     * @param  array{tenant_id: string, plan_id: int, plan_price_id?: int, starts_at?: string|CarbonInterface}  $data
     *
     * @throws ModelNotFoundException When the tenant or plan does not exist.
     * @throws ValidationException|Throwable When the tenant already has a current subscription or no active price exists.
     */
    public function store(array $data): Subscription
    {
        return DB::transaction(function () use ($data): Subscription {
            Tenant::query()->whereKey($data['tenant_id'])->lockForUpdate()->firstOrFail();

            if (Subscription::query()->where('tenant_id', $data['tenant_id'])->current()->exists()) {
                throw ValidationException::withMessages([
                    'tenant_id' => 'The tenant already has a current subscription.',
                ]);
            }

            $plan = Plan::query()->where('status', PlanStatus::Active)->findOrFail($data['plan_id']);
            $planPrice = $this->resolvePlanPrice($plan, $data['plan_price_id'] ?? null);
            $startsAt = isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : now();

            try {
                $subscription = Subscription::query()->create([
                    'tenant_id' => $data['tenant_id'],
                    'is_current' => 1,
                    ...$this->termsFromPlanPrice($plan, $planPrice, $startsAt),
                ])->load(['tenant', 'plan', 'planPrice']);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages([
                    'tenant_id' => 'The tenant already has a current subscription.',
                ]);
            }

            if ($subscription->status !== SubscriptionStatus::Trialing) {
                $this->invoiceService->issueFor(
                    $subscription,
                    $subscription->starts_at ?? now(),
                    $subscription->ends_at,
                );
            }

            return $subscription;
        });
    }

    /**
     * Change the plan on a current subscription and re-snapshot terms.
     *
     * Preserves the current billing period dates. When prorate_plan_changes is enabled and
     * the plan price changed, issues an open invoice for the remaining-period fraction of the new price.
     *
     * @param  array{plan_id?: int, plan_price_id?: int}  $data
     *
     * @throws ModelNotFoundException When the plan or price does not exist.
     * @throws ValidationException When plan changes are disabled, the subscription is canceled, or no active price exists.
     */
    public function changePlan(Subscription $subscription, array $data): Subscription
    {
        if (! $this->settings->value('subscriptions.allow_plan_changes', true)) {
            throw ValidationException::withMessages([
                'plan_id' => ['Plan changes are disabled.'],
            ]);
        }

        if ($subscription->status === SubscriptionStatus::Canceled) {
            throw ValidationException::withMessages([
                'status' => 'The subscription is already canceled.',
            ]);
        }

        if ($subscription->canceled_at !== null) {
            throw ValidationException::withMessages([
                'status' => 'The subscription is scheduled for cancellation.',
            ]);
        }

        if (! isset($data['plan_id']) && ! isset($data['plan_price_id'])) {
            return $subscription->refresh();
        }

        $plan = Plan::query()->findOrFail($data['plan_id'] ?? $subscription->plan_id);

        $planPriceId = $data['plan_price_id'] ?? null;

        if ($planPriceId === null && isset($data['plan_id']) && (int) $data['plan_id'] !== (int) $subscription->plan_id) {
            $planPriceId = null;
        } else {
            $planPriceId ??= $subscription->plan_price_id;
        }

        $planPrice = $this->resolvePlanPrice($plan, $planPriceId);

        $termsChanged = (int) $subscription->plan_id !== (int) $plan->id
            || (int) $subscription->plan_price_id !== (int) $planPrice->id
            || (int) $subscription->price !== (int) $planPrice->amount;

        $periodStartsAt = $subscription->starts_at;
        $periodEndsAt = $subscription->ends_at;
        $trialEndsAt = $subscription->trial_ends_at;
        $status = $subscription->status;

        $subscription->fill($this->termsFromPlanPrice($plan, $planPrice, $periodStartsAt ?? now(), $status));
        $subscription->starts_at = $periodStartsAt;
        $subscription->ends_at = $periodEndsAt;
        $subscription->trial_ends_at = $trialEndsAt;
        $subscription->status = $status;

        $prorate = (bool) $this->settings->value('subscriptions.prorate_plan_changes', false);
        $prorationAmount = 0;

        if ($prorate && $termsChanged) {
            $prorationAmount = $this->proratedAmount($subscription, (int) $planPrice->amount);

            if ($prorationAmount > 0) {
                $subscription->status = SubscriptionStatus::PendingPayment;
            }
        }

        $subscription->save();

        if ($prorationAmount > 0) {
            $this->invoiceService->issueFor(
                $subscription->refresh(),
                now(),
                $subscription->ends_at,
                amount: $prorationAmount,
            );
        }

        $tenant = $subscription->tenant;

        if ($tenant instanceof Tenant) {
            $this->entitlements->forget($tenant);
        }

        return $subscription->refresh();
    }

    /**
     * Cancel a current subscription immediately or at period end per settings.
     *
     * @throws ValidationException When the subscription is already canceled or immediate cancel is not allowed.
     */
    public function cancel(Subscription $subscription): Subscription
    {
        if ($subscription->status === SubscriptionStatus::Canceled || $subscription->canceled_at !== null) {
            throw ValidationException::withMessages([
                'status' => 'The subscription is already canceled.',
            ]);
        }

        $cancelAtPeriodEnd = (bool) $this->settings->value('subscriptions.cancel_at_period_end', true);
        $allowImmediate = (bool) $this->settings->value('subscriptions.allow_immediate_cancel', false);

        if ($cancelAtPeriodEnd) {
            $subscription->update([
                'canceled_at' => now(),
            ]);

            return $subscription->refresh();
        }

        if (! $allowImmediate) {
            throw ValidationException::withMessages([
                'status' => ['Immediate cancellation is disabled.'],
            ]);
        }

        return $this->cancelImmediately($subscription);
    }

    /**
     * Issue a renewal invoice without advancing the subscription period.
     *
     * Sets status to pending payment when renewal is allowed.
     *
     * @throws ValidationException When the subscription cannot be renewed.
     */
    public function requestRenewal(Subscription $subscription): Subscription
    {
        if ($subscription->canceled_at !== null) {
            throw ValidationException::withMessages([
                'status' => 'The subscription is scheduled for cancellation.',
            ]);
        }

        if (! in_array($subscription->status, SubscriptionStatus::renewableCases(), true)) {
            throw ValidationException::withMessages([
                'status' => 'The subscription cannot be renewed.',
            ]);
        }

        $periodStart = $subscription->ends_at ?? now();
        $periodEnd = $this->nextPeriodEnd($subscription, $periodStart);

        $this->invoiceService->issueFor($subscription, $periodStart, $periodEnd);

        $subscription->update([
            'status' => SubscriptionStatus::PendingPayment,
        ]);

        return $subscription->refresh()->load(['tenant', 'plan', 'invoices']);
    }

    /**
     * Advance subscription after verified payment. Idempotent per invoice period.
     *
     * Always attempts to reactivate a suspended tenant and invalidate entitlements after a successful payment path.
     *
     * @throws ModelNotFoundException When the subscription row disappears during the transaction.
     */
    public function renewAfterPayment(Subscription $subscription, Invoice $invoice): Subscription
    {
        return DB::transaction(function () use ($subscription, $invoice): Subscription {
            $subscription = Subscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            if ($invoice->period_ends_at !== null
                && ($subscription->ends_at === null || $subscription->ends_at->lt($invoice->period_ends_at))
            ) {
                $subscription->update([
                    'status' => SubscriptionStatus::Active,
                    'ends_at' => $invoice->period_ends_at,
                    'is_current' => 1,
                ]);

                $subscription = $subscription->refresh();
            }

            $tenant = $subscription->tenant;

            if ($tenant instanceof Tenant) {
                if ($tenant->status->canReactivate()) {
                    $this->tenants->reactivate($tenant);
                }

                $this->entitlements->forget($tenant);
            }

            return $subscription->refresh();
        });
    }

    /**
     * Detect due subscriptions and issue renewal invoices without marking them paid.
     *
     * Side effects: transitions trialing subscriptions past trial end and active/past-due subscriptions
     * past period end to pending payment or past due, respectively, and issues invoices.
     * Finalizes period-end cancellations and may suspend tenants after past-due grace.
     */
    public function processDue(): void
    {
        Subscription::query()
            ->current()
            ->whereNotNull('canceled_at')
            ->where(function ($query): void {
                $query->where(function ($inner): void {
                    $inner->whereNotNull('ends_at')->where('ends_at', '<=', now());
                })->orWhere(function ($inner): void {
                    $inner->where('status', SubscriptionStatus::Trialing)
                        ->whereNotNull('trial_ends_at')
                        ->where('trial_ends_at', '<=', now());
                });
            })
            ->each(function (Subscription $subscription): void {
                $this->cancelImmediately($subscription);
            });

        Subscription::query()
            ->current()
            ->whereNull('canceled_at')
            ->where('status', SubscriptionStatus::Trialing)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->each(function (Subscription $subscription): void {
                $periodStart = $subscription->trial_ends_at ?? now();
                $periodEnd = $this->nextPeriodEnd($subscription, $periodStart);

                $this->invoiceService->issueFor($subscription, $periodStart, $periodEnd);

                $subscription->update([
                    'status' => SubscriptionStatus::PendingPayment,
                ]);
            });

        Subscription::query()
            ->current()
            ->whereNull('canceled_at')
            ->where('ends_at', '<=', now())
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
            ->each(function (Subscription $subscription): void {
                $periodStart = $subscription->ends_at ?? now();
                $periodEnd = $this->nextPeriodEnd($subscription, $periodStart);

                $this->invoiceService->issueFor($subscription, $periodStart, $periodEnd);

                $subscription->update([
                    'status' => SubscriptionStatus::PastDue,
                ]);

                $this->suspendTenantIfPastDueGraceElapsed($subscription->refresh());
            });
    }

    /**
     * Resolve an active plan price for the given plan.
     *
     * @throws ModelNotFoundException When an explicit price id is invalid.
     * @throws ValidationException When no active price exists for the plan.
     */
    private function resolvePlanPrice(Plan $plan, ?int $planPriceId): PlanPrice
    {
        $query = PlanPrice::query()
            ->where('plan_id', $plan->id)
            ->where('is_active', true);

        if ($planPriceId !== null) {
            return $query->whereKey($planPriceId)->firstOrFail();
        }

        $price = $query->orderBy('id')->first();

        if (! $price instanceof PlanPrice) {
            throw ValidationException::withMessages([
                'plan_price_id' => 'No active price is available for this plan.',
            ]);
        }

        return $price;
    }

    /**
     * Build snapshotted subscription terms from a plan price.
     *
     * @return array<string, mixed>
     */
    private function termsFromPlanPrice(
        Plan $plan,
        PlanPrice $planPrice,
        CarbonInterface $startsAt,
        ?SubscriptionStatus $status = null,
    ): array {
        $trialEndsAt = $planPrice->trial_days > 0 ? $startsAt->copy()->addDays($planPrice->trial_days) : null;
        $periodStart = $trialEndsAt ?? $startsAt;
        $endsAt = $this->nextPeriodEndFromPrice($planPrice, $periodStart);

        return [
            'plan_id' => $plan->id,
            'plan_price_id' => $planPrice->id,
            'plan_name' => $plan->name,
            'price' => $planPrice->amount,
            'currency' => $planPrice->currency,
            'interval' => $planPrice->interval,
            'interval_count' => $planPrice->interval_count,
            'status' => $status ?? ($trialEndsAt instanceof CarbonInterface
                ? SubscriptionStatus::Trialing
                : SubscriptionStatus::PendingPayment),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'trial_ends_at' => $trialEndsAt,
            'canceled_at' => null,
        ];
    }

    /**
     * Calculate the next period end from a subscription's snapshotted interval.
     */
    private function nextPeriodEnd(Subscription $subscription, CarbonInterface $periodStart): CarbonInterface
    {
        $interval = $subscription->interval;
        $count = $subscription->interval_count ?? 1;

        return $interval === PlanInterval::Yearly
            ? $periodStart->copy()->addYears($count)
            : $periodStart->copy()->addMonths($count);
    }

    /**
     * Calculate the next period end from a plan price interval.
     */
    private function nextPeriodEndFromPrice(PlanPrice $planPrice, CarbonInterface $periodStart): CarbonInterface
    {
        return $planPrice->interval === PlanInterval::Yearly
            ? $periodStart->copy()->addYears($planPrice->interval_count)
            : $periodStart->copy()->addMonths($planPrice->interval_count);
    }

    /**
     * Remaining-period fraction of the new price for mid-cycle plan changes.
     */
    private function proratedAmount(Subscription $subscription, int $newPrice): int
    {
        $endsAt = $subscription->ends_at;

        if ($endsAt === null || $endsAt->lte(now())) {
            return 0;
        }

        $startsAt = $subscription->starts_at;

        if ($startsAt === null || $startsAt->gte($endsAt)) {
            $periodSeconds = max(1, (int) now()->diffInSeconds($this->nextPeriodEnd($subscription, now())));
        } else {
            $periodSeconds = max(1, (int) $startsAt->diffInSeconds($endsAt));
        }

        $remainingSeconds = max(0, (int) now()->diffInSeconds($endsAt));

        return max(0, (int) round($newPrice * $remainingSeconds / $periodSeconds));
    }

    private function cancelImmediately(Subscription $subscription): Subscription
    {
        $this->abandonOpenBilling($subscription);

        $subscription->update([
            'status' => SubscriptionStatus::Canceled,
            'canceled_at' => $subscription->canceled_at ?? now(),
            'is_current' => null,
        ]);

        $tenant = $subscription->tenant;

        if ($tenant instanceof Tenant) {
            $this->entitlements->forget($tenant);
        }

        return $subscription->refresh();
    }

    /**
     * Void open invoices and abandon pending payments for a subscription being canceled.
     */
    private function abandonOpenBilling(Subscription $subscription): void
    {
        Invoice::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', InvoiceStatus::Open)
            ->get()
            ->each(function (Invoice $invoice): void {
                $this->invoiceService->void($invoice);
            });

        Payment::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', PaymentStatus::Pending)
            ->update([
                'status' => PaymentStatus::Cancelled,
                'failed_at' => now(),
            ]);
    }

    private function suspendTenantIfPastDueGraceElapsed(Subscription $subscription): void
    {
        $days = max(1, (int) $this->settings->value('subscriptions.past_due_suspend_after_days', 14));

        if ($subscription->ends_at === null || $subscription->ends_at->copy()->addDays($days)->gt(now())) {
            return;
        }

        $tenant = $subscription->tenant;

        if (! $tenant instanceof Tenant || ! $tenant->status->canSuspend()) {
            return;
        }

        $this->tenants->suspend($tenant);
    }
}
