<?php

declare(strict_types=1);

namespace App\Settings\Landlord;

use App\Enums\Landlord\SettingType;
use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingsSchema;
use Illuminate\Validation\Rule;

/**
 * Landlord notification delivery preferences.
 *
 * Domain: which platform events emit mail/in-app notices and how digests run.
 */
final class NotificationsDomain implements SettingsSchema
{
    /**
     * Domain slug used in routes and storage group.
     */
    public function name(): string
    {
        return 'notifications';
    }

    /**
     * Absolute dotted keys mapped to their typed definitions.
     *
     * @return array<string, SettingDefinition>
     */
    public function definitions(): array
    {
        return [
            'notifications.email_enabled' => new SettingDefinition(
                type: SettingType::Boolean,
                default: true,
                rules: ['sometimes', 'boolean'],
            ),
            'notifications.in_app_enabled' => new SettingDefinition(
                type: SettingType::Boolean,
                default: true,
                rules: ['sometimes', 'boolean'],
            ),
            'notifications.billing_alerts' => new SettingDefinition(
                type: SettingType::Boolean,
                default: true,
                rules: ['sometimes', 'boolean'],
            ),
            'notifications.tenant_lifecycle_alerts' => new SettingDefinition(
                type: SettingType::Boolean,
                default: true,
                rules: ['sometimes', 'boolean'],
            ),
            'notifications.security_alerts' => new SettingDefinition(
                type: SettingType::Boolean,
                default: true,
                rules: ['sometimes', 'boolean'],
            ),
            'notifications.digest_enabled' => new SettingDefinition(
                type: SettingType::Boolean,
                default: false,
                rules: ['sometimes', 'boolean'],
            ),
            'notifications.digest_frequency' => new SettingDefinition(
                type: SettingType::String,
                default: 'daily',
                rules: ['sometimes', 'string', Rule::in(['hourly', 'daily', 'weekly'])],
            ),
            'notifications.slack_webhook_enabled' => new SettingDefinition(
                type: SettingType::Boolean,
                default: false,
                rules: ['sometimes', 'boolean'],
            ),
            'notifications.admin_digest_recipients' => new SettingDefinition(
                type: SettingType::Json,
                default: [],
                rules: ['sometimes', 'array'],
            ),
            'notifications.quiet_hours_enabled' => new SettingDefinition(
                type: SettingType::Boolean,
                default: false,
                rules: ['sometimes', 'boolean'],
            ),
            'notifications.quiet_hours_start' => new SettingDefinition(
                type: SettingType::String,
                default: '22:00',
                rules: ['sometimes', 'string', 'date_format:H:i'],
            ),
            'notifications.quiet_hours_end' => new SettingDefinition(
                type: SettingType::String,
                default: '07:00',
                rules: ['sometimes', 'string', 'date_format:H:i'],
            ),
        ];
    }
}
