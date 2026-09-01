<?php

declare(strict_types=1);

namespace App\Policies\Landlord;

use App\Enums\Landlord\Permission;
use App\Models\Landlord\Payment;
use App\Models\Landlord\User;
use App\Policies\Landlord\Concerns\ChecksPermission;

class PaymentPolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allow($user, Permission::PaymentsView);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->allow($user, Permission::PaymentsView);
    }

    public function verify(User $user, Payment $payment): bool
    {
        return $this->allow($user, Permission::PaymentsVerify);
    }
}
