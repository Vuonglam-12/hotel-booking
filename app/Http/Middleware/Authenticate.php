<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Lấy đường dẫn mà người dùng sẽ được chuyển hướng đến khi họ chưa được xác thực.
     */
    protected function redirectTo(Request $request): ?string
    {
        return null;
    }
}
