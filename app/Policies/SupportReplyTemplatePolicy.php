<?php

namespace App\Policies;

use App\Models\SupportReplyTemplate;
use App\Models\User;
use App\Services\SupportAccess;

class SupportReplyTemplatePolicy
{
    private function allowed(User $user): bool
    {
        return app(SupportAccess::class)->canManage($user, 'ManageTemplates:SupportTicket');
    }

    public function viewAny(User $user): bool
    {
        return $this->allowed($user);
    }

    public function view(User $user, SupportReplyTemplate $record): bool
    {
        return $this->allowed($user);
    }

    public function create(User $user): bool
    {
        return $this->allowed($user);
    }

    public function update(User $user, SupportReplyTemplate $record): bool
    {
        return $this->allowed($user);
    }

    public function delete(User $user, SupportReplyTemplate $record): bool
    {
        return $this->allowed($user);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
