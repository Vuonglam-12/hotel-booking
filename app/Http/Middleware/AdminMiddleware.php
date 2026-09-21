<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $staff = $request->user('admin');

        if (!$staff || !($staff instanceof \App\Models\Staff)) {
            return response()->json(['message' => 'Chỉ admin mới có quyền truy cập'], 403);
        }

        return $next($request);
    }
}