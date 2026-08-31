---
paths:
  - 'app/Models/Concerns/**'
---

# Concerns

## Reuse AllowsIncludes on any model that accepts include
Use App\Models\Concerns\AllowsIncludes for ?include= allow-lists. Each model defines allowedIncludes(). Do not add a second include-parsing helper for Landlord or Tenant models.
