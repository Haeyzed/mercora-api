---
paths:
  - 'app/Models/Tenant/**'
---

# Models Tenant

## Tenant Spatie RBAC uses guard tenant
App\Models\Tenant\User uses HasRoles with $guard_name = 'tenant'. Seed via App\Support\Tenant\Authorization::seed() (roles Admin/Staff; permissions users.view, users.manage). Spatie permission tables live in database/migrations/tenant. No public tenant register—Admin is created only in FinalizeTenantProvision from pending_provision.
