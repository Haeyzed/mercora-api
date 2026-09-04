<?php

declare(strict_types=1);

namespace App\Services\Landlord\Payments\DTOs;

use App\Models\Landlord\Payment;

/**
 * Immutable input for starting a provider checkout session.
 *
 * Amount is expressed in minor currency units (integer). The reference must be
 * the application-generated merchant reference persisted on {@see Payment}
 * before the provider is called.
 */
readonly class PaymentInitializationData
{
    /**
     * @param  string  $reference  Unique merchant reference (tx_ref) sent to the provider.
     * @param  int  $amount  Charge amount in minor units (e.g. kobo, cents).
     * @param  string  $currency  ISO 4217 currency code.
     * @param  string  $email  Payer email for provider customer record.
     * @param  string  $name  Payer display name.
     * @param  string|null  $redirectUrl  URL the provider redirects to after checkout.
     * @param  array<string, mixed>  $metadata  Opaque key/value pairs forwarded to the provider.
     * @param  string|null  $paymentMethod  Optional normalized method slug to constrain checkout options.
     * @param  string|null  $title  Optional checkout / statement title (statement descriptor).
     */
    public function __construct(
        public string $reference,
        public int $amount,
        public string $currency,
        public string $email,
        public string $name,
        public ?string $redirectUrl = null,
        public array $metadata = [],
        public ?string $paymentMethod = null,
        public ?string $title = null,
    ) {}
}
