<?php

declare(strict_types=1);

namespace App\Settings\Landlord;

use App\Enums\Landlord\SettingType;
use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingsSchema;
use Illuminate\Validation\Rule;

/**
 * Landlord subscription lifecycle defaults.
 *
 * Domain: how plans renew, cancel, and enter dunning (payment secrets stay in env/config).
 */
final class SubscriptionsDomain implements SettingsSchema
{
    /**
     * Domain slug used in routes and storage group.
     */
    public function name(): string
    {
        return 'subscriptions';
    }

    /**
     * Absolute dotted keys mapped to their typed definitions.
     *
     * @return array<string, SettingDefinition>
     */
    public function definitions(): array
    {
        return [
            'subscriptions.allow_plan_changes' => new SettingDefinition(
                type: SettingType::Boolean,
                default: true,
                rules: ['sometimes', 'boolean'],
            ),
            'subscriptions.prorate_plan_changes' => new SettingDefinition(
                type: SettingType::Boolean,
                default: true,
                rules: ['sometimes', 'boolean'],
            ),
            'subscriptions.cancel_at_period_end' => new SettingDefinition(
                type: SettingType::Boolean,
                default: true,
                rules: ['sometimes', 'boolean'],
            ),
            'subscriptions.allow_immediate_cancel' => new SettingDefinition(
                type: SettingType::Boolean,
                default: false,
                rules: ['sometimes', 'boolean'],
            ),
            'subscriptions.dunning_enabled' => new SettingDefinition(
                type: SettingType::Boolean,
                default: true,
                rules: ['sometimes', 'boolean'],
            ),
            'subscriptions.dunning_attempts' => new SettingDefinition(
                type: SettingType::Integer,
                default: 3,
                rules: ['sometimes', 'integer', 'min:0', 'max:10'],
            ),
            'subscriptions.dunning_interval_days' => new SettingDefinition(
                type: SettingType::Integer,
                default: 3,
                rules: ['sometimes', 'integer', 'min:1', 'max:30'],
            ),
            'subscriptions.past_due_suspend_after_days' => new SettingDefinition(
                type: SettingType::Integer,
                default: 14,
                rules: ['sometimes', 'integer', 'min:1', 'max:90'],
            ),
            'subscriptions.renewal_reminder_days' => new SettingDefinition(
                type: SettingType::Integer,
                default: 7,
                rules: ['sometimes', 'integer', 'min:0', 'max:60'],
            ),
            'subscriptions.default_billing_interval' => new SettingDefinition(
                type: SettingType::String,
                default: 'month',
                rules: ['sometimes', 'string', Rule::in(['day', 'week', 'month', 'year'])],
            ),
        ];
    }
}
