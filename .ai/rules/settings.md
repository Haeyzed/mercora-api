---
paths:
  - 'app/Http/Controllers/Landlord/Settings/**'
  - 'app/Support/Settings/**'
  - 'app/Settings/**'
---

# Settings

## Settings are schema domains, not CRUD
Landlord settings are typed key/value rows backed by App\Support\Settings (SettingsSchema + SettingsManager). Keys are defined in App\Settings\Landlord\*Domain classes with type, default, nullable, and validation rules. Expose GET/PUT /settings/{domain} (and GET /settings for all domains). Do not create arbitrary keys via API. Reads use Cache::remember per key; writes invalidate. Inject SettingService::value() for app code. Soft-delete and World-style CRUD are not used.
