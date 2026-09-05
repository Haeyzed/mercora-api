---
paths:
  - app/Services/Landlord/AuthService.php
---

# Auth

## Landlord routes require Sanctum except login and password recovery
Landlord API auth is Sanctum tokens on App\Models\Landlord\User. Public POST /api/landlord/auth/login, /register, /forgot-password, and /reset-password are throttled via landlord-auth. Authenticated routes include logout, me, profile update, avatar replace/remove, change-password, and personal-data export/erase. World feature tests authenticate with actingAsLandlord() from tests/Pest.php.

## Self-serve registration and GDPR
Register creates a landlord User (Operator role), Tenant via TenantService, and optional Subscription from registration.default_plan_slug with registration.trial_days override. Gate on tenant_registration_enabled, allowed_email_domains, require_terms_acceptance. GET/DELETE auth/personal-data gated by compliance.export_personal_data_enabled / erase_personal_data_enabled. Erase anonymizes, revokes tokens/keys, soft-deletes the user. landlord:purge-deleted-users uses soft_deleted_user_retention_days.
