<?php

declare(strict_types=1);

namespace App\Models\Landlord;

use App\Enums\Landlord\NotificationDeliveryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'notifiable_type',
    'notifiable_id',
    'notification_key',
    'channel',
    'status',
    'provider',
    'provider_message_id',
    'error',
    'metadata',
    'sent_at',
    'failed_at',
])]
class NotificationDelivery extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => NotificationDeliveryStatus::class,
            'metadata' => 'array',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * Recipient model for this delivery attempt.
     *
     * @return MorphTo<Model, $this>
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
