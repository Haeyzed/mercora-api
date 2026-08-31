<?php

declare(strict_types=1);

namespace App\Policies\Landlord;

use App\Enums\Landlord\Permission;
use App\Models\Landlord\Invoice;
use App\Models\Landlord\User;
use App\Policies\Landlord\Concerns\ChecksPermission;

class InvoicePolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allow($user, Permission::InvoicesView);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->allow($user, Permission::InvoicesView);
    }

    public function create(User $user): bool
    {
        return $this->allow($user, Permission::InvoicesCreate);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->allow($user, Permission::InvoicesUpdate);
    }

    public function delete(User $user, ?Invoice $invoice = null): bool
    {
        return $this->allow($user, Permission::InvoicesDelete);
    }

    public function restore(User $user, ?Invoice $invoice = null): bool
    {
        return $this->allow($user, Permission::InvoicesDelete);
    }

    public function pay(User $user, Invoice $invoice): bool
    {
        return $this->allow($user, Permission::InvoicesPay);
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $this->allow($user, Permission::InvoicesVoid);
    }
}
