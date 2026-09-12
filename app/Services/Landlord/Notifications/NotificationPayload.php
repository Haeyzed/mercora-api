<?php

declare(strict_types=1);

namespace App\Services\Landlord\Notifications;

/**
 * Rendered template content ready for channel adapters.
 */
final readonly class NotificationPayload
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $channels
     * @param  array<string, string>  $content
     */
    public function __construct(
        public string $key,
        public array $data,
        public array $channels,
        public array $content,
        public bool $isMandatory,
    ) {}
}
