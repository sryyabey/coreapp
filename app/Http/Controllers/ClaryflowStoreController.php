<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClaryflowStoreController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, string $platform): RedirectResponse
    {
        $data = $request->validate([
            'source' => ['sometimes', 'string', 'in:manual,automatic'],
            'lang' => ['sometimes', 'string', 'in:en,tr,de'],
            'utm_source' => ['sometimes', 'nullable', 'string', 'max:200'],
            'utm_medium' => ['sometimes', 'nullable', 'string', 'max:200'],
            'utm_campaign' => ['sometimes', 'nullable', 'string', 'max:200'],
            'utm_content' => ['sometimes', 'nullable', 'string', 'max:200'],
            'utm_term' => ['sometimes', 'nullable', 'string', 'max:200'],
        ]);

        Log::info('claryflow_store_redirect', [
            'platform' => $platform,
            'source' => $data['source'] ?? 'manual',
            'language' => $data['lang'] ?? 'en',
            'campaign' => array_intersect_key($data, array_flip([
                'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
            ])),
        ]);

        $url = $platform === 'ios'
            ? 'https://apps.apple.com/tr/app/claryflow/id6786839637'
            : 'https://play.google.com/store/apps/details?id=com.claryflow.app';

        return redirect()->away($url)->header('Cache-Control', 'no-store, private');
    }
}
