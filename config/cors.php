<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        'http://localhost:3000',     // React development server
        'http://localhost:5173',     // Vite development server
        'http://127.0.0.1:3000',     // Alternative localhost
        'http://127.0.0.1:5173',     // Alternative localhost
        'http://localhost:8080',     // Vue CLI development server
        'https://kelompokfwd7-sibm3.karyakreasi.id', // Production frontend domain
        'https://backend-kelompokfwd7-sibm3.karyakreasi.id' // Production backend domain
    ],

    'allowed_origins_patterns' => [
        // Pattern untuk subdomain development
        '#^https?://.*\.localhost$#',
        '#^https?://.*\.ngrok\.io$#',
    ],

    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'Accept',
        'Origin',
        'X-Auth-Token',
    ],

    'exposed_headers' => [
        'Authorization',
        'X-Auth-Token',
    ],

    'max_age' => 86400, // 24 hours

    'supports_credentials' => true,

];
