---
paths:
  - 'app/Http/Controllers/Landlord/Plans/**'
---

# Plans

## Plans are a catalog; billing lives on plan prices
Plans are the landlord subscription catalog. Follow World HTTP conventions: filter/search, options, restore, destroyMany, restoreMany. Skip import/export/template. Billing amounts, currency, interval, and trial days belong on nested `plan_prices` records — not on the plan row. Store requires a nested `price` object with the initial active price. Status is draft, active, or archived. Slug is generated from name. Route keys stay integer ids. Do not add subscriptions or payment-provider columns here.
