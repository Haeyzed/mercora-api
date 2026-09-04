---
paths:
  - 'app/Http/Controllers/Landlord/NotificationController.php'
  - 'app/Services/Landlord/NoticeService.php'
---

# Notifications

## Notifications are a notice ledger
Notifications are a landlord notice ledger, not a mail-provider integration. Follow World HTTP conventions except options, plus POST /notifications/read-all and /notifications/{notice}/read. The model is Notice on the notices table so Laravel Notifiable and the framework notifications table stay free. Store creates unread notices. The client cannot set status or read_at. Update and read require an unread notice and throw ValidationException on status with "The notice is not unread." channel is in_app or mail; mail is recorded only and is not sent. Soft delete. Integer ids. Skip import/export/template. Do not add Mailgun, Postmark, or a notification package.

## Lifecycle alerts fan out via NoticeService
Payment success, subscription past-due, and subscription cancel call NoticeService::notifyBillingAlert (gated by notifications.billing_alerts + in_app_enabled). Tenant suspend calls notifyTenantLifecycleAlert (gated by notifications.tenant_lifecycle_alerts + in_app_enabled). Fan-out creates unread in-app notices for every active landlord user and never throws when alerts/channels are disabled. Past-due notices fire only on the Active→PastDue transition.
