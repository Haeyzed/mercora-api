---
paths:
  - 'app/Http/Controllers/Landlord/Subscriptions/**'
---

# Subscriptions

## Subscriptions snapshot plan terms
Subscriptions attach a tenant to a catalog plan. Follow World HTTP conventions plus POST /subscriptions/{subscription}/cancel. A tenant may have only one current (trialing, active, past_due) subscription. Snapshot price, currency, and interval from the plan on create and plan change. Do not accept those fields or payment-provider ids from the client. Subscribe only to active, non-trashed plans. Skip import/export/template.
