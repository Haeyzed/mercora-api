<?php

declare(strict_types=1);

namespace App\Settings\Landlord;

use App\Enums\Landlord\SettingType;
use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingsSchema;

/**
 * Landlord tenant signup and onboarding defaults.
 *
 * Domain: controls for public tenant registration and first-plan assignment.
 */
final class RegistrationDomain implements SettingsSchema
{
    /**
     * Domain slug used in routes and storage group.
     */
    public function name(): string
    {
        return 'registration';
    }

    /**
     * Absolute dotted keys mapped to their typed definitions.
     *
     * @return array<string, SettingDefinition>
     */
    public function definitions(): array
    {
        return [
            'registration.tenant_registration_enabled' => new SettingDefinition(
                type: SettingType::Boolean,
                default: true,
                rules: ['sometimes', 'boolean'],
            ),
            'registration.require_email_verification' => new SettingDefinition(
                type: SettingType::Boolean,
                default: true,
                rules: ['sometimes', 'boolean'],
            ),
            'registration.require_terms_acceptance' => new SettingDefinition(
                type: SettingType::Boolean,
                default: true,
                rules: ['sometimes', 'boolean'],
            ),
            'registration.default_plan_slug' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'string', 'max:255'],
            ),
            'registration.trial_days' => new SettingDefinition(
                type: SettingType::Integer,
                default: 14,
                rules: ['sometimes', 'integer', 'min:0', 'max:365'],
            ),
            'registration.auto_provision_tenant' => new SettingDefinition(
                type: SettingType::Boolean,
                default: true,
                rules: ['sometimes', 'boolean'],
            ),
            'registration.send_welcome_email' => new SettingDefinition(
                type: SettingType::Boolean,
                default: true,
                rules: ['sometimes', 'boolean'],
            ),
            'registration.allowed_email_domains' => new SettingDefinition(
                type: SettingType::Json,
                default: [],
                rules: ['sometimes', 'array'],
            ),
        ];
    }
}
