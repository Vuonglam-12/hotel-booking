<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:customer,email',
            'phone'    => 'nullable|string|max:20',
            'password' => 'required|string|min:6',
        ]);

        $customer = Customer::create([
            'name'          => $request->name,
            'email'         => $request->email,
            'phone'         => $request->phone,
            'password_hash' => Hash::make($request->password),
            'role'          => 'user', 
        ]);

        $token = $customer->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Đăng ký thành công',
            'token'   => $token,
            'user'    => [
                'id'    => $customer->id,
                'name'  => $customer->name,
                'email' => $customer->email,
                'role'  => $customer->role, 
            ],
        ], 201);
    }

    // Trong AuthController.php — hàm login()
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $customer = Customer::where('email', $request->email)->first();

        if (!$customer || !Hash::check($request->password, $customer->password_hash)) {
            return response()->json(['message' => 'Email hoặc mật khẩu không đúng'], 401);
        }

        $customer->tokens()->delete();
        $token = $customer->createToken('customer_token')->plainTextToken;

        $isAdmin = $customer->isAdmin();

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'       => $customer->id,
                'name'     => $customer->name,
                'email'    => $customer->email,
                'phone'    => $customer->phone,
                'avatar_url' => $customer->avatar_url,
                // ✅ THÊM MỚI:
                'is_admin' => $isAdmin,
                'admin_role' => $isAdmin ? 'admin' : null,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Đăng xuất thành công']);
    }
    
    // ==========================================
    // GET /api/me — Lấy thông tin profile
    // ==========================================
    public function me(Request $request)
    {
        $customer = Customer::findOrFail(auth('sanctum')->id()); // ← thay $request->user()
        
        return response()->json([
            'id'         => $customer->id,
            'name'       => $customer->name,
            'email'      => $customer->email,
            'phone'      => $customer->phone,
            'dob'        => $customer->dob,
            'gender'     => $customer->gender,
            'address'    => $customer->address,
            'avatar_url' => $customer->avatar_url ? Storage::url($customer->avatar_url) : null,
            'created_at' => $customer->created_at,
            'points'     => $customer->points ?? 0,
            'bookings'   => $customer->bookings()->count(), // ← đếm thật từ DB
            'vouchers'   => $customer->vouchers ?? 0,
        ]);
    }

    // ==========================================
    // PUT /api/me — Cập nhật thông tin profile
    // ==========================================
    public function updateProfile(Request $request)
    {
        $customer = Customer::findOrFail(auth('sanctum')->id());

        $request->validate([
            'name'  => 'required|string|max:100',
            'phone' => 'nullable|string|max:20',
            'dob'   => 'nullable|date',
        ]);

        $customer->update([
            'name'  => $request->name,
            'phone' => $request->phone,
            'dob'   => $request->dob,
        ]);

        return response()->json([
            'message' => 'Cập nhật thông tin thành công',
            'user'    => [
                'id'    => $customer->id,
                'name'  => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'dob'   => $customer->dob,
            ],
        ]);
    }

    // ==========================================
    // POST /api/me/password — Đổi mật khẩu
    // ==========================================
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6|confirmed',
        ]);

        $customer = Customer::findOrFail(auth('sanctum')->id());

        if (!Hash::check($request->current_password, $customer->password_hash)) {
            return response()->json(['message' => 'Mật khẩu hiện tại không đúng'], 422);
        }

        $customer->update([
            'password_hash' => Hash::make($request->new_password),
        ]);

        return response()->json(['message' => 'Đổi mật khẩu thành công']);
    }

    // ==========================================
    // POST /api/auth/login-phone
    // ==========================================
    public function loginByPhone(Request $request)
    {
        $request->validate(['phone' => 'required|string', 'password' => 'required|string']);

        $customer = Customer::where('phone', $request->phone)
            ->orWhere('phone', '+84' . ltrim($request->phone, '0'))
            ->first();

        if (!$customer || !Hash::check($request->password, $customer->password_hash)) {
            return response()->json(['message' => 'Số điện thoại hoặc mật khẩu không đúng'], 401);
        }

        $token = $customer->createToken('auth_token')->plainTextToken;
        return response()->json([
            'message' => 'Đăng nhập thành công',
            'token'   => $token,
            'user'    => [
                'id'    => $customer->id,
                'name'  => $customer->name,
                'email' => $customer->email,
                'role'  => $customer->role ?? 'user', 
            ],
        ]);
    }

    // ==========================================
    // POST /api/forgot-password-otp
    // ==========================================
    public function forgotPasswordOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $customer = Customer::where('email', $request->email)->first();
        if (!$customer) {
            return response()->json(['message' => 'Email không tồn tại trong hệ thống'], 404);
        }

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        DB::table('password_reset_tokens')->insert([
            'email'      => $request->email,
            'token'      => Hash::make($otp),
            'created_at' => now(),
        ]);

        Mail::send('emails.otp', [
            'otp'      => $otp,
            'customer' => $customer,
            'phone'    => '',
        ], function ($mail) use ($customer) {
            $mail->to($customer->email)->subject('Mã OTP đặt lại mật khẩu — Hotel Booking');
        });

        return response()->json(['message' => 'Đã gửi OTP về email']);
    }

    // ==========================================
    // POST /api/forgot-password-verify-otp
    // ==========================================
    public function forgotPasswordVerifyOtp(Request $request)
    {
        $request->validate(['email' => 'required|email', 'otp' => 'required|string|size:6']);

        $record = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$record || !Hash::check($request->otp, $record->token)) {
            return response()->json(['message' => 'Mã OTP không đúng hoặc đã hết hạn'], 422);
        }

        if (now()->diffInMinutes($record->created_at) > 10) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['message' => 'Mã OTP đã hết hạn (10 phút), vui lòng gửi lại'], 422);
        }

        $resetToken = Str::random(64);
        DB::table('password_reset_tokens')->where('email', $request->email)->update([
            'token' => Hash::make($resetToken),
        ]);

        return response()->json(['message' => 'OTP hợp lệ', 'reset_token' => $resetToken]);
    }

    // ==========================================
    // POST /api/forgot-password
    // ==========================================
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $customer = Customer::where('email', $request->email)->first();
        if (!$customer) {
            return response()->json(['message' => 'Nếu email tồn tại, bạn sẽ nhận được link đặt lại mật khẩu']);
        }

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        $token = Str::random(64);
        DB::table('password_reset_tokens')->insert([
            'email'      => $request->email,
            'token'      => Hash::make($token),
            'created_at' => now(),
        ]);

        $resetLink = url('/reset-password/' . $token . '?email=' . urlencode($request->email));
        Mail::send('emails.reset_password', ['customer' => $customer, 'resetLink' => $resetLink], function ($mail) use ($customer) {
            $mail->to($customer->email)->subject('Đặt lại mật khẩu — Hotel Booking');
        });

        return response()->json(['message' => 'Nếu email tồn tại, bạn sẽ nhận được link đặt lại mật khẩu']);
    }

    // ==========================================
    // POST /api/reset-password
    // ==========================================
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'token'    => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$record || !Hash::check($request->token, $record->token)) {
            return response()->json(['message' => 'Token không hợp lệ hoặc đã hết hạn'], 422);
        }

        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['message' => 'Token đã hết hạn, vui lòng yêu cầu lại'], 422);
        }

        Customer::where('email', $request->email)->update(['password_hash' => Hash::make($request->password)]);
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'Đặt lại mật khẩu thành công, vui lòng đăng nhập lại']);
    }

    // ==========================================
    // POST /api/auth/google
    // ==========================================
    public function loginGoogle(Request $request)
    {
        $request->validate([
            'credential' => 'required|string',
        ]);

        // Giải mã token từ Google (Google trả về dạng JWT)
        $jwt = explode('.', $request->credential);
        if (count($jwt) !== 3) {
            return response()->json(['message' => 'Token Google không hợp lệ'], 401);
        }
        
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $jwt[1])), true);

        if (!$payload || !isset($payload['email'])) {
            return response()->json(['message' => 'Không thể lấy thông tin từ Google'], 401);
        }

        // Tìm khách hàng theo email
        $customer = Customer::where('email', $payload['email'])->first();

        // Nếu chưa có tài khoản, tự động tạo mới
        if (!$customer) {
            $customer = Customer::create([
                'name'          => $payload['name'] ?? 'Khách hàng',
                'email'         => $payload['email'],
                // Mật khẩu ngẫu nhiên vì họ đăng nhập bằng Google
                'password_hash' => Hash::make(\Illuminate\Support\Str::random(24)), 
            ]);
        }

        // Tạo token Sanctum để đăng nhập
        $token = $customer->createToken('auth_token')->plainTextToken;

        $isAdmin = $customer->isAdmin();

        return response()->json([
            'message' => 'Đăng nhập Google thành công',
            'token'   => $token,
            'user'    => [
                'id'         => $customer->id, 
                'name'       => $customer->name, 
                'email'      => $customer->email,
                'is_admin'   => $isAdmin,
                'admin_role' => $isAdmin ? 'admin' : null,
            ],
        ]);
    }
    
    // ==========================================
    // POST /api/me/avatar — Cập nhật ảnh đại diện
    // ==========================================
    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048'
        ]);

    
    $customer = Customer::findOrFail(auth('sanctum')->id());        

        // ĐÚNG
        if ($customer->avatar_url && Storage::disk('public')->exists($customer->avatar_url)) {
            Storage::disk('public')->delete($customer->avatar_url);
        }

        // Lưu ảnh mới
        $path = $request->file('avatar')->store('avatars', 'public');

        $customer->update(['avatar_url' => $path]);

        return response()->json([
            'message'    => 'Cập nhật ảnh thành công!',
            'avatar_url' => Storage::url($path),
        ]);
    }
}