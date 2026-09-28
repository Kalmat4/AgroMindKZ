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

    'telegram' => [
        'token' => env('TG_TOKEN', ''),
        'almat' => env('ALMAT_CHAT_ID', 0),
        'beka' => env('BEKA_CHAT_ID', 0)
    ],

    'firms' => [
        'key' => env('FIRMS_MAP_KEY'),
    ],

    'openweather' => [
        'key' => env('OPENWEATHER_API_KEY'),
    ],

    'n8n' => [
        'crop_webhook' => env('N8N_CROP_WEBHOOK_URL'),
        'crop_secret' => env('N8N_CROP_WEBHOOK_SECRET'),
    ],

];
