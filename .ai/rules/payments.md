---
paths:
  - 'app/Services/Landlord/Payments/**'
---

# Payments

## Multi-provider payment drivers
Payment drivers: flutterwave, paystack, stripe via PaymentManager match + config/payments.php. Amounts are minor units. Unified webhook POST /api/webhooks/payments/{provider}; legacy /webhooks/flutterwave still works. ProcessPaymentWebhookJob requires provider slug. Do not add payment SDKs without approval—use Http client like existing drivers.
