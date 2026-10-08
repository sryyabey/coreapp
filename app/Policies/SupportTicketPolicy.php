<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportAccess;
use Filament\Facades\Filament;

class SupportTicketPolicy
{
    private function allowed(User $user, string $permission): bool
    {
        return $user->canAccessPanel(Filament::getPanel('manage')) && $user->can($permission);
    }

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, 'ViewAny:SupportTicket');
    }

    public function view(User $user, SupportTicket $ticket): bool
    {
        return $this->allowed($user, 'View:SupportTicket') && app(SupportAccess::class)->app($user, $ticket->app_id);
    }

    public function update(User $user, SupportTicket $ticket): bool
    {
        return $this->allowed($user, 'Update:SupportTicket') && app(SupportAccess::class)->eligibleAgent($user, $ticket->app_id);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, SupportTicket $ticket): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
