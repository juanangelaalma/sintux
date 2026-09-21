<?php

return [
    'name' => 'Purchasing',

    'supplier_do' => [
        'base_url' => env('SUPPLIER_DO_BASE_URL', ''),
        'api_key' => env('SUPPLIER_DO_API_KEY', ''),
        'username' => env('SUPPLIER_DO_USERNAME', ''),
        'password' => env('SUPPLIER_DO_PASSWORD', ''),
        'timeout' => env('SUPPLIER_DO_TIMEOUT', 15),
    ],
];
