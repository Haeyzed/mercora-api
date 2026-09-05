<?php

declare(strict_types=1);

namespace App\Services\Landlord\Notifications;

use App\Enums\Landlord\NoticeChannel;
use App\Models\Landlord\NotificationPreference;
use App\Models\Landlord\NotificationTemplate;
use App\Models\Landlord\User;

/**
 * Resolves and updates per-user notification channel preferences.
 *
 * Domain: landlord self-serve opt-in/out per template × channel.
 *
 * Invariants:
 * - Mandatory templates lock in_app and mail to enabled.
 * - Missing preference rows default to enabled.
 *
 * Side effects: upserts {@see NotificationPreference} rows.
 */
class NotificationPreferenceService
{
    /**
     * List effective preferences for a user across active templates.
     *
     * @return list<array{
     *     notification_key: string,
     *     name: string,
     *     is_mandatory: bool,
     *     channels: list<array{channel: string, enabled: bool, locked: bool}>
     * }>
     */
    public function listForUser(User $user): array
    {
        $templates = NotificationTemplate::query()
            ->where('is_active', true)
            ->ordered()
            ->get();

        $stored = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->get()
            ->groupBy('notification_key');

        $result = [];

        foreach ($templates as $template) {
            /** @var list<string> $channels */
            $channels = $template->channels ?? [];
            $channelPrefs = [];

            foreach ($channels as $channel) {
                $locked = $this->isLocked($template, $channel);

                $preference = ($stored->get($template->key) ?? collect())
                    ->firstWhere('channel', $channel);

                $enabled = $locked
                    ? true
                    : ($preference?->enabled ?? true);

                $channelPrefs[] = [
                    'channel' => $channel,
                    'enabled' => (bool) $enabled,
                    'locked' => $locked,
                ];
            }

            $result[] = [
                'notification_key' => $template->key,
                'name' => $template->name,
                'is_mandatory' => $template->is_mandatory,
                'channels' => $channelPrefs,
            ];
        }

        return $result;
    }

    /**
     * Upsert preference rows for the user.
     *
     * @param  list<array{notification_key: string, channel: string, enabled: bool}>  $preferences
     * @return list<array{
     *     notification_key: string,
     *     name: string,
     *     is_mandatory: bool,
     *     channels: list<array{channel: string, enabled: bool, locked: bool}>
     * }>
     */
    public function syncForUser(User $user, array $preferences): array
    {
        foreach ($preferences as $preference) {
            $template = NotificationTemplate::query()
                ->where('key', $preference['notification_key'])
                ->first();

            if ($template === null) {
                continue;
            }

            $channel = (string) $preference['channel'];
            $locked = $this->isLocked($template, $channel);
            $enabled = $locked ? true : (bool) $preference['enabled'];

            NotificationPreference::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'notification_key' => $template->key,
                    'channel' => $channel,
                ],
                ['enabled' => $enabled],
            );
        }

        return $this->listForUser($user);
    }

    /**
     * Whether the user allows the channel for the given notification key.
     */
    public function isEnabled(User $user, string $notificationKey, string $channel, bool $isMandatory = false): bool
    {
        if ($isMandatory && $this->isLockableChannel($channel)) {
            return true;
        }

        $preference = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('notification_key', $notificationKey)
            ->where('channel', $channel)
            ->first();

        return $preference?->enabled ?? true;
    }

    private function isLocked(NotificationTemplate $template, string $channel): bool
    {
        return $template->is_mandatory && $this->isLockableChannel($channel);
    }

    private function isLockableChannel(string $channel): bool
    {
        return in_array($channel, [
            NoticeChannel::InApp->value,
            NoticeChannel::Mail->value,
        ], true);
    }
}
