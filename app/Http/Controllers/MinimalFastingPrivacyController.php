<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class MinimalFastingPrivacyController extends Controller
{
    public function __invoke(Request $request): View
    {
        $language = $request->query('lang', 'tr');
        $language = is_string($language) && in_array($language, ['tr', 'en', 'de'], true) ? $language : 'tr';

        return view('minimal-fasting-privacy', [
            'language' => $language,
            'email' => config('support.minimal_fasting_email'),
            'copy' => config('minimal-fasting-privacy.'.$language),
        ]);
    }
}
