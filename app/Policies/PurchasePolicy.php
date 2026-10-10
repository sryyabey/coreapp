<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Purchase;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PurchasePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Purchase');
    }

    public function view(AuthUser $authUser, Purchase $record): bool
    {
        return $authUser->can('View:Purchase');
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser, Purchase $record): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser, Purchase $record): bool
    {
        return false;
    }
}
