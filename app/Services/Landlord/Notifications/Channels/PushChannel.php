<?php

declare(strict_types=1);

namespace App\Services\Landlord\Notifications\Channels;

use App\Enums\Landlord\NotificationChannel;
use App\Enums\Landlord\NotificationDeliveryStatus;
use App\Models\Landlord\NotificationDelivery;
use App\Models\Landlord\User;
use App\Services\Landlord\Notifications\Contracts\NotificationChannelHandler;
use App\Services\Landlord\Notifications\NotificationPayload;

/**
 * Push adapter without FCM or device tokens. Audits a skipped delivery until devices exist.
 */
class PushChannel implements NotificationChannelHandler
{
    public function name(): NotificationChannel
    {
        return NotificationChannel::Push;
    }

    public function send(User $user, NotificationPayload $payload): NotificationDelivery
    {
        return NotificationDelivery::query()->create([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'notification_key' => $payload->key,
            'channel' => $this->name()->value,
            'status' => NotificationDeliveryStatus::Skipped,
            'provider' => 'push',
            'error' => 'No device tokens. Push delivery is deferred until a provider is wired.',
            'metadata' => [
                'title' => $payload->content['push_title'] ?? $payload->content['title'] ?? '',
                'body' => $payload->content['push_body'] ?? $payload->content['body'] ?? '',
            ],
        ]);
    }
}
