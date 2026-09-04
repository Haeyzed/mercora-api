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
use App\Services\Landlord\SettingService;
use App\Settings\Landlord\ApiDomain;
use App\Settings\Landlord\BillingDomain;
use App\Settings\Landlord\ComplianceDomain;
use App\Settings\Landlord\LocalizationDomain;
use App\Settings\Landlord\MailDomain;
use App\Settings\Landlord\NotificationsDomain;
use App\Settings\Landlord\PlatformDomain;
use App\Settings\Landlord\RegistrationDomain;
use App\Settings\Landlord\SecurityDomain;
use App\Settings\Landlord\StorageDomain;
use App\Settings\Landlord\SubscriptionsDomain;
use App\Settings\Landlord\TenancyDomain;
use App\Support\Settings\SettingsRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsRegistry::class, function (): SettingsRegistry {
            $registry = new SettingsRegistry;
            $registry->register(new PlatformDomain);
            $registry->register(new RegistrationDomain);
            $registry->register(new LocalizationDomain);
            $registry->register(new BillingDomain);
            $registry->register(new MailDomain);
            $registry->register(new SecurityDomain);
            $registry->register(new TenancyDomain);
            $registry->register(new NotificationsDomain);
            $registry->register(new ApiDomain);
            $registry->register(new StorageDomain);
            $registry->register(new SubscriptionsDomain);
            $registry->register(new ComplianceDomain);

            return $registry;
        });
    }

    public function boot(): void
    {
        $this->configureLocalizationFromSettings();
        $this->configureSanctumFromSettings();
        $this->configureActivityLogRetention();
        $this->configureRateLimiters();
        $this->configureMailFromSettings();

        Password::defaults(function () {
            $min = max(8, (int) $this->setting('security.password_min_length', 8));
            $rule = Password::min($min);

            if ($this->setting('security.require_strong_passwords', false)) {
                $rule = $rule->mixedCase()->numbers();
            }

            return $rule;
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

    private function configureRateLimiters(): void
    {
        RateLimiter::for('landlord-login', function (Request $request) {
            $attempts = max(1, (int) $this->setting('security.max_login_attempts', 5));
            $lockout = max(1, (int) $this->setting('security.lockout_minutes', 15));

            return Limit::perMinutes($lockout, $attempts)->by(Str::transliterate(
                Str::lower($request->string('email')->toString()).'|'.$request->ip()
            ));
        });

        RateLimiter::for('landlord-auth', function (Request $request) {
            $attempts = max(1, (int) $this->setting('security.max_login_attempts', 5));
            $lockout = max(1, (int) $this->setting('security.lockout_minutes', 15));

            return Limit::perMinutes($lockout, $attempts)->by(Str::transliterate(
                Str::lower($request->string('email')->toString()).'|'.$request->ip()
            ));
        });

        RateLimiter::for('landlord-api', function (Request $request) {
            $perMinute = max(1, (int) $this->setting('api.rate_limit_per_minute', 60));
            $burst = max($perMinute, (int) $this->setting('api.burst_limit', 120));
            $key = (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());

            return [
                Limit::perMinute($perMinute)->by('landlord-api:'.$key),
                Limit::perSecond(max(1, (int) ceil($burst / 60)))->by('landlord-api-burst:'.$key),
            ];
        });
    }

    /**
     * Override mail from/reply-to from landlord settings when configured.
     */
    private function configureMailFromSettings(): void
    {
        $fromAddress = $this->setting('mail.from_address');
        $fromName = $this->setting('mail.from_name', config('mail.from.name'));

        if (is_string($fromAddress) && $fromAddress !== '') {
            config([
                'mail.from.address' => $fromAddress,
                'mail.from.name' => is_string($fromName) && $fromName !== '' ? $fromName : config('mail.from.name'),
            ]);
        }

        $replyTo = $this->setting('mail.reply_to_address');

        if (is_string($replyTo) && $replyTo !== '') {
            config([
                'mail.reply_to.address' => $replyTo,
                'mail.reply_to.name' => is_string($fromName) && $fromName !== '' ? $fromName : config('mail.from.name'),
            ]);
        }
    }

    private function configureLocalizationFromSettings(): void
    {
        $timezone = $this->setting('localization.default_timezone');
        $language = $this->setting('localization.default_language');

        if (is_string($timezone) && $timezone !== '') {
            config(['app.timezone' => $timezone]);
            date_default_timezone_set($timezone);
        }

        if (is_string($language) && $language !== '') {
            config(['app.locale' => $language]);
        }
    }

    private function configureSanctumFromSettings(): void
    {
        $minutes = (int) $this->setting('security.session_timeout_minutes', 120);

        if ($minutes > 0) {
            config(['sanctum.expiration' => $minutes]);
        }
    }

    private function configureActivityLogRetention(): void
    {
        $days = (int) $this->setting('compliance.activity_log_retention_days', 365);

        if ($days > 0) {
            config(['activitylog.clean_after_days' => $days]);
        }
    }

    /**
     * Read a setting value, falling back when the settings table is unavailable.
     */
    private function setting(string $key, mixed $default = null): mixed
    {
        try {
            if (! Schema::hasTable('settings')) {
                return $default;
            }

            return $this->app->make(SettingService::class)->value($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}
