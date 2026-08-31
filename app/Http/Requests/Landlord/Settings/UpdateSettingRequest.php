<?php

declare(strict_types=1);

namespace App\Http\Requests\Landlord\Settings;

use App\Enums\Landlord\SettingType;
use App\Models\Landlord\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validate an update to a landlord platform setting.
 */
class UpdateSettingRequest extends FormRequest
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
             * @example mail
             */
            'group' => ['sometimes', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/'],
            /**
             * Stored value type.
             *
             * @example boolean
             */
            'type' => ['sometimes', Rule::enum(SettingType::class)],
            /**
             * Setting value. Shape must match type.
             *
             * @example false
             */
            'value' => ['sometimes'],
            /**
             * Optional landlord note.
             *
             * @example Disable public registration
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
                if ($validator->errors()->hasAny(['type', 'value']) || ! $this->exists('value')) {
                    return;
                }

                $type = $this->resolvedType();

                if ($type === null || SettingType::accepts($type, $this->input('value'))) {
                    return;
                }

                $validator->errors()->add('value', $this->valueMessage($type));
            },
        ];
    }

    private function resolvedType(): ?SettingType
    {
        if ($this->filled('type')) {
            return SettingType::tryFrom((string) $this->input('type'));
        }

        $setting = $this->route('setting');

        return $setting instanceof Setting ? $setting->type : null;
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
