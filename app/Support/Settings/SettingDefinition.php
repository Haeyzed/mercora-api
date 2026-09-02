<?php

declare(strict_types=1);

namespace App\Support\Settings;

use App\Enums\Landlord\SettingType;

/**
 * Typed definition for a single setting key within a domain schema.
 *
 * Domain: schema metadata used by {@see SettingsManager} for cast, default, and validation.
 */
final readonly class SettingDefinition
{
    /**
     * @param  list<mixed>  $rules  Laravel validation rules applied on write.
     */
    public function __construct(
        public SettingType $type,
        public mixed $default = null,
        public bool $nullable = false,
        public array $rules = [],
    ) {}
}
