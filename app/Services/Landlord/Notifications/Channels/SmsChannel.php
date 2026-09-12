<?php

declare(strict_types=1);

namespace App\Services\Landlord\Notifications\Channels;

use App\Contracts\Landlord\Notifications\SmsProvider;
use App\Enums\Landlord\NotificationChannel;
use App\Enums\Landlord\NotificationDeliveryStatus;
use App\Models\Landlord\NotificationDelivery;
use App\Models\Landlord\User;
use App\Services\Landlord\Notifications\Contracts\NotificationChannelHandler;
use App\Services\Landlord\Notifications\NotificationPayload;

/**
 * Delivers SMS through the configured SMS provider and audits the attempt.
 */
class SmsChannel implements NotificationChannelHandler
{
    public function __construct(private SmsProvider $smsProvider) {}

    public function name(): NotificationChannel
    {
        return NotificationChannel::Sms;
    }

    public function send(User $user, NotificationPayload $payload): NotificationDelivery
    {
        $body = (string) ($payload->content['sms_body'] ?? $payload->content['body'] ?? '');
        $phone = $user->phone;

        if (! is_string($phone) || $phone === '') {
            return NotificationDelivery::query()->create([
                'notifiable_type' => $user->getMorphClass(),
                'notifiable_id' => $user->getKey(),
                'notification_key' => $payload->key,
                'channel' => $this->name()->value,
                'status' => NotificationDeliveryStatus::Skipped,
                'provider' => $this->smsProvider->name(),
                'error' => 'Notifiable has no phone number.',
                'metadata' => ['body' => $body],
            ]);
        }

        if (! $this->smsProvider->isEnabled()) {
            return NotificationDelivery::query()->create([
                'notifiable_type' => $user->getMorphClass(),
                'notifiable_id' => $user->getKey(),
                'notification_key' => $payload->key,
                'channel' => $this->name()->value,
                'status' => NotificationDeliveryStatus::Skipped,
                'provider' => $this->smsProvider->name(),
                'error' => 'SMS provider disabled or not configured.',
                'metadata' => ['body' => $body],
            ]);
        }

        $result = $this->smsProvider->send($phone, $body);
        $success = (bool) ($result['success'] ?? false);

        return NotificationDelivery::query()->create([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'notification_key' => $payload->key,
            'channel' => $this->name()->value,
            'status' => $success ? NotificationDeliveryStatus::Sent : NotificationDeliveryStatus::Failed,
            'provider' => $this->smsProvider->name(),
            'provider_message_id' => $result['message_id'] ?? null,
            'error' => $result['error'] ?? null,
            'metadata' => ['body' => $body],
            'sent_at' => $success ? now() : null,
            'failed_at' => $success ? null : now(),
        ]);
    }
}
