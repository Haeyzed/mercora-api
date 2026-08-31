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
            'group' => 'general',
            'key' => 'app.'.fake()->unique()->slug(2),
            'type' => SettingType::String,
            'value' => 'Mercora',
            'description' => 'Platform display name',
        ];
    }

    public function boolean(): static
    {
        return $this->state(fn (array $attributes): array => [
            'key' => 'app.'.fake()->unique()->slug(2).'.enabled',
            'type' => SettingType::Boolean,
            'value' => '1',
            'description' => 'Feature flag',
        ]);
    }

    public function integer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'key' => 'app.'.fake()->unique()->slug(2).'.limit',
            'type' => SettingType::Integer,
            'value' => '14',
            'description' => 'Numeric limit',
        ]);
    }

    public function json(): static
    {
        return $this->state(fn (array $attributes): array => [
            'key' => 'app.'.fake()->unique()->slug(2).'.options',
            'type' => SettingType::Json,
            'value' => json_encode(['locale' => 'en'], JSON_THROW_ON_ERROR),
            'description' => 'Structured options',
        ]);
    }
}
