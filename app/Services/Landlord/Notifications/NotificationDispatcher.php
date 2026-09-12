<?php

declare(strict_types=1);

namespace App\Services\Landlord\Notifications;

use App\Enums\Landlord\NotificationDeliveryStatus;
use App\Models\Landlord\Notice;
use App\Models\Landlord\NotificationDelivery;
use App\Models\Landlord\User;
use App\Services\Landlord\SettingService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers templated notifications through ChannelResolver adapters.
 *
 * Domain: landlord lifecycle alerts keyed by notification_templates.key.
 *
 * Invariants:
 * - Missing/inactive templates are no-ops (logged).
 * - Category kill-switches: billing_alerts, tenant_lifecycle_alerts.
 * - Channel kill-switches and preferences are applied in ChannelResolver.
 * - Quiet hours skip non-mandatory templates.
 * - InApp and Mail write Notice rows; Push and SMS audit skipped deliveries until providers exist.
 * - Never throws for policy gates.
 *
 * Side effects: creates {@see Notice} and {@see NotificationDelivery} rows.
 */
class NotificationDispatcher
{
    public function __construct(
        private NotificationTemplateService $templates,
        private TemplateRenderer $renderer,
        private ChannelResolver $channels,
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

        $payload = new NotificationPayload(
            key: $template->key,
            data: $data,
            channels: $templateChannels,
            content: [
                'title' => $this->renderer->render($template->title, $data, $variables),
                'body' => $this->renderer->render($template->body, $data, $variables),
                'email_subject' => $this->renderer->render($template->email_subject, $data, $variables),
                'email_body' => $this->renderer->render($template->email_body, $data, $variables),
                'push_title' => $this->renderer->render($template->push_title, $data, $variables),
                'push_body' => $this->renderer->render($template->push_body, $data, $variables),
                'sms_body' => $this->renderer->render($template->sms_body, $data, $variables),
            ],
            isMandatory: $template->is_mandatory,
        );

        $delivered = 0;

        foreach ($this->channels->resolve($user, $template->key, $templateChannels, $template->is_mandatory) as $channel) {
            try {
                $delivery = $channel->send($user, $payload);

                if ($delivery->status === NotificationDeliveryStatus::Sent) {
                    $delivered++;
                }
            } catch (Throwable $exception) {
                Log::warning('Notification channel failed', [
                    'key' => $template->key,
                    'channel' => $channel->name()->value,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $delivered;
    }

    private function categoryAllowed(string $key): bool
    {
        return match (true) {
            str_starts_with($key, 'tenant.') => (bool) $this->settings->value('notifications.tenant_lifecycle_alerts', true),
            str_starts_with($key, 'auth.') => true,
            default => (bool) $this->settings->value('notifications.billing_alerts', true),
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
