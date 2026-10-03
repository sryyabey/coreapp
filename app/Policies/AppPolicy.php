<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\App;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class AppPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:App');
    }

    public function view(AuthUser $authUser, App $app): bool
    {
        return $authUser->can('View:App');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:App');
    }

    public function update(AuthUser $authUser, App $app): bool
    {
        return $authUser->can('Update:App');
    }

    public function delete(AuthUser $authUser, App $app): bool
    {
        return $authUser->can('Delete:App');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:App');
    }

    public function restore(AuthUser $authUser, App $app): bool
    {
        return $authUser->can('Restore:App');
    }

    public function forceDelete(AuthUser $authUser, App $app): bool
    {
        return $authUser->can('ForceDelete:App');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:App');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:App');
    }

    public function replicate(AuthUser $authUser, App $app): bool
    {
        return $authUser->can('Replicate:App');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:App');
    }
}
