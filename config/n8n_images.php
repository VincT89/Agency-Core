<?php

return [
    'timeout_seconds' => 15,
    'max_redirects' => 3,
    'max_bytes' => 15 * 1024 * 1024, // 15 MB
    'max_pixels' => 40000000,
    'allowed_mime_types' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ],
];
