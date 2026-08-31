---
paths:
  - 'app/Services/Landlord/Subscriptions/**'
---

# Landlord Subscriptions

## Current subscription uniqueness and scheduled renewals
One current subscription per tenant is enforced with lockForUpdate plus unique (tenant_id, is_current) where is_current is 1 or null. Cancel sets is_current null. Do not accept status from HTTP. landlord:process-subscriptions converts ended trials to active, then renews ended current periods and issues invoices. Do not invent past_due without a payment event. Canceled and expired cannot renew.
