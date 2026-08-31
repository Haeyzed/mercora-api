---
paths:
  - 'app/Http/Controllers/Landlord/Notifications/**'
---

# Notifications

## Notifications are a notice ledger
Notifications are a landlord notice ledger, not a mail-provider integration. Follow World HTTP conventions plus POST /notifications/{notice}/read. The model is Notice on the notices table so Laravel Notifiable and the framework notifications table stay free. Store creates unread notices. The client cannot set status or read_at. Update and read require an unread notice and throw ValidationException on status with "The notice is not unread." channel is in_app or mail; mail is recorded only and is not sent. Soft delete. Integer ids. Skip import/export/template. Do not add Mailgun, Postmark, or a notification package.
