<?php

declare(strict_types=1);

namespace App\Enums\Landlord;

/**
 * Lifecycle status of a landlord payment record.
 *
 * Terminal statuses ({@see Successful}, {@see Failed}, {@see Cancelled}, {@see Refunded})
 * must not be overwritten by verification or webhook reconciliation. Only
 * {@see Pending} payments accept provider state transitions.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Successful = 'successful';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /**
     * Whether the payment has reached a final, non-reversible state.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Successful, self::Failed, self::Cancelled, self::Refunded], true);
    }
}
