<?php

declare(strict_types=1);

namespace Database\Seeders\Landlord;

use App\Enums\Landlord\NoticeChannel;
use App\Models\Landlord\NotificationTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds lifecycle notification templates used by the landlord dispatcher.
 */
class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $channels = [NoticeChannel::InApp->value, NoticeChannel::Mail->value];

        $templates = [
            [
                'key' => 'payment.successful',
                'name' => 'Payment Successful',
                'description' => 'Fan-out when a payment is marked successful.',
                'channels' => $channels,
                'variables' => ['reference', 'amount', 'currency'],
                'title' => 'Payment successful',
                'body' => 'Payment {{reference}} for {{amount}} {{currency}} was marked successful.',
                'email_subject' => 'Payment successful',
                'email_body' => 'Payment {{reference}} for {{amount}} {{currency}} was marked successful.',
                'is_mandatory' => false,
            ],
            [
                'key' => 'payment.refunded',
                'name' => 'Payment Refunded',
                'description' => 'Fan-out when a payment is refunded.',
                'channels' => $channels,
                'variables' => ['reference', 'amount', 'currency'],
                'title' => 'Payment refunded',
                'body' => 'Payment {{reference}} for {{amount}} {{currency}} was refunded.',
                'email_subject' => 'Payment refunded',
                'email_body' => 'Payment {{reference}} for {{amount}} {{currency}} was refunded.',
                'is_mandatory' => false,
            ],
            [
                'key' => 'subscription.past_due',
                'name' => 'Subscription Past Due',
                'description' => 'Fan-out when a subscription becomes past due.',
                'channels' => $channels,
                'variables' => ['subscription_id', 'tenant_name'],
                'title' => 'Subscription past due',
                'body' => 'Subscription #{{subscription_id}} for {{tenant_name}} is past due. A renewal invoice was issued.',
                'email_subject' => 'Subscription past due',
                'email_body' => 'Subscription #{{subscription_id}} for {{tenant_name}} is past due. A renewal invoice was issued.',
                'is_mandatory' => false,
            ],
            [
                'key' => 'subscription.canceled',
                'name' => 'Subscription Canceled',
                'description' => 'Fan-out when a subscription is canceled.',
                'channels' => $channels,
                'variables' => ['subscription_id', 'tenant_name'],
                'title' => 'Subscription canceled',
                'body' => 'Subscription #{{subscription_id}} for {{tenant_name}} was canceled.',
                'email_subject' => 'Subscription canceled',
                'email_body' => 'Subscription #{{subscription_id}} for {{tenant_name}} was canceled.',
                'is_mandatory' => false,
            ],
            [
                'key' => 'invoice.due_soon',
                'name' => 'Invoice Due Soon',
                'description' => 'Reminder when an open invoice is approaching its due date.',
                'channels' => $channels,
                'variables' => ['invoice_number', 'tenant_name', 'due_date'],
                'title' => 'Invoice due soon',
                'body' => 'Invoice {{invoice_number}} for {{tenant_name}} is due on {{due_date}}.',
                'email_subject' => 'Invoice due soon',
                'email_body' => 'Invoice {{invoice_number}} for {{tenant_name}} is due on {{due_date}}.',
                'is_mandatory' => false,
            ],
            [
                'key' => 'invoice.overdue',
                'name' => 'Invoice Overdue',
                'description' => 'Reminder when an open invoice is overdue.',
                'channels' => $channels,
                'variables' => ['invoice_number', 'tenant_name', 'days_overdue'],
                'title' => 'Invoice overdue',
                'body' => 'Invoice {{invoice_number}} for {{tenant_name}} is overdue by {{days_overdue}} day(s).',
                'email_subject' => 'Invoice overdue',
                'email_body' => 'Invoice {{invoice_number}} for {{tenant_name}} is overdue by {{days_overdue}} day(s).',
                'is_mandatory' => false,
            ],
            [
                'key' => 'subscription.renewing_soon',
                'name' => 'Subscription Renewing Soon',
                'description' => 'Reminder when a subscription is approaching renewal.',
                'channels' => $channels,
                'variables' => ['subscription_id', 'tenant_name', 'renews_on'],
                'title' => 'Subscription renewing soon',
                'body' => 'Subscription #{{subscription_id}} for {{tenant_name}} renews on {{renews_on}}.',
                'email_subject' => 'Subscription renewing soon',
                'email_body' => 'Subscription #{{subscription_id}} for {{tenant_name}} renews on {{renews_on}}.',
                'is_mandatory' => false,
            ],
            [
                'key' => 'subscription.dunning',
                'name' => 'Subscription Dunning',
                'description' => 'Dunning reminder for an outstanding subscription payment.',
                'channels' => $channels,
                'variables' => ['attempt', 'subscription_id', 'tenant_name'],
                'title' => 'Payment dunning reminder',
                'body' => 'Dunning attempt {{attempt}} for subscription #{{subscription_id}} ({{tenant_name}}). Payment is still outstanding.',
                'email_subject' => 'Payment dunning reminder',
                'email_body' => 'Dunning attempt {{attempt}} for subscription #{{subscription_id}} ({{tenant_name}}). Payment is still outstanding.',
                'is_mandatory' => false,
            ],
            [
                'key' => 'tenant.suspended',
                'name' => 'Tenant Suspended',
                'description' => 'Fan-out when a tenant is suspended.',
                'channels' => $channels,
                'variables' => ['tenant_name'],
                'title' => 'Tenant suspended',
                'body' => 'Tenant {{tenant_name}} was suspended.',
                'email_subject' => 'Tenant suspended',
                'email_body' => 'Tenant {{tenant_name}} was suspended.',
                'is_mandatory' => false,
            ],
            [
                'key' => 'auth.welcome',
                'name' => 'Welcome',
                'description' => 'Welcome notice for newly registered landlord users.',
                'channels' => $channels,
                'variables' => [],
                'title' => 'Welcome to Mercora',
                'body' => 'Your landlord account and tenant workspace are ready.',
                'email_subject' => 'Welcome to Mercora',
                'email_body' => 'Your landlord account and tenant workspace are ready.',
                'is_mandatory' => true,
            ],
        ];

        foreach ($templates as $template) {
            NotificationTemplate::query()->updateOrCreate(
                ['key' => $template['key']],
                [
                    ...$template,
                    'is_active' => true,
                ],
            );
        }
    }
}
