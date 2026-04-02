<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // Đăng ký
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
        ]);

        $token = $customer->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Đăng ký thành công',
            'token'   => $token,
            'user'    => [
                'id'    => $customer->id,
                'name'  => $customer->name,
                'email' => $customer->email,
            ]
        ], 201);
    }

    // Đăng nhập bằng email + password
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $customer = Customer::where('email', $request->email)->first();

        if (!$customer || !Hash::check($request->password, $customer->password_hash)) {
            return response()->json([
                'message' => 'Email hoặc mật khẩu không đúng'
            ], 401);
        }

        $token = $customer->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'token'   => $token,
            'user'    => [
                'id'    => $customer->id,
                'name'  => $customer->name,
                'email' => $customer->email,
            ]
        ]);
    }

    // Đăng xuất
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Đăng xuất thành công'
        ]);
    }

    // Thông tin user đang đăng nhập
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    // ==========================================
    // POST /api/auth/send-phone-otp
    // Nhận phone + email → tạo OTP 6 số → gửi Gmail
    // ==========================================
    public function sendPhoneOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:20',
            'email' => 'required|email',
        ]);

        $phone = $request->phone;
        $email = $request->email;

        // Tìm customer theo SĐT (hỗ trợ cả 0901... và +84901...)
        $customer = Customer::where('phone', $phone)
            ->orWhere('phone', '+84' . ltrim($phone, '0'))
            ->first();

        if (!$customer) {
            return response()->json([
                'message' => 'Số điện thoại chưa được đăng ký. Vui lòng đăng ký tài khoản trước.',
            ], 404);
        }

        // Verify email khớp với account
        if ($customer->email !== $email) {
            return response()->json([
                'message' => 'Email không khớp với tài khoản có số điện thoại này.',
            ], 422);
        }

        // Tạo OTP 6 số ngẫu nhiên
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Lưu OTP vào bảng password_reset_tokens (tái dụng bảng sẵn có)
        DB::table('password_reset_tokens')
            ->where('email', $email)
            ->delete();

        DB::table('password_reset_tokens')->insert([
            'email'      => $email,
            'token'      => Hash::make($otp),
            'created_at' => now(),
        ]);

        // Gửi email OTP
        Mail::send('emails.otp', [
            'otp'      => $otp,
            'customer' => $customer,
            'phone'    => $phone,
        ], function ($mail) use ($customer) {
            $mail->to($customer->email)
                 ->subject('Mã OTP đăng nhập — Hotel Booking');
        });

        // Trả về email đã che bớt để hiển thị UI (bảo mật)
        $maskedEmail = substr($email, 0, 3) . '***' . strstr($email, '@');

        return response()->json([
            'message'      => 'Đã gửi mã OTP đến email của bạn',
            'masked_email' => $maskedEmail,
        ]);
    }

    // ==========================================
    // POST /api/auth/verify-phone-otp
    // Nhận phone + email + otp → verify → trả Sanctum token
    // ==========================================
    public function verifyPhoneOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'email' => 'required|email',
            'otp'   => 'required|string|size:6',
        ]);

        $email = $request->email;
        $otp   = $request->otp;

        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$record) {
            return response()->json([
                'message' => 'Mã OTP không hợp lệ hoặc đã hết hạn',
            ], 422);
        }

        if (!Hash::check($otp, $record->token)) {
            return response()->json([
                'message' => 'Mã OTP không đúng, vui lòng kiểm tra lại',
            ], 422);
        }

        // OTP hết hạn sau 10 phút
        if (now()->diffInMinutes($record->created_at) > 10) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            return response()->json([
                'message' => 'Mã OTP đã hết hạn, vui lòng yêu cầu mã mới',
            ], 422);
        }

        // Xóa OTP sau khi verify thành công — chỉ dùng 1 lần
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        $customer = Customer::where('email', $email)->firstOrFail();
        $token    = $customer->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'token'   => $token,
            'user'    => [
                'id'    => $customer->id,
                'name'  => $customer->name,
                'email' => $customer->email,
            ]
        ]);
    }

    // ==========================================
    // POST /api/forgot-password
    // ==========================================
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $customer = Customer::where('email', $request->email)->first();

        if (!$customer) {
            return response()->json([
                'message' => 'Nếu email tồn tại, bạn sẽ nhận được link đặt lại mật khẩu',
            ]);
        }

        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        $token = Str::random(64);

        DB::table('password_reset_tokens')->insert([
            'email'      => $request->email,
            'token'      => Hash::make($token),
            'created_at' => now(),
        ]);

        $resetLink = url('/reset-password/' . $token . '?email=' . urlencode($request->email));

        Mail::send('emails.reset_password', [
            'customer'  => $customer,
            'resetLink' => $resetLink,
        ], function ($mail) use ($customer) {
            $mail->to($customer->email)
                 ->subject('Đặt lại mật khẩu — Hotel Booking');
        });

        return response()->json([
            'message' => 'Nếu email tồn tại, bạn sẽ nhận được link đặt lại mật khẩu',
        ]);
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

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record || !Hash::check($request->token, $record->token)) {
            return response()->json([
                'message' => 'Token không hợp lệ hoặc đã hết hạn',
            ], 422);
        }

        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'message' => 'Token đã hết hạn, vui lòng yêu cầu lại',
            ], 422);
        }

        Customer::where('email', $request->email)
            ->update(['password_hash' => Hash::make($request->password)]);

        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        return response()->json([
            'message' => 'Đặt lại mật khẩu thành công, vui lòng đăng nhập lại',
        ]);
    }

    // POST /api/auth/login-phone
public function loginByPhone(Request $request)
{
    $request->validate([
        'phone'    => 'required|string',
        'password' => 'required|string',
    ]);

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
        'user'    => ['id' => $customer->id, 'name' => $customer->name, 'email' => $customer->email],
    ]);
}

// POST /api/forgot-password-otp
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

    // POST /api/forgot-password-verify-otp
    public function forgotPasswordVerifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|string|size:6',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record || !Hash::check($request->otp, $record->token)) {
            return response()->json(['message' => 'Mã OTP không đúng hoặc đã hết hạn'], 422);
        }

        if (now()->diffInMinutes($record->created_at) > 10) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['message' => 'Mã OTP đã hết hạn (10 phút), vui lòng gửi lại'], 422);
        }

        // Tạo reset_token để redirect sang /reset-password
        $resetToken = \Illuminate\Support\Str::random(64);
        DB::table('password_reset_tokens')->where('email', $request->email)->update([
            'token' => Hash::make($resetToken),
        ]);

        return response()->json([
            'message'     => 'OTP hợp lệ',
            'reset_token' => $resetToken,
        ]);
    }
}
