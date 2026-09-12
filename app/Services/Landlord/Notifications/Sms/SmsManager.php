<?php

declare(strict_types=1);

namespace App\Services\Landlord\Notifications\Sms;

use App\Contracts\Landlord\Notifications\SmsProvider;
use App\Services\Landlord\Notifications\Sms\Providers\AfricasTalkingSmsProvider;
use App\Services\Landlord\Notifications\Sms\Providers\AmazonSnsSmsProvider;
use App\Services\Landlord\Notifications\Sms\Providers\BulkSmsProvider;
use App\Services\Landlord\Notifications\Sms\Providers\HubtelSmsProvider;
use App\Services\Landlord\Notifications\Sms\Providers\MessageBirdSmsProvider;
use App\Services\Landlord\Notifications\Sms\Providers\TermiiSmsProvider;
use App\Services\Landlord\Notifications\Sms\Providers\TwilioSmsProvider;
use App\Services\Landlord\Notifications\Sms\Providers\VonageSmsProvider;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves configured SMS provider drivers.
 */
class SmsManager
{
    public function __construct(private readonly Container $container) {}

    /**
     * Resolve an SMS provider driver by name.
     */
    public function driver(?string $name = null): SmsProvider
    {
        $name ??= (string) config('notifications.sms.default', 'null');

        if ($name === '') {
            $name = 'null';
        }

        return match ($name) {
            'null' => $this->container->make(NullSmsProvider::class),
            'twilio' => $this->container->make(TwilioSmsProvider::class),
            'vonage' => $this->container->make(VonageSmsProvider::class),
            'messagebird' => $this->container->make(MessageBirdSmsProvider::class),
            'sns', 'amazon_sns' => $this->container->make(AmazonSnsSmsProvider::class),
            'termii' => $this->container->make(TermiiSmsProvider::class),
            'africastalking' => $this->container->make(AfricasTalkingSmsProvider::class),
            'bulksms' => $this->container->make(BulkSmsProvider::class),
            'hubtel' => $this->container->make(HubtelSmsProvider::class),
            default => throw new InvalidArgumentException("Unsupported SMS driver [{$name}]."),
        };
    }
}
