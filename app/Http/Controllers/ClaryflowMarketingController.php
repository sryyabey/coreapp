<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ClaryflowMarketingController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        $language = $request->query('lang', $request->getPreferredLanguage(['en', 'tr', 'de']) ?? 'en');
        $language = is_string($language) && in_array($language, ['en', 'tr', 'de'], true) ? $language : 'en';

        return view('claryflow', [
            'language' => $language,
            'copy' => config('claryflow-marketing.'.$language),
        ]);
    }
}
