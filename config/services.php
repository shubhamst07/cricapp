<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

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

    'cricket' => [
        'provider' => env('CRICKET_PROVIDER', 'cricapi'),
        'local_url' => env('CRICKET_LOCAL_URL', 'http://127.0.0.1:6020'),
        'api_key' => env('CRICKET_API_KEY', '1508e78a-cc6d-46ee-93c0-e80c0d86a60b'),
        'ttl' => [
            'current_matches' => env('CRICKET_PROVIDER', 'cricapi') === 'local' ? 10 : 30,
            'match_detail' => env('CRICKET_PROVIDER', 'cricapi') === 'local' ? 10 : 300,
            'scorecard' => env('CRICKET_PROVIDER', 'cricapi') === 'local' ? 10 : 300,
        ],
    ],

];
