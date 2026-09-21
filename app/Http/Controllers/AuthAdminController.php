<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthAdminController extends Controller
{
    // ==========================================
    // Staff đăng nhập
    // POST /api/admin/login
    // ==========================================
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $staff = Staff::where('email', $request->email)->first();

        if (!$staff || !Hash::check($request->password, $staff->password_hash)) {
            return response()->json([
                'message' => 'Email hoặc mật khẩu không đúng',
            ], 401);
        }

        $staff->tokens()->delete();
        $token = $staff->createToken('admin_token', ['admin'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'staff' => [
                'id'       => $staff->id,
                'name'     => $staff->name,
                'email'    => $staff->email,
                'role'     => $staff->role,
                'hotel_id' => $staff->hotel_id,
            ],
        ]);
    }

    // ==========================================
    // Staff đăng xuất
    // POST /api/admin/logout
    // ==========================================
    public function logout(Request $request)
    {
        
        $request->user('admin')->currentAccessToken()->delete();

        return response()->json(['message' => 'Đăng xuất thành công']);
    }

    // ==========================================
    // Xem thông tin staff hiện tại
    // GET /api/admin/me
    // ==========================================
    public function me(Request $request)
    {
        $staff = $request->user('admin');

        if (!$staff) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'id'       => $staff->id,
            'name'     => $staff->name,
            'email'    => $staff->email,
            'role'     => $staff->role,
            'hotel_id' => $staff->hotel_id,
        ]);
    }
}