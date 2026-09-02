<?php

declare(strict_types=1);

namespace App\Http\Resources\Shared\World;

/**
 * Label/value option row wrapped by {@see OptionResource}.
 */
final readonly class Option
{
    public function __construct(
        public string $label,
        public int|string $value,
    ) {}
}
