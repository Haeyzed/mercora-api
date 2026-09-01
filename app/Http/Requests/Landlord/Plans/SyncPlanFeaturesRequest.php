<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Plans;

use App\Models\Landlord\Feature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate entitlement features attached to a plan.
 */
class SyncPlanFeaturesRequest extends FormRequest
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
            'features' => ['required', 'array'],
            'features.*.feature_id' => ['required', 'integer', Rule::exists(Feature::class, 'id')->whereNull('deleted_at')],
            'features.*.value' => ['required'],
        ];
    }
}
