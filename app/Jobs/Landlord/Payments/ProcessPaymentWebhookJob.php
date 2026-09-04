<?php

declare(strict_types=1);

namespace App\Jobs\Landlord\Payments;

use App\Services\Landlord\Payments\DTOs\WebhookPayload;
use App\Services\Landlord\Payments\PaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asynchronously process an inbound payment provider webhook.
 *
 * Delegates to {@see PaymentService::handleWebhook()} which enforces signature
 * validation and idempotent event deduplication before reconciling payment state.
 * Failures are logged and re-thrown so the queue worker can retry transient errors.
 */
class ProcessPaymentWebhookJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  WebhookPayload  $payload  Verified-ready webhook envelope built by the HTTP controller.
     * @param  string  $provider  Payment provider slug (flutterwave, paystack, stripe).
     */
    public function __construct(
        public WebhookPayload $payload,
        public string $provider,
    ) {}

    /**
     * Run webhook handling for the configured provider.
     *
     * @throws Throwable When webhook processing fails after logging; triggers queue retry.
     */
    public function handle(PaymentService $paymentService): void
    {
        try {
            $paymentService->handleWebhook($this->payload, $this->provider);
        } catch (Throwable $exception) {
            Log::error('Payment webhook processing failed.', [
                'provider' => $this->provider,
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }
}
