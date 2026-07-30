<?php

return [
    'timeout_seconds' => 15,
    'connect_timeout_seconds' => 5,
    'max_redirects' => 3,
    'max_bytes' => 15 * 1024 * 1024, // 15 MB
    'max_pixels' => 40000000,
    'chunk_size_bytes' => 64 * 1024,
    'allowed_schemes' => ['https'],
    'allowed_ports' => [443],
    'allowed_mime_types' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ],
];
