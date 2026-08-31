<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Settings;

use App\Enums\Landlord\SettingType;
use App\Models\Landlord\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validate a new landlord platform setting.
 */
class StoreSettingRequest extends FormRequest
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
             * Setting group used to organize the catalog.
             *
             * @example general
             */
            'group' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/'],
            /**
             * Unique dotted setting key. Immutable after create.
             *
             * @example app.name
             */
            'key' => ['required', 'string', 'max:100', 'regex:/^[a-z][a-z0-9_]*(\.[a-z0-9_]+)*$/', Rule::unique(Setting::class, 'key')],
            /**
             * Stored value type.
             *
             * @example string
             */
            'type' => ['required', Rule::enum(SettingType::class)],
            /**
             * Setting value. Shape must match type.
             *
             * @example Mercora
             */
            'value' => ['required'],
            /**
             * Optional landlord note.
             *
             * @example Platform display name
             */
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'group' => 'group',
            'key' => 'key',
            'type' => 'type',
            'value' => 'value',
            'description' => 'description',
        ];
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['type', 'value'])) {
                    return;
                }

                $type = SettingType::tryFrom((string) $this->input('type'));

                if ($type === null || SettingType::accepts($type, $this->input('value'))) {
                    return;
                }

                $validator->errors()->add('value', $this->valueMessage($type));
            },
        ];
    }

    private function valueMessage(SettingType $type): string
    {
        return match ($type) {
            SettingType::String => 'The value must be a string.',
            SettingType::Boolean => 'The value must be true or false.',
            SettingType::Integer => 'The value must be an integer.',
            SettingType::Json => 'The value must be a JSON object or array.',
        };
    }
}
