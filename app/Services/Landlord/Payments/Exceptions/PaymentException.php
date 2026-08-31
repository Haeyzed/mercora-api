<?php

declare(strict_types=1);

namespace App\Services\Landlord\Payments\Exceptions;

use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Domain exception for payment provider and configuration failures.
 *
 * Distinguishes user-facing validation errors ({@see ValidationException})
 * from provider, verification, and driver setup problems. Factory methods produce
 * messages safe to surface in API error responses.
 */
class PaymentException extends RuntimeException
{
    /**
     * The requested currency is not enabled for the active provider driver.
     */
    public static function unsupportedCurrency(string $currency): self
    {
        return new self("Currency [{$currency}] is not supported by the payment provider.");
    }

    /**
     * The provider rejected or could not complete checkout initialization.
     */
    public static function initializationFailed(string $message): self
    {
        return new self($message);
    }

    /**
     * Verification failed due to provider error, invalid webhook, or amount/currency mismatch.
     */
    public static function verificationFailed(string $message): self
    {
        return new self($message);
    }

    /**
     * No configuration exists for the requested payment driver slug.
     */
    public static function driverNotConfigured(string $driver): self
    {
        return new self("Payment driver [{$driver}] is not configured.");
    }
}
