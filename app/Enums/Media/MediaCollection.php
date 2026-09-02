<?php

declare(strict_types=1);

namespace App\Enums\Media;

enum MediaCollection: string
{
    case Avatar = 'avatar';

    public function isSingleFile(): bool
    {
        return $this === self::Avatar;
    }
}
