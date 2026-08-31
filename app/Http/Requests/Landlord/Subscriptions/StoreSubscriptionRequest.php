<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Subscriptions;

use App\Enums\Landlord\PlanStatus;
use App\Models\Landlord\Plan;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validate a new landlord subscription.
 */
class StoreSubscriptionRequest extends FormRequest
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
             * Tenant to subscribe.
             *
             * @example 9d8f0a1e-2b3c-4d5e-8f70-1234567890ab
             */
            'tenant_id' => ['required', 'uuid', Rule::exists(Tenant::class, 'id')->whereNull('deleted_at')],
            /**
             * Active catalog plan.
             *
             * @example 1
             */
            'plan_id' => ['required', 'integer', Rule::exists(Plan::class, 'id')->where('status', PlanStatus::Active->value)->whereNull('deleted_at')],
            /**
             * Active plan price for the subscription.
             *
             * @example 1
             */
            'plan_price_id' => ['sometimes', 'integer', Rule::exists('plan_prices', 'id')->where('is_active', true)->whereNull('deleted_at')],
            /**
             * When the subscription starts. Defaults to now.
             *
             * @example 2026-08-29T20:00:00Z
             */
            'starts_at' => ['sometimes', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tenant_id' => 'tenant',
            'plan_id' => 'plan',
            'starts_at' => 'start date',
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('tenant_id')) {
                    return;
                }

                $hasCurrent = Subscription::query()
                    ->where('tenant_id', $this->input('tenant_id'))
                    ->current()
                    ->exists();

                if ($hasCurrent) {
                    $validator->errors()->add('tenant_id', 'The tenant already has a current subscription.');
                }
            },
        ];
    }
}
