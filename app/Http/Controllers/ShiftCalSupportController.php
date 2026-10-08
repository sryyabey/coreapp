<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftCalSupportController extends Controller
{
    public function __invoke(Request $request): View
    {
        $language = $request->query('lang', 'tr');
        $language = is_string($language) && in_array($language, ['tr', 'en'], true) ? $language : 'tr';

        return view('shiftcal-support', [
            'language' => $language,
            'email' => config('support.shiftcal_email'),
        ]);
    }
}
