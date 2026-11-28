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

    'brevo' => [
        'key' => env('BREVO_API_KEY'),
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
        'provider' => env('CRICKET_PROVIDER', 'local'),
        'local_url' => env('CRICKET_LOCAL_URL', 'http://127.0.0.1:6020'),
        'api_key' => env('CRICKET_API_KEY', '1508e78a-cc6d-46ee-93c0-e80c0d86a60b'),
        'ttl' => [
            'current_matches' => env('CRICKET_PROVIDER', 'local') === 'local' ? 10 : 30,
            'match_detail' => env('CRICKET_PROVIDER', 'local') === 'local' ? 10 : 300,
            'scorecard' => env('CRICKET_PROVIDER', 'local') === 'local' ? 10 : 300,
        ],
    ],

    'agora' => [
        'app_id' => env('AGORA_APP_ID', 'f4929bb36b5a4d3a972226ad8b6ce599'),
        'app_certificate' => env('AGORA_APP_CERTIFICATE', '19b692f8de2a41598b57b55e1755997e'),
    ],

    'imagekit' => [
        'public_key' => env('IMAGEKIT_PUBLIC_KEY', 'public_t65SBzKhS7mvo2WlfsobLFKsH7w='),
        'private_key' => env('IMAGEKIT_PRIVATE_KEY', 'private_lumI6KJa9uDbXdM9lGqQkSb0z5I='),
        'url_endpoint' => env('IMAGEKIT_URL_ENDPOINT', 'https://ik.imagekit.io/muuqkim6d'),
    ],

    'adsense' => [
        'client_id' => env('ADSENSE_CLIENT_ID', 'ca-pub-7211778841091994'),
    ],

    'google' => [
        'site_verification' => env('GOOGLE_SITE_VERIFICATION', 'CQog30azwDeG1wL2_sRtyCVnJ_SHqfFdeGK08GrnpZA'),
        'analytics_id' => env('GA_MEASUREMENT_ID', 'G-RBGZ4Z285V'),
    ],

    'indexnow' => [
        'key' => env('INDEXNOW_KEY', 'cricapp2026seo9f8a7b6c5d4e3f2a1098'),
    ],

    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'graph_version' => env('META_GRAPH_VERSION', 'v22.0'),
        'redirect_uri' => env('META_REDIRECT_URI'),
        'instagram_account_id' => env('INSTAGRAM_ACCOUNT_ID'),
        'instagram_access_token' => env('INSTAGRAM_ACCESS_TOKEN'),
    ],

    'youtube' => [
        'client_id' => env('YOUTUBE_CLIENT_ID', env('GOOGLE_CLIENT_ID')),
        'client_secret' => env('YOUTUBE_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET')),
        'redirect_uri' => env('YOUTUBE_REDIRECT_URI'),
        'default_privacy' => env('YOUTUBE_DEFAULT_PRIVACY', 'public'),
    ],

];
