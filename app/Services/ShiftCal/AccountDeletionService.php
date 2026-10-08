<?php

namespace App\Services\ShiftCal;

use App\Models\AppUser;
use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AccountDeletionService
{
    public function delete(AppUser $membership): void
    {
        $user = User::whereKey($membership->user_id)->lockForUpdate()->firstOrFail();
        $membership = AppUser::whereKey($membership->id)->lockForUpdate()->firstOrFail();
        $appId = $membership->app_id;
        $userId = $membership->user_id;
        $connections = DB::table('shiftcal_partner_links')->where('app_id', $appId)->where(function ($query) use ($userId): void {
            $query->where('user_id', $userId)->orWhere('partner_user_id', $userId);
        })->pluck('connection_id');
        DB::table('shiftcal_push_outbox')->where('app_id', $appId)->whereIn('connection_id', $connections)->delete();
        foreach (['shiftcal_partner_links' => ['user_id', 'partner_user_id'], 'shiftcal_shared_plans' => ['proposer_id', 'recipient_id']] as $table => $columns) {
            DB::table($table)->where('app_id', $appId)->where(function ($query) use ($columns, $userId): void {
                foreach ($columns as $column) {
                    $query->orWhere($column, $userId);
                }
            })->delete();
        }
        foreach (['shiftcal_events', 'shiftcal_shift_templates', 'shiftcal_wage_settings', 'shiftcal_cloud_backups', 'shiftcal_template_applications', 'shiftcal_partner_invitations', 'shiftcal_notification_preferences', 'shiftcal_push_outbox', 'shiftcal_push_devices', 'support_tickets', 'support_push_outbox', 'support_push_devices', 'purchases'] as $table) {
            DB::table($table)->where('app_id', $appId)->where('user_id', $userId)->delete();
        }
        foreach (Device::where('app_id', $appId)->where('user_id', $userId)->get() as $device) {
            $device->tokens()->delete();
            $device->delete();
        }
        $membership->delete();
        if (! $user->appMemberships()->exists() && ! $user->roles()->exists() && ! $user->permissions()->exists() && ! DB::table('support_agent_apps')->where('user_id', $userId)->exists()) {
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $userId)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $user->delete();
        }
    }
}
