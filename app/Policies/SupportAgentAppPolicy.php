<?php

namespace App\Policies;

use App\Models\SupportAgentApp;
use App\Models\User;
use App\Services\SupportAccess;

class SupportAgentAppPolicy
{
    private function allowed(User $user): bool
    {
        return app(SupportAccess::class)->canManage($user, 'ManageAccess:SupportTicket');
    }

    public function viewAny(User $user): bool
    {
        return $this->allowed($user);
    }

    public function view(User $user, SupportAgentApp $record): bool
    {
        return $this->allowed($user);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user);
    }

    public function update(User $user, SupportAgentApp $record): bool
    {
        return $this->allowed($user);
    }

    public function delete(User $user, SupportAgentApp $record): bool
    {
        return $this->allowed($user);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
