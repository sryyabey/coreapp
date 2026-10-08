<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftCalMarketingController extends Controller
{
    public function __invoke(Request $request): View
    {
        $language = $request->query('lang', 'tr');
        $language = is_string($language) && in_array($language, ['tr', 'en'], true) ? $language : 'tr';

        return view('shiftcal', [
            'language' => $language,
        ]);
    }
}
