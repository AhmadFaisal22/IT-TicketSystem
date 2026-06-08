<?php

return [
    /*
     * Paths that CORS headers are applied to.
     */
    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    /*
     * Only allow requests from the configured frontend URL.
     * Set FRONTEND_URL in .env — never use '*' in production.
     */
    'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:5173')],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'Accept',
        'X-XSRF-TOKEN',
    ],

    'exposed_headers' => [],

    'max_age' => 7200,

    /*
     * Required for Laravel Sanctum token auth to send cookies/credentials.
     */
    'supports_credentials' => true,
];
