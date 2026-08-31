<?php

declare(strict_types=1);

namespace App\Services\Landlord\Payments\DTOs;

use App\Enums\Landlord\PaymentStatus;

/**
 * Normalized outcome of a provider checkout initialization call.
 *
 * Returned immediately after the provider accepts the payment intent. Status is
 * typically {@see PaymentStatus::Pending} until the customer completes checkout
 * and verification runs.
 */
readonly class PaymentInitializationResult
{
    /**
     * @param  string  $reference  Merchant reference echoed from initialization input.
     * @param  string|null  $providerReference  Provider-assigned transaction or payment id.
     * @param  PaymentStatus  $status  Initial local status (usually pending).
     * @param  string|null  $checkoutUrl  Hosted payment page URL for the customer.
     * @param  array<string, mixed>  $providerResponse  Raw provider response for auditing.
     */
    public function __construct(
        public string $reference,
        public ?string $providerReference,
        public PaymentStatus $status,
        public ?string $checkoutUrl,
        public array $providerResponse = [],
    ) {}
}
