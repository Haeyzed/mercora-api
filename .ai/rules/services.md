---
paths:
  - 'app/Services/**'
  - 'app/Http/Controllers/Landlord/**'
---

# Services

## Share perPage via PaginatesRequests
List endpoints clamp page size via App\Services\Concerns\PaginatesRequests (default 15, min 1, max 100). Use the trait instead of a private perPage copy or an inline min/max clamp.

## Flatten single-file landlord folders
Keep domain folders only when they hold multiple related classes (Plans, Tenants, Payments, Roles controllers). Single-class services/controllers live directly under App\Services\Landlord or App\Http\Controllers\Landlord.
