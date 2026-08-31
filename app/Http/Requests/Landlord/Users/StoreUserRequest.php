<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Users;

use App\Models\Landlord\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validate a new landlord user.
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
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
             * Unique login email.
             *
             * @example ada@mercora.test
             */
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')],
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
             * Role names to assign.
             *
             * @example ["Operator"]
             */
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ];
    }
}
