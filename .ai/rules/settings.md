---
paths:
  - 'app/Http/Controllers/Landlord/SettingController.php'
  - 'app/Support/Settings/**'
  - 'app/Settings/**'
---

# Settings

## Settings are schema domains, not CRUD
Landlord settings are typed key/value rows backed by App\Support\Settings (SettingsSchema + SettingsManager). Keys are defined in App\Settings\Landlord\*Domain classes with type, default, nullable, and validation rules. Expose GET/PUT /settings/{domain} (and GET /settings for all domains). Do not create arbitrary keys via API. Reads use Cache::remember per key; writes invalidate. Inject SettingService::value() for app code. Soft-delete and World-style CRUD are not used.

## Twelve landlord settings domains
Registered domains: platform, registration, localization, billing, mail, security, tenancy, notifications, api, storage, subscriptions, compliance. ApiKeyService enforces api.keys_enabled, max_keys_per_user, default_key_ttl_days, require_key_expiry. MediaValidation reads storage.*_max_kb (avatar uses storage.avatar_max_kb). Subscriptions/compliance are schema-ready for lifecycle and retention; wire consumers when those features land. Secrets stay in env/config.

## Settings enforcement map
Enforce where consumers exist: maintenance middleware, API throttle, Sanctum timeout, lockout window, locale/timezone, activitylog clean, notice channels, billing_alerts and tenant_lifecycle_alerts (lifecycle fan-out), billing due/overdue/renewal reminders, subscriptions dunning_*, tenancy.soft_delete_retention_days purge, registration.* (public register), compliance export/erase + soft_deleted_user_retention_days purge, billing.tax_* and company_* on invoices, domain subdomain/custom policy, concurrent provisions, auto_provision, invoice memo, payment statement title, thumbnails, PII mask. Display-only and no-consumer keys stay schema-only until a feature lands.
