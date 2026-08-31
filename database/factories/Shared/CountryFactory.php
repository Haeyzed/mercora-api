<?php

declare(strict_types=1);

namespace Database\Factories\Shared;

use App\Models\Shared\Country;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Country>
 */
class CountryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'iso2' => strtoupper(fake()->unique()->lexify('??')),
            'name' => fake()->country(),
            'status' => 1,
            'phone_code' => (string) fake()->numberBetween(1, 999),
            'iso3' => strtoupper(fake()->unique()->lexify('???')),
            'native' => fake()->country(),
            'region' => 'Africa',
            'subregion' => 'Western Africa',
            'latitude' => '9.08200000',
            'longitude' => '8.67530000',
            'emoji' => '🇳🇬',
            'emojiU' => 'U+1F1F3 U+1F1EC',
        ];
    }
}
