<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Billing;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate an update to an open landlord invoice.
 */
class UpdateInvoiceRequest extends FormRequest
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
            'due_at' => 'due date',
            'notes' => 'notes',
        ];
    }
}
