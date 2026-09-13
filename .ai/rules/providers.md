---
paths:
  - app/Providers/TenancyServiceProvider.php
  - app/Providers/AppServiceProvider.php
  - app/Providers/ScrambleDocumentationServiceProvider.php
---

# Providers

## Soft delete tenants without dropping databases
Stancl maps Eloquent deleted to TenantDeleted, which would run DeleteDatabase. Only forceDelete may drop a tenant database. Skip CreateDatabase, MigrateDatabase, and DeleteDatabase while APP_ENV is testing so Feature tests never provision or drop tenant databases.

## One Stancl provisioning owner: TenantService plus the job
Do not attach Stancl CreateDatabase/MigrateDatabase/SeedDatabase to TenantCreated. TenantService dispatches ProvisionTenantJob, the single provisioning owner (Create → Migrate → Seed → FinalizeTenantProvision → complete). See `.ai/rules/jobs-landlord.md`. TenantDeleted drops the database only on forceDelete and never in testing. Soft delete must not drop the tenant database.

## Tenant route identification middleware priority
Register InitializeTenancyByDomainOrHeader and PreventAccessFromCentralDomainsUnlessTenantHeader in makeTenancyMiddlewareHighestPriority ahead of stock Stancl domain middleware. Tenant routes live in routes/tenant.php (api/tenant), not bootstrap withRouting.

## Do not use bound() for SettingService
Never gate SettingService reads on app()->bound(SettingService::class)—auto-wired classes are not bound until resolved. Use Schema::hasTable('settings') inside try/catch and app()->make() instead.

## API throttle is per-minute only
landlord-api and tenant-api throttles use Limit::perMinute only. Do not add a perSecond burst derived from api.burst_limit/60 — that capped ~2 rps and 429'd normal multi-request UI/test sequences.

## Scramble docs are split landlord / tenant
Default `/docs/api` is disabled via `Scramble::ignoreDefaultRoutes()`. Serve `/docs/landlord` (`api/landlord/*`) and `/docs/tenant` (`api/tenant/*` + `api/public/*`) from ScrambleDocumentationServiceProvider. Gate `viewApiDocs` allows access outside production. Do not reintroduce a single combined docs site.
