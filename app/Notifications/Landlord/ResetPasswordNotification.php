<?php

declare(strict_types=1);

namespace App\Notifications\Landlord;

use App\Services\Landlord\Notifications\NotificationTemplateService;
use App\Services\Landlord\Notifications\TemplateRenderer;
use App\Services\Landlord\SettingService;
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
        $resetUrl = $this->resetUrl($notifiable);
        $expiresMinutes = (int) config('auth.passwords.users.expire');
        $rendered = $this->renderedTemplateContent($notifiable, $resetUrl, $expiresMinutes);

        $subject = $rendered['email_subject'] !== ''
            ? $rendered['email_subject']
            : __('Reset Password Notification');

        $body = $rendered['email_body'] !== ''
            ? $rendered['email_body']
            : __('You are receiving this email because we received a password reset request for your account.');

        $mail = (new MailMessage)
            ->subject($subject)
            ->line($body)
            ->action(__('Reset Password'), $resetUrl)
            ->line(__('This password reset link will expire in :count minutes.', ['count' => $expiresMinutes]))
            ->line(__('If you did not request a password reset, no further action is required.'));

        $footer = $this->setting('mail.footer_text');

        if (is_string($footer) && $footer !== '') {
            $mail->line($footer);
        }

        return $mail;
    }

    /**
     * @return array{email_subject: string, email_body: string}
     */
    private function renderedTemplateContent(object $notifiable, string $resetUrl, int $expiresMinutes): array
    {
        try {
            $templates = app(NotificationTemplateService::class);
            $renderer = app(TemplateRenderer::class);
            $template = $templates->findActiveByKey('auth.password_reset');

            if ($template === null) {
                return ['email_subject' => '', 'email_body' => ''];
            }

            /** @var list<string> $variables */
            $variables = $template->variables ?? [];
            $data = [
                'user_name' => (string) data_get($notifiable, 'name', ''),
                'email' => (string) $notifiable->getEmailForPasswordReset(),
                'reset_url' => $resetUrl,
                'expires_minutes' => $expiresMinutes,
            ];

            return [
                'email_subject' => $renderer->render($template->email_subject, $data, $variables),
                'email_body' => $renderer->render($template->email_body, $data, $variables),
            ];
        } catch (\Throwable) {
            return ['email_subject' => '', 'email_body' => ''];
        }
    }

    private function resetUrl(object $notifiable): string
    {
        $base = rtrim((string) config('app.landlord_password_reset_url', config('app.url')), '/');
        $query = http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return $base.'?'.$query;
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
