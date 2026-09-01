<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Plans;

use App\Enums\Landlord\PlanInterval;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate an update to a plan price.
 */
class UpdatePlanPriceRequest extends FormRequest
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
            'currency' => ['sometimes', 'string', 'size:3', 'alpha', 'uppercase'],
            'amount' => ['sometimes', 'integer', 'min:0'],
            'interval' => ['sometimes', Rule::enum(PlanInterval::class)],
            'interval_count' => ['sometimes', 'integer', 'min:1', 'max:36'],
            'trial_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
        ];
    }
}
