<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Plans;

use App\Enums\Landlord\PlanStatus;
use App\Http\Requests\Landlord\Plans\Concerns\MapsLegacyPlanFeatureHighlights;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate an update to a landlord plan.
 */
class UpdatePlanRequest extends FormRequest
{
    use MapsLegacyPlanFeatureHighlights;

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
             * Plan display name. Updating this regenerates the slug.
             *
             * @example Growth Plan
             */
            'name' => ['sometimes', 'string', 'max:255'],
            /**
             * Optional plan summary shown to landlords.
             *
             * @example For growing stores
             */
            'description' => ['sometimes', 'nullable', 'string'],
            /**
             * Plan lifecycle status.
             *
             * @example active
             */
            'status' => ['sometimes', Rule::enum(PlanStatus::class)],
            /**
             * Marketing bullet points shown with the plan.
             *
             * @example ["Online store", "Priority support"]
             */
            'feature_highlights' => ['sometimes', 'nullable', 'array'],
            'feature_highlights.*' => ['required', 'string', 'max:255'],
            /**
             * @deprecated Use feature_highlights. Accepted for backward compatibility.
             */
            'features' => ['sometimes', 'nullable', 'array'],
            'features.*' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'name',
            'description' => 'description',
            'status' => 'status',
            'feature_highlights' => 'feature highlights',
            'feature_highlights.*' => 'feature highlight',
            'features' => 'features',
            'features.*' => 'feature',
        ];
    }
}
