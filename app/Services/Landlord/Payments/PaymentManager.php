<?php

declare(strict_types=1);

namespace App\Services\Landlord\Payments;

use App\Enums\Landlord\PaymentProvider;
use App\Services\Landlord\Payments\Contracts\PaymentDriver;
use App\Services\Landlord\Payments\Drivers\Flutterwave\FlutterwaveDriver;
use App\Services\Landlord\Payments\Drivers\Paypal\PaypalDriver;
use App\Services\Landlord\Payments\Drivers\Paystack\PaystackDriver;
use App\Services\Landlord\Payments\Drivers\Stripe\StripeDriver;
use App\Services\Landlord\Payments\Exceptions\PaymentException;
use InvalidArgumentException;

/**
 * Resolves and caches payment provider drivers from configuration.
 *
 * Acts as the boundary between application payment logic and provider-specific
 * implementations. Drivers are instantiated once per request lifecycle and
 * keyed by {@see PaymentProvider} value.
 */
class PaymentManager
{
    /**
     * @var array<string, PaymentDriver>
     */
    private array $drivers = [];

    /**
     * Resolve a configured payment driver by name.
     *
     * Falls back to {@see config('payments.default')} when no name is given.
     * Resolved instances are memoized for the lifetime of this manager.
     *
     * @param  string|null  $name  Provider slug (e.g. {@see PaymentProvider::Flutterwave}).
     *
     * @throws PaymentException When the driver slug has no configuration entry.
     * @throws InvalidArgumentException When the slug is not a supported provider.
     */
    public function driver(?string $name = null): PaymentDriver
    {
        $name ??= (string) config('payments.default', PaymentProvider::Flutterwave->value);

        if (isset($this->drivers[$name])) {
            return $this->drivers[$name];
        }

        $config = config("payments.drivers.{$name}");

        if (! is_array($config)) {
            throw PaymentException::driverNotConfigured($name);
        }

        return $this->drivers[$name] = match ($name) {
            PaymentProvider::Flutterwave->value => new FlutterwaveDriver($config),
            PaymentProvider::Paystack->value => new PaystackDriver($config),
            PaymentProvider::Stripe->value => new StripeDriver($config),
            PaymentProvider::Paypal->value => new PaypalDriver($config),
            default => throw new InvalidArgumentException("Unsupported payment driver [{$name}]."),
        };
    }
}
