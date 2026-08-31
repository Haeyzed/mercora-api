---
paths:
  - 'app/Policies/Landlord/**'
---

# Policies Landlord

## Landlord RBAC uses Spatie roles plus policies
Authorize with Spatie Permission via policies and $user->can(), never hasPermissionTo (Gate::before Super Admin would be bypassed). Super Admin is the role name and is granted every ability in AppServiceProvider. Operator is a seeded subset. Do not hardcode emails. Protect purge, force-delete, void, settings writes, and world.manage.
