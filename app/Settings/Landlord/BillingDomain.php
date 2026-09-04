<?php

declare(strict_types=1);

namespace App\Settings\Landlord;

use App\Enums\Landlord\SettingType;
use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingsSchema;

/**
 * Landlord billing and invoice presentation defaults.
 *
 * Domain: non-secret billing knobs (prefixes, grace periods, tax display). Payment provider secrets stay in env/config.
 */
final class BillingDomain implements SettingsSchema
{
    /**
     * Domain slug used in routes and storage group.
     */
    public function name(): string
    {
        return 'billing';
    }

    /**
     * Absolute dotted keys mapped to their typed definitions.
     *
     * @return array<string, SettingDefinition>
     */
    public function definitions(): array
    {
        return [
            'billing.invoice_prefix' => new SettingDefinition(
                type: SettingType::String,
                default: 'INV',
                rules: ['sometimes', 'string', 'max:20'],
            ),
            'billing.invoice_suffix' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'string', 'max:20'],
            ),
            'billing.invoice_footer' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'string', 'max:2000'],
            ),
            'billing.invoice_memo' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'string', 'max:1000'],
            ),
            'billing.grace_days' => new SettingDefinition(
                type: SettingType::Integer,
                default: 3,
                rules: ['sometimes', 'integer', 'min:0', 'max:90'],
            ),
            'billing.reminder_days_before_due' => new SettingDefinition(
                type: SettingType::Integer,
                default: 3,
                rules: ['sometimes', 'integer', 'min:0', 'max:30'],
            ),
            'billing.overdue_reminder_days' => new SettingDefinition(
                type: SettingType::Integer,
                default: 7,
                rules: ['sometimes', 'integer', 'min:1', 'max:90'],
            ),
            'billing.tax_enabled' => new SettingDefinition(
                type: SettingType::Boolean,
                default: false,
                rules: ['sometimes', 'boolean'],
            ),
            'billing.default_tax_rate' => new SettingDefinition(
                type: SettingType::String,
                default: '0',
                rules: ['sometimes', 'numeric', 'min:0', 'max:100'],
            ),
            'billing.tax_inclusive' => new SettingDefinition(
                type: SettingType::Boolean,
                default: false,
                rules: ['sometimes', 'boolean'],
            ),
            'billing.company_name' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'string', 'max:255'],
            ),
            'billing.company_address' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'string', 'max:1000'],
            ),
            'billing.company_vat_number' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'string', 'max:100'],
            ),
            'billing.company_email' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'email', 'max:255'],
            ),
            'billing.statement_descriptor' => new SettingDefinition(
                type: SettingType::String,
                default: 'MERCORA',
                rules: ['sometimes', 'string', 'max:22'],
            ),
        ];
    }
}
