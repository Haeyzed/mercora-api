---
paths:
  - 'app/Services/Landlord/Billing/**'
---

# Landlord Billing

## Invoices are period-idempotent ledger documents
Issue invoices only for current subscriptions via issueFor, snapshotting amount and currency. Unique (subscription_id, period_starts_at) makes period issuance idempotent. Clients cannot set number, amount, currency, tenant_id, or status. Pay and void are open-only actions. Paid and void invoices cannot reverse.
