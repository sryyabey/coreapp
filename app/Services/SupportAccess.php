<?php

namespace App\Services;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SupportAccess
{
    public function panelUser(User $user): bool
    {
        return $user->canAccessPanel(Filament::getPanel('manage'));
    }

    public function allApps(User $user): bool
    {
        return $this->panelUser($user) && $user->can('ViewAllApps:SupportTicket');
    }

    public function app(User $user, int $appId): bool
    {
        return $this->panelUser($user) && ($this->allApps($user) || DB::table('support_agent_apps')->where('user_id', $user->id)->where('app_id', $appId)->exists());
    }

    public function scope(Builder $query, User $user): Builder
    {
        if (! $this->panelUser($user)) {
            return $query->whereRaw('1 = 0');
        }
        if ($this->allApps($user)) {
            return $query;
        }

        return $query->whereIn('app_id', DB::table('support_agent_apps')->select('app_id')->where('user_id', $user->id));
    }

    public function canManage(User $user, string $permission): bool
    {
        return $this->panelUser($user) && $user->can($permission);
    }

    public function eligibleAgent(User $user, int $appId): bool
    {
        return $this->app($user, $appId) && $user->can('ViewAny:SupportTicket') && $user->can('View:SupportTicket') && $user->can('Update:SupportTicket');
    }

    public function agents(int $appId): array
    {
        return User::query()->whereHas('roles')->orderBy('name')->get()->filter(fn (User $user): bool => $this->eligibleAgent($user, $appId))->mapWithKeys(fn (User $user): array => [$user->id => $user->name.' · '.$user->email])->all();
    }
}
