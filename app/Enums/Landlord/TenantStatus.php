<?php

declare(strict_types=1);

namespace App\Enums\Landlord;

enum TenantStatus: string
{
    case Pending = 'pending';
    case Provisioning = 'provisioning';
    case Failed = 'failed';
    case Active = 'active';
    case Suspended = 'suspended';

    /**
     * @return list<self>
     */
    public static function provisionable(): array
    {
        return [self::Pending, self::Failed];
    }

    public function canSuspend(): bool
    {
        return $this === self::Active;
    }

    public function canReactivate(): bool
    {
        return $this === self::Suspended;
    }

    public function canActivate(): bool
    {
        return in_array($this, [self::Pending, self::Failed], true);
    }
}
