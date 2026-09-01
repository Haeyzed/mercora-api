<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Plans;

use App\Enums\Landlord\FeatureType;
use App\Models\Landlord\Feature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate an update to an entitlement feature.
 */
class UpdateFeatureRequest extends FormRequest
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
        /** @var Feature|null $feature */
        $feature = $this->route('feature');

        return [
            'key' => ['sometimes', 'string', 'max:100', Rule::unique(Feature::class, 'key')->whereNull('deleted_at')->ignore($feature?->id)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'type' => ['sometimes', Rule::enum(FeatureType::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
