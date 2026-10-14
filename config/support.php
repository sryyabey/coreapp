<?php

return [
    'minimal_fasting_email' => env('MINIMAL_FASTING_SUPPORT_EMAIL', 'info@sryya.dev'),
    'shiftcal_email' => env('SHIFTCAL_SUPPORT_EMAIL', 'info@sryya.dev'),
    'fcm' => [
        'apps' => [
            // Add each app slug and its own Firebase service account settings here.
        ],
    ],
];
