---
paths:
  - 'app/Http/Controllers/Landlord/Tenants/**'
---

# Tenants

## Landlord tenants follow World HTTP conventions
Tenants use the Laravel-default resource envelope, filter/search/include, options, restore, destroyMany, and restoreMany. Nested domains are /tenants/{tenant}/domains with scopeBindings. Skip import/export/template. Store requires name plus a first hostname and rejects central domains. Soft delete a tenant; hard-delete hostnames.

## Tenant lifecycle is action-based, not status CRUD
Tenant status is not client-writable. Create starts pending then queues ProvisionTenantJob. Activate only after provisioned_at. Use POST provision, activate, suspend, reactivate and DELETE .../force. Soft delete keeps the tenant database; force delete drops it outside testing. Skip Stancl CreateDatabase/MigrateDatabase/DeleteDatabase when APP_ENV=testing.
