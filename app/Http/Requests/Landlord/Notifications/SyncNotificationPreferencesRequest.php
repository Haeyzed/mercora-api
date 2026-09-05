<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Notifications;

use App\Enums\Landlord\NoticeChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a batch update of the authenticated user's notification preferences.
 */
class SyncNotificationPreferencesRequest extends FormRequest
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
        return [
            'preferences' => ['required', 'array'],
            'preferences.*.notification_key' => ['required', 'string', 'max:100'],
            'preferences.*.channel' => ['required', 'string', Rule::enum(NoticeChannel::class)],
            'preferences.*.enabled' => ['required', 'boolean'],
        ];
    }
}
