---
paths:
  - 'app/Services/Landlord/Payments/**'
---

# Payments

## Multi-provider payment drivers
Payment drivers: flutterwave, paystack, stripe via PaymentManager match + config/payments.php. Amounts are minor units. Unified webhook POST /api/webhooks/payments/{provider}; legacy /webhooks/flutterwave still works. ProcessPaymentWebhookJob requires provider slug. Do not add payment SDKs without approval—use Http client like existing drivers.

## PayPal payment driver
Providers: flutterwave, paystack, stripe, paypal. PayPal uses Orders v2 + OAuth client credentials; verify uses provider_reference (order id). Webhook signature is JSON-encoded PAYPAL-* headers verified via /v1/notifications/verify-webhook-signature.

## Payment refunds
POST payments/{payment}/refund requires payments.refund. Only Successful payments refund; drivers implement refund() and PaymentService marks Refunded + refunded_at. Invoices stay Paid (ledger is not reopened). Billing alert fan-out on refund.
