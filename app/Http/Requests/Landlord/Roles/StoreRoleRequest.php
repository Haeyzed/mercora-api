<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Roles;

use App\Enums\Landlord\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Validate a new landlord role.
 */
class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Role::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /**
             * Role name.
             *
             * @example Billing
             */
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->where('guard_name', 'web')],
            /**
             * Permission names to assign.
             *
             * @example ["invoices.view", "invoices.pay"]
             */
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['required', 'string', Rule::in(Permission::values())],
        ];
    }
}
