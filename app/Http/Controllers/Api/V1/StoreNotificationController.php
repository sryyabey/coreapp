<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\StoreNotificationVerifier;
use App\Jobs\ProcessStoreNotificationJob;
use App\Models\App;
use App\Models\StoreApp;
use App\Models\StoreNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StoreNotificationController extends Controller
{
    public function __invoke(Request $request, string $app, string $platform, StoreNotificationVerifier $verifier): Response
    {
        $catalog = App::where('slug', $app)->firstOrFail();
        $store = StoreApp::where('app_id', $catalog->id)->where('platform', $platform)->firstOrFail();
        $verified = $platform === 'ios' ? $verifier->apple($request, $store) : $verifier->google($request, $store);
        $event = StoreNotification::firstOrCreate(['store_app_id' => $store->id, 'event_id' => $verified['event_id']], [
            'type' => $verified['type'], 'environment' => $verified['environment'], 'identity' => $verified['identity'], 'proof' => $verified['proof'],
            'status' => $verified['test'] ? 'ignored' : 'pending', 'processed_at' => $verified['test'] ? now() : null,
        ]);
        if (! in_array($event->status, ['processed', 'ignored', 'exhausted'], true)) {
            ProcessStoreNotificationJob::dispatch($event->id)->afterCommit();
        }

        return response()->noContent();
    }
}
