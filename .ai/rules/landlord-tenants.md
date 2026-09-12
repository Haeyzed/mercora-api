---
paths:
  - 'app/Http/Middleware/InitializeTenancyByDomainOrHeader.php,app/Http/Middleware/PreventAccessFromCentralDomainsUnlessTenantHeader.php,routes/tenant.php,app/Http/Controllers/Tenant/**,app/Http/Controllers/Public/**,app/Services/Landlord/Tenants/PublicTenantResolver.php'
---

# Landlord Tenants

## Tenant API identification and public resolve
Tenant API (/api/tenant/*) uses InitializeTenancyByDomainOrHeader + PreventAccessFromCentralDomainsUnlessTenantHeader: Host domain, or X-Tenant-Domain when Host is central (BFF). Keep EnsureTenantHttps and EnsureTenantNotSuspended. GET /api/public/tenant?domain= resolves branding via PublicTenantResolver without initializing tenancy; allows_login only when status is Active. Staff Sanctum auth is documented in `.ai/rules/tenant.md`. Authenticated product routes use `subscription.active` (402 unless Trialing/Active) then optional `feature:{key}` (403 if plan lacks the feature); auth endpoints stay ungated. Identification-only feature tests may disable tenancy.bootstrappers; auth tests provision a disposable tenant DB via TenantTestContext.
