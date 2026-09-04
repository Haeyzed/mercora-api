---
paths:
  - 'app/Http/Controllers/Landlord/InvoiceController.php'
---

# Billing

## Billing is a landlord invoice ledger
Invoices snapshot amount and currency from the subscription. Follow World HTTP conventions except options and delete/restore, plus POST /invoices/{invoice}/pay and /void. Store creates open invoices. The client cannot set amount, currency, number, status, tenant_id, or payment-provider ids. Update, pay, and void require an open invoice and throw ValidationException on status with "The invoice is not open." Invoice numbers are INV-{Ymd}-{6 uppercase}. Invoices are immutable ledger records — no HTTP delete or restore. Integer ids. Skip import/export/template. Do not add Cashier or Stripe.
