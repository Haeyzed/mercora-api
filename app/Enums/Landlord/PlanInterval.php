<?php

declare(strict_types=1);

namespace App\Enums\Landlord;

enum PlanInterval: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';
}
