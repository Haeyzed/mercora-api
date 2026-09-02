<?php

declare(strict_types=1);

namespace App\Settings\Landlord;

use App\Enums\Landlord\SettingType;
use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingsSchema;

/**
 * Landlord default locale, currency, and regional preferences.
 *
 * Domain: defaults applied when creating tenants or rendering landlord UI.
 */
final class LocalizationDomain implements SettingsSchema
{
    /**
     * Domain slug used in routes and storage group.
     */
    public function name(): string
    {
        return 'localization';
    }

    /**
     * Absolute dotted keys mapped to their typed definitions.
     *
     * @return array<string, SettingDefinition>
     */
    public function definitions(): array
    {
        return [
            'localization.default_currency' => new SettingDefinition(
                type: SettingType::String,
                default: 'USD',
                rules: ['sometimes', 'string', 'size:3'],
            ),
            'localization.default_timezone' => new SettingDefinition(
                type: SettingType::String,
                default: 'UTC',
                rules: ['sometimes', 'string', 'timezone:all', 'max:100'],
            ),
            'localization.default_language' => new SettingDefinition(
                type: SettingType::String,
                default: 'en',
                rules: ['sometimes', 'string', 'size:2'],
            ),
            'localization.default_country' => new SettingDefinition(
                type: SettingType::String,
                default: null,
                nullable: true,
                rules: ['sometimes', 'nullable', 'string', 'size:2'],
            ),
            'localization.date_format' => new SettingDefinition(
                type: SettingType::String,
                default: 'Y-m-d',
                rules: ['sometimes', 'string', 'max:32'],
            ),
            'localization.time_format' => new SettingDefinition(
                type: SettingType::String,
                default: 'H:i',
                rules: ['sometimes', 'string', 'max:32'],
            ),
        ];
    }
}
