<?php

declare(strict_types=1);

namespace App\Policies\Landlord;

use App\Enums\Landlord\Permission;
use App\Models\Landlord\NotificationTemplate;
use App\Models\Landlord\User;
use App\Policies\Landlord\Concerns\ChecksPermission;

class NotificationTemplatePolicy
{
    use ChecksPermission;

    public function viewAny(User $user): bool
    {
        return $this->allow($user, Permission::NotificationTemplatesView);
    }

    public function view(User $user, NotificationTemplate $notificationTemplate): bool
    {
        return $this->allow($user, Permission::NotificationTemplatesView);
    }

    public function create(User $user): bool
    {
        return $this->allow($user, Permission::NotificationTemplatesCreate);
    }

    public function update(User $user, NotificationTemplate $notificationTemplate): bool
    {
        return $this->allow($user, Permission::NotificationTemplatesUpdate);
    }

    public function delete(User $user, NotificationTemplate $notificationTemplate): bool
    {
        return $this->allow($user, Permission::NotificationTemplatesDelete);
    }

    public function preview(User $user, NotificationTemplate $notificationTemplate): bool
    {
        return $this->allow($user, Permission::NotificationTemplatesView);
    }
}
