---
paths:
  - 'app/Http/Controllers/Landlord/Plans/**'
---

# Plans

## Plans are a catalog; billing lives on plan prices
Plans are the landlord subscription catalog. Follow World HTTP conventions: filter/search, options, restore, destroyMany, restoreMany. Skip import/export/template. Billing amounts, currency, interval, and trial days belong on nested `plan_prices` records — not on the plan row. Store requires a nested `price` object with the initial active price. Status is draft, active, or archived. Slug is generated from name. Route keys stay integer ids. Do not add subscriptions or payment-provider columns here.

## Entitlements and FeatureGate
Typed features (boolean/integer/string/unlimited) attach via plan_features.value. EntitlementService caches values and ignores inactive catalog features. FeatureGate wraps allows/assert/limit/canUse/assertCanUse for tenant checks — use it from services when enforcing plan capabilities. HTTP alias `feature:{key}` (EnsureFeature) calls FeatureGate::allows after subscription.active; it does not meter usage. UsageLimiter meters live tenant counts (first key: users.max → User::count) on create via assertCanCreate; resolve entitlements on central, count on tenant. Feature catalog updates invalidate entitlements for subscribed tenants.
