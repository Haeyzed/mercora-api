<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Users;

use App\Enums\Tenant\RoleName;
use App\Models\Tenant\User;
use App\Support\Tenant\Authorization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validate a new tenant staff user.
 */
class StoreUserRequest extends FormRequest
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
             * Display name.
             *
             * @example Ada Lovelace
             */
            'name' => ['required', 'string', 'max:255'],
            /**
             * Unique login email within the tenant.
             *
             * @example ada@acme.test
             */
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')],
            /**
             * Optional E.164 phone number.
             *
             * @example +15551234567
             */
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            /**
             * Initial password. Hashed before storage.
             *
             * @example password
             */
            'password' => ['required', 'string', Password::defaults()],
            /**
             * Whether the user may sign in. Defaults to true.
             *
             * @example true
             */
            'is_active' => ['sometimes', 'boolean'],
            /**
             * Role names to assign. Defaults to Staff when omitted.
             *
             * @example ["Staff"]
             */
            'roles' => ['sometimes', 'array'],
            'roles.*' => [
                'required',
                'string',
                Rule::in([RoleName::Admin->value, RoleName::Staff->value]),
                Rule::exists('roles', 'name')->where('guard_name', Authorization::GUARD),
            ],
        ];
    }
}
