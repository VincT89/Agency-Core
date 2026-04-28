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

    'n8n' => [
        'token' => env('N8N_API_TOKEN'),
        'regenerate_social_post_webhook_url' => env('N8N_REGENERATE_SOCIAL_POST_WEBHOOK_URL'),
        'generate_social_post_webhook_url' => env('N8N_GENERATE_SOCIAL_POST_WEBHOOK_URL'),
        'generate_editorial_plan_webhook_url' => env('N8N_GENERATE_EDITORIAL_PLAN_WEBHOOK_URL'),
        'send_whatsapp_review_webhook_url' => env('N8N_SEND_WHATSAPP_REVIEW_WEBHOOK_URL'),
        'publish_social_post_webhook_url' => env('N8N_PUBLISH_SOCIAL_POST_WEBHOOK_URL'),
    ],

];
