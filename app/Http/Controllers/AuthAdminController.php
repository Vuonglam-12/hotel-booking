<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthAdminController extends Controller
{
    // ==========================================
    // Admin đăng nhập bằng Customer có role=admin
    // POST /api/admin/login
    // ==========================================
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $admin = Customer::where('email', $request->email)
            ->where('role', 'admin')
            ->first();

        if (!$admin || !Hash::check($request->password, $admin->password_hash)) {
            return response()->json([
                'message' => 'Email hoặc mật khẩu không đúng',
            ], 401);
        }

        $admin->tokens()->delete();
        $token = $admin->createToken('admin_token', ['admin'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'admin' => [
                'id'    => $admin->id,
                'name'  => $admin->name,
                'email' => $admin->email,
                'role'  => $admin->role,
            ],
        ]);
    }

    // ==========================================
    // Admin đăng xuất
    // POST /api/admin/logout
    // ==========================================
    public function logout(Request $request)
    {
        
        $request->user('admin')->currentAccessToken()->delete();

        return response()->json(['message' => 'Đăng xuất thành công']);
    }

    // ==========================================
    // Xem thông tin Admin hiện tại
    // GET /api/admin/me
    // ==========================================
    public function me(Request $request)
    {
        $admin = $request->user('admin');

        if (!$admin || !$admin->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'id'    => $admin->id,
            'name'  => $admin->name,
            'email' => $admin->email,
            'role'  => $admin->role,
        ]);
    }
}