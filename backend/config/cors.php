<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Local development setup. The Vue dev server (Vite) runs on a different
    | origin than the Laravel API, so we allow it explicitly.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(explode(',', (string) env(
        'CORS_ALLOWED_ORIGINS',
        'http://localhost:5173,http://127.0.0.1:5173,https://whatsapp-crm-frontend.onrender.com,https://whatsapp-crm-frontend-w400.onrender.com,https://whatsapp-crm-backend.onrender.com,https://whatsapp-crm-backend-g4xx.onrender.com'
    )))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];