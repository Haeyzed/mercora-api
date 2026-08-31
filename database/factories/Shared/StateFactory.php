<?php

declare(strict_types=1);

namespace Database\Factories\Shared;

use App\Models\Shared\Country;
use App\Models\Shared\State;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<State>
 */
class StateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'name' => fake()->state(),
            'country_code' => 'XX',
            'state_code' => strtoupper(fake()->lexify('??')),
            'type' => 'state',
            'latitude' => '0',
            'longitude' => '0',
        ];
    }

    public function forCountry(Country $country): static
    {
        return $this->state(fn (): array => [
            'country_id' => $country->id,
            'country_code' => $country->iso2,
        ]);
    }
}
