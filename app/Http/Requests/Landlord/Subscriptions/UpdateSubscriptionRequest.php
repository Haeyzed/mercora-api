<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Subscriptions;

use App\Enums\Landlord\PlanStatus;
use App\Models\Landlord\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a plan change on a landlord subscription.
 */
class UpdateSubscriptionRequest extends FormRequest
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
             * Active catalog plan to switch to.
             *
             * @example 2
             */
            'plan_id' => ['required', 'integer', Rule::exists(Plan::class, 'id')->where('status', PlanStatus::Active->value)->whereNull('deleted_at')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'plan_id' => 'plan',
        ];
    }
}
