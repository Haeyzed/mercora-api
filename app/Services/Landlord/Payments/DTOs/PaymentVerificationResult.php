<?php

declare(strict_types=1);

namespace App\Services\Landlord\Payments\DTOs;

use App\Enums\Landlord\PaymentStatus;
use App\Models\Landlord\Payment;
use App\Services\Landlord\Payments\Contracts\PaymentDriver;

/**
 * Normalized outcome of verifying a payment with the provider.
 *
 * Used by both polling ({@see PaymentDriver::verify})
 * and webhook normalization. Amount is in minor units and must match the stored
 * {@see Payment} when status is successful.
 */
readonly class PaymentVerificationResult
{
    /**
     * @param  string  $reference  Merchant reference linking to the local payment row.
     * @param  string|null  $providerReference  Provider transaction id.
     * @param  PaymentStatus  $status  Normalized payment status.
     * @param  int  $amount  Verified amount in minor units.
     * @param  string  $currency  Verified ISO 4217 currency code.
     * @param  string|null  $paymentMethod  Provider-reported method (e.g. card).
     * @param  array<string, mixed>  $providerResponse  Raw provider payload for auditing.
     */
    public function __construct(
        public string $reference,
        public ?string $providerReference,
        public PaymentStatus $status,
        public int $amount,
        public string $currency,
        public ?string $paymentMethod = null,
        public array $providerResponse = [],
    ) {}

    /**
     * Whether the provider reported a successful, settled charge.
     */
    public function isSuccessful(): bool
    {
        return $this->status === PaymentStatus::Successful;
    }
}
