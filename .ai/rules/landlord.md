---
paths:
  - 'app/Models/Landlord/**'
  - app/Models/Landlord/Tenant.php
---

# Landlord

## Keep tenant profile columns out of data JSON
App\Models\Landlord\Tenant extends Stancl Tenant and implements TenantWithDatabase with HasDatabase and HasDomains. List id, name, slug, status, created_at, updated_at, and deleted_at in getCustomColumns() so VirtualColumn does not swallow them into data. Route keys stay the UUID id. Slug is generated from name via HasSlug. Tenant destroyMany/restoreMany ids are UUID strings, not World integers.

## Domain models write Spatie activities; never log secrets
Use LogsLandlordActivity (Spatie Activitylog), not owen-it. Exclude secrets with activitylogExcept: key_hash, password, setting value, provision_error. Activities are application-generated; the API is read and purge only. Purge requires activities.purge. Test fixtures should create users via activity()->withoutLogging so counts stay meaningful.

## activeSubscription means access-granting current
Tenant::activeSubscription() returns the is_current=1 subscription in Trialing or Active only. Use it for subscription.active middleware and product access checks—not SubscriptionStatus::currentCases() (which includes PastDue/PendingPayment for billing).

## Landlord models use CentralConnection
Landlord Eloquent models that live on the central DB (Subscription, Plan, Feature, PlanPrice, and siblings) must use Stancl CentralConnection so queries stay on tenancy.database.central_connection after DatabaseTenancyBootstrapper switches the default connection.
