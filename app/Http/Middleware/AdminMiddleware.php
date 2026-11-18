<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $admin = $request->user('admin');

        if (!$admin || !($admin instanceof \App\Models\Customer) || !$admin->isAdmin()) {
            return response()->json(['message' => 'Chỉ admin mới có quyền truy cập'], 403);
        }

        $token = $admin->currentAccessToken();
        if (!($token instanceof \Laravel\Sanctum\PersonalAccessToken) || $token->name !== 'admin_token' || !$token->can('admin')) {
            return response()->json(['message' => 'Vui lòng đăng nhập bằng phiên Admin'], 403);
        }

        return $next($request);
    }
}
