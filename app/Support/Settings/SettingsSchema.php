<?php

declare(strict_types=1);

namespace App\Support\Settings;

/**
 * Named domain of typed setting keys with defaults and validation rules.
 *
 * Domain: contract implemented by each settings domain class (for example landlord platform settings).
 */
interface SettingsSchema
{
    /**
     * Domain slug used in routes and as the settings group column.
     */
    public function name(): string;

    /**
     * Absolute dotted keys mapped to their typed definitions.
     *
     * @return array<string, SettingDefinition>
     */
    public function definitions(): array;
}
