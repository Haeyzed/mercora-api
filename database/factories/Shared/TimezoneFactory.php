<?php

declare(strict_types=1);

namespace Database\Factories\Shared;

use App\Models\Shared\Country;
use App\Models\Shared\Timezone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Timezone>
 */
class TimezoneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'name' => fake()->timezone(),
        ];
    }

    public function forCountry(Country $country): static
    {
        return $this->state(fn (): array => [
            'country_id' => $country->id,
        ]);
    }
}
