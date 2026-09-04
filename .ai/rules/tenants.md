---
paths:
  - 'app/Http/Controllers/Landlord/Tenants/**'
---

# Tenants

## Landlord tenants follow World HTTP conventions
Tenants use the Laravel-default resource envelope, filter/search/include, options, restore, destroyMany, and restoreMany. Nested domains are /tenants/{tenant}/domains with scopeBindings. Skip import/export/template. Store requires name plus a first hostname and rejects central domains. Soft delete a tenant; hard-delete hostnames.

## Soft-deleted tenant retention purge
landlord:purge-deleted-tenants force-deletes tenants whose deleted_at is older than tenancy.soft_delete_retention_days (via TenantService::purgeExpiredSoftDeletes). Soft delete keeps the tenant DB; force delete drops it outside testing.
