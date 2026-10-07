<?php

namespace App\Http\Controllers\Api\V1\ShiftCal;

use App\Http\Controllers\Controller;
use App\Rules\OffsetDateTime;
use App\Services\ShiftCal\PushOutbox;
use App\Services\ShiftCal\SharedPlanService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SharedPlanController extends Controller
{
    public function __construct(private SharedPlanService $plans) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['from' => ['required', new OffsetDateTime], 'to' => ['required', new OffsetDateTime, 'after:from']]);
        $from = CarbonImmutable::parse($filters['from'])->utc();
        $to = CarbonImmutable::parse($filters['to'])->utc();
        abort_if($from->diffInMinutes($to) > 1560, 422, 'En fazla bir gün görüntülenebilir.');
        $appId = $request->attributes->get('mobile_app')->id;
        $userId = $request->user()->id;
        $link = DB::table('shiftcal_partner_links')->where('app_id', $appId)->where('user_id', $userId)->first();
        abort_unless($link, 404, 'Eş bağlantısı bulunamadı.');
        abort_unless(DB::table('app_users')->where('app_id', $appId)->where('user_id', $link->partner_user_id)->where('is_active', true)->exists(), 404, 'Eş bağlantısı kullanılamıyor.');
        $query = DB::table('shiftcal_shared_plans')->where('app_id', $appId)->where('connection_id', $link->connection_id)
            ->where(fn ($query) => $query->where('proposer_id', $userId)->orWhere('recipient_id', $userId));
        $active = (clone $query)->whereIn('status', ['pending', 'accepted'])->where(function ($query) use ($from, $to): void {
            $query->where(fn ($query) => $query->where('status', 'pending')->where('starts_at', '>', now()))
                ->orWhere(fn ($query) => $query->where('starts_at', '<', $to)->where('ends_at', '>', $from));
        })->orderBy('starts_at')->get();
        $closed = (clone $query)->whereIn('status', ['declined', 'cancelled'])->where('starts_at', '<', $to)->where('ends_at', '>', $from)->orderByDesc('updated_at')->limit(20)->get();
        $incoming = (clone $query)->where('recipient_id', $userId)->where('status', 'pending')->where('starts_at', '>', now())->count();

        return response()->json(['data' => $active->concat($closed)->map(fn (object $plan): array => $this->plans->resource($plan, $userId))->values(), 'meta' => ['incoming_count' => $incoming]])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, string $app, string $plan): JsonResponse
    {
        $appId = $request->attributes->get('mobile_app')->id;
        $userId = $request->user()->id;
        $link = DB::table('shiftcal_partner_links')->where('app_id', $appId)->where('user_id', $userId)->first();
        abort_unless($link, 404);
        abort_unless(DB::table('app_users')->where('app_id', $appId)->where('user_id', $link->partner_user_id)->where('is_active', true)->exists(), 404);
        $record = DB::table('shiftcal_shared_plans')->where('id', $plan)->where('app_id', $appId)->where('connection_id', $link->connection_id)
            ->where(fn ($query) => $query->where('proposer_id', $userId)->orWhere('recipient_id', $userId))->first();
        abort_unless($record, 404);

        return $this->response($record, $userId);
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge(['title' => is_string($request->input('title')) ? trim($request->input('title')) : $request->input('title')]);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'starts_at' => ['required', new OffsetDateTime], 'ends_at' => ['required', new OffsetDateTime, 'after:starts_at'],
            'timezone' => ['required', 'timezone:all'], 'client_request_id' => ['required', 'uuid'],
            'app_id' => ['prohibited'], 'proposer_id' => ['prohibited'], 'recipient_id' => ['prohibited'], 'connection_id' => ['prohibited'], 'status' => ['prohibited'],
        ]);
        $start = CarbonImmutable::parse($data['starts_at'])->utc();
        $end = CarbonImmutable::parse($data['ends_at'])->utc();
        abort_unless($start->diffInMinutes($end) >= 1 && $start->diffInHours($end) <= 26, 422, 'Plan süresi 1 dakika ile 26 saat arasında olmalı.');

        return DB::transaction(function () use ($request, $data, $start, $end): JsonResponse {
            $appId = $request->attributes->get('mobile_app')->id;
            $userId = $request->user()->id;
            $link = $this->plans->lockConnection($appId, $userId);
            $existing = DB::table('shiftcal_shared_plans')->where('app_id', $appId)->where('proposer_id', $userId)->where('client_request_id', $data['client_request_id'])->lockForUpdate()->first();
            if ($existing) {
                abort_unless($existing->connection_id === $link->connection_id && $existing->title === $data['title'] && $existing->timezone === $data['timezone'] && CarbonImmutable::parse($existing->starts_at)->equalTo($start) && CarbonImmutable::parse($existing->ends_at)->equalTo($end), 409, 'Bu işlem kimliği farklı bir öneride kullanılmış.');

                return $this->response($existing, $userId);
            }
            abort_unless($start->gt(now()), 422, 'Geçmiş bir saate plan önerilemez.');
            abort_if($this->plans->conflicts($appId, $userId, $link->partner_user_id, $start, $end), 409, 'Bu saatler artık ortak boş değil. Programınızı yenileyin.');
            abort_if(DB::table('shiftcal_shared_plans')->where('connection_id', $link->connection_id)->where('status', 'pending')->where('starts_at', '>', now())->count() >= 50, 422, 'Önce bekleyen önerileri yanıtlayın.');
            $id = (string) Str::uuid();
            DB::table('shiftcal_shared_plans')->insert([
                'id' => $id, 'app_id' => $appId, 'connection_id' => $link->connection_id,
                'proposer_id' => $userId, 'recipient_id' => $link->partner_user_id, 'client_request_id' => $data['client_request_id'],
                'title' => $data['title'], 'starts_at' => $start, 'ends_at' => $end, 'timezone' => $data['timezone'],
                'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ]);

            app(PushOutbox::class)->enqueue($appId, $link->partner_user_id, 'offered', 'offered:'.$id, $id, $link->connection_id, $start);

            return $this->response(DB::table('shiftcal_shared_plans')->where('id', $id)->first(), $userId, 201);
        });
    }

    public function update(Request $request, string $app, string $plan): JsonResponse
    {
        $data = $request->validate(['action' => ['required', 'in:accept,decline,cancel']]);

        return DB::transaction(function () use ($request, $plan, $data): JsonResponse {
            $appId = $request->attributes->get('mobile_app')->id;
            $userId = $request->user()->id;
            $link = $this->plans->lockConnection($appId, $userId);
            $record = DB::table('shiftcal_shared_plans')->where('id', $plan)->where('app_id', $appId)->where('connection_id', $link->connection_id)
                ->where(fn ($query) => $query->where('proposer_id', $userId)->orWhere('recipient_id', $userId))->lockForUpdate()->first();
            abort_unless($record, 404);
            $target = match ($data['action']) {
                'accept' => 'accepted', 'decline' => 'declined', 'cancel' => 'cancelled'
            };
            if ($data['action'] !== 'cancel') {
                abort_unless($record->recipient_id === $userId, 403, 'Öneriyi yalnızca alıcısı yanıtlayabilir.');
            } else {
                abort_if($record->status === 'pending' && $record->proposer_id !== $userId, 403, 'Gelen öneriyi reddedebilirsiniz.');
            }
            if ($record->status === $target) {
                return $this->response($record, $userId);
            }
            abort_unless($record->status === 'pending' || ($data['action'] === 'cancel' && $record->status === 'accepted'), 409, 'Bu planın durumu değişmiş. Yenileyin.');
            if ($data['action'] === 'accept') {
                $start = CarbonImmutable::parse($record->starts_at)->utc();
                $end = CarbonImmutable::parse($record->ends_at)->utc();
                abort_unless($start->gt(now()), 409, 'Önerinin saati geçmiş. Yeni bir plan önerin.');
                abort_if($this->plans->conflicts($appId, $record->proposer_id, $record->recipient_id, $start, $end, $record->id), 409, 'Bu saatler artık ortak boş değil. Programınızı yenileyin.');
            }
            DB::table('shiftcal_shared_plans')->where('id', $plan)->update(['status' => $target, 'updated_at' => now()]);
            $type = $data['action'] === 'cancel' && $record->status === 'pending' ? 'withdrawn' : $target;
            $recipient = $record->proposer_id === $userId ? $record->recipient_id : $record->proposer_id;
            app(PushOutbox::class)->enqueue($appId, $recipient, $type, $type.':'.$plan, $plan, $link->connection_id);
            $record->status = $target;

            return $this->response($record, $userId);
        });
    }

    private function response(object $plan, int $userId, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $this->plans->resource($plan, $userId)], $status)->header('Cache-Control', 'private, no-store');
    }
}
