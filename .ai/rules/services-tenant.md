---
paths:
  - 'app/Services/Tenant/**'
  - app/Services/Tenant/UserService.php
---

# Services Tenant

## Tenant auth Phase 2: HasRoles, no public register
Tenant staff auth remains Sanctum guard tenant at /api/tenant/auth/* with no public register. User has HasRoles (guard tenant). Auth feature tests use TenantTestContext; for seeded Admin use provisionWithAdmin()/seedRbac(). Call auth()->forgetGuards() between sequential bearer requests. Password reset broker tenant_users + App\Notifications\Tenant\ResetPasswordNotification (mail only).

## Tenant user create enforces users.max seats
Tenant UserService::store calls UsageLimiter::assertCanCreate(tenant, users.max) before create. Default role Staff. Soft-delete/deactivate blocks the last Admin. Routes: GET users under permission:users.view; POST/PATCH/DELETE under users.manage; all inside subscription.active.
