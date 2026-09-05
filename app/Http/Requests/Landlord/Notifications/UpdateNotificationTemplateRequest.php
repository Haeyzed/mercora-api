<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Notifications;

use App\Enums\Landlord\NoticeChannel;
use App\Models\Landlord\NotificationTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate an update to a notification template.
 */
class UpdateNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var NotificationTemplate $template */
        $template = $this->route('notification_template');

        return [
            'key' => ['sometimes', 'string', 'max:100', Rule::unique(NotificationTemplate::class, 'key')->ignore($template->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'channels' => ['sometimes', 'array', 'min:1'],
            'channels.*' => ['required', 'string', Rule::enum(NoticeChannel::class)],
            'variables' => ['sometimes', 'nullable', 'array'],
            'variables.*' => ['required', 'string', 'max:100'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string'],
            'email_subject' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email_body' => ['sometimes', 'nullable', 'string'],
            'push_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'push_body' => ['sometimes', 'nullable', 'string'],
            'sms_body' => ['sometimes', 'nullable', 'string'],
            'is_mandatory' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
