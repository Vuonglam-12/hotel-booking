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

    'google' => [
        // Public OAuth audience already used by the existing GIS login/register buttons.
        'client_id' => env('GOOGLE_CLIENT_ID', '829842960470-itgpqhmc91rqk4oosurbc1ufvc5el3kq.apps.googleusercontent.com'),
    ],

    // VNPAY payment gateway
    'vnpay' => [
        'tmn_code'    => env('VNPAY_TMN_CODE'),        // Terminal ID từ VNPay
        'hash_secret' => env('VNPAY_HASH_SECRET'),      // Secret key để tạo chữ ký
        'url'         => env('VNPAY_URL'),              // URL sandbox VNPay
        'return_url'  => env('VNPAY_RETURN_URL'),       // URL VNPay gọi lại sau thanh toán
        'ipn_url'     => env('VNPAY_IPN_URL'),          // Public server callback, configured only after approval.
    ],

    'sepay' => [
        'webhook' => [
            'enabled' => env('SEPAY_WEBHOOK_ENABLED', false),
            'mode' => env('SEPAY_WEBHOOK_MODE', 'test'),
            'auth_method' => env('SEPAY_WEBHOOK_AUTH_METHOD'),
            'api_key' => env('SEPAY_WEBHOOK_API_KEY'),
            'hmac_secret' => env('SEPAY_WEBHOOK_HMAC_SECRET'),
            'account_number' => env('SEPAY_WEBHOOK_ACCOUNT_NUMBER'),
        ],
    ],

    // Groq AI API
    'groq' => [
        'api_key' => env('GROQ_API_KEY'),              // API key từ Groq
        'model'   => env('GROQ_MODEL', 'openai/gpt-oss-20b'),
        'chat' => [
            'temperature' => 0.3,
            'max_tokens' => 1800,
        ],
        'itinerary' => [
            'temperature' => 0.2,
            'max_tokens' => 4000,
        ],
    ],

    // Read-only public place catalog from a Google Sheet. Credentials remain outside this project.
    'place_sheet' => [
        'spreadsheet_id' => env('GOOGLE_SHEETS_SPREADSHEET_ID'),
        'data_tab' => env('GOOGLE_SHEETS_DATA_TAB', 'Dia_diem'),
        'guide_tab' => env('GOOGLE_SHEETS_GUIDE_TAB', 'Huong_dan'),
        'hours_tab' => env('GOOGLE_SHEETS_HOURS_TAB', 'Gio_hoat_dong'),
        'credentials' => env('GOOGLE_SHEETS_CREDENTIALS'),
        'range' => env('GOOGLE_SHEETS_RANGE', 'A1:ZZ'),
        'location_name' => env('GOOGLE_SHEETS_LOCATION_NAME', 'Đà Lạt'),
        'candidate_limit' => 12,
        'additional_sources' => [
            [
                'location_name' => 'Phú Quốc',
                'data_tab' => env('GOOGLE_SHEETS_PHU_QUOC_DATA_TAB', 'Phú Quốc'),
                'spreadsheet_id' => env('GOOGLE_SHEETS_PHU_QUOC_SPREADSHEET_ID', env('GOOGLE_SHEETS_SPREADSHEET_ID')),
                'id_prefix' => 'PQ',
            ],
        ],
    ],

    // Geoapify Places API
    'geoapify' => [
        'api_key' => env('GEOAPIFY_API_KEY'),
        'external_photos' => env('GEOAPIFY_EXTERNAL_PHOTOS', true),
        'base_url' => 'https://api.geoapify.com/v2',
        'timeout' => 15,
    ],

];
