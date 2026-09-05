<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Notifications;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate preview sample data for a notification template.
 */
class PreviewNotificationTemplateRequest extends FormRequest
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
            'data' => ['sometimes', 'array'],
        ];
    }
}
