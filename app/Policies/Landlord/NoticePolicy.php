<?php

declare(strict_types=1);

namespace App\Policies\Landlord;

use App\Enums\Landlord\Permission;
use App\Models\Landlord\Notice;
use App\Models\Landlord\User;
use App\Policies\Landlord\Concerns\ChecksPermission;

class NoticePolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allow($user, Permission::NoticesView);
    }

    public function view(User $user, Notice $notice): bool
    {
        return $this->allow($user, Permission::NoticesView);
    }

    public function create(User $user): bool
    {
        return $this->allow($user, Permission::NoticesCreate);
    }

    public function update(User $user, Notice $notice): bool
    {
        return $this->allow($user, Permission::NoticesUpdate);
    }

    public function delete(User $user, ?Notice $notice = null): bool
    {
        return $this->allow($user, Permission::NoticesDelete);
    }

    public function restore(User $user, ?Notice $notice = null): bool
    {
        return $this->allow($user, Permission::NoticesDelete);
    }

    public function read(User $user, Notice $notice): bool
    {
        return $this->allow($user, Permission::NoticesRead);
    }
}
