---
paths:
  - 'app/Http/Controllers/Landlord/Audit/**'
---

# Audit

## Activities are a read and cleanup log
Landlord activities are a read and cleanup surface over Spatie laravel-activitylog, not a client-created ledger. Use App\Models\Landlord\Activity as activity_model. Follow World HTTP conventions on GET /activities, /activities/options, GET /activities/{activity}, DELETE /activities/{activity}, and DELETE /activities/destroy-many. Skip store, update, restore, restoreMany, and import/export/template. The activity_log table has no soft deletes, so destroy is permanent. Includes are causer and subject. Integer ids. Do not add a second logging package or authenticate with these rows.
