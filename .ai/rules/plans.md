---
paths:
  - 'app/Http/Controllers/Landlord/Plans/**'
---

# Plans

## Plans are a catalog without billing
Plans are the landlord subscription catalog. Follow World HTTP conventions: filter/search, options, restore, destroyMany, restoreMany. Skip import/export/template. Price is an integer in the smallest currency unit. currency is an ISO 4217 code string, not a World Currency foreign key. Interval is monthly or yearly. Status is draft, active, or archived. Slug is generated from name. Route keys stay integer ids. Do not add subscriptions or payment-provider columns here.
