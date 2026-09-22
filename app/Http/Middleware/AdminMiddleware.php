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

        return $next($request);
    }
}