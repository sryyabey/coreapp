<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StoreNotification;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class StoreNotificationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:StoreNotification');
    }

    public function view(AuthUser $authUser, StoreNotification $record): bool
    {
        return $authUser->can('View:StoreNotification');
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, StoreNotification $record): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, StoreNotification $record): bool
    {
        return false;
    }
}
