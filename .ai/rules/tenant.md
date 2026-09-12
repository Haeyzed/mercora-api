---
paths:
  - 'app/Services/Tenant/**,app/Http/Controllers/Tenant/**,app/Models/Tenant/**,routes/tenant.php,config/auth.php'
---

# Tenant

## Tenant Sanctum auth endpoints
Tenant staff auth is Sanctum guard tenant on App\Models\Tenant\User at /api/tenant/auth/* (login, forgot/reset password, logout, me, profile, change-password). Apply tenant.guard after identification middleware. No public register—first Admin comes from FinalizeTenantProvision. User uses HasRoles (guard tenant); see `.ai/rules/models-tenant.md`. Auth routes stay outside `subscription.active` so PastDue tenants can still call me/profile; product routes under that alias require Trialing or Active (402 otherwise)—see middleware rule. Staff CRUD at /api/tenant/users uses permission users.view/users.manage; store enforces UsageLimiter users.max seats. Auth feature tests use Tests\Support\TenantTestContext (CreateDatabase + MigrateDatabase + disposable SQLite); use provisionWithAdmin()/seedRbac() when roles matter; call auth()->forgetGuards() between sequential bearer requests. Password reset uses broker tenant_users and App\Notifications\Tenant\ResetPasswordNotification (mail only—no landlord NotificationDispatcher).
