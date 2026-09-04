<?php

declare(strict_types=1);

namespace App\Services\Landlord;

use App\Enums\Landlord\InvoiceStatus;
use App\Enums\Landlord\SubscriptionStatus;
use App\Models\Landlord\Invoice;
use App\Models\Landlord\Subscription;
use Illuminate\Support\Facades\Cache;

/**
 * Scheduled billing reminders and dunning for landlord subscriptions/invoices.
 *
 * Domain: ops-side collection notices driven by billing and subscription settings.
 *
 * Invariants:
 * - Reminders and dunning only emit when {@see notifications.billing_alerts} allows (via NoticeService).
 * - Each reminder is idempotent per invoice/subscription window via cache keys.
 * - Dunning increments {@see Subscription::$dunning_attempts} up to subscriptions.dunning_attempts.
 *
 * Side effects: creates notices; updates subscription dunning columns; reads SettingService.
 */
class BillingOpsService
{
    public function __construct(
        private SettingService $settings,
        private NoticeService $notices,
    ) {}

    /**
     * Send due-soon, overdue, and renewal reminders for the current day windows.
     *
     * @return array{due_soon: int, overdue: int, renewal: int}
     */
    public function sendReminders(): array
    {
        return [
            'due_soon' => $this->sendDueSoonReminders(),
            'overdue' => $this->sendOverdueReminders(),
            'renewal' => $this->sendRenewalReminders(),
        ];
    }

    /**
     * Notify for past-due subscriptions on the dunning cadence.
     *
     * @return int Number of subscriptions dunned in this run.
     */
    public function processDunning(): int
    {
        if (! (bool) $this->settings->value('subscriptions.dunning_enabled', true)) {
            return 0;
        }

        $maxAttempts = max(0, (int) $this->settings->value('subscriptions.dunning_attempts', 3));
        $intervalDays = max(1, (int) $this->settings->value('subscriptions.dunning_interval_days', 3));

        if ($maxAttempts === 0) {
            return 0;
        }

        $dunned = 0;

        Subscription::query()
            ->current()
            ->where('status', SubscriptionStatus::PastDue)
            ->where('dunning_attempts', '<', $maxAttempts)
            ->with('tenant')
            ->orderBy('id')
            ->each(function (Subscription $subscription) use ($intervalDays, &$dunned): void {
                if (! $this->isDueForDunning($subscription, $intervalDays)) {
                    return;
                }

                $tenantName = $subscription->tenant?->name ?? 'Unknown tenant';
                $attempt = $subscription->dunning_attempts + 1;

                $this->notices->notifyBillingAlert(
                    'Payment dunning reminder',
                    sprintf(
                        'Dunning attempt %d for subscription #%d (%s). Payment is still outstanding.',
                        $attempt,
                        $subscription->id,
                        $tenantName,
                    ),
                );

                $subscription->update([
                    'dunning_attempts' => $attempt,
                    'last_dunned_at' => now(),
                ]);

                $dunned++;
            });

        return $dunned;
    }

    private function sendDueSoonReminders(): int
    {
        $days = max(0, (int) $this->settings->value('billing.reminder_days_before_due', 3));
        $until = now()->addDays($days)->endOfDay();
        $sent = 0;

        Invoice::query()
            ->where('status', InvoiceStatus::Open)
            ->whereNotNull('due_at')
            ->where('due_at', '>', now())
            ->where('due_at', '<=', $until)
            ->with('tenant')
            ->orderBy('id')
            ->each(function (Invoice $invoice) use (&$sent): void {
                $key = sprintf(
                    'landlord.billing.reminder.due_soon.%d.%s',
                    $invoice->id,
                    $invoice->due_at?->timestamp ?? 0,
                );

                if (! Cache::add($key, true, now()->addDays(60))) {
                    return;
                }

                $tenantName = $invoice->tenant?->name ?? 'Unknown tenant';

                $this->notices->notifyBillingAlert(
                    'Invoice due soon',
                    sprintf(
                        'Invoice %s for %s is due on %s.',
                        $invoice->number,
                        $tenantName,
                        $invoice->due_at?->toDateString() ?? 'unknown',
                    ),
                );

                $sent++;
            });

        return $sent;
    }

    private function sendOverdueReminders(): int
    {
        $intervalDays = max(1, (int) $this->settings->value('billing.overdue_reminder_days', 7));
        $sent = 0;

        Invoice::query()
            ->where('status', InvoiceStatus::Open)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->with('tenant')
            ->orderBy('id')
            ->each(function (Invoice $invoice) use ($intervalDays, &$sent): void {
                $daysOverdue = max(1, (int) $invoice->due_at?->diffInDays(now()) ?: 1);
                $bucket = (int) floor($daysOverdue / $intervalDays);

                if ($bucket < 1) {
                    return;
                }

                $key = sprintf('landlord.billing.reminder.overdue.%d.%d', $invoice->id, $bucket);

                if (! Cache::add($key, true, now()->addDays(90))) {
                    return;
                }

                $tenantName = $invoice->tenant?->name ?? 'Unknown tenant';

                $this->notices->notifyBillingAlert(
                    'Invoice overdue',
                    sprintf(
                        'Invoice %s for %s is overdue by %d day(s).',
                        $invoice->number,
                        $tenantName,
                        $daysOverdue,
                    ),
                );

                $sent++;
            });

        return $sent;
    }

    private function sendRenewalReminders(): int
    {
        $days = max(0, (int) $this->settings->value('subscriptions.renewal_reminder_days', 7));
        $until = now()->addDays($days)->endOfDay();
        $sent = 0;

        Subscription::query()
            ->current()
            ->whereNull('canceled_at')
            ->where('status', SubscriptionStatus::Active)
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', now())
            ->where('ends_at', '<=', $until)
            ->with('tenant')
            ->orderBy('id')
            ->each(function (Subscription $subscription) use (&$sent): void {
                $key = sprintf(
                    'landlord.billing.reminder.renewal.%d.%s',
                    $subscription->id,
                    $subscription->ends_at?->timestamp ?? 0,
                );

                if (! Cache::add($key, true, now()->addDays(60))) {
                    return;
                }

                $tenantName = $subscription->tenant?->name ?? 'Unknown tenant';

                $this->notices->notifyBillingAlert(
                    'Subscription renewing soon',
                    sprintf(
                        'Subscription #%d for %s renews on %s.',
                        $subscription->id,
                        $tenantName,
                        $subscription->ends_at?->toDateString() ?? 'unknown',
                    ),
                );

                $sent++;
            });

        return $sent;
    }

    private function isDueForDunning(Subscription $subscription, int $intervalDays): bool
    {
        if ($subscription->last_dunned_at !== null) {
            return $subscription->last_dunned_at->lte(now()->subDays($intervalDays));
        }

        if ($subscription->ends_at === null) {
            return true;
        }

        return $subscription->ends_at->lte(now()->subDays($intervalDays));
    }
}
