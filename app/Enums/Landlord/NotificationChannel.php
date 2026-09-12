<?php

declare(strict_types=1);

namespace App\Enums\Landlord;

enum NotificationChannel: string
{
    case InApp = 'in_app';
    case Mail = 'mail';
    case Push = 'push';
    case Sms = 'sms';

    /**
     * Channels that write into the Notice inbox ledger.
     */
    public function writesNotice(): bool
    {
        return match ($this) {
            self::InApp, self::Mail => true,
            self::Push, self::Sms => false,
        };
    }

    /**
     * Mandatory templates keep these channels enabled.
     */
    public function isLockable(): bool
    {
        return match ($this) {
            self::InApp, self::Mail => true,
            self::Push, self::Sms => false,
        };
    }

    public function settingsKey(): string
    {
        return match ($this) {
            self::InApp => 'notifications.in_app_enabled',
            self::Mail => 'notifications.email_enabled',
            self::Push => 'notifications.push_enabled',
            self::Sms => 'notifications.sms_enabled',
        };
    }

    public function settingsDefault(): bool
    {
        return match ($this) {
            self::InApp, self::Mail => true,
            self::Push, self::Sms => false,
        };
    }
}
