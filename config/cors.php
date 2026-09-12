<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    // Cache preflight để các request POS có Authorization/X-Device-ID không gửi OPTIONS liên tục.
    'max_age' => 600,
    'supports_credentials' => false,
];
