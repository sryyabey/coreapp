<?php

return [
    'allow_sandbox' => env('BILLING_ALLOW_SANDBOX', false),
    'apple' => [
        'root_ca_paths' => array_filter(explode(',', env('APPLE_IAP_ROOT_CA_PATHS', ''))),
        'issuer_id' => env('APPLE_IAP_ISSUER_ID'),
        'key_id' => env('APPLE_IAP_KEY_ID'),
        'private_key_path' => env('APPLE_IAP_PRIVATE_KEY_PATH'),
    ],
    'google' => ['service_account_path' => env('GOOGLE_PLAY_SERVICE_ACCOUNT_PATH'), 'push_audience' => env('GOOGLE_PLAY_PUSH_AUDIENCE'), 'push_service_account_email' => env('GOOGLE_PLAY_PUSH_EMAIL'), 'push_subscription' => env('GOOGLE_PLAY_PUSH_SUBSCRIPTION')],
    'apps' => [],
];
