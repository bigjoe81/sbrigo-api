<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
    'allowed_origins' => array_filter(array_map(
        'trim',
        explode(',', env(
            'FRONTEND_URL',
            'http://localhost:4200,http://127.0.0.1:4200',
        )),
    )),
    'allowed_origins_patterns' => [],
    'allowed_headers' => [
        'Accept',
        'Content-Type',
        'X-Requested-With',
        'X-XSRF-TOKEN',
    ],
    'exposed_headers' => [],
    'max_age' => 600,
    'supports_credentials' => true,
];
