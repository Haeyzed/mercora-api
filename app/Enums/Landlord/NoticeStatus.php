<?php

declare(strict_types=1);

namespace App\Enums\Landlord;

enum NoticeStatus: string
{
    case Unread = 'unread';
    case Read = 'read';
}
