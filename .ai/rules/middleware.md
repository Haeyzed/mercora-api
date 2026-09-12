---
paths:
  - app/Http/Middleware/EnsureActiveSubscription.php
  - app/Http/Middleware/EnsureFeature.php
---

# Middleware

## subscription.active grants Trialing and Active only
Alias subscription.active gates tenant product routes after auth:tenant. Only Trialing/Active (via Tenant::activeSubscription + SubscriptionStatus::grantsAccess) pass; PastDue/PendingPayment/Canceled/Expired/none return 402. Auth routes (login/logout/me/profile/change-password) stay outside the gate so clients can show a paywall. Response shape matches EnsureTenantNotSuspended ({ message }).

## feature key gates plan entitlements
Alias feature:{key} nests under auth:tenant + subscription.active. Uses FeatureGate::allows only (no usage metering). Missing tenant or denied feature returns 403 with { message }. Resolve entitlements on the central connection (CentralConnection on Subscription/Plan/Feature/PlanPrice, or tenancy()->central) — never query landlord tables on the tenant DB after tenancy init.
