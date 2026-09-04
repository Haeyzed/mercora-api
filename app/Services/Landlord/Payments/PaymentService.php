<?php

declare(strict_types=1);

namespace App\Services\Landlord\Payments;

use App\Enums\Landlord\InvoiceStatus;
use App\Enums\Landlord\PaymentStatus;
use App\Models\Landlord\Invoice;
use App\Models\Landlord\Payment;
use App\Models\Landlord\PaymentWebhookEvent;
use App\Models\Landlord\User;
use App\Services\Landlord\Billing\InvoiceService;
use App\Services\Landlord\Payments\DTOs\PaymentInitializationData;
use App\Services\Landlord\Payments\DTOs\PaymentVerificationResult;
use App\Services\Landlord\Payments\DTOs\WebhookPayload;
use App\Services\Landlord\Payments\Exceptions\PaymentException;
use App\Services\Landlord\Settings\SettingService;
use App\Services\Landlord\Subscriptions\SubscriptionService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates landlord payment initialization, verification, and webhook handling.
 *
 * Financial invariants enforced here:
 * - Payment amount and currency are copied from the open invoice at creation time.
 * - Successful verification requires the provider-reported amount and currency to match the stored payment.
 * - Terminal payment statuses ({@see PaymentStatus::Successful}, failed, cancelled, refunded) are never downgraded or re-applied.
 * - Invoice settlement and subscription renewal run only after a payment transitions to successful.
 *
 * Idempotency:
 * - At most one pending payment per invoice; repeated initialization returns the existing pending record.
 * - {@see verify()} is a no-op when the payment is already terminal.
 * - Webhook processing deduplicates on (provider, provider_event_id) via {@see PaymentWebhookEvent}.
 */
class PaymentService
{
    public function __construct(
        private PaymentManager $paymentManager,
        private InvoiceService $invoiceService,
        private SubscriptionService $subscriptionService,
        private SettingService $settings,
    ) {}

    /**
     * Paginate payments using model filter scopes.
     *
     * @return LengthAwarePaginator<int, Payment>
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        return Payment::query()
            ->filter($request->input('filter', []))
            ->ordered()
            ->paginate(min(max($request->integer('per_page', 15), 1), 100))
            ->withQueryString();
    }

    /**
     * Load a payment record.
     */
    public function show(Payment $payment): Payment
    {
        return $payment;
    }

    /**
     * Create or reuse a pending payment for an open invoice and start provider checkout.
     *
     * Idempotent: if a pending payment already exists for the invoice, that record is
     * returned without calling the provider again. The invoice must be open; amount and
     * currency are snapshotted from the invoice and must not be client-supplied.
     *
     * @param  string|null  $redirectUrl  Post-checkout redirect URL; defaults to {@see config('payments.redirect_url')}.
     *
     * @throws ValidationException When the invoice is not open.
     * @throws PaymentException When provider initialization fails (e.g. unsupported currency).
     */
    public function initializeForInvoice(Invoice $invoice, User $payer, ?string $redirectUrl = null): Payment
    {
        if ($invoice->status !== InvoiceStatus::Open) {
            throw ValidationException::withMessages([
                'status' => 'The invoice is not open.',
            ]);
        }

        $existing = Payment::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', PaymentStatus::Pending)
            ->first();

        if ($existing instanceof Payment) {
            return $existing;
        }

        $driver = $this->paymentManager->driver();
        $reference = $this->generateReference();

        $payment = Payment::query()->create([
            'tenant_id' => $invoice->tenant_id,
            'subscription_id' => $invoice->subscription_id,
            'invoice_id' => $invoice->id,
            'provider' => $driver->provider(),
            'reference' => $reference,
            'amount' => $invoice->amount,
            'currency' => $invoice->currency,
            'status' => PaymentStatus::Pending,
            'metadata' => [
                'tenant_id' => $invoice->tenant_id,
                'subscription_id' => $invoice->subscription_id,
                'invoice_id' => $invoice->id,
            ],
        ]);

        $descriptor = $this->settings->value('billing.statement_descriptor');
        $title = is_string($descriptor) && $descriptor !== '' ? $descriptor : null;

        $result = $driver->initialize(new PaymentInitializationData(
            reference: $reference,
            amount: $invoice->amount,
            currency: $invoice->currency,
            email: $payer->email,
            name: $payer->name,
            redirectUrl: $redirectUrl ?? config('payments.redirect_url'),
            metadata: $payment->metadata ?? [],
            title: $title,
        ));

        $payment->update([
            'provider_reference' => $result->providerReference,
            'checkout_url' => $result->checkoutUrl,
            'provider_response' => $result->providerResponse,
        ]);

        $invoice->update(['payment_id' => $payment->id]);

        return $payment->refresh();
    }

    /**
     * Poll the payment provider and apply the verification outcome to the local payment.
     *
     * Idempotent: terminal payments are returned unchanged without contacting the provider.
     * Uses a row lock when applying results so concurrent webhook and polling paths cannot
     * double-settle an invoice or renew a subscription.
     *
     * @throws PaymentException When the provider cannot verify the transaction or amount/currency mismatch on success.
     */
    public function verify(Payment $payment): Payment
    {
        if ($payment->status->isTerminal()) {
            return $payment;
        }

        $driver = $this->paymentManager->driver($payment->provider->value);
        $result = $driver->verify($payment->reference, $payment->amount, $payment->currency);

        return $this->applyVerificationResult($payment, $result);
    }

    /**
     * Validate, deduplicate, and process an inbound provider webhook.
     *
     * Provider boundary: signature verification is delegated to the driver before any
     * side effects. Webhook events are idempotent on (provider, provider_event_id);
     * already-processed events are returned without re-running verification. When a
     * matching local payment exists, {@see verify()} is invoked to reconcile state.
     *
     * @param  string  $provider  Provider slug matching {@see PaymentProvider}.
     *
     * @throws PaymentException When the webhook signature is invalid or normalization fails.
     */
    public function handleWebhook(WebhookPayload $payload, string $provider): PaymentWebhookEvent
    {
        $driver = $this->paymentManager->driver($provider);

        if (! $driver->verifyWebhookSignature($payload->signature ?? '', $payload->rawPayload)) {
            throw PaymentException::verificationFailed('Invalid webhook signature.');
        }

        $eventId = $payload->eventId ?? hash('sha256', $payload->rawPayload);

        $event = PaymentWebhookEvent::query()->firstOrCreate(
            [
                'provider' => $provider,
                'provider_event_id' => $eventId,
            ],
            [
                'event_type' => $payload->eventType ?? 'unknown',
                'payload' => $payload->body,
                'status' => 'pending',
            ],
        );

        if ($event->processed_at !== null) {
            return $event;
        }

        $result = $driver->normalizeWebhook($payload);
        $payment = Payment::query()->where('reference', $result->reference)->first();

        if ($payment instanceof Payment) {
            $this->verify($payment);
        }

        $event->update([
            'status' => 'processed',
            'processed_at' => now(),
        ]);

        return $event;
    }

    /**
     * Persist a verification result under row lock, triggering billing side effects on success.
     *
     * @throws ModelNotFoundException When the payment row disappears during the transaction.
     */
    private function applyVerificationResult(Payment $payment, PaymentVerificationResult $result): Payment
    {
        return DB::transaction(function () use ($payment, $result): Payment {
            $payment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status->isTerminal()) {
                return $payment;
            }

            if ($result->status === PaymentStatus::Successful) {
                $payment->update([
                    'status' => PaymentStatus::Successful,
                    'provider_reference' => $result->providerReference ?? $payment->provider_reference,
                    'payment_method' => $result->paymentMethod,
                    'provider_response' => $result->providerResponse,
                    'paid_at' => now(),
                ]);

                $invoice = $payment->invoice;

                if ($invoice !== null) {
                    $this->invoiceService->markPaidFromPayment($invoice, $payment);
                    $subscription = $invoice->subscription;

                    if ($subscription !== null) {
                        $this->subscriptionService->renewAfterPayment($subscription, $invoice);
                    }
                }

                return $payment->refresh();
            }

            if (in_array($result->status, [PaymentStatus::Failed, PaymentStatus::Cancelled], true)) {
                $payment->update([
                    'status' => $result->status,
                    'provider_response' => $result->providerResponse,
                    'failed_at' => now(),
                ]);
            }

            return $payment->refresh();
        });
    }

    /**
     * Generate a globally unique merchant reference for provider checkout.
     */
    private function generateReference(): string
    {
        do {
            $reference = config('payments.reference_prefix', 'mercora').'_'.Str::lower(Str::random(20));
        } while (Payment::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
