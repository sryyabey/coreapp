<?php

namespace App\Http\Controllers\Api\V1\ShiftCal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ShiftCal\CloudBackupRequest;
use App\Models\AppUser;
use App\Models\ShiftCal\CloudBackup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CloudBackupController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $backup = CloudBackup::where('app_id', $request->attributes->get('mobile_app')->id)->where('user_id', $request->user()->id)->first();

        return $this->response($backup);
    }

    public function store(CloudBackupRequest $request): JsonResponse
    {
        abort_if(strlen($request->getContent()) > 2 * 1024 * 1024, 413, 'Yedek en fazla 2 MB olabilir.');

        return DB::transaction(function () use ($request): JsonResponse {
            $membership = AppUser::whereKey($request->attributes->get('app_membership')->id)->lockForUpdate()->firstOrFail();
            $backup = CloudBackup::where('app_id', $membership->app_id)->where('user_id', $membership->user_id)->first();
            abort_unless(($backup?->revision ?? 0) === $request->integer('revision'), 409, 'Bulut yedeği değişti. Tekrar deneyin.');
            $backup ??= new CloudBackup;
            $backup->forceFill(['app_id' => $membership->app_id, 'user_id' => $membership->user_id,
                'revision' => ($backup->revision ?? 0) + 1, 'payload' => ['schema_version' => 1, 'entries' => $request->validated('entries')]])->save();

            return $this->response($backup);
        });
    }

    private function response(?CloudBackup $backup): JsonResponse
    {
        return response()->json(['data' => ['exists' => $backup !== null, 'revision' => $backup?->revision ?? 0,
            'saved_at' => $backup?->updated_at?->toIso8601String(), 'schema_version' => 1,
            'entries' => $backup?->payload['entries'] ?? []]])->header('Cache-Control', 'private, no-store');
    }
}
