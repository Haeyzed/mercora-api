<?php

declare(strict_types=1);

namespace App\Enums\Landlord;

enum ApiKeyStatus: string
{
    case Active = 'active';
    case Revoked = 'revoked';
}
