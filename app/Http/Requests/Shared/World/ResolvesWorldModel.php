<?php

declare(strict_types=1);

namespace App\Http\Requests\Shared\World;

use App\Models\Shared\City;
use App\Models\Shared\Country;
use App\Models\Shared\Currency;
use App\Models\Shared\Language;
use App\Models\Shared\State;
use App\Models\Shared\Timezone;
use Illuminate\Database\Eloquent\Model;

trait ResolvesWorldModel
{
    /**
     * @return class-string<Model>
     */
    protected function worldModelClass(): string
    {
        return match (true) {
            $this->routeIs('landlord.world.countries.*') => Country::class,
            $this->routeIs('landlord.world.states.*') => State::class,
            $this->routeIs('landlord.world.cities.*') => City::class,
            $this->routeIs('landlord.world.timezones.*') => Timezone::class,
            $this->routeIs('landlord.world.languages.*') => Language::class,
            $this->routeIs('landlord.world.currencies.*') => Currency::class,
            default => abort(404),
        };
    }
}
