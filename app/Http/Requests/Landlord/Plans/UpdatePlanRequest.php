<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Plans;

use App\Enums\Landlord\PlanInterval;
use App\Enums\Landlord\PlanStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate an update to a landlord plan.
 */
class UpdatePlanRequest extends FormRequest
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
             * Price in the smallest currency unit (cents).
             *
             * @example 7900
             */
            'price' => ['sometimes', 'integer', 'min:0'],
            /**
             * ISO 4217 currency code.
             *
             * @example USD
             */
            'currency' => ['sometimes', 'string', 'size:3', 'alpha', 'uppercase'],
            /**
             * Billing interval.
             *
             * @example yearly
             */
            'interval' => ['sometimes', Rule::enum(PlanInterval::class)],
            /**
             * Number of trial days before billing starts.
             *
             * @example 14
             */
            'trial_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            /**
             * Plan lifecycle status.
             *
             * @example active
             */
            'status' => ['sometimes', Rule::enum(PlanStatus::class)],
            /**
             * Feature labels shown with the plan.
             *
             * @example ["Online store", "Priority support"]
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
            'price' => 'price',
            'currency' => 'currency',
            'interval' => 'interval',
            'trial_days' => 'trial days',
            'status' => 'status',
            'features' => 'features',
            'features.*' => 'feature',
        ];
    }
}
