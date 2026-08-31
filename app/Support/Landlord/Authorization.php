<?php

declare(strict_types=1);

namespace App\Support\Landlord;

use App\Enums\Landlord\Permission;
use App\Enums\Landlord\RoleName;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class Authorization
{
    public static function seed(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permission::cases() as $permission) {
            \Spatie\Permission\Models\Permission::findOrCreate($permission->value, 'web');
        }

        Role::findOrCreate(RoleName::SuperAdmin->value, 'web')
            ->syncPermissions(Permission::values());

        Role::findOrCreate(RoleName::Operator->value, 'web')
            ->syncPermissions([
                Permission::TenantsView->value,
                Permission::TenantsCreate->value,
                Permission::TenantsUpdate->value,
                Permission::TenantsProvision->value,
                Permission::TenantsActivate->value,
                Permission::TenantsSuspend->value,
                Permission::DomainsView->value,
                Permission::DomainsCreate->value,
                Permission::DomainsDelete->value,
                Permission::PlansView->value,
                Permission::SubscriptionsView->value,
                Permission::SubscriptionsCreate->value,
                Permission::SubscriptionsChangePlan->value,
                Permission::SubscriptionsCancel->value,
                Permission::SubscriptionsRenew->value,
                Permission::InvoicesView->value,
                Permission::InvoicesCreate->value,
                Permission::InvoicesUpdate->value,
                Permission::InvoicesPay->value,
                Permission::NoticesView->value,
                Permission::NoticesCreate->value,
                Permission::NoticesUpdate->value,
                Permission::NoticesRead->value,
                Permission::SettingsView->value,
                Permission::ActivitiesView->value,
                Permission::UsersView->value,
                Permission::RolesView->value,
                Permission::WorldView->value,
            ]);
    }
}
