<?php

// File: config/services.php
// Thêm phần vnpay vào cuối array return, TRƯỚC dấu ];

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'   => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // VNPAY payment gateway
    'vnpay' => [
        'tmn_code'    => env('VNPAY_TMN_CODE'),        // Terminal ID từ VNPay
        'hash_secret' => env('VNPAY_HASH_SECRET'),      // Secret key để tạo chữ ký
        'url'         => env('VNPAY_URL'),              // URL sandbox VNPay
        'return_url'  => env('VNPAY_RETURN_URL'),       // URL VNPay gọi lại sau thanh toán
    ],

    // Groq AI API
    'groq' => [
        'api_key' => env('GROQ_API_KEY'),              // API key từ Groq
        'model'   => env('GROQ_MODEL', 'llama3-8b-8192'), // Model mặc định nếu không set trong .env
    ],

];