<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\CustomerPasswordRecovery;
use App\Support\CustomerSessions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    // These existing routes have no SMS verification implementation or approved provider.
    public function sendPhoneOtp(Request $request)
    {
        return response()->json(['message' => 'Xác minh số điện thoại hiện chưa khả dụng.'], 503)->header('Cache-Control', 'no-store');
    }

    public function verifyPhoneOtp(Request $request)
    {
        return response()->json(['message' => 'Xác minh số điện thoại hiện chưa khả dụng.'], 503)->header('Cache-Control', 'no-store');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|max:100|unique:customer,email',
            'phone'    => 'nullable|string|max:20',
            'password' => 'required|string|min:8|max:72',
        ]);

        $customer = Customer::create([
            'name'          => $request->name,
            'email'         => $request->email,
            'phone'         => $request->phone,
            'password_hash' => Hash::make($request->password),
            'role'          => 'user', 
        ]);

        $token = CustomerSessions::issuePasswordToken($customer)->plainTextToken;

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

        $result = DB::transaction(function () use ($request) {
            $customer = Customer::where('email', $request->email)->lockForUpdate()->first();
            if (!$customer || !Hash::check($request->password, $customer->password_hash)) return null;
            CustomerSessions::revokePasswordTokens($customer);
            return [$customer, CustomerSessions::issuePasswordToken($customer)->plainTextToken];
        });
        if (!$result) return response()->json(['message' => 'Email hoặc mật khẩu không đúng'], 401);
        [$customer, $token] = $result;

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
        $token = $request->user()->currentAccessToken();
        if (!($token instanceof \Laravel\Sanctum\PersonalAccessToken) || CustomerSessions::isAdminToken($request->user(), $token)) {
            return response()->json(['message' => 'Vui lòng đăng xuất Admin từ khu vực Admin'], 403);
        }

        $request->user()->tokens()->whereKey($token->id)->delete();
        return response()->json(['message' => 'Đăng xuất thành công', 'revoked' => true])->header('Cache-Control', 'no-store');
    }
    
    // ==========================================
    // GET /api/me — Lấy thông tin profile
    // ==========================================
    public function me(Request $request)
    {
        $customer = Customer::findOrFail(auth('sanctum')->id()); // ← thay $request->user()
        $supportsDob = $customer->supportsDob();
        
        return response()->json([
            'id'         => $customer->id,
            'name'       => $customer->name,
            'email'      => $customer->email,
            'phone'      => $customer->phone,
            'dob'        => $supportsDob ? $customer->dob?->format('Y-m-d') : null,
            'profile_fields' => ['dob' => $supportsDob],
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

        $supportsDob = $customer->supportsDob();
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'sometimes|nullable|string|max:20',
            'dob' => $supportsDob ? 'sometimes|nullable|date_format:Y-m-d|before_or_equal:today' : 'prohibited',
        ], ['dob.prohibited' => 'Ngày sinh chưa được hỗ trợ lưu trên hệ thống hiện tại.']);
        $customer->fill(\Illuminate\Support\Arr::only($data, ['name', 'phone']));
        if ($supportsDob && array_key_exists('dob', $data)) $customer->setAttribute('dob', $data['dob']);
        $customer->save();

        return response()->json([
            'message' => 'Cập nhật thông tin thành công',
            'user'    => [
                'id'    => $customer->id,
                'name'  => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'dob'   => $supportsDob ? $customer->dob?->format('Y-m-d') : null,
            ],
        ]);
    }

    // ==========================================
    // POST /api/me/password — Đổi mật khẩu
    // ==========================================
    public function updatePassword(Request $request)
    {
        $token = $request->user()->currentAccessToken();
        if (CustomerSessions::isAdminToken($request->user(), $token)) {
            return response()->json(['message' => 'Vui lòng sử dụng phiên khách hàng để đổi mật khẩu.'], 403);
        }
        $request->validate([
            'current_password' => 'required|string|max:4096',
            'new_password' => 'required|string|min:8|max:72|confirmed|different:current_password',
        ]);
        $key = 'customer-password-change:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['message' => 'Bạn đã thử quá nhiều lần. Vui lòng thử lại sau.'], 429)
                ->header('Retry-After', (string) RateLimiter::availableIn($key));
        }
        $changed = DB::transaction(function () use ($request, $token) {
            $customer = Customer::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if (!Hash::check($request->current_password, $customer->password_hash)) return false;
            $customer->update(['password_hash' => Hash::make($request->new_password)]);
            CustomerSessions::revokeCustomerTokens($customer, $token instanceof \Laravel\Sanctum\PersonalAccessToken ? $token->id : null);
            if ($customer->getConnection()->getSchemaBuilder()->hasTable('password_reset_tokens')) {
                DB::table('password_reset_tokens')->whereRaw('LOWER(email) = ?', [mb_strtolower($customer->email)])->delete();
            }
            return true;
        });
        if (!$changed) {
            RateLimiter::hit($key, 900);
            return response()->json(['message' => 'Mật khẩu hiện tại không đúng'], 422);
        }
        RateLimiter::clear($key);
        return response()->json(['message' => 'Đổi mật khẩu thành công']);
    }

    // ==========================================
    // POST /api/auth/login-phone
    // ==========================================
    public function loginByPhone(Request $request)
    {
        $request->validate(['phone' => 'required|string', 'password' => 'required|string']);

        $result = DB::transaction(function () use ($request) {
            $customer = Customer::where('phone', $request->phone)
                ->orWhere('phone', '+84' . ltrim($request->phone, '0'))->lockForUpdate()->first();
            if (!$customer || !Hash::check($request->password, $customer->password_hash)) return null;
            CustomerSessions::revokePasswordTokens($customer);
            return [$customer, CustomerSessions::issuePasswordToken($customer)->plainTextToken];
        });
        if (!$result) {
            return response()->json(['message' => 'Số điện thoại hoặc mật khẩu không đúng'], 401);
        }
        [$customer, $token] = $result;
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
        $data = $request->validate(['email' => 'required|email|max:100']);
        return app(CustomerPasswordRecovery::class)->request($data['email'], true);
    }

    public function forgotPasswordVerifyOtp(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:100', 'otp' => 'required|string|regex:/^[0-9]{6}$/D']);
        return app(CustomerPasswordRecovery::class)->verifyOtp($data['email'], $data['otp']);
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:100']);
        return app(CustomerPasswordRecovery::class)->request($data['email'], false);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|max:100',
            'token' => 'required|string|size:64',
            'password' => 'required|string|min:8|max:72|confirmed',
        ]);
        return app(CustomerPasswordRecovery::class)->reset($data['email'], $data['token'], $data['password']);
    }

    // ==========================================
    // POST /api/auth/google
    // ==========================================
    public function loginGoogle(Request $request)
    {
        $request->validate([
            'credential' => 'required|string|max:8192',
        ]);

        try {
            $payload = app(\App\Services\GoogleIdentityVerifier::class)->verify($request->credential);
        } catch (\UnexpectedValueException $e) {
            return response()->json(['message' => 'Token Google không hợp lệ'], 401);
        } catch (\DomainException $e) {
            return response()->json(['message' => 'Vui lòng đăng nhập bằng email/mật khẩu để xác minh tài khoản này'], 403);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => 'Tạm thời chưa xác minh được Google, vui lòng thử lại sau'], 503);
        }

        // Serialize Customer token issuance with password changes/resets. Identity
        // verification and the existing email-to-Customer mapping remain unchanged.
        [$customer, $token] = DB::transaction(function () use ($payload) {
            $customer = Customer::where('email', $payload['email'])->lockForUpdate()->first();
            if (!$customer) {
                $customer = Customer::create([
                    'name' => $payload['name'] ?? 'Khách hàng',
                    'email' => $payload['email'],
                    'password_hash' => Hash::make(\Illuminate\Support\Str::random(24)),
                ]);
            }

            return [$customer, CustomerSessions::issueGoogleToken($customer)->plainTextToken];
        });

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
