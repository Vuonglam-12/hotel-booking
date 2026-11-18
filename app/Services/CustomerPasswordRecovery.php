<?php

namespace App\Services;

use App\Models\Customer;
use App\Support\CustomerSessions;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final class CustomerPasswordRecovery
{
    public function request(string $email, bool $otp): JsonResponse
    {
        if ($unavailable = $this->unavailable()) return $unavailable;
        $email = $this->email($email);
        $key = $this->key('request', $email);
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return $this->limited($key);
        }
        // Apply the same cooldown and response to existing and unknown addresses.
        RateLimiter::hit($key, 60);
        $secret = $otp ? str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT) : Str::random(64);
        $hash = Hash::make($secret);
        $customer = Customer::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($customer) {
            DB::transaction(function () use ($customer, $email, $hash): void {
                Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
                DB::table('password_reset_tokens')->updateOrInsert(['email' => $email], [
                    'token' => $hash, 'created_at' => now(),
                ]);
            });

            // Send through the existing mail transport after the HTTP response. Delivery
            // failures are logged, not exposed as an account-existence oracle.
            $delivered = false;
            app()->terminating(function () use ($customer, $email, $secret, $otp, &$delivered): void {
                if ($delivered) return;
                $delivered = true;
                try {
                    $view = $otp ? 'emails.otp' : 'emails.reset_password';
                    $data = $otp ? ['otp' => $secret, 'customer' => $customer] : [
                        'customer' => $customer,
                        'resetLink' => url('/reset-password/'.$secret.'?email='.urlencode($email)),
                    ];
                    Mail::send($view, $data, function ($mail) use ($customer, $otp): void {
                        $mail->to($customer->email)->subject($otp ? 'Mã OTP đặt lại mật khẩu — Angel' : 'Đặt lại mật khẩu — Angel');
                    });
                } catch (\Throwable $exception) {
                    Log::error('Account recovery mail delivery failed.', [
                        'customer_id' => $customer->id, 'exception' => get_class($exception),
                    ]);
                }
            });
        }

        return response()->json(['message' => 'Nếu email đã được đăng ký, hướng dẫn đặt lại mật khẩu sẽ được gửi đến email đó.'])
            ->header('Retry-After', '60')->header('Cache-Control', 'no-store');
    }

    public function verifyOtp(string $email, string $otp): JsonResponse
    {
        if ($unavailable = $this->unavailable()) return $unavailable;
        $email = $this->email($email);
        $key = $this->key('otp-attempt', $email);
        if (RateLimiter::tooManyAttempts($key, 5)) return $this->limited($key);
        RateLimiter::hit($key, 600);
        $dummy = Hash::make(Str::random(64));
        $token = DB::transaction(function () use ($email, $otp, $dummy): ?string {
            $record = DB::table('password_reset_tokens')->whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first();
            $matches = Hash::check($otp, $record?->token ?? $dummy);
            if (!$record || !$matches || !$this->fresh($record->created_at, 10)) return null;
            $token = Str::random(64);
            DB::table('password_reset_tokens')->where('email', $record->email)->update([
                'token' => Hash::make($token), 'created_at' => now(),
            ]);
            return $token;
        });

        if ($token === null) return $this->invalid('Mã OTP không đúng hoặc đã hết hạn.');
        RateLimiter::clear($key);
        return response()->json(['message' => 'OTP hợp lệ', 'reset_token' => $token])->header('Cache-Control', 'no-store');
    }

    public function reset(string $email, string $token, string $password): JsonResponse
    {
        if ($unavailable = $this->unavailable()) return $unavailable;
        $email = $this->email($email);
        $key = $this->key('token-attempt', $email);
        if (RateLimiter::tooManyAttempts($key, 5)) return $this->limited($key);
        RateLimiter::hit($key, 3600);
        $dummy = Hash::make(Str::random(64));
        $changed = DB::transaction(function () use ($email, $token, $password, $dummy): bool {
            $customer = Customer::whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first();
            $record = DB::table('password_reset_tokens')->whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first();
            $matches = Hash::check($token, $record?->token ?? $dummy);
            if (!$customer || !$record || !$matches || !$this->fresh($record->created_at, 60)) return false;
            $customer->update(['password_hash' => Hash::make($password)]);
            CustomerSessions::revokeCustomerTokens($customer);
            DB::table('password_reset_tokens')->where('email', $record->email)->delete();
            return true;
        });

        if (!$changed) return $this->invalid('Thông tin đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.');
        return response()->json(['message' => 'Đặt lại mật khẩu thành công, vui lòng đăng nhập lại.'])->header('Cache-Control', 'no-store');
    }

    private function email(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function unavailable(): ?JsonResponse
    {
        if (DB::getSchemaBuilder()->hasTable('password_reset_tokens')) return null;
        // Older installations must fail equally for known and unknown addresses.
        return response()->json(['message' => 'Tạm thời chưa thể đặt lại mật khẩu. Vui lòng thử lại sau.'], 503)
            ->header('Cache-Control', 'no-store');
    }

    private function key(string $action, string $email): string
    {
        return 'customer-recovery:'.$action.':'.hash('sha256', $email);
    }

    private function fresh(?string $date, int $minutes): bool
    {
        if (!$date) return false;
        $issued = Carbon::parse($date);
        return $issued->lte(now()) && $issued->gt(now()->subMinutes($minutes));
    }

    private function limited(string $key): JsonResponse
    {
        return response()->json(['message' => 'Bạn đã thử quá nhiều lần. Vui lòng thử lại sau.'], 429)
            ->header('Retry-After', (string) RateLimiter::availableIn($key))->header('Cache-Control', 'no-store');
    }

    private function invalid(string $message): JsonResponse
    {
        return response()->json(['message' => $message], 422)->header('Cache-Control', 'no-store');
    }
}
