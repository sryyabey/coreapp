<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class WelcomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $language = $request->query('lang', 'tr');
        $language = is_string($language) && in_array($language, ['tr', 'en', 'ru'], true)
            ? $language : 'tr';

        return view('welcome', [
            'language' => $language,
            'copy' => config('studio.'.$language),
        ]);
    }
}
