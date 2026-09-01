---
paths:
  - 'app/Http/Controllers/Landlord/Settings/**'
---

# Settings

## Settings are a typed key-value catalog
Landlord settings are platform key-value rows, not env files or payment-provider config. Follow World HTTP conventions except options. Skip import/export/template. group is a lowercase slug. key is a unique dotted identifier and is immutable after create. type is string, boolean, integer, or json; value is stored encoded and returned decoded. Soft delete. Integer ids. Unique keys include soft-deleted rows.
