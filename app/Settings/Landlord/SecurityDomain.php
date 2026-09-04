<?php

declare(strict_types=1);

namespace App\Settings\Landlord;

use App\Enums\Landlord\SettingType;
use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingsSchema;

/**
 * Landlord security and session policy knobs.
 *
 * Domain: non-secret auth hardening defaults for the landlord panel.
 */
final class SecurityDomain implements SettingsSchema
{
    /**
     * Domain slug used in routes and storage group.
     */
    public function name(): string
    {
        return 'security';
    }

    /**
     * Absolute dotted keys mapped to their typed definitions.
     *
     * @return array<string, SettingDefinition>
     */
    public function definitions(): array
    {
        return [
            'security.password_min_length' => new SettingDefinition(
                type: SettingType::Integer,
                default: 8,
                rules: ['sometimes', 'integer', 'min:8', 'max:128'],
            ),
            'security.session_timeout_minutes' => new SettingDefinition(
                type: SettingType::Integer,
                default: 120,
                rules: ['sometimes', 'integer', 'min:5', 'max:10080'],
            ),
            'security.idle_timeout_minutes' => new SettingDefinition(
                type: SettingType::Integer,
                default: 30,
                rules: ['sometimes', 'integer', 'min:1', 'max:1440'],
            ),
            'security.max_login_attempts' => new SettingDefinition(
                type: SettingType::Integer,
                default: 5,
                rules: ['sometimes', 'integer', 'min:1', 'max:50'],
            ),
            'security.lockout_minutes' => new SettingDefinition(
                type: SettingType::Integer,
                default: 15,
                rules: ['sometimes', 'integer', 'min:1', 'max:1440'],
            ),
            'security.require_strong_passwords' => new SettingDefinition(
                type: SettingType::Boolean,
                default: false,
                rules: ['sometimes', 'boolean'],
            ),
            'security.revoke_tokens_on_password_change' => new SettingDefinition(
                type: SettingType::Boolean,
                default: true,
                rules: ['sometimes', 'boolean'],
            ),
            'security.require_email_verification_for_admins' => new SettingDefinition(
                type: SettingType::Boolean,
                default: false,
                rules: ['sometimes', 'boolean'],
            ),
        ];
    }
}
