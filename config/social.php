<?php

return [
    'auto_publish_enabled' => env('SOCIAL_AUTO_PUBLISH_ENABLED', false),

    'auto_dispatch_batch_size' => env('SOCIAL_AUTO_DISPATCH_BATCH_SIZE', 100),

    'publication_stale_deadlines' => [
        // Facebook is synchronous, so if it stays in Publishing for more than 5 minutes, something is wrong
        'facebook' => 5, 
        
        // Instagram uses async containers, Meta says max 15 minutes processing
        'instagram' => 15, 
        
        // TikTok has draft upload and publish phase, can take up to 30 minutes
        'tiktok' => 30,
    ],
    
    'media_hardening' => [
        // Enable FFprobe deep inspection for videos
        'ffprobe_enabled' => env('SOCIAL_FFPROBE_ENABLED', false),
    ],

    'publishing' => [
        'dry_run' => env('SOCIAL_PUBLISHING_DRY_RUN', false),
    ],

    'production_readiness' => [
        'pending_stale_minutes' => env(
            'SOCIAL_PENDING_STALE_MINUTES',
            15
        ),
        'publishing_without_deadline_minutes' => env(
            'SOCIAL_PUBLISHING_WITHOUT_DEADLINE_MINUTES',
            30
        ),
    ],

    'url_validation' => env('SOCIAL_URL_VALIDATION_ENABLED', true),
];
