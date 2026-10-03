<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SubscriptionPlan;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class SubscriptionPlanPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SubscriptionPlan');
    }

    public function view(AuthUser $authUser, SubscriptionPlan $subscriptionPlan): bool
    {
        return $authUser->can('View:SubscriptionPlan');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SubscriptionPlan');
    }

    public function update(AuthUser $authUser, SubscriptionPlan $subscriptionPlan): bool
    {
        return $authUser->can('Update:SubscriptionPlan');
    }

    public function delete(AuthUser $authUser, SubscriptionPlan $subscriptionPlan): bool
    {
        return $authUser->can('Delete:SubscriptionPlan');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SubscriptionPlan');
    }

    public function restore(AuthUser $authUser, SubscriptionPlan $subscriptionPlan): bool
    {
        return $authUser->can('Restore:SubscriptionPlan');
    }

    public function forceDelete(AuthUser $authUser, SubscriptionPlan $subscriptionPlan): bool
    {
        return $authUser->can('ForceDelete:SubscriptionPlan');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SubscriptionPlan');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SubscriptionPlan');
    }

    public function replicate(AuthUser $authUser, SubscriptionPlan $subscriptionPlan): bool
    {
        return $authUser->can('Replicate:SubscriptionPlan');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SubscriptionPlan');
    }
}
