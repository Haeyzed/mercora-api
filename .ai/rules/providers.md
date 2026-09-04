---
paths:
  - app/Providers/TenancyServiceProvider.php
  - app/Providers/AppServiceProvider.php
---

# Providers

## Soft delete tenants without dropping databases
Stancl maps Eloquent deleted to TenantDeleted, which would run DeleteDatabase. Only forceDelete may drop a tenant database. Skip CreateDatabase, MigrateDatabase, and DeleteDatabase while APP_ENV is testing so Feature tests never provision or drop tenant databases.

## One Stancl provisioning owner: TenantService plus the job
Do not attach Stancl CreateDatabase/MigrateDatabase to TenantCreated. TenantService dispatches ProvisionTenantJob, the single provisioning owner. TenantDeleted drops the database only on forceDelete and never in testing. Soft delete must not drop the tenant database.

## Do not use bound() for SettingService
Never gate SettingService reads on app()->bound(SettingService::class)—auto-wired classes are not bound until resolved. Use Schema::hasTable('settings') inside try/catch and app()->make() instead.
