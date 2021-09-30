<?php

return [

    /**
     * Token life time
     */
    'ttl' => env('TOKEN_TTL', 3600),

    /**
     * Hash key
     */
    'hash_key' => env('APP_KEY', ''),

    /**
     * store
     */
    'store' => env('TOKEN_STORE', 'array'),

    'cipher' => env('APP_CIPHER', 'AES-256-CBC'),
];
