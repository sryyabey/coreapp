<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StoreProduct;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class StoreProductPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:StoreProduct');
    }

    public function view(AuthUser $authUser, StoreProduct $storeProduct): bool
    {
        return $authUser->can('View:StoreProduct');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:StoreProduct');
    }

    public function update(AuthUser $authUser, StoreProduct $storeProduct): bool
    {
        return $authUser->can('Update:StoreProduct');
    }

    public function delete(AuthUser $authUser, StoreProduct $storeProduct): bool
    {
        return $authUser->can('Delete:StoreProduct');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:StoreProduct');
    }

    public function restore(AuthUser $authUser, StoreProduct $storeProduct): bool
    {
        return $authUser->can('Restore:StoreProduct');
    }

    public function forceDelete(AuthUser $authUser, StoreProduct $storeProduct): bool
    {
        return $authUser->can('ForceDelete:StoreProduct');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:StoreProduct');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:StoreProduct');
    }

    public function replicate(AuthUser $authUser, StoreProduct $storeProduct): bool
    {
        return $authUser->can('Replicate:StoreProduct');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:StoreProduct');
    }
}
