<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StoreApp;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class StoreAppPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:StoreApp');
    }

    public function view(AuthUser $authUser, StoreApp $storeApp): bool
    {
        return $authUser->can('View:StoreApp');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:StoreApp');
    }

    public function update(AuthUser $authUser, StoreApp $storeApp): bool
    {
        return $authUser->can('Update:StoreApp');
    }

    public function delete(AuthUser $authUser, StoreApp $storeApp): bool
    {
        return $authUser->can('Delete:StoreApp');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:StoreApp');
    }

    public function restore(AuthUser $authUser, StoreApp $storeApp): bool
    {
        return $authUser->can('Restore:StoreApp');
    }

    public function forceDelete(AuthUser $authUser, StoreApp $storeApp): bool
    {
        return $authUser->can('ForceDelete:StoreApp');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:StoreApp');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:StoreApp');
    }

    public function replicate(AuthUser $authUser, StoreApp $storeApp): bool
    {
        return $authUser->can('Replicate:StoreApp');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:StoreApp');
    }
}
