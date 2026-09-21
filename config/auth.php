<?php

return [

    'defaults' => [
        'guard'     => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver'   => 'session',
            'provider' => 'users',
        ],

        // Guard cho Customer (user thường) - dùng sanctum mặc định
        'sanctum' => [
            'driver'   => 'sanctum',
            'provider' => 'customers',
        ],

        // ✅ ĐÃ SỬA: Guard cho Staff/Admin - THÊM MỚI
        'admin' => [
            'driver'   => 'sanctum',
            'provider' => 'staff',
        ],
    ],  // ✅ ĐÃ SỬA: Đóng mảng guards

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model'  => App\Models\User::class,
        ],

        'customers' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Customer::class,
        ],

        'staff' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Staff::class,
        ],
    ],  // ✅ ĐÃ SỬA: Chỉ còn 1 mảng providers

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table'    => 'password_reset_tokens',
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];