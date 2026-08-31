<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Billing;

use App\Models\Landlord\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a new landlord invoice.
 */
class StoreInvoiceRequest extends FormRequest
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
             * Subscription whose snapshotted terms become the invoice amount.
             *
             * @example 1
             */
            'subscription_id' => ['required', 'integer', Rule::exists(Subscription::class, 'id')->whereNull('deleted_at')],
            /**
             * Optional due date.
             *
             * @example 2026-09-12T20:00:00Z
             */
            'due_at' => ['sometimes', 'nullable', 'date'],
            /**
             * Optional landlord note.
             *
             * @example August usage
             */
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'subscription_id' => 'subscription',
            'due_at' => 'due date',
            'notes' => 'notes',
        ];
    }
}
