<?php

declare(strict_types=1);

namespace App\Services\Landlord\Notifications\Contracts;

use App\Enums\Landlord\NotificationChannel;
use App\Models\Landlord\NotificationDelivery;
use App\Models\Landlord\User;
use App\Services\Landlord\Notifications\NotificationPayload;

interface NotificationChannelHandler
{
    public function name(): NotificationChannel;

    public function send(User $user, NotificationPayload $payload): NotificationDelivery;
}
