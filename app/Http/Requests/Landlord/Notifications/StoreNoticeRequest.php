<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Notifications;

use App\Enums\Landlord\NoticeChannel;
use App\Models\Landlord\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a new landlord notice.
 */
class StoreNoticeRequest extends FormRequest
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
             * Landlord user who receives the notice.
             *
             * @example 1
             */
            'user_id' => ['required', 'integer', Rule::exists(User::class, 'id')],
            /**
             * Notice title.
             *
             * @example Invoice past due
             */
            'title' => ['required', 'string', 'max:255'],
            /**
             * Notice body.
             *
             * @example Acme Stores has an open invoice that is past due.
             */
            'body' => ['required', 'string'],
            /**
             * Delivery channel. Defaults to in_app. Mail is recorded only; it is not sent.
             *
             * @example in_app
             */
            'channel' => ['sometimes', Rule::enum(NoticeChannel::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'user',
            'title' => 'title',
            'body' => 'body',
            'channel' => 'channel',
        ];
    }
}
