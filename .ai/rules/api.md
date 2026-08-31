---
paths:
  - 'app/Http/Controllers/**'
  - 'app/Http/Resources/**'
---

# Api

## Return Eloquent resources the Laravel way
Return JsonResource or ResourceCollection from controllers. Laravel wraps the payload in data and adds links and meta for paginated collections. Do not add a success or message envelope. Create with toResource()->response()->setStatusCode(201). Delete with response()->noContent().

## Controllers authorize through AuthorizesRequests
The base Controller uses AuthorizesRequests. Landlord and World controllers call $this->authorize() against policies. Form Requests may also authorize. Authentication (auth:sanctum) is not authorization.
