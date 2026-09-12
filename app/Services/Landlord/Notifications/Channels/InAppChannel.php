<?php

declare(strict_types=1);

namespace App\Services\Landlord\Notifications\Channels;

use App\Enums\Landlord\NoticeChannel;
use App\Enums\Landlord\NoticeStatus;
use App\Enums\Landlord\NotificationChannel;
use App\Enums\Landlord\NotificationDeliveryStatus;
use App\Models\Landlord\Notice;
use App\Models\Landlord\NotificationDelivery;
use App\Models\Landlord\User;
use App\Services\Landlord\Notifications\Contracts\NotificationChannelHandler;
use App\Services\Landlord\Notifications\NotificationPayload;

/**
 * Writes an unread in-app notice to the landlord Notice ledger.
 */
class InAppChannel implements NotificationChannelHandler
{
    public function name(): NotificationChannel
    {
        return NotificationChannel::InApp;
    }

    public function send(User $user, NotificationPayload $payload): NotificationDelivery
    {
        Notice::query()->create([
            'user_id' => $user->id,
            'title' => $payload->content['title'] ?? '',
            'body' => $payload->content['body'] ?? '',
            'channel' => NoticeChannel::InApp,
            'status' => NoticeStatus::Unread,
            'read_at' => null,
        ]);

        return NotificationDelivery::query()->create([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'notification_key' => $payload->key,
            'channel' => $this->name()->value,
            'status' => NotificationDeliveryStatus::Sent,
            'provider' => 'notice',
            'sent_at' => now(),
        ]);
    }
}
