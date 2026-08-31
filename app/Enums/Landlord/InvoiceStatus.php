<?php

declare(strict_types=1);

namespace App\Enums\Landlord;

enum InvoiceStatus: string
{
    case Open = 'open';
    case Paid = 'paid';
    case Void = 'void';
}
