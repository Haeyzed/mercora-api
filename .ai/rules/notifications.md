---
paths:
  - 'app/Http/Controllers/Landlord/NotificationController.php'
  - 'app/Http/Controllers/Landlord/NotificationTemplateController.php'
  - 'app/Http/Controllers/Landlord/NotificationPreferenceController.php'
  - 'app/Services/Landlord/NoticeService.php'
  - 'app/Services/Landlord/Notifications/**'
---

# Notifications

## Notifications are a notice ledger
Notifications are a landlord notice ledger, not a mail-provider integration. Follow World HTTP conventions except options, plus POST /notifications/read-all and /notifications/{notice}/read. The model is Notice on the notices table so Laravel Notifiable and the framework notifications table stay free. Store creates unread notices. The client cannot set status or read_at. Update and read require an unread notice and throw ValidationException on status with "The notice is not unread." channel is in_app or mail; mail is recorded only and is not sent. Soft delete. Integer ids. Skip import/export/template. Do not add Mailgun, Postmark, or a notification package.

## Lifecycle alerts use templated NotificationDispatcher
Domain code calls NotificationDispatcher::notifyActiveUsers('payment.successful', $vars) or ::send($user, 'auth.welcome'). Templates live in notification_templates (seeded keys: payment.*, subscription.*, invoice.*, tenant.suspended, auth.welcome). In-app and mail channels create Notice rows (mail is recorded, not SMTP-sent). Gates: notifications.billing_alerts for billing keys, notifications.tenant_lifecycle_alerts for tenant.*, plus in_app_enabled / email_enabled, per-user preferences, and quiet hours for non-mandatory templates. Mandatory templates lock in_app + mail preferences on. Missing/inactive templates are no-ops. Past-due notices fire only on the Active→PastDue transition. Admin CRUD: /notification-templates (+ preview/options). Self prefs: GET/PUT /notification-preferences.
