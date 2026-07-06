<?php

return [
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
];
