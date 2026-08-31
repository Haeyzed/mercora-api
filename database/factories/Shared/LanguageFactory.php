<?php

declare(strict_types=1);

namespace Database\Factories\Shared;

use App\Models\Shared\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Language>
 */
class LanguageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtolower(fake()->unique()->lexify('??')),
            'name' => fake()->languageCode(),
            'name_native' => fake()->word(),
            'dir' => 'ltr',
        ];
    }
}
