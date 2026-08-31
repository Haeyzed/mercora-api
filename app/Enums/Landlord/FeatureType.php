<?php

declare(strict_types=1);

namespace App\Enums\Landlord;

use App\Models\Landlord\Feature;

/**
 * Value type for plan feature entitlements on the plan_features pivot.
 *
 * Determines how {@see Feature} pivot values are parsed
 * when resolving tenant entitlements (boolean flags, numeric limits, strings,
 * or unlimited grants).
 */
enum FeatureType: string
{
    case Boolean = 'boolean';
    case Integer = 'integer';
    case String = 'string';
    case Unlimited = 'unlimited';
}
