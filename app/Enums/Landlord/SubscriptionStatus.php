<?php

declare(strict_types=1);

namespace App\Enums\Landlord;

use App\Services\Landlord\SubscriptionService;

/**
 * Lifecycle status of a tenant subscription on the landlord database.
 *
 * Governs whether a subscription can receive invoices, accept payment, or renew.
 * {@see PendingPayment} and {@see PastDue} indicate billing action is required;
 * successful payment transitions subscriptions back to {@see Active} via
 * {@see SubscriptionService::renewAfterPayment}.
 */
enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PendingPayment = 'pending_payment';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Expired = 'expired';

    /**
     * Statuses representing a subscription that has not yet ended.
     *
     * @return list<self>
     */
    public static function currentCases(): array
    {
        return [
            self::Trialing,
            self::Active,
            self::PendingPayment,
            self::PastDue,
        ];
    }

    /**
     * Statuses eligible for renewal after a successful invoice payment.
     *
     * @return list<self>
     */
    public static function renewableCases(): array
    {
        return [
            self::Active,
            self::PastDue,
            self::PendingPayment,
        ];
    }

    /**
     * Whether this status grants tenant product API access.
     *
     * PastDue and PendingPayment do not — clients may still authenticate
     * for billing UI; product routes return 402 until Active or Trialing.
     */
    public function grantsAccess(): bool
    {
        return match ($this) {
            self::Trialing, self::Active => true,
            default => false,
        };
    }

    /**
     * Statuses that grant tenant product API access.
     *
     * @return list<self>
     */
    public static function accessGrantingCases(): array
    {
        return [
            self::Trialing,
            self::Active,
        ];
    }
}
