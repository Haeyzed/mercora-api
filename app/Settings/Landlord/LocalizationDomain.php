<?php

declare(strict_types=1);

namespace App\Settings\Landlord;

use App\Enums\Landlord\SettingType;
use App\Support\Settings\SettingDefinition;
use App\Support\Settings\SettingsSchema;
use Illuminate\Validation\Rule;

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
                rules: ['sometimes', 'string', 'max:32', Rule::in($this->dateFormats())],
            ),
            'localization.time_format' => new SettingDefinition(
                type: SettingType::String,
                default: 'H:i',
                rules: ['sometimes', 'string', 'max:32', Rule::in($this->timeFormats())],
            ),
            'localization.datetime_format' => new SettingDefinition(
                type: SettingType::String,
                default: 'Y-m-d H:i',
                rules: ['sometimes', 'string', 'max:64', Rule::in($this->dateTimeFormats())],
            ),
            'localization.display_date_format' => new SettingDefinition(
                type: SettingType::String,
                default: 'M j, Y',
                rules: ['sometimes', 'string', 'max:64', Rule::in($this->displayDateFormats())],
            ),
            'localization.first_day_of_week' => new SettingDefinition(
                type: SettingType::Integer,
                default: 1,
                rules: ['sometimes', 'integer', 'min:0', 'max:6'],
            ),
            'localization.week_numbering' => new SettingDefinition(
                type: SettingType::String,
                default: 'iso',
                rules: ['sometimes', 'string', Rule::in(['iso', 'us'])],
            ),
            'localization.number_decimal_separator' => new SettingDefinition(
                type: SettingType::String,
                default: '.',
                rules: ['sometimes', 'string', 'size:1', Rule::in(['.', ','])],
            ),
            'localization.number_thousands_separator' => new SettingDefinition(
                type: SettingType::String,
                default: ',',
                rules: ['sometimes', 'string', 'size:1', Rule::in([',', '.', ' ', "'"])],
            ),
            'localization.currency_display' => new SettingDefinition(
                type: SettingType::String,
                default: 'symbol',
                rules: ['sometimes', 'string', Rule::in(['symbol', 'code', 'name'])],
            ),
        ];
    }

    /**
     * Allowed PHP date() patterns for compact calendar dates.
     *
     * @return list<string>
     */
    private function dateFormats(): array
    {
        return [
            'Y-m-d',
            'd/m/Y',
            'd-m-Y',
            'm/d/Y',
            'm-d-Y',
            'd.m.Y',
            'Y/m/d',
        ];
    }

    /**
     * Allowed PHP date() patterns for clock times.
     *
     * @return list<string>
     */
    private function timeFormats(): array
    {
        return [
            'H:i',
            'H:i:s',
            'h:i A',
            'h:i:s A',
            'g:i a',
        ];
    }

    /**
     * Allowed PHP date() patterns for date+time values.
     *
     * @return list<string>
     */
    private function dateTimeFormats(): array
    {
        return [
            'Y-m-d H:i',
            'Y-m-d H:i:s',
            'd/m/Y H:i',
            'd/m/Y H:i:s',
            'd-m-Y H:i',
            'm/d/Y h:i A',
            'd.m.Y H:i',
            'M j, Y g:i a',
        ];
    }

    /**
     * Allowed PHP date() patterns for human-readable dates in UI copy.
     *
     * @return list<string>
     */
    private function displayDateFormats(): array
    {
        return [
            'M j, Y',
            'j M Y',
            'F j, Y',
            'j F Y',
            'D, M j, Y',
            'l, F j, Y',
            'd/m/Y',
            'Y-m-d',
        ];
    }
}
