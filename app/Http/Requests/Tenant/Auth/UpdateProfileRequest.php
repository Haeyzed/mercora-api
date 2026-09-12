<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Auth;

use App\Models\Tenant\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
        /** @var User|null $user */
        $user = $this->user();

        return [
            /**
             * Display name.
             *
             * @example Ada Lovelace
             */
            'name' => ['sometimes', 'string', 'max:255'],
            /**
             * Unique login email.
             *
             * @example ada@acme.test
             */
            'email' => ['sometimes', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($user?->id)],
            /**
             * Optional phone number.
             *
             * @example +15551234567
             */
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
        ];
    }
}
