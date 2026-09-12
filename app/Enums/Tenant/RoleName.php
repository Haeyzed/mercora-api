<?php

declare(strict_types=1);

namespace App\Enums\Tenant;

enum RoleName: string
{
    case Admin = 'Admin';
    case Staff = 'Staff';

    public function isProtected(): bool
    {
        return $this === self::Admin;
    }
}
