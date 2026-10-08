<?php

return [

    'apple_login' => [
        'team_id' => env('SHIFTCAL_APPLE_TEAM_ID'),
        'key_id' => env('SHIFTCAL_APPLE_KEY_ID'),
        'private_key_path' => env('SHIFTCAL_APPLE_PRIVATE_KEY_PATH'),
        'client_ids' => ['shiftcal' => env('SHIFTCAL_APPLE_CLIENT_ID', 'com.shiftcal.app')],
    ],

    'google_login' => [
        'client_ids' => ['shiftcal' => env('SHIFTCAL_GOOGLE_WEB_CLIENT_ID')],
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'shiftcal_fcm' => [
        'enabled' => env('SHIFTCAL_FCM_ENABLED', false),
        'credentials' => env('SHIFTCAL_FCM_CREDENTIALS'),
    ],

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
