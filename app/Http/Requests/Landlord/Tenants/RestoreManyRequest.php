<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Tenants;

use App\Models\Landlord\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate tenant ids for a restoreMany action.
 */
class RestoreManyRequest extends FormRequest
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
             * Soft-deleted tenant ids to restore.
             *
             * @example ["9d8f0a1e-2b3c-4d5e-8f70-1234567890ab"]
             */
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'uuid', Rule::exists(Tenant::class, 'id')->whereNotNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'ids' => 'ids',
            'ids.*' => 'id',
        ];
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return $this->validated('ids');
    }
}
