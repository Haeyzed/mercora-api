<?php

declare(strict_types=1);

namespace App\Services\Landlord\Notifications;

use App\Enums\Landlord\NoticeChannel;
use App\Enums\Landlord\NoticeStatus;
use App\Enums\Landlord\NotificationDeliveryStatus;
use App\Models\Landlord\Notice;
use App\Models\Landlord\NotificationDelivery;
use App\Models\Landlord\NotificationTemplate;
use App\Models\Landlord\User;
use App\Services\Landlord\SettingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Delivers templated notifications into the Notice ledger (and records mail notices).
 *
 * Domain: landlord lifecycle alerts keyed by notification_templates.key.
 *
 * Invariants:
 * - Missing/inactive templates are no-ops (logged).
 * - Category kill-switches: billing_alerts, tenant_lifecycle_alerts.
 * - Channel kill-switches: in_app_enabled, email_enabled.
 * - Quiet hours skip non-mandatory templates.
 * - Never throws for policy gates; channel store ValidationException is recorded as skipped/failed.
 *
 * Side effects: creates {@see Notice} and {@see NotificationDelivery} rows.
 */
class NotificationDispatcher
{
    public function __construct(
        private NotificationTemplateService $templates,
        private NotificationPreferenceService $preferences,
        private TemplateRenderer $renderer,
        private SettingService $settings,
    ) {}

    /**
     * Fan out a templated notification to every active landlord user.
     *
     * @param  array<string, mixed>  $data
     */
    public function notifyActiveUsers(string $key, array $data = []): int
    {
        if (! $this->categoryAllowed($key)) {
            return 0;
        }

        $created = 0;

        User::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->each(function (User $user) use ($key, $data, &$created): void {
                $created += $this->send($user, $key, $data, checkCategory: false);
            });

        return $created;
    }

    /**
     * Send a templated notification to a single user.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>|null  $onlyChannels
     */
    public function send(User $user, string $key, array $data = [], ?array $onlyChannels = null, bool $checkCategory = true): int
    {
        if ($checkCategory && ! $this->categoryAllowed($key)) {
            return 0;
        }

        $template = $this->templates->findActiveByKey($key);

        if ($template === null) {
            Log::warning('Notification template missing or inactive', ['key' => $key]);

            return 0;
        }

        if (! $template->is_mandatory && $this->isQuietHours()) {
            return 0;
        }

        /** @var list<string> $templateChannels */
        $templateChannels = $template->channels ?? [];

        if ($onlyChannels !== null) {
            $templateChannels = array_values(array_intersect($templateChannels, $onlyChannels));
        }

        /** @var list<string> $variables */
        $variables = $template->variables ?? [];

        $content = [
            'title' => $this->renderer->render($template->title, $data, $variables),
            'body' => $this->renderer->render($template->body, $data, $variables),
            'email_subject' => $this->renderer->render($template->email_subject, $data, $variables),
            'email_body' => $this->renderer->render($template->email_body, $data, $variables),
        ];

        $delivered = 0;

        foreach ($templateChannels as $channel) {
            if (! $this->preferences->isEnabled($user, $template->key, $channel, $template->is_mandatory)) {
                $this->recordDelivery($user, $template, $channel, NotificationDeliveryStatus::Skipped, error: 'Preference disabled');

                continue;
            }

            if (! $this->channelEnabled($channel)) {
                $this->recordDelivery($user, $template, $channel, NotificationDeliveryStatus::Skipped, error: 'Channel disabled');

                continue;
            }

            try {
                $this->deliverChannel($user, $template, $channel, $content);
                $this->recordDelivery($user, $template, $channel, NotificationDeliveryStatus::Sent);
                $delivered++;
            } catch (Throwable $exception) {
                $this->recordDelivery(
                    $user,
                    $template,
                    $channel,
                    NotificationDeliveryStatus::Failed,
                    error: $exception->getMessage(),
                );
            }
        }

        return $delivered;
    }

    /**
     * @param  array{title: string, body: string, email_subject: string, email_body: string}  $content
     *
     * @throws ValidationException
     */
    private function deliverChannel(User $user, NotificationTemplate $template, string $channel, array $content): void
    {
        $noticeChannel = NoticeChannel::from($channel);

        [$title, $body] = match ($noticeChannel) {
            NoticeChannel::InApp => [$content['title'], $content['body']],
            NoticeChannel::Mail => [
                $content['email_subject'] !== '' ? $content['email_subject'] : $content['title'],
                $content['email_body'] !== '' ? $content['email_body'] : $content['body'],
            ],
        };

        Notice::query()->create([
            'user_id' => $user->id,
            'title' => $title,
            'body' => $body,
            'channel' => $noticeChannel,
            'status' => NoticeStatus::Unread,
            'read_at' => null,
        ]);
    }

    private function recordDelivery(
        User $user,
        NotificationTemplate $template,
        string $channel,
        NotificationDeliveryStatus $status,
        ?string $error = null,
    ): void {
        NotificationDelivery::query()->create([
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->getKey(),
            'notification_key' => $template->key,
            'channel' => $channel,
            'status' => $status,
            'error' => $error,
            'sent_at' => $status === NotificationDeliveryStatus::Sent ? now() : null,
            'failed_at' => $status === NotificationDeliveryStatus::Failed ? now() : null,
        ]);
    }

    private function categoryAllowed(string $key): bool
    {
        return match (true) {
            str_starts_with($key, 'tenant.') => (bool) $this->settings->value('notifications.tenant_lifecycle_alerts', true),
            str_starts_with($key, 'auth.') => true,
            default => (bool) $this->settings->value('notifications.billing_alerts', true),
        };
    }

    private function channelEnabled(string $channel): bool
    {
        return match ($channel) {
            NoticeChannel::Mail->value => (bool) $this->settings->value('notifications.email_enabled', true),
            NoticeChannel::InApp->value => (bool) $this->settings->value('notifications.in_app_enabled', true),
            default => false,
        };
    }

    private function isQuietHours(): bool
    {
        if (! (bool) $this->settings->value('notifications.quiet_hours_enabled', false)) {
            return false;
        }

        $start = (string) $this->settings->value('notifications.quiet_hours_start', '22:00');
        $end = (string) $this->settings->value('notifications.quiet_hours_end', '07:00');
        $now = now()->format('H:i');

        if ($start <= $end) {
            return $now >= $start && $now < $end;
        }

        return $now >= $start || $now < $end;
    }
}
