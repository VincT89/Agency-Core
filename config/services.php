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
        'regenerate_social_post_webhook_url' => env('N8N_REGENERATE_SOCIAL_POST_WEBHOOK_URL', env('N8N_GENERATE_SOCIAL_POST_WEBHOOK_URL')),
        'generate_social_post_webhook_url' => env('N8N_GENERATE_SOCIAL_POST_WEBHOOK_URL'),
        'submit_marketing_campaign_post_webhook_url' => env('N8N_SUBMIT_MARKETING_CAMPAIGN_POST_WEBHOOK_URL', env('N8N_GENERATE_SOCIAL_POST_WEBHOOK_URL')),
        'send_whatsapp_review_webhook_url' => env('N8N_SEND_WHATSAPP_REVIEW_WEBHOOK_URL'),
        'chatbot_outgoing_message_webhook_url' => env('N8N_CHATBOT_OUTGOING_MESSAGE_WEBHOOK_URL'),
        'sody_connection_webhook_url' => env('N8N_SODY_CONNECTION_WEBHOOK_URL'),
    ],

    'meta' => [
        'client_id' => env('META_CLIENT_ID'),
        'client_secret' => env('META_CLIENT_SECRET'),
        'redirect_uri' => env('META_REDIRECT_URI'),
        'graph_version' => env('META_GRAPH_VERSION', 'v19.0'),
        'instagram' => [
            'max_container_lifecycle' => env('META_INSTAGRAM_MAX_CONTAINER_LIFECYCLE', 15),
        ],
    ],

    'facebook' => [
        'client_id' => env('META_CLIENT_ID'),
        'client_secret' => env('META_CLIENT_SECRET'),
        'redirect' => env('META_REDIRECT_URI'),
    ],

    'tiktok' => [
        'client_key' => env('TIKTOK_CLIENT_KEY'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
        'redirect_uri' => env('TIKTOK_REDIRECT_URI'),
    ],

    'nextcloud' => [
        'base_url' => env('NEXTCLOUD_BASE_URL'),
        'username' => env('NEXTCLOUD_USERNAME'),
        'password' => env('NEXTCLOUD_PASSWORD'),
        'webdav_path' => env('NEXTCLOUD_WEBDAV_PATH', '/remote.php/dav/files'),
        'photos_root' => env('NEXTCLOUD_PHOTOS_ROOT', '/FotoClienti'),
        'videos_root' => env('NEXTCLOUD_VIDEOS_ROOT', '/VideoClienti'),
        'share_expire_days' => env('NEXTCLOUD_SHARE_EXPIRE_DAYS', 7),
    ],

];
