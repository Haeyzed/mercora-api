<?php

declare(strict_types=1);

namespace App\Services\Landlord\Billing;

use App\Enums\Landlord\InvoiceStatus;
use App\Enums\Landlord\SubscriptionStatus;
use App\Models\Landlord\Invoice;
use App\Models\Landlord\Payment;
use App\Models\Landlord\Subscription;
use App\Services\Concerns\PaginatesRequests;
use App\Services\Landlord\Settings\SettingService;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Issues and settles landlord invoices against subscription billing terms.
 *
 * Domain: landlord billing ledger tied to tenant subscriptions.
 *
 * Invariants:
 * - Invoices are issued only for subscriptions in a current status.
 * - Amount and currency are snapshotted from the subscription at issue time.
 * - Number prefix/suffix and default due date come from billing settings.
 * - Only open invoices can be updated, voided, or marked paid.
 * - Payment settlement requires matching amount and currency on the verified payment.
 * - At most one invoice per subscription billing period (idempotent via period_starts_at).
 *
 * Side effects: creates, updates, voids, soft-deletes, and restores {@see Invoice} records;
 * links paid invoices to {@see Payment} records; reads {@see SettingService} for numbering and grace.
 */
class InvoiceService
{
    use PaginatesRequests;

    public function __construct(private SettingService $settings) {}

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
     * Load an invoice with optional allowed relationships.
     */
    public function show(Invoice $invoice, Request $request): Invoice
    {
        return $invoice->loadAllowedIncludes($request->query('include'));
    }

    /**
     * Issue an open invoice from a subscription's snapshotted terms.
     *
     * Locks the subscription row for the duration of the transaction.
     *
     * @param  array{subscription_id: int, due_at?: string, notes?: string|null}  $data
     *
     * @throws ModelNotFoundException When the subscription does not exist.
     * @throws ValidationException When the subscription is not current.
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
     *
     * Returns an existing invoice when one already exists for the same subscription and period start.
     * When due date is omitted, applies {@see billing.grace_days}. When notes are omitted, uses
     * {@see billing.invoice_footer} when configured.
     *
     * @throws ValidationException When the subscription is not current.
     * @throws UniqueConstraintViolationException When a concurrent insert races and no existing row is found.
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

        $dueAt ??= now()->addDays($this->graceDays());
        $notes ??= $this->defaultNotes();

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
     *
     * @throws ValidationException When the invoice is not open.
     */
    public function update(Invoice $invoice, array $data): Invoice
    {
        $this->ensureOpen($invoice);

        $invoice->update($data);

        return $invoice->refresh();
    }

    /**
     * Mark an open invoice as paid from a verified payment.
     *
     * @throws ValidationException When the invoice is not open or amount/currency mismatch.
     * @throws ModelNotFoundException When the invoice row disappears during the transaction.
     */
    public function markPaidFromPayment(Invoice $invoice, Payment $payment): Invoice
    {
        return DB::transaction(function () use ($invoice, $payment): Invoice {
            $invoice = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            $this->ensureOpen($invoice);

            if ($invoice->amount !== $payment->amount || $invoice->currency !== $payment->currency) {
                throw ValidationException::withMessages([
                    'amount' => 'The payment does not match the invoice.',
                ]);
            }

            $invoice->update([
                'status' => InvoiceStatus::Paid,
                'paid_at' => $payment->paid_at ?? now(),
                'payment_id' => $payment->id,
            ]);

            return $invoice->refresh();
        });
    }

    /**
     * Void an open invoice.
     *
     * @throws ValidationException When the invoice is not open.
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
     * Ensure the subscription is in a current billable status.
     *
     * @throws ValidationException When the subscription is not current.
     */
    private function ensureCurrent(Subscription $subscription): void
    {
        if (in_array($subscription->status, SubscriptionStatus::currentCases(), true)) {
            return;
        }

        throw ValidationException::withMessages([
            'subscription_id' => 'The subscription is not current.',
        ]);
    }

    /**
     * Ensure the invoice is open before mutating or settling.
     *
     * @throws ValidationException When the invoice is not open.
     */
    private function ensureOpen(Invoice $invoice): void
    {
        if ($invoice->status === InvoiceStatus::Open) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => 'The invoice is not open.',
        ]);
    }

    /**
     * Generate a unique invoice number using billing prefix and optional suffix settings.
     */
    private function nextNumber(): string
    {
        $prefix = $this->sanitizeSegment(
            (string) $this->settings->value('billing.invoice_prefix', 'INV'),
            'INV',
        );
        $suffix = $this->sanitizeSegment(
            $this->settings->value('billing.invoice_suffix'),
            '',
        );

        do {
            $number = $prefix.'-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));

            if ($suffix !== '') {
                $number .= '-'.$suffix;
            }
        } while (Invoice::withTrashed()->where('number', $number)->exists());

        return $number;
    }

    /**
     * Days until payment is due when a due date is not supplied.
     */
    private function graceDays(): int
    {
        return max(0, (int) $this->settings->value('billing.grace_days', 3));
    }

    /**
     * Default invoice notes from memo and footer settings, when present.
     */
    private function defaultNotes(): ?string
    {
        $parts = [];

        foreach (['billing.invoice_memo', 'billing.invoice_footer'] as $key) {
            $value = $this->settings->value($key);

            if (is_string($value) && $value !== '') {
                $parts[] = $value;
            }
        }

        return $parts === [] ? null : implode("\n\n", $parts);
    }

    /**
     * Keep invoice number segments alphanumeric and dash-safe.
     */
    private function sanitizeSegment(mixed $value, string $fallback): string
    {
        if (! is_string($value) || $value === '') {
            return $fallback;
        }

        $sanitized = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '', $value));

        return $sanitized !== '' ? $sanitized : $fallback;
    }
}
