<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Users;

use App\Models\Tenant\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validate an update to a tenant staff user.
 */
class UpdateUserRequest extends FormRequest
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
        $user = $this->route('user');

        return [
            /**
             * Display name.
             *
             * @example Ada Lovelace
             */
            'name' => ['sometimes', 'string', 'max:255'],
            /**
             * Unique login email within the tenant.
             *
             * @example ada@acme.test
             */
            'email' => ['sometimes', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($user?->id)],
            /**
             * Optional E.164 phone number.
             *
             * @example +15551234567
             */
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            /**
             * New password. Hashed before storage.
             *
             * @example NewPassword1!
             */
            'password' => ['sometimes', 'string', Password::defaults()],
            /**
             * Whether the user may sign in.
             *
             * @example true
             */
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
