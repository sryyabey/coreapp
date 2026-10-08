<?php

namespace App\Services;

use App\Models\AppUser;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SupportService
{
    public function reply(SupportTicket $ticket, User $agent, string $body, string $requestId): SupportMessage
    {
        Gate::forUser($agent)->authorize('update', $ticket);
        $data = Validator::make(['body' => trim($body), 'client_request_id' => $requestId], [
            'body' => ['required', 'string', 'max:5000'], 'client_request_id' => ['required', 'uuid'],
        ])->validate();

        return DB::transaction(function () use ($ticket, $agent, $data): SupportMessage {
            $record = SupportTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if (! $record->app->is_active || ! AppUser::where('app_id', $record->app_id)->where('user_id', $record->user_id)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['body' => 'Uygulama veya kullanıcı üyeliği aktif değil.']);
            }
            $message = $record->messages()->firstOrCreate(['sender_type' => 'staff', 'client_request_id' => $data['client_request_id']], ['sender_id' => $agent->id, 'body' => $data['body']]);
            if ($message->body !== $data['body']) {
                throw ValidationException::withMessages(['body' => 'Bu gönderim kimliği farklı bir yanıt için kullanılmış.']);
            }
            if ($message->wasRecentlyCreated) {
                $record->update(['status' => 'answered', 'last_staff_reply_at' => $message->created_at]);
                app(SupportPush::class)->enqueue($message);
            }

            return $message;
        });
    }

    public function close(SupportTicket $ticket, User $agent): void
    {
        Gate::forUser($agent)->authorize('update', $ticket);
        DB::transaction(function () use ($ticket): void {
            SupportTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail()->update(['status' => 'closed']);
        });
    }
}
