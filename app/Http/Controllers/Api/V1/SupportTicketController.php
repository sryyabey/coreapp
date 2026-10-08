<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupportTicketResource;
use App\Models\AppUser;
use App\Models\SupportTicket;
use App\Services\SupportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupportTicketController extends Controller
{
    private function scoped(Request $request): Builder
    {
        return SupportTicket::where('app_id', $request->attributes->get('mobile_app')->id)->where('user_id', $request->user()->id);
    }

    private function input(Request $request, bool $creating = false): array
    {
        $request->merge(['body' => is_string($request->input('body')) ? trim($request->input('body')) : $request->input('body')]);
        $rules = ['body' => ['required', 'string', 'max:5000'], 'client_request_id' => ['required', 'uuid']];
        foreach (['app_id', 'user_id', 'sender_id', 'sender_type', 'status'] as $field) {
            $rules[$field] = ['prohibited'];
        }
        if ($creating) {
            $request->merge(['subject' => is_string($request->input('subject')) ? trim($request->input('subject')) : $request->input('subject')]);
            $rules['subject'] = ['required', 'string', 'max:160'];
            $rules['locale'] = ['required', Rule::in(['tr', 'en'])];
        }

        return $request->validate($rules);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return SupportTicketResource::collection($this->scoped($request)->with('app')->latest('updated_at')->paginate(20));
    }

    public function show(Request $request, string $app, string $ticket): SupportTicketResource
    {
        return new SupportTicketResource($this->scoped($request)->with(['app', 'messages'])->findOrFail($ticket));
    }

    public function store(Request $request): SupportTicketResource
    {
        $data = $this->input($request, true);
        $ticket = DB::transaction(function () use ($request, $data): SupportTicket {
            AppUser::whereKey($request->attributes->get('app_membership')->id)->lockForUpdate()->firstOrFail();
            $ticket = $this->scoped($request)->where('client_request_id', $data['client_request_id'])->first();
            if ($ticket) {
                abort_unless($ticket->subject === $data['subject'] && $ticket->messages()->oldest('id')->value('body') === $data['body'], 409);

                return $ticket;
            }
            $ticket = new SupportTicket(collect($data)->except('body')->all());
            $ticket->forceFill(['app_id' => $request->attributes->get('mobile_app')->id, 'user_id' => $request->user()->id]);
            $ticket->save();
            $message = $ticket->messages()->create(['sender_id' => $request->user()->id, 'sender_type' => 'user', 'body' => $data['body'], 'client_request_id' => $data['client_request_id']]);

            app(SupportService::class)->customerMessage($ticket, $request->user()->id, $message, true);

            return $ticket;
        });

        return new SupportTicketResource($ticket->load(['app', 'messages']));
    }

    public function message(Request $request, string $app, string $ticket): SupportTicketResource
    {
        $data = $this->input($request);
        $record = DB::transaction(function () use ($request, $ticket, $data): SupportTicket {
            $record = $this->scoped($request)->lockForUpdate()->findOrFail($ticket);
            $message = $record->messages()->firstOrCreate(['sender_type' => 'user', 'client_request_id' => $data['client_request_id']], ['sender_id' => $request->user()->id, 'body' => $data['body']]);
            abort_unless($message->body === $data['body'], 409);
            if ($message->wasRecentlyCreated) {
                app(SupportService::class)->customerMessage($record, $request->user()->id, $message);
            }

            return $record;
        });

        return new SupportTicketResource($record->load(['app', 'messages']));
    }

    public function read(Request $request, string $app, string $ticket): SupportTicketResource
    {
        $data = $request->validate(['last_message_id' => ['required', 'integer', 'min:1']]);
        $record = DB::transaction(function () use ($request, $ticket, $data): SupportTicket {
            $record = $this->scoped($request)->lockForUpdate()->findOrFail($ticket);
            $message = $record->messages()->whereKey($data['last_message_id'])->firstOrFail();
            if (! $record->user_read_at || $record->user_read_at->lt($message->created_at)) {
                $record->update(['user_read_at' => $message->created_at]);
            }

            return $record;
        });

        return new SupportTicketResource($record->load('app'));
    }
}
