<?php

declare(strict_types=1);

namespace App\Services\Landlord\Billing;

use App\Enums\Landlord\InvoiceStatus;
use App\Enums\Landlord\SubscriptionStatus;
use App\Models\Landlord\Invoice;
use App\Models\Landlord\Subscription;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Landlord invoices issued against subscription terms.
 */
class InvoiceService
{
    /**
     * Paginate invoices using model filter, search, and include scopes.
     *
     * @return LengthAwarePaginator<int, Invoice>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return Invoice::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->withIncludes($request->query('include'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString();
    }

    /**
     * Paginate invoice select options as label/value pairs.
     *
     * @return LengthAwarePaginator<int, array{label: string, value: int}>
     */
    public function options(Request $request): LengthAwarePaginator
    {
        return Invoice::query()
            ->filter($request->input('filter', []))
            ->search($request->query('search'))
            ->ordered()
            ->paginate($this->perPage($request))
            ->withQueryString()
            ->through(fn (Invoice $invoice): array => [
                'label' => $invoice->number,
                'value' => $invoice->id,
            ]);
    }

    /**
     * Load an invoice with optional allowed relationships.
     */
    public function show(Invoice $invoice, Request $request): Invoice
    {
        return $invoice->loadAllowedIncludes($request->query('include'));
    }

    /**
     * Issue an open invoice from a subscription's snapshotted terms.
     *
     * @param  array{subscription_id: int, due_at?: string, notes?: string|null}  $data
     */
    public function store(array $data): Invoice
    {
        return DB::transaction(function () use ($data): Invoice {
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($data['subscription_id']);

            return $this->issueFor(
                $subscription,
                $subscription->starts_at ?? now(),
                $subscription->ends_at,
                isset($data['due_at']) ? Carbon::parse($data['due_at']) : null,
                $data['notes'] ?? null,
            );
        });
    }

    /**
     * Issue an invoice from a subscription snapshot. Idempotent per billing period.
     */
    public function issueFor(
        Subscription $subscription,
        CarbonInterface $periodStart,
        ?CarbonInterface $periodEnd = null,
        ?CarbonInterface $dueAt = null,
        ?string $notes = null,
    ): Invoice {
        $this->ensureCurrent($subscription);

        $existing = Invoice::query()
            ->where('subscription_id', $subscription->id)
            ->where('period_starts_at', $periodStart)
            ->first();

        if ($existing instanceof Invoice) {
            return $existing->load(['tenant', 'subscription']);
        }

        try {
            return Invoice::query()->create([
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->id,
                'number' => $this->nextNumber(),
                'status' => InvoiceStatus::Open,
                'amount' => $subscription->price,
                'currency' => $subscription->currency,
                'issued_at' => now(),
                'period_starts_at' => $periodStart,
                'period_ends_at' => $periodEnd,
                'due_at' => $dueAt,
                'notes' => $notes,
            ])->load(['tenant', 'subscription']);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Invoice::query()
                ->where('subscription_id', $subscription->id)
                ->where('period_starts_at', $periodStart)
                ->first();

            if ($existing instanceof Invoice) {
                return $existing->load(['tenant', 'subscription']);
            }

            throw $exception;
        }
    }

    /**
     * Update notes or due date on an open invoice.
     *
     * @param  array{due_at?: string|null, notes?: string|null}  $data
     */
    public function update(Invoice $invoice, array $data): Invoice
    {
        $this->ensureOpen($invoice);

        $invoice->update($data);

        return $invoice->refresh();
    }

    /**
     * Mark an open invoice as paid.
     */
    public function pay(Invoice $invoice): Invoice
    {
        $this->ensureOpen($invoice);

        $invoice->update([
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
        ]);

        return $invoice->refresh();
    }

    /**
     * Void an open invoice.
     */
    public function void(Invoice $invoice): Invoice
    {
        $this->ensureOpen($invoice);

        $invoice->update([
            'status' => InvoiceStatus::Void,
            'voided_at' => now(),
        ]);

        return $invoice->refresh();
    }

    /**
     * Soft delete an invoice.
     */
    public function destroy(Invoice $invoice): void
    {
        $invoice->delete();
    }

    /**
     * Restore a soft-deleted invoice.
     */
    public function restore(Invoice $invoice): Invoice
    {
        abort_unless($invoice->trashed(), 404);

        $invoice->restore();

        return $invoice->refresh();
    }

    /**
     * Soft delete many invoices.
     *
     * @param  list<int>  $ids
     */
    public function destroyMany(array $ids): void
    {
        Invoice::query()->whereKey($ids)->delete();
    }

    /**
     * Restore many soft-deleted invoices.
     *
     * @param  list<int>  $ids
     */
    public function restoreMany(array $ids): void
    {
        Invoice::onlyTrashed()->whereKey($ids)->restore();
    }

    private function ensureCurrent(Subscription $subscription): void
    {
        if (in_array($subscription->status, SubscriptionStatus::currentCases(), true)) {
            return;
        }

        throw ValidationException::withMessages([
            'subscription_id' => 'The subscription is not current.',
        ]);
    }

    private function ensureOpen(Invoice $invoice): void
    {
        if ($invoice->status === InvoiceStatus::Open) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => 'The invoice is not open.',
        ]);
    }

    private function nextNumber(): string
    {
        do {
            $number = 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Invoice::withTrashed()->where('number', $number)->exists());

        return $number;
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
