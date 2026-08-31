<?php

declare(strict_types=1);

namespace Database\Factories\Shared;

use App\Models\Shared\Country;
use App\Models\Shared\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'country_id' => Country::factory(),
            'name' => 'US Dollar',
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'precision' => 2,
            'symbol' => '$',
            'symbol_native' => '$',
            'symbol_first' => true,
            'decimal_mark' => '.',
            'thousands_separator' => ',',
        ];
    }

    public function forCountry(Country $country): static
    {
        return $this->state(fn (): array => [
            'country_id' => $country->id,
        ]);
    }
}
