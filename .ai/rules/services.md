---
paths:
  - 'app/Services/**'
---

# Services

## Share perPage via PaginatesRequests
List endpoints clamp page size via App\Services\Concerns\PaginatesRequests (default 15, min 1, max 100). Use the trait instead of a private perPage copy or an inline min/max clamp.
