---
paths:
  - 'app/Http/Controllers/Landlord/SubscriptionController.php'
---

# Subscriptions

## Subscriptions snapshot plan terms
Subscriptions attach a tenant to a catalog plan. Follow World HTTP conventions except options and delete/restore, plus POST /subscriptions/{subscription}/change-plan, /cancel, and /renew. A tenant may have only one current (trialing, active, past_due) subscription. Snapshot price, currency, and interval from the plan price on create and plan change. Do not accept those fields or payment-provider ids from the client. Subscribe only to active, non-trashed plans. Subscriptions are lifecycle-managed records — no HTTP delete or restore. Skip import/export/template.
