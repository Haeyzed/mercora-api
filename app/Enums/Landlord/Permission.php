<?php

declare(strict_types=1);

namespace App\Enums\Landlord;

enum Permission: string
{
    case TenantsView = 'tenants.view';
    case TenantsCreate = 'tenants.create';
    case TenantsUpdate = 'tenants.update';
    case TenantsProvision = 'tenants.provision';
    case TenantsActivate = 'tenants.activate';
    case TenantsSuspend = 'tenants.suspend';
    case TenantsDelete = 'tenants.delete';
    case TenantsForceDelete = 'tenants.force_delete';

    case DomainsView = 'domains.view';
    case DomainsCreate = 'domains.create';
    case DomainsDelete = 'domains.delete';

    case PlansView = 'plans.view';
    case PlansCreate = 'plans.create';
    case PlansUpdate = 'plans.update';
    case PlansDelete = 'plans.delete';

    case FeaturesView = 'features.view';
    case FeaturesCreate = 'features.create';
    case FeaturesUpdate = 'features.update';
    case FeaturesDelete = 'features.delete';

    case SubscriptionsView = 'subscriptions.view';
    case SubscriptionsCreate = 'subscriptions.create';
    case SubscriptionsChangePlan = 'subscriptions.change_plan';
    case SubscriptionsCancel = 'subscriptions.cancel';
    case SubscriptionsRenew = 'subscriptions.renew';
    case SubscriptionsDelete = 'subscriptions.delete';

    case InvoicesView = 'invoices.view';
    case InvoicesCreate = 'invoices.create';
    case InvoicesUpdate = 'invoices.update';
    case InvoicesPay = 'invoices.pay';
    case InvoicesVoid = 'invoices.void';
    case InvoicesDelete = 'invoices.delete';

    case PaymentsView = 'payments.view';
    case PaymentsVerify = 'payments.verify';

    case ApiKeysView = 'api_keys.view';
    case ApiKeysCreate = 'api_keys.create';
    case ApiKeysUpdate = 'api_keys.update';
    case ApiKeysRevoke = 'api_keys.revoke';
    case ApiKeysDelete = 'api_keys.delete';

    case NoticesView = 'notices.view';
    case NoticesCreate = 'notices.create';
    case NoticesUpdate = 'notices.update';
    case NoticesRead = 'notices.read';
    case NoticesDelete = 'notices.delete';

    case SettingsView = 'settings.view';
    case SettingsCreate = 'settings.create';
    case SettingsUpdate = 'settings.update';
    case SettingsDelete = 'settings.delete';

    case ActivitiesView = 'activities.view';
    case ActivitiesPurge = 'activities.purge';

    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersUpdate = 'users.update';
    case UsersDelete = 'users.delete';

    case RolesView = 'roles.view';
    case RolesCreate = 'roles.create';
    case RolesUpdate = 'roles.update';
    case RolesDelete = 'roles.delete';

    case WorldView = 'world.view';
    case WorldManage = 'world.manage';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
