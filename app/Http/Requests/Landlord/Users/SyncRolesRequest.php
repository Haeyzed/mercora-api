<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Users;

use App\Models\Landlord\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate role assignment for a landlord user.
 */
class SyncRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user instanceof User && ($this->user()?->can('assignRole', $user) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Complete set of role names for the user.
             *
             * @example ["Operator"]
             */
            'roles' => ['required', 'array'],
            'roles.*' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web')],
        ];
    }

    /**
     * @return list<string>
     */
    public function roles(): array
    {
        return $this->validated('roles');
    }
}
