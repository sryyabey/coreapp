<?php

namespace App\Http\Controllers\Api\V1\ShiftCal;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ShiftCal\EventResource;
use App\Models\AppUser;
use App\Models\ShiftCal\Event;
use App\Rules\OffsetDateTime;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class TemplateApplicationController extends Controller
{
    private function owned(Request $request): Builder
    {
        return Event::where('app_id', $request->attributes->get('mobile_app')->id)->where('user_id', $request->user()->id);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'operation_id' => ['required', 'uuid'],
            'only_safe_days' => ['required', 'boolean'],
            'records' => ['required', 'array', 'min:1', 'max:732'],
            'records.*.day_key' => ['required', 'regex:/^\d{4}-\d{1,2}-\d{1,2}$/'],
            'records.*.date_key' => ['required', 'regex:/^\d{4}-\d{1,2}-\d{1,2}$/'],
            'records.*.client_request_id' => ['required', 'uuid', 'distinct'],
            'records.*.type' => ['required', 'in:work,sleep'],
            'records.*.starts_at' => ['required', new OffsetDateTime],
            'records.*.ends_at' => ['required', new OffsetDateTime, 'after:records.*.starts_at'],
            'records.*.timezone' => ['required', 'timezone:all'],
            'records.*.color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'records.*.note' => ['nullable', 'string', 'max:5000'],
        ]);

        return DB::transaction(function () use ($request, $data): JsonResponse {
            AppUser::whereKey($request->attributes->get('app_membership')->id)->lockForUpdate()->firstOrFail();
            $appId = $request->attributes->get('mobile_app')->id;
            $userId = $request->user()->id;
            $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            $application = DB::table('shiftcal_template_applications')->where('id', $data['operation_id'])->first();
            if ($application) {
                abort_unless($application->app_id === $appId && $application->user_id === $userId, 404);
                abort_unless(hash_equals($application->request_hash, $hash) && $application->state === 'applied', 409, 'Bu işlem daha önce farklı içerikle kullanılmış veya geri alınmış.');

                return response()->json(['data' => json_decode($application->receipt, true, flags: JSON_THROW_ON_ERROR)]);
            }
            $records = collect($data['records'])->map(function (array $record): array {
                $record['starts_at'] = CarbonImmutable::parse($record['starts_at'])->utc();
                $record['ends_at'] = CarbonImmutable::parse($record['ends_at'])->utc();
                abort_unless($record['ends_at']->greaterThan($record['starts_at']) && $record['starts_at']->diffInHours($record['ends_at']) <= 25, 422, 'Geçersiz süre.');
                $local = $record['starts_at']->setTimezone($record['timezone']);
                abort_unless($local->format('Y-n-j') === $record['date_key'], 422, 'Kayıt tarihi saatlerle eşleşmiyor.');

                return $record;
            });
            $from = $records->min('starts_at');
            $until = $records->max('ends_at');
            $existing = $this->owned($request)->where('starts_at', '<', $until)->where('ends_at', '>', $from)->lockForUpdate()->get();
            $blocked = [];
            $new = [];
            foreach ($records as $record) {
                $duplicate = $existing->first(fn (Event $event): bool => $event->type === $record['type'] && $event->starts_at->equalTo($record['starts_at']) && $event->ends_at->equalTo($record['ends_at']));
                if ($duplicate) {
                    continue;
                }
                foreach ($existing as $event) {
                    if ($record['starts_at']->lessThan($event->ends_at) && $event->starts_at->lessThan($record['ends_at'])) {
                        $blocked[$record['day_key']] = true;
                    }
                }
                $new[] = $record;
            }
            foreach ($new as $i => $record) {
                foreach (array_slice($new, $i + 1) as $other) {
                    if ($record['starts_at']->lessThan($other['ends_at']) && $other['starts_at']->lessThan($record['ends_at'])) {
                        $blocked[$record['day_key']] = true;
                        $blocked[$other['day_key']] = true;
                    }
                }
            }
            abort_if($blocked && ! $data['only_safe_days'], 409, 'Çakışan günler: '.implode(', ', array_keys($blocked)));
            $created = [];
            $days = [];
            foreach ($new as $record) {
                if (isset($blocked[$record['day_key']])) {
                    continue;
                }
                $event = new Event(collect($record)->except(['day_key', 'date_key'])->all());
                $event->forceFill(['app_id' => $appId, 'user_id' => $userId])->save();
                $created[] = ['date_key' => $record['date_key'], 'entry' => (new EventResource($event))->resolve($request)];
                $days[$record['day_key']] = true;
            }
            abort_if(! $created, 409, 'Uygulanacak yeni ve uygun gün yok.');
            $receipt = ['id' => $data['operation_id'], 'days' => array_keys($days), 'records' => $created];
            DB::table('shiftcal_template_applications')->insert(['id' => $data['operation_id'], 'app_id' => $appId, 'user_id' => $userId, 'request_hash' => $hash, 'receipt' => json_encode($receipt, JSON_THROW_ON_ERROR), 'state' => 'applied', 'created_at' => now(), 'updated_at' => now()]);

            return response()->json(['data' => $receipt], 201);
        });
    }

    public function destroy(Request $request, string $app, string $application): Response
    {
        return DB::transaction(function () use ($request, $application): Response {
            AppUser::whereKey($request->attributes->get('app_membership')->id)->lockForUpdate()->firstOrFail();
            $record = DB::table('shiftcal_template_applications')->where('id', $application)->where('app_id', $request->attributes->get('mobile_app')->id)->where('user_id', $request->user()->id)->lockForUpdate()->first();
            abort_unless($record, 404);
            if ($record->state === 'applied') {
                $receipt = json_decode($record->receipt, true, flags: JSON_THROW_ON_ERROR);
                $ids = array_map(fn (array $entry): string => $entry['entry']['id'], $receipt['records']);
                $this->owned($request)->whereIn('id', $ids)->delete();
                DB::table('shiftcal_template_applications')->where('id', $application)->update(['state' => 'undone', 'updated_at' => now()]);
            }

            return response()->noContent();
        });
    }
}
