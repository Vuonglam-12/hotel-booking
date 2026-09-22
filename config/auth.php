<?php

return [

    'defaults' => [
        'guard'     => 'web',
        'passwords' => 'customers',
    ],

    'guards' => [
        'web' => [
            'driver'   => 'session',
            'provider' => 'customers',
        ],

        // Guard cho Customer (user thường) - dùng sanctum mặc định
        'sanctum' => [
            'driver'   => 'sanctum',
            'provider' => 'customers',
        ],

        // Guard cho Admin dùng chung model Customer
        'admin' => [
            'driver'   => 'sanctum',
            'provider' => 'customers',
        ],
    ],  // ✅ ĐÃ SỬA: Đóng mảng guards

    'providers' => [
        'customers' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Customer::class,
        ],

    ],  // ✅ ĐÃ SỬA: Chỉ còn 1 mảng providers

    'passwords' => [
        'customers' => [
            'provider' => 'customers',
            'table'    => 'password_reset_tokens',
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];