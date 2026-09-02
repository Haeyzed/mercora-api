<?php

declare(strict_types=1);

namespace App\Enums\Media;

enum MediaConversion: string
{
    case Thumb = 'thumb';
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';
}
