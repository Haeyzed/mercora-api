<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Plans;

use App\Enums\Landlord\FeatureType;
use App\Models\Landlord\Feature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a new entitlement feature.
 */
class StoreFeatureRequest extends FormRequest
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
            'key' => ['required', 'string', 'max:100', Rule::unique(Feature::class, 'key')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'type' => ['required', Rule::enum(FeatureType::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
