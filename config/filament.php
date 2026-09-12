<?php

return [
    /** Echo dùng giao thức Pusher tương thích của Reverb và session auth Filament. */
    'broadcasting' => [
        'echo' => [
            'broadcaster' => 'pusher',
            'key' => env('VITE_REVERB_APP_KEY'),
            'wsHost' => env('VITE_REVERB_HOST', 'localhost'),
            'wsPort' => env('VITE_REVERB_PORT', 8080),
            'wssPort' => env('VITE_REVERB_PORT', 443),
            'authEndpoint' => '/broadcasting/auth',
            'disableStats' => true,
            'enabledTransports' => ['ws', 'wss'],
            'forceTLS' => env('VITE_REVERB_SCHEME', 'http') === 'https',
        ],
    ],
];
