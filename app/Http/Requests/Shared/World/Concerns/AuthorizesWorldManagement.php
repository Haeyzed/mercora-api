<?php

declare(strict_types=1);

namespace App\Http\Requests\Shared\World\Concerns;

use App\Enums\Landlord\Permission;

trait AuthorizesWorldManagement
{
    public function authorizeWorldManagement(): bool
    {
        return $this->user()?->can(Permission::WorldManage->value) ?? false;
    }
}
