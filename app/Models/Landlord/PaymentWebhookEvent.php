<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use App\Services\Landlord\Payments\PaymentService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Idempotency ledger for inbound payment provider webhooks.
 *
 * Each row represents a single provider event keyed by (provider, provider_event_id).
 * Once {@see processed_at} is set, the event must not be reprocessed. Payload is
 * stored for audit and debugging; payment reconciliation is delegated to
 * {@see PaymentService}.
 *
 * @property array<string, mixed> $payload
 * @property Carbon|null $processed_at
 */
#[Fillable([
    'provider',
    'provider_event_id',
    'event_type',
    'payload',
    'status',
    'processed_at',
    'error_message',
])]
class PaymentWebhookEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
