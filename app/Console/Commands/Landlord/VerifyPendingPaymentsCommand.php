<?php

declare(strict_types=1);

namespace App\Console\Commands\Landlord;

use App\Enums\Landlord\PaymentStatus;
use App\Models\Landlord\Payment;
use App\Services\Landlord\Payments\PaymentService;
use Illuminate\Console\Command;

/**
 * Reconcile stale pending payments with the payment provider.
 *
 * Scheduled safety net for webhooks or redirect callbacks that were missed.
 * Only payments older than two minutes are polled to avoid racing in-flight
 * checkout sessions. Individual verification failures are reported but do
 * not abort the batch.
 */
class VerifyPendingPaymentsCommand extends Command
{
    protected $signature = 'landlord:verify-pending-payments';

    protected $description = 'Verify pending landlord payments with the payment provider';

    /**
     * Poll each eligible pending payment via {@see PaymentService::verify()}.
     */
    public function handle(PaymentService $paymentService): int
    {
        Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->where('created_at', '<=', now()->subMinutes(2))
            ->orderBy('id')
            ->each(function (Payment $payment) use ($paymentService): void {
                try {
                    $paymentService->verify($payment);
                } catch (\Throwable $exception) {
                    report($exception);
                }
            });

        return self::SUCCESS;
    }
}
