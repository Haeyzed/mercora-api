---
paths:
  - app/Services/Landlord/Plans/UsageLimiter.php
---

# Landlord Plans

## UsageLimiter: central entitlements, tenant counts
UsageLimiter.assertCanCreate asserts FeatureGate entitlement on the central connection, then counts usage on the tenant DB. First metered key is users.max → App\Models\Tenant\User::query()->count(). Missing feature fails assert; null limit means unlimited.
