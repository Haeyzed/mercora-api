<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Billing;

use App\Models\Landlord\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate invoice ids for a restoreMany action.
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
             * Soft-deleted invoice ids to restore.
             *
             * @example [1, 2, 3]
             */
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', Rule::exists(Invoice::class, 'id')->whereNotNull('deleted_at')],
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
     * @return list<int>
     */
    public function ids(): array
    {
        return $this->validated('ids');
    }
}
