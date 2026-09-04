<?php

declare(strict_types=1);

namespace App\Settings\Landlord;

use App\Enums\Landlord\SettingType;
use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingsSchema;

/**
 * Landlord compliance, retention, and audit policy.
 *
 * Domain: data-lifecycle knobs for GDPR-style retention and activity logs.
 */
final class ComplianceDomain implements SettingsSchema
{
    /**
     * Domain slug used in routes and storage group.
     */
    public function name(): string
    {
        return 'compliance';
    }

    /**
     * Absolute dotted keys mapped to their typed definitions.
     *
     * @return array<string, SettingDefinition>
     */
    public function definitions(): array
    {
        return [
            'compliance.activity_log_retention_days' => new SettingDefinition(
                type: SettingType::Integer,
                default: 365,
                rules: ['sometimes', 'integer', 'min:30', 'max:3650'],
            ),
            'compliance.soft_deleted_user_retention_days' => new SettingDefinition(
                type: SettingType::Integer,
                default: 90,
                rules: ['sometimes', 'integer', 'min:7', 'max:730'],
            ),
            'compliance.export_personal_data_enabled' => new SettingDefinition(
                type: SettingType::Boolean,
                default: true,
                rules: ['sometimes', 'boolean'],
            ),
            'compliance.erase_personal_data_enabled' => new SettingDefinition(
                type: SettingType::Boolean,
                default: true,
                rules: ['sometimes', 'boolean'],
            ),
            'compliance.require_cookie_consent' => new SettingDefinition(
                type: SettingType::Boolean,
                default: false,
                rules: ['sometimes', 'boolean'],
            ),
            'compliance.data_processing_agreement_url' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'url', 'max:255'],
            ),
            'compliance.privacy_contact_email' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'email', 'max:255'],
            ),
            'compliance.mask_pii_in_logs' => new SettingDefinition(
                type: SettingType::Boolean,
                default: true,
                rules: ['sometimes', 'boolean'],
            ),
        ];
    }
}
