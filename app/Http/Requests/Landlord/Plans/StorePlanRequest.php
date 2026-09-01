<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Plans;

use App\Enums\Landlord\PlanInterval;
use App\Enums\Landlord\PlanStatus;
use App\Http\Requests\Landlord\Plans\Concerns\MapsLegacyPlanFeatureHighlights;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a new landlord plan.
 */
class StorePlanRequest extends FormRequest
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
             * Plan display name. The slug is generated from this value.
             *
             * @example Starter Plan
             */
            'name' => ['required', 'string', 'max:255'],
            /**
             * Optional plan summary shown to landlords.
             *
             * @example For new stores
             */
            'description' => ['sometimes', 'nullable', 'string'],
            /**
             * Initial billable price for the plan.
             */
            'price' => ['required', 'array'],
            /**
             * Price in the smallest currency unit (cents).
             *
             * @example 2900
             */
            'price.amount' => ['required', 'integer', 'min:0'],
            /**
             * ISO 4217 currency code. World currency is ISO reference only.
             *
             * @example USD
             */
            'price.currency' => ['required', 'string', 'size:3', 'alpha', 'uppercase'],
            /**
             * Billing interval.
             *
             * @example monthly
             */
            'price.interval' => ['required', Rule::enum(PlanInterval::class)],
            /**
             * Number of billing intervals per charge.
             *
             * @example 1
             */
            'price.interval_count' => ['sometimes', 'integer', 'min:1', 'max:36'],
            /**
             * Number of trial days before billing starts.
             *
             * @example 14
             */
            'price.trial_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            /**
             * Plan lifecycle status. Defaults to draft when omitted.
             *
             * @example draft
             */
            'status' => ['sometimes', Rule::enum(PlanStatus::class)],
            /**
             * Marketing bullet points shown with the plan.
             *
             * @example ["Online store", "Basic reports"]
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
            'price' => 'price',
            'price.amount' => 'price amount',
            'price.currency' => 'price currency',
            'price.interval' => 'price interval',
            'price.interval_count' => 'price interval count',
            'price.trial_days' => 'price trial days',
            'status' => 'status',
            'feature_highlights' => 'feature highlights',
            'feature_highlights.*' => 'feature highlight',
            'features' => 'features',
            'features.*' => 'feature',
        ];
    }
}
