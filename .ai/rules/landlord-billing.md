---
paths:
  - 'app/Services/Landlord/Billing/**'
---

# Landlord Billing

## Invoices are period-idempotent ledger documents
Issue invoices only for current subscriptions via issueFor, snapshotting amount and currency. Unique (subscription_id, period_starts_at) makes period issuance idempotent. Clients cannot set number, amount, currency, tenant_id, or status. Pay and void are open-only actions. Paid and void invoices cannot reverse. Invoices are immutable ledger records with no HTTP delete or restore.

## Invoice numbers use billing settings
Invoice numbering and defaults come from billing settings via SettingService: billing.invoice_prefix, billing.invoice_suffix, billing.grace_days (default due_at), and billing.invoice_footer (default notes). Do not hardcode INV- prefixes.

## Invoice memo and statement descriptor
Invoice defaults also merge billing.invoice_memo with billing.invoice_footer into notes. Payment checkout uses billing.statement_descriptor as Flutterwave customizations.title.
