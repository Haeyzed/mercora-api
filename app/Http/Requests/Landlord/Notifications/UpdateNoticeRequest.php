<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Notifications;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate an update to an unread landlord notice.
 */
class UpdateNoticeRequest extends FormRequest
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
            /**
             * Notice title.
             *
             * @example Invoice past due
             */
            'title' => ['sometimes', 'string', 'max:255'],
            /**
             * Notice body.
             *
             * @example Acme Stores has an open invoice that is past due.
             */
            'body' => ['sometimes', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'title',
            'body' => 'body',
        ];
    }
}
