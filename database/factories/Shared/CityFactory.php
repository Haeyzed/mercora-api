<?php

declare(strict_types=1);

namespace Database\Factories\Shared;

use App\Models\Shared\City;
use App\Models\Shared\Country;
use App\Models\Shared\State;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<City>
 */
class CityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'state_id' => State::factory(),
            'name' => fake()->city(),
            'country_code' => 'XX',
            'state_code' => 'YY',
            'latitude' => '0',
            'longitude' => '0',
        ];
    }

    public function forState(State $state): static
    {
        return $this->state(fn (): array => [
            'country_id' => $state->country_id,
            'state_id' => $state->id,
            'country_code' => $state->country_code,
            'state_code' => $state->state_code,
        ]);
    }
}
