<?php

declare(strict_types=1);

namespace Database\Factories\Landlord;

use App\Enums\Landlord\SettingType;
use App\Models\Landlord\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group' => 'platform',
            'key' => 'platform.'.fake()->unique()->slug(2),
            'type' => SettingType::String,
            'value' => 'Mercora',
            'description' => null,
        ];
    }

    public function boolean(): static
    {
        return $this->state(fn (array $attributes): array => [
            'key' => 'platform.'.fake()->unique()->slug(2).'.enabled',
            'type' => SettingType::Boolean,
            'value' => '1',
        ]);
    }

    public function integer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'key' => 'platform.'.fake()->unique()->slug(2).'.limit',
            'type' => SettingType::Integer,
            'value' => '14',
        ]);
    }

    public function json(): static
    {
        return $this->state(fn (array $attributes): array => [
            'key' => 'platform.'.fake()->unique()->slug(2).'.options',
            'type' => SettingType::Json,
            'value' => json_encode(['locale' => 'en'], JSON_THROW_ON_ERROR),
        ]);
    }
}
