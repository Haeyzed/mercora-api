---
paths:
  - app/Jobs/Landlord/ProvisionTenantJob.php
---

# Jobs Landlord

## ProvisionTenantJob order: seed then finalize
ProvisionTenantJob is the sole owner: CreateDatabase → MigrateDatabase → SeedDatabase → FinalizeTenantProvision → completeProvisioning. Do not attach Create/Migrate/Seed to TenantCreated. TenantDatabaseSeeder (and Role/Permission seeders) must never create users—only RBAC. FinalizeTenantProvision creates the first Admin from Tenant.pending_provision.admin then clears pending_provision. pending_provision must be on Tenant Fillable (Stancl virtual/data column) or mass assignment drops it.
