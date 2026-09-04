<?php

declare(strict_types=1);

namespace App\Settings\Landlord;

use App\Enums\Landlord\SettingType;
use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingsSchema;

/**
 * Landlord API access and machine-credential policy.
 *
 * Domain: defaults for API keys and programmatic rate limits (not provider secrets).
 */
final class ApiDomain implements SettingsSchema
{
    /**
     * Domain slug used in routes and storage group.
     */
    public function name(): string
    {
        return 'api';
    }

    /**
     * Absolute dotted keys mapped to their typed definitions.
     *
     * @return array<string, SettingDefinition>
     */
    public function definitions(): array
    {
        return [
            'api.keys_enabled' => new SettingDefinition(
                type: SettingType::Boolean,
                default: true,
                rules: ['sometimes', 'boolean'],
            ),
            'api.max_keys_per_user' => new SettingDefinition(
                type: SettingType::Integer,
                default: 10,
                rules: ['sometimes', 'integer', 'min:1', 'max:100'],
            ),
            'api.default_key_ttl_days' => new SettingDefinition(
                type: SettingType::Integer,
                default: 365,
                nullable: false,
                rules: ['sometimes', 'integer', 'min:0', 'max:3650'],
            ),
            'api.require_key_expiry' => new SettingDefinition(
                type: SettingType::Boolean,
                default: false,
                rules: ['sometimes', 'boolean'],
            ),
            'api.rate_limit_per_minute' => new SettingDefinition(
                type: SettingType::Integer,
                default: 60,
                rules: ['sometimes', 'integer', 'min:1', 'max:10000'],
            ),
            'api.burst_limit' => new SettingDefinition(
                type: SettingType::Integer,
                default: 120,
                rules: ['sometimes', 'integer', 'min:1', 'max:20000'],
            ),
            'api.log_requests' => new SettingDefinition(
                type: SettingType::Boolean,
                default: true,
                rules: ['sometimes', 'boolean'],
            ),
        ];
    }
}
