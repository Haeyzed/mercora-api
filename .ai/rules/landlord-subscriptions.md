---
paths:
  - 'app/Services/Landlord/SubscriptionService.php'
---

# Landlord Subscriptions

## Current subscription uniqueness and scheduled renewals
One current subscription per tenant is enforced with lockForUpdate plus unique (tenant_id, is_current) where is_current is 1 or null. Cancel sets is_current null. Do not accept status from HTTP. landlord:process-subscriptions converts ended trials to active, then renews ended current periods and issues invoices. Do not invent past_due without a payment event. Canceled and expired cannot renew.

## Subscription cancel and past-due policy
Cancel respects subscriptions.cancel_at_period_end (default): sets canceled_at and keeps is_current until ends_at; processDue finalizes. Immediate cancel only when cancel_at_period_end is false and allow_immediate_cancel is true. Plan changes gated by allow_plan_changes. Past-due tenants suspend after past_due_suspend_after_days.

## Payment renew and cancel billing side effects
renewAfterPayment reactivates suspended tenants and EntitlementService::forget (versioned cache). Immediate/finalized cancel voids open invoices and marks pending payments Cancelled. changePlan preserves billing period dates; when subscriptions.prorate_plan_changes is true and terms changed, issues an open invoice for remaining-period fraction of the new price and may set PendingPayment. Successful payment resets dunning_attempts and last_dunned_at.
