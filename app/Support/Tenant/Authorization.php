<?php

declare(strict_types=1);

namespace App\Support\Tenant;

use App\Enums\Tenant\Permission;
use App\Enums\Tenant\RoleName;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class Authorization
{
    public const GUARD = 'tenant';

    public static function seed(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permission::cases() as $permission) {
            \Spatie\Permission\Models\Permission::findOrCreate($permission->value, self::GUARD);
        }

        Role::findOrCreate(RoleName::Admin->value, self::GUARD)
            ->syncPermissions(Permission::values());

        Role::findOrCreate(RoleName::Staff->value, self::GUARD)
            ->syncPermissions([]);
    }
}
