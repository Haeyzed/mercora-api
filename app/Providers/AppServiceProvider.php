<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Landlord\RoleName;
use App\Models\Landlord\User;
use App\Models\Shared\City;
use App\Models\Shared\Country;
use App\Models\Shared\Currency;
use App\Models\Shared\Language;
use App\Models\Shared\State;
use App\Models\Shared\Timezone;
use App\Policies\Landlord\RolePolicy;
use App\Policies\Landlord\WorldPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        RateLimiter::for('landlord-login', function (Request $request) {
            return Limit::perMinute(5)->by(Str::transliterate(
                Str::lower($request->string('email')->toString()).'|'.$request->ip()
            ));
        });

        Gate::before(function (mixed $user, string $ability): ?bool {
            if (! $user instanceof User) {
                return null;
            }

            return $user->hasRole(RoleName::SuperAdmin->value) ? true : null;
        });

        Gate::policy(Role::class, RolePolicy::class);

        foreach ([City::class, Country::class, Currency::class, Language::class, State::class, Timezone::class] as $model) {
            Gate::policy($model, WorldPolicy::class);
        }
    }
}
