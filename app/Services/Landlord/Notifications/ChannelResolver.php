<?php

declare(strict_types=1);

namespace App\Services\Landlord\Notifications;

use App\Enums\Landlord\NotificationChannel;
use App\Models\Landlord\User;
use App\Services\Landlord\Notifications\Contracts\NotificationChannelHandler;
use App\Services\Landlord\SettingService;

/**
 * Filters template channels to handlers the user may receive.
 */
class ChannelResolver
{
    /**
     * @param  iterable<NotificationChannelHandler>  $channels
     */
    public function __construct(
        private iterable $channels,
        private NotificationPreferenceService $preferences,
        private SettingService $settings,
    ) {}

    /**
     * @param  list<string>  $templateChannels
     * @return list<NotificationChannelHandler>
     */
    public function resolve(User $user, string $notificationKey, array $templateChannels, bool $isMandatory): array
    {
        $resolved = [];

        foreach ($this->channels as $channel) {
            $name = $channel->name();

            if (! in_array($name->value, $templateChannels, true)) {
                continue;
            }

            if (! $this->preferences->isEnabled($user, $notificationKey, $name->value, $isMandatory)) {
                continue;
            }

            if (! (bool) $this->settings->value($name->settingsKey(), $name->settingsDefault())) {
                continue;
            }

            if ($name === NotificationChannel::Sms && blank($user->phone)) {
                continue;
            }

            $resolved[] = $channel;
        }

        return $resolved;
    }
}
