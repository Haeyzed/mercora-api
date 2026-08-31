<?php

declare(strict_types=1);

namespace App\Enums\Landlord;

enum NoticeChannel: string
{
    case InApp = 'in_app';
    case Mail = 'mail';
}
