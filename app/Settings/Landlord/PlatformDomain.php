<?php

declare(strict_types=1);

namespace App\Settings\Landlord;

use App\Enums\Landlord\SettingType;
use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingsSchema;

/**
 * Landlord platform branding and operational flags.
 *
 * Domain: public-facing platform identity and maintenance controls.
 */
final class PlatformDomain implements SettingsSchema
{
    /**
     * Domain slug used in routes and storage group.
     */
    public function name(): string
    {
        return 'platform';
    }

    /**
     * Absolute dotted keys mapped to their typed definitions.
     *
     * @return array<string, SettingDefinition>
     */
    public function definitions(): array
    {
        return [
            'platform.name' => new SettingDefinition(
                type: SettingType::String,
                default: 'Mercora',
                rules: ['sometimes', 'string', 'max:255'],
            ),
            'platform.tagline' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'string', 'max:255'],
            ),
            'platform.support_email' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'email', 'max:255'],
            ),
            'platform.support_phone' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'string', 'max:50'],
            ),
            'platform.support_hours' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'string', 'max:255'],
            ),
            'platform.website_url' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'url', 'max:255'],
            ),
            'platform.terms_url' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'url', 'max:255'],
            ),
            'platform.privacy_url' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'url', 'max:255'],
            ),
            'platform.help_center_url' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'url', 'max:255'],
            ),
            'platform.copyright_text' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'string', 'max:255'],
            ),
            'platform.primary_color' => new SettingDefinition(
                type: SettingType::String,
                default: '#0F172A',
                rules: ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            ),
            'platform.secondary_color' => new SettingDefinition(
                type: SettingType::String,
                default: '#2563EB',
                rules: ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            ),
            'platform.logo_url' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'url', 'max:255'],
            ),
            'platform.favicon_url' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'url', 'max:255'],
            ),
            'platform.maintenance_mode' => new SettingDefinition(
                type: SettingType::Boolean,
                default: false,
                rules: ['sometimes', 'boolean'],
            ),
            'platform.maintenance_message' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'string', 'max:1000'],
            ),
            'platform.allow_status_page' => new SettingDefinition(
                type: SettingType::Boolean,
                default: true,
                rules: ['sometimes', 'boolean'],
            ),
        ];
    }
}
