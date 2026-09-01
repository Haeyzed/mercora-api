<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Plans;

use App\Models\Landlord\Feature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate feature ids for a restoreMany action.
 */
class RestoreManyFeaturesRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'integer', Rule::exists(Feature::class, 'id')->whereNotNull('deleted_at')],
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
