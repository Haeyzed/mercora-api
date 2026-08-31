<?php

declare(strict_types=1);

namespace App\Enums\Landlord;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Expired = 'expired';

    /**
     * @return list<self>
     */
    public static function currentCases(): array
    {
        return [
            self::Trialing,
            self::Active,
            self::PastDue,
        ];
    }
}
