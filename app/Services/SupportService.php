<?php

namespace App\Services;

use App\Models\AppUser;
use App\Models\SupportActivity;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupportService
{
    private function current(SupportTicket $ticket, User $agent, ?int $expectedVersion = null): SupportTicket
    {
        $record = SupportTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
        Gate::forUser($agent)->authorize('update', $record);
        if ($expectedVersion !== null && $record->version !== $expectedVersion) {
            throw ValidationException::withMessages(['body' => 'Görüşme başka bir işlemle güncellendi. Yenileyip son mesajları kontrol edin; metniniz korunuyor.']);
        }

        return $record;
    }

    public function reply(SupportTicket $ticket, User $agent, string $body, string $requestId, bool $closeAfterReply = false, ?int $expectedVersion = null): SupportMessage
    {
        Gate::forUser($agent)->authorize('update', $ticket);
        $data = Validator::make(['body' => trim($body), 'client_request_id' => $requestId], [
            'body' => ['required', 'string', 'max:5000'], 'client_request_id' => ['required', 'uuid'],
        ])->validate();

        return DB::transaction(function () use ($ticket, $agent, $data, $closeAfterReply, $expectedVersion): SupportMessage {
            $record = $this->current($ticket, $agent);
            $existing = $record->messages()->where('sender_type', 'staff')->where('client_request_id', $data['client_request_id'])->first();
            if ($existing) {
                if ($existing->body !== $data['body']) {
                    throw ValidationException::withMessages(['body' => 'Bu gönderim kimliği farklı bir yanıt için kullanılmış.']);
                }

                return $existing;
            }
            $this->current($record, $agent, $expectedVersion);
            if (! $record->app->is_active || ! AppUser::where('app_id', $record->app_id)->where('user_id', $record->user_id)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages(['body' => 'Uygulama veya kullanıcı üyeliği aktif değil.']);
            }
            $message = $record->messages()->create(['sender_type' => 'staff', 'client_request_id' => $data['client_request_id'], 'sender_id' => $agent->id, 'body' => $data['body']]);
            $before = $record->status;
            $record->update(['status' => $closeAfterReply ? 'closed' : 'answered', 'closed_at' => $closeAfterReply ? now() : null,
                'last_staff_reply_at' => $message->created_at, 'version' => $record->version + 1,
                'assigned_to' => $record->assigned_to ?? (app(SupportAccess::class)->eligibleAgent($agent, $record->app_id) ? $agent->id : null)]);
            $this->activity($record, $agent->id, 'reply', ['message_id' => $message->id, 'from' => $before, 'to' => $record->status]);
            app(SupportPush::class)->enqueue($message);

            return $message;
        });
    }

    public function close(SupportTicket $ticket, User $agent, ?int $expectedVersion = null): void
    {
        $this->status($ticket, $agent, 'closed', $expectedVersion);
    }

    public function reopen(SupportTicket $ticket, User $agent, ?int $expectedVersion = null): void
    {
        $this->status($ticket, $agent, 'open', $expectedVersion);
    }

    private function status(SupportTicket $ticket, User $agent, string $status, ?int $expectedVersion): void
    {
        DB::transaction(function () use ($ticket, $agent, $status, $expectedVersion): void {
            $record = $this->current($ticket, $agent, $expectedVersion);
            if ($record->status === $status) {
                return;
            }
            $before = $record->status;
            $record->update(['status' => $status, 'closed_at' => $status === 'closed' ? now() : null, 'version' => $record->version + 1]);
            $this->activity($record, $agent->id, $status === 'closed' ? 'closed' : 'reopened', ['from' => $before, 'to' => $status]);
        });
    }

    public function updateDetails(SupportTicket $ticket, User $agent, array $input, ?int $expectedVersion = null): void
    {
        $data = Validator::make($input, [
            'priority' => ['required', Rule::in(array_keys(SupportTicket::priorities()))],
            'category' => ['required', Rule::in(array_keys(SupportTicket::categories()))],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'], 'due_at' => ['nullable', 'date'],
        ])->validate();
        DB::transaction(function () use ($ticket, $agent, $data, $expectedVersion): void {
            $record = $this->current($ticket, $agent, $expectedVersion);
            if (($data['assigned_to'] ?? null) !== null && ! app(SupportAccess::class)->eligibleAgent(User::findOrFail($data['assigned_to']), $record->app_id)) {
                throw ValidationException::withMessages(['assigned_to' => 'Görevlinin bu uygulamada destek erişimi ve yanıtlama izni yok.']);
            }
            $record->fill($data);
            $changes = [];
            foreach (['priority', 'category', 'assigned_to', 'due_at'] as $key) {
                if ($record->isDirty($key)) {
                    $changes[$key] = ['from' => $record->getRawOriginal($key), 'to' => $record->getAttributes()[$key] ?? null];
                }
            }
            if ($changes === []) {
                return;
            }
            $record->version++;
            $record->save();
            $this->activity($record, $agent->id, 'updated', $changes);
        });
    }

    public function take(SupportTicket $ticket, User $agent): void
    {
        DB::transaction(function () use ($ticket, $agent): void {
            $record = $this->current($ticket, $agent);
            if ($record->assigned_to !== null && $record->assigned_to !== $agent->id) {
                throw ValidationException::withMessages(['assigned_to' => 'Talep başka bir görevliye atanmış. Atamayı yönet seçeneğini kullanın.']);
            }
            $this->updateDetails($record, $agent, ['priority' => $record->priority, 'category' => $record->category, 'assigned_to' => $agent->id, 'due_at' => $record->due_at]);
        });
    }

    public function note(SupportTicket $ticket, User $agent, string $body, string $requestId): SupportActivity
    {
        $data = Validator::make(['body' => trim($body), 'request_id' => $requestId], ['body' => ['required', 'string', 'max:5000'], 'request_id' => ['required', 'uuid']])->validate();

        return DB::transaction(function () use ($ticket, $agent, $data): SupportActivity {
            $record = $this->current($ticket, $agent);
            $note = $record->activities()->firstOrCreate(['request_id' => $data['request_id']], ['type' => 'note', 'actor_id' => $agent->id, 'body' => $data['body']]);
            if ($note->body !== $data['body']) {
                throw ValidationException::withMessages(['body' => 'Bu gönderim kimliği farklı bir not için kullanılmış.']);
            }
            if ($note->wasRecentlyCreated) {
                $record->update(['version' => $record->version + 1]);
            }

            return $note;
        });
    }

    public function customerMessage(SupportTicket $ticket, int $userId, SupportMessage $message, bool $created = false): void
    {
        $before = $ticket->status;
        $ticket->update(['status' => 'open', 'closed_at' => null, 'last_customer_message_at' => $message->created_at, 'version' => $ticket->version + 1]);
        $this->activity($ticket, $userId, $created ? 'created' : 'customer_message', ['message_id' => $message->id, 'from' => $before, 'to' => 'open']);
    }

    private function activity(SupportTicket $ticket, ?int $actor, string $type, array $metadata = []): void
    {
        $ticket->activities()->create(['actor_id' => $actor, 'type' => $type, 'metadata' => $metadata]);
    }
}
