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

        // Tìm staff theo email
        $staff = Staff::where('email', $request->email)->first();

        // Kiểm tra password — staff dùng password_hash thay vì password
        if (!$staff || !Hash::check($request->password, $staff->password_hash)) {
            return response()->json([
                'message' => 'Email hoặc mật khẩu không đúng',
            ], 401);
        }

        // Xóa token cũ — chỉ cho đăng nhập 1 thiết bị
        $staff->tokens()->delete();

        // Tạo token mới với ability 'admin'
        $token = $staff->createToken('admin_token', ['admin'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'staff' => [
                'id'       => $staff->id,
                'name'     => $staff->name,
                'email'    => $staff->email,
                'role'     => $staff->role,
                'hotel_id' => $staff->hotel_id, // NULL = superadmin
            ],
        ]);
    }

    // ==========================================
    // Staff đăng xuất
    // POST /api/admin/logout
    // ==========================================
    public function logout(Request $request)
    {
        // Xóa token hiện tại
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Đăng xuất thành công']);
    }

    // ==========================================
    // Xem thông tin staff hiện tại
    // GET /api/admin/me
    // ==========================================
    public function me(Request $request)
    {
        $staff = auth('sanctum')->user();

        return response()->json([
            'id'       => $staff->id,
            'name'     => $staff->name,
            'email'    => $staff->email,
            'role'     => $staff->role,
            'hotel_id' => $staff->hotel_id,
        ]);
    }
}