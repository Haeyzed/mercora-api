<?php

declare(strict_types=1);

namespace App\Notifications\Landlord;

use App\Services\Landlord\Settings\SettingService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Schema;

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
        $resetUrl = rtrim((string) config('app.landlord_password_reset_url', config('app.url')), '/');
        $query = http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $mail = (new MailMessage)
            ->subject(__('Reset Password Notification'))
            ->line(__('You are receiving this email because we received a password reset request for your account.'))
            ->action(__('Reset Password'), $resetUrl.'?'.$query)
            ->line(__('This password reset link will expire in :count minutes.', ['count' => config('auth.passwords.users.expire')]))
            ->line(__('If you did not request a password reset, no further action is required.'));

        $footer = $this->setting('mail.footer_text');

        if (is_string($footer) && $footer !== '') {
            $mail->line($footer);
        }

        return $mail;
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        try {
            if (! Schema::hasTable('settings')) {
                return $default;
            }

            return app(SettingService::class)->value($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}
