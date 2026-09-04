<?php

declare(strict_types=1);

namespace App\Settings\Landlord;

use App\Enums\Landlord\SettingType;
use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingsSchema;

/**
 * Landlord tenant provisioning and domain policy defaults.
 *
 * Domain: limits and flags applied when creating or managing tenants.
 */
final class TenancyDomain implements SettingsSchema
{
    /**
     * Domain slug used in routes and storage group.
     */
    public function name(): string
    {
        return 'tenancy';
    }

    /**
     * Absolute dotted keys mapped to their typed definitions.
     *
     * @return array<string, SettingDefinition>
     */
    public function definitions(): array
    {
        return [
            'tenancy.allow_custom_domains' => new SettingDefinition(
                type: SettingType::Boolean,
                default: true,
                rules: ['sometimes', 'boolean'],
            ),
            'tenancy.allow_subdomains' => new SettingDefinition(
                type: SettingType::Boolean,
                default: true,
                rules: ['sometimes', 'boolean'],
            ),
            'tenancy.default_domain_suffix' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'string', 'max:255'],
            ),
            'tenancy.max_domains_per_tenant' => new SettingDefinition(
                type: SettingType::Integer,
                default: 5,
                rules: ['sometimes', 'integer', 'min:1', 'max:100'],
            ),
            'tenancy.soft_delete_retention_days' => new SettingDefinition(
                type: SettingType::Integer,
                default: 30,
                rules: ['sometimes', 'integer', 'min:1', 'max:365'],
            ),
            'tenancy.provisioning_queue' => new SettingDefinition(
                type: SettingType::String,
                default: 'default',
                rules: ['sometimes', 'string', 'max:100'],
            ),
            'tenancy.require_https' => new SettingDefinition(
                type: SettingType::Boolean,
                default: true,
                rules: ['sometimes', 'boolean'],
            ),
            'tenancy.max_concurrent_provisions' => new SettingDefinition(
                type: SettingType::Integer,
                default: 5,
                rules: ['sometimes', 'integer', 'min:1', 'max:50'],
            ),
        ];
    }
}
