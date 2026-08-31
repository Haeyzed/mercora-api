<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Roles;

use App\Enums\Landlord\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Validate an update to a landlord role.
 */
class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role && ($this->user()?->can('update', $role) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');

        return [
            /**
             * Role name.
             *
             * @example Billing
             */
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role->id)],
            /**
             * Complete permission set for the role.
             *
             * @example ["invoices.view", "invoices.pay"]
             */
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['required', 'string', Rule::in(Permission::values())],
        ];
    }
}
