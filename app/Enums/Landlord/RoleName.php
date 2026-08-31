<?php

declare(strict_types=1);

namespace App\Enums\Landlord;

enum RoleName: string
{
    case SuperAdmin = 'Super Admin';
    case Operator = 'Operator';

    public function isProtected(): bool
    {
        return $this === self::SuperAdmin;
    }
}
