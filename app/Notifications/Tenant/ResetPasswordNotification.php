<?php

declare(strict_types=1);

namespace App\Notifications\Tenant;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Simple mail password reset for tenant staff (no landlord template DB).
 */
class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expiresMinutes = (int) config('auth.passwords.tenant_users.expire', 60);

        return (new MailMessage)
            ->subject(__('Reset Password Notification'))
            ->line(__('You are receiving this email because we received a password reset request for your account.'))
            ->action(__('Reset Password'), $this->resetUrl($notifiable))
            ->line(__('This password reset link will expire in :count minutes.', ['count' => $expiresMinutes]))
            ->line(__('If you did not request a password reset, no further action is required.'));
    }

    private function resetUrl(object $notifiable): string
    {
        $base = rtrim((string) config('app.tenant_password_reset_url', config('app.url')), '/');
        $query = http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return $base.'?'.$query;
    }
}
