<?php

namespace App\Http\Controllers\Api\V1\ShiftCal;

use App\Http\Controllers\Controller;
use App\Models\ShiftCal\Event;
use App\Rules\OffsetDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PartnerController extends Controller
{
    public function schedule(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['required', new OffsetDateTime],
            'to' => ['required', new OffsetDateTime, 'after:from'],
        ]);
        $from = CarbonImmutable::parse($filters['from'])->utc();
        $to = CarbonImmutable::parse($filters['to'])->utc();
        abort_if($from->diffInMinutes($to) > 1560, 422, 'Bir seferde en fazla bir gün görüntülenebilir.');
        $link = $this->links($request)->where('user_id', $request->user()->id)->first();
        abort_unless($link, 404, 'Eş bağlantısı bulunamadı.');
        abort_unless(DB::table('app_users')->where('app_id', $link->app_id)->where('user_id', $link->partner_user_id)->where('is_active', true)->exists(), 404, 'Eş bağlantısı kullanılamıyor.');
        $events = Event::where('app_id', $link->app_id)->where('user_id', $link->partner_user_id)
            ->where('starts_at', '<', $to)->where('ends_at', '>', $from)
            ->orderBy('starts_at')->get(['type', 'starts_at', 'ends_at']);

        return response()->json(['data' => $events->map(fn (Event $event): array => [
            'state' => match ($event->type) {
                'work', 'duty' => 'work',
                'sleep' => 'sleep',
                'freeTime' => 'free',
                default => 'busy',
            },
            'starts_at' => ($event->starts_at->lt($from) ? $from : $event->starts_at)->toIso8601String(),
            'ends_at' => ($event->ends_at->gt($to) ? $to : $event->ends_at)->toIso8601String(),
        ])->values()])->header('Cache-Control', 'private, no-store');
    }

    private function links(Request $request): Builder
    {
        return DB::table('shiftcal_partner_links')->where('app_id', $request->attributes->get('mobile_app')->id);
    }

    private function invitations(Request $request): Builder
    {
        return DB::table('shiftcal_partner_invitations')->where('app_id', $request->attributes->get('mobile_app')->id);
    }

    public function show(Request $request): JsonResponse
    {
        $link = $this->links($request)->where('user_id', $request->user()->id)->first();
        $partner = $link ? DB::table('users')->where('id', $link->partner_user_id)->first(['name']) : null;
        $invitation = $this->invitations($request)->where('user_id', $request->user()->id)->where('expires_at', '>', now())->first();

        return response()->json(['data' => [
            'partner' => $partner ? ['name' => $partner->name] : null,
            'invitation' => $invitation ? ['expires_at' => Carbon::parse($invitation->expires_at)->toIso8601String()] : null,
        ]]);
    }

    public function invite(Request $request): JsonResponse
    {
        return DB::transaction(function () use ($request): JsonResponse {
            DB::table('users')->where('id', $request->user()->id)->lockForUpdate()->get();
            abort_if($this->links($request)->where('user_id', $request->user()->id)->exists(), 409, 'Zaten bir eş bağlantınız var.');
            $code = strtoupper(bin2hex(random_bytes(6)));
            $expiresAt = now()->addDays(7);
            $this->invitations($request)->where('user_id', $request->user()->id)->delete();
            DB::table('shiftcal_partner_invitations')->insert([
                'app_id' => $request->attributes->get('mobile_app')->id,
                'user_id' => $request->user()->id,
                'code_hash' => hash('sha256', $code),
                'expires_at' => $expiresAt,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return response()->json(['data' => ['code' => $code, 'expires_at' => $expiresAt->toIso8601String()]], 201);
        });
    }

    private function invitation(Request $request): object
    {
        $validated = $request->validate(['code' => ['required', 'string', 'regex:/^[A-Fa-f0-9]{12}$/']]);
        $invitation = $this->invitations($request)->where('code_hash', hash('sha256', strtoupper($validated['code'])))->where('expires_at', '>', now())->first();
        abort_unless($invitation, 404, 'Davet geçersiz veya süresi dolmuş.');
        abort_if($invitation->user_id === $request->user()->id, 422, 'Kendi davetinizi kabul edemezsiniz.');
        abort_unless(DB::table('app_users')->where('app_id', $invitation->app_id)->where('user_id', $invitation->user_id)->where('is_active', true)->exists(), 404, 'Davet artık kullanılamıyor.');
        abort_if($this->links($request)->whereIn('user_id', [$request->user()->id, $invitation->user_id])->exists(), 409, 'Birinizin zaten eş bağlantısı var.');

        return $invitation;
    }

    public function preview(Request $request): JsonResponse
    {
        $invitation = $this->invitation($request);

        return response()->json(['data' => ['name' => DB::table('users')->where('id', $invitation->user_id)->value('name')]]);
    }

    public function accept(Request $request): JsonResponse
    {
        $invitation = $this->invitation($request);

        return DB::transaction(function () use ($request, $invitation): JsonResponse {
            $users = [$request->user()->id, $invitation->user_id];
            DB::table('users')->whereIn('id', $users)->orderBy('id')->lockForUpdate()->get();
            $this->invitation($request);
            $connection = (string) Str::uuid();
            foreach ([$users, array_reverse($users)] as [$user, $partner]) {
                DB::table('shiftcal_partner_links')->insert([
                    'app_id' => $invitation->app_id, 'user_id' => $user, 'partner_user_id' => $partner,
                    'connection_id' => $connection, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $this->invitations($request)->whereIn('user_id', $users)->delete();

            return $this->show($request);
        });
    }

    public function cancel(Request $request): JsonResponse
    {
        DB::transaction(function () use ($request): void {
            DB::table('users')->where('id', $request->user()->id)->lockForUpdate()->get();
            $this->invitations($request)->where('user_id', $request->user()->id)->delete();
        });

        return response()->json(null, 204);
    }

    public function disconnect(Request $request): JsonResponse
    {
        $link = $this->links($request)->where('user_id', $request->user()->id)->first();
        if ($link) {
            DB::transaction(function () use ($request, $link): void {
                DB::table('users')->whereIn('id', [$request->user()->id, $link->partner_user_id])->orderBy('id')->lockForUpdate()->get();
                $this->links($request)->where('connection_id', $link->connection_id)->delete();
            });
        }

        return response()->json(null, 204);
    }
}
