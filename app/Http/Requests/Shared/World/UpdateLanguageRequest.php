<?php

declare(strict_types=1);

namespace App\Http\Requests\Shared\World;

use App\Models\Shared\Language;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate an update to a shared language.
 */
class UpdateLanguageRequest extends FormRequest
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
        $language = $this->route('language');

        return [
            /**
             * Two-letter language code.
             *
             * @example en
             */
            'code' => ['sometimes', 'string', 'size:2', Rule::unique(Language::class, 'code')->ignore($language)],
            /**
             * Language name in English.
             *
             * @example English
             */
            'name' => ['sometimes', 'string', 'max:255'],
            /**
             * Language name in its native script.
             *
             * @example English
             */
            'name_native' => ['sometimes', 'string', 'max:255'],
            /**
             * Text direction.
             *
             * @example ltr
             */
            'dir' => ['sometimes', 'string', Rule::in(['ltr', 'rtl'])],
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
