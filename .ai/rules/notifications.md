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
Notifications are a landlord notice ledger, not a mail-provider integration. Follow World HTTP conventions except options, plus POST /notifications/read-all and /notifications/{notice}/read. The model is Notice on the notices table so Laravel Notifiable and the framework notifications table stay free. Store creates unread notices. The client cannot set status or read_at. Update and read require an unread notice and throw ValidationException on status with "The notice is not unread." Notice.channel is in_app or mail; mail is recorded only and is not sent. Soft delete. Integer ids. Skip import/export/template. Do not add Mailgun, Postmark, or a notification package.

## Lifecycle alerts use templated NotificationDispatcher
Domain code calls NotificationDispatcher::notifyActiveUsers('payment.successful', $vars) or ::send($user, 'auth.welcome'). Templates live in notification_templates (seeded keys: payment.successful/refunded/failed/cancelled, subscription.past_due/canceled/renewing_soon/dunning, invoice.due_soon/overdue, tenant.suspended/activated/reactivated/provisioned/provision_failed, auth.welcome/password_reset/password_changed). ChannelResolver fans out NotificationChannel values: in_app, mail, push, sms. In-app and mail write Notice rows (mail is recorded, not SMTP-sent). Push audits skipped deliveries until devices exist. SMS uses App\Services\Landlord\Notifications\Sms (SmsManager + drivers: null/twilio/vonage/messagebird/amazon_sns/termii/africastalking/bulksms/hubtel); env SMS_ENABLED/SMS_DRIVER + credentials in config/notifications.php; product gate notifications.sms_enabled; landlord User.phone required to send. Mandatory templates lock in_app + mail preferences on. Missing/inactive templates are no-ops. Past-due notices fire only on the Active→PastDue transition. Password reset still sends real SMTP via ResetPasswordNotification (copy from auth.password_reset template when present) in addition to the dispatcher ledger. Admin CRUD: /notification-templates (+ preview/options). Self prefs: GET/PUT /notification-preferences.
