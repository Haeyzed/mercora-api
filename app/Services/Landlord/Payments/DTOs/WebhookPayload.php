<?php

declare(strict_types=1);

namespace App\Services\Landlord\Payments\DTOs;

/**
 * Inbound provider webhook envelope passed into payment processing.
 *
 * Preserves the raw request body for signature verification. Parsed {@see $body}
 * is used for normalization after the signature passes. {@see $eventId} enables
 * idempotent webhook deduplication when the provider supplies a stable event id.
 */
readonly class WebhookPayload
{
    /**
     * @param  string  $rawPayload  Unmodified request body used for signature verification.
     * @param  array<string, mixed>  $body  Decoded JSON payload.
     * @param  string|null  $signature  Provider signature header value.
     * @param  string|null  $eventType  Provider event name (e.g. charge.completed).
     * @param  string|null  $eventId  Provider-supplied unique event identifier.
     */
    public function __construct(
        public string $rawPayload,
        public array $body,
        public ?string $signature,
        public ?string $eventType = null,
        public ?string $eventId = null,
    ) {}
}
