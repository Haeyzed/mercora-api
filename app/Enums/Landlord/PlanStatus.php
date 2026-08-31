<?php

declare(strict_types=1);

namespace App\Enums\Landlord;

enum PlanStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
