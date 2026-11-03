<?php

$rawOrigins = env('APP_ALLOWED_ORIGINS', 'http://localhost:5173');
$allowedOrigins = array_values(array_filter(array_map('trim', explode(',', $rawOrigins))));

return [
    'paths' => ['api/*', 'up'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => $allowedOrigins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Accept', 'Authorization', 'X-Requested-With'],
    'exposed_headers' => [],
    'max_age' => 86400,
    'supports_credentials' => false,
];
