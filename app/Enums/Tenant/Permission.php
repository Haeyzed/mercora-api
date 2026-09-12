<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum Permission: string
{
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
