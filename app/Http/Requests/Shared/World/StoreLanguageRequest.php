<?php

declare(strict_types=1);

namespace App\Http\Requests\Shared\World;

use App\Models\Shared\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a new shared language.
 */
class StoreLanguageRequest extends FormRequest
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
             * Two-letter language code.
             *
             * @example en
             */
            'code' => ['required', 'string', 'size:2', Rule::unique(Language::class, 'code')],
            /**
             * Language name in English.
             *
             * @example English
             */
            'name' => ['required', 'string', 'max:255'],
            /**
             * Language name in its native script.
             *
             * @example English
             */
            'name_native' => ['required', 'string', 'max:255'],
            /**
             * Text direction.
             *
             * @example ltr
             */
            'dir' => ['required', 'string', Rule::in(['ltr', 'rtl'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'language code',
            'name_native' => 'native name',
            'dir' => 'text direction',
        ];
    }
}
