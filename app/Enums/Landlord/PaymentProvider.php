<?php

declare(strict_types=1);

namespace App\Enums\Landlord;

use App\Services\Landlord\Payments\Contracts\PaymentDriver;

/**
 * Supported third-party payment gateways for landlord billing.
 *
 * Each case maps to a configured driver in {@see config('payments.drivers')}
 * and a {@see PaymentDriver} implementation.
 */
enum PaymentProvider: string
{
    case Flutterwave = 'flutterwave';
    case Paystack = 'paystack';
    case Stripe = 'stripe';

    /**
     * All provider slug values for validation and configuration lookups.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
