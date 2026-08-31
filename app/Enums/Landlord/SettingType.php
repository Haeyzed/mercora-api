<?php

declare(strict_types=1);

namespace App\Enums\Landlord;

enum SettingType: string
{
    case String = 'string';
    case Boolean = 'boolean';
    case Integer = 'integer';
    case Json = 'json';

    public static function accepts(self $type, mixed $value): bool
    {
        return match ($type) {
            self::String => is_string($value),
            self::Boolean => is_bool($value),
            self::Integer => is_int($value),
            self::Json => is_array($value),
        };
    }
}
