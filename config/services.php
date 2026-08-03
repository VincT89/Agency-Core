<?php

$arubaEnvironment = strtolower((string) env('ARUBA_EINVOICING_ENV', 'demo'));
$arubaProduction = $arubaEnvironment === 'production';

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
        'signing_secret' => env('N8N_SIGNING_SECRET'),
        'require_signature' => filter_var(
            env('N8N_REQUIRE_SIGNATURE', env('APP_ENV') === 'production'),
            FILTER_VALIDATE_BOOL
        ),
        'signature_max_clock_skew_seconds' => (int) env(
            'N8N_SIGNATURE_MAX_CLOCK_SKEW_SECONDS',
            300
        ),
        'require_idempotency_key' => filter_var(
            env('N8N_REQUIRE_IDEMPOTENCY_KEY', env('APP_ENV') === 'production'),
            FILTER_VALIDATE_BOOL
        ),
        'idempotency_ttl_hours' => (int) env(
            'N8N_IDEMPOTENCY_TTL_HOURS',
            48
        ),
        'idempotency_lock_seconds' => (int) env(
            'N8N_IDEMPOTENCY_LOCK_SECONDS',
            600
        ),
        'idempotency_lock_wait_seconds' => (int) env(
            'N8N_IDEMPOTENCY_LOCK_WAIT_SECONDS',
            5
        ),
        'idempotency_in_progress_timeout_minutes' => (int) env(
            'N8N_IDEMPOTENCY_IN_PROGRESS_TIMEOUT_MINUTES',
            30
        ),
        'connect_timeout' => (int) env('N8N_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('N8N_HTTP_TIMEOUT', 15),
        'regenerate_social_post_webhook_url' => env('N8N_REGENERATE_SOCIAL_POST_WEBHOOK_URL', env('N8N_GENERATE_SOCIAL_POST_WEBHOOK_URL')),
        'generate_social_post_webhook_url' => env('N8N_GENERATE_SOCIAL_POST_WEBHOOK_URL'),
        'submit_marketing_campaign_post_webhook_url' => env('N8N_SUBMIT_MARKETING_CAMPAIGN_POST_WEBHOOK_URL', env('N8N_GENERATE_SOCIAL_POST_WEBHOOK_URL')),
        'chatbot_outgoing_message_webhook_url' => env('N8N_CHATBOT_OUTGOING_MESSAGE_WEBHOOK_URL'),
    ],

    'meta' => [
        'client_id' => env('META_CLIENT_ID'),
        'client_secret' => env('META_CLIENT_SECRET'),
        'config_id' => env('META_CONFIG_ID'),
        'redirect_uri' => env('META_REDIRECT_URI'),
        'graph_version' => env('META_GRAPH_VERSION', 'v25.0'),
        'connect_timeout' => (int) env('META_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('META_HTTP_TIMEOUT', 15),
        'max_sync_pages' => (int) env('META_MAX_SYNC_PAGES', 25),
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
        'api_base' => env('TIKTOK_API_BASE', 'https://open.tiktokapis.com'),
        'connect_timeout' => (int) env('TIKTOK_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('TIKTOK_HTTP_TIMEOUT', 20),
        'delivery_mode' => env('TIKTOK_DELIVERY_MODE', 'disabled'),
        'upload_mode' => env('TIKTOK_UPLOAD_MODE', 'PullFromUrl'),
        'max_photo_count' => env('TIKTOK_MAX_PHOTO_COUNT', 10),
        'media_url_ttl' => env('TIKTOK_MEDIA_URL_TTL', 1440),
        'enable_photo_mode' => env('TIKTOK_ENABLE_PHOTO_MODE', false),
        'mock_publishing' => env('TIKTOK_MOCK_PUBLISHING', true),
        'direct_publish_enabled' => env('TIKTOK_DIRECT_PUBLISH_ENABLED', false),
        'creator_info_ttl_seconds' => env('TIKTOK_CREATOR_INFO_TTL_SECONDS', 300),
    ],

    'nextcloud' => [
        'base_url' => env('NEXTCLOUD_BASE_URL'),
        'username' => env('NEXTCLOUD_USERNAME'),
        'password' => env('NEXTCLOUD_PASSWORD'),
        'webdav_path' => env('NEXTCLOUD_WEBDAV_PATH', '/remote.php/dav/files'),
        'photos_root' => env('NEXTCLOUD_PHOTOS_ROOT', '/FotoClienti'),
        'videos_root' => env('NEXTCLOUD_VIDEOS_ROOT', '/VideoClienti'),
        'share_expire_days' => env('NEXTCLOUD_SHARE_EXPIRE_DAYS', 7),
        'connect_timeout' => env('NEXTCLOUD_CONNECT_TIMEOUT', 5),
        'request_timeout' => env('NEXTCLOUD_REQUEST_TIMEOUT', 15),
        'stream_timeout' => env('NEXTCLOUD_STREAM_TIMEOUT', 300),
        'stream_read_timeout' => env('NEXTCLOUD_STREAM_READ_TIMEOUT', 30),
    ],

    'aruba_einvoicing' => [
        'enabled' => filter_var(env('ARUBA_EINVOICING_ENABLED', false), FILTER_VALIDATE_BOOL),
        'environment' => $arubaEnvironment,
        'username' => env('ARUBA_EINVOICING_USERNAME'),
        'password' => env('ARUBA_EINVOICING_PASSWORD'),
        'callback_key' => env('ARUBA_EINVOICING_CALLBACK_KEY'),
        'allow_send' => filter_var(env('ARUBA_EINVOICING_ALLOW_SEND', false), FILTER_VALIDATE_BOOL),
        'require_dry_run' => filter_var(env('ARUBA_EINVOICING_REQUIRE_DRY_RUN', true), FILTER_VALIDATE_BOOL),
        'signature_domain' => env('ARUBA_EINVOICING_SIGNATURE_DOMAIN'),
        'signature_credential' => env('ARUBA_EINVOICING_SIGNATURE_CREDENTIAL'),
        'auth_base_url' => $arubaProduction
            ? 'https://auth.fatturazioneelettronica.aruba.it'
            : 'https://demoauth.fatturazioneelettronica.aruba.it',
        'api_base_url' => $arubaProduction
            ? 'https://ws.fatturazioneelettronica.aruba.it'
            : 'https://demows.fatturazioneelettronica.aruba.it',
        'connect_timeout' => (int) env('ARUBA_EINVOICING_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('ARUBA_EINVOICING_HTTP_TIMEOUT', 20),
    ],

];
