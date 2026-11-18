@extends('layout')

@section('title', 'Đăng nhập')
@section('body-class', request()->boolean('popup') ? 'aurel-login-page aurel-login-embedded' : 'aurel-login-page')

@section('content')

<style>
    .aurel-login-page .hv-header { display: none !important; }
    .aurel-login-page .hv-header-experiences,
    .aurel-login-page #compact-search-toggle,
    .aurel-login-page #header-search-panel,
    .aurel-login-page #nav-auth,
    .aurel-login-page #mobile-menu-toggle,
    .aurel-login-page #mobile-menu,
    .aurel-login-page .hv-footer { display: none !important; }
    .aurel-login-page .hv-layout-main {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100svh;
        padding: 20px 16px !important;
        background: #f8fafc;
    }
    .aurel-login-page .aurel-login-content { width: 100%; max-width: 480px; margin-inline: auto; }
    .aurel-login-page .aurel-login-card {
        position: relative;
        padding: 24px 32px;
        border: 1px solid #e5e7eb;
        border-radius: 22px;
        background: #fff;
        box-shadow: 0 16px 48px rgb(15 23 42 / 10%);
    }
    .aurel-login-page .aurel-login-close {
        position: absolute;
        top: 16px;
        right: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border: 1px solid #e5e7eb;
        border-radius: 50%;
        color: #334155;
    }
    .aurel-login-page .aurel-login-close svg { width: 18px; height: 18px; }
    .aurel-login-page .aurel-login-logo { margin-bottom: 14px; text-align: center; }
    .aurel-login-page .aurel-login-logo img { width: 92px; height: auto; }
    .aurel-login-page .aurel-login-title { margin: 0; color: #111827; font-size: 24px; font-weight: 700; line-height: 1.3; text-align: center; }
    .aurel-login-page .aurel-login-subtitle { margin: 5px 0 18px; color: #4b5563; font-size: 14px; text-align: center; }
    .aurel-login-page .aurel-login-error { color: #b91c1c; }
    .auth-input {
        width: 100%; min-height: 46px; border: 1px solid #9ca3af; border-radius: 10px;
        padding: 10px 13px; font-size: 15px; color: #111827;
        transition: border-color 0.2s ease, box-shadow 0.2s ease; outline: none; box-sizing: border-box;
    }
    .auth-input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.14); }

    .btn-sky-full {
        width: 100%; min-height: 48px; background: #2563eb; color: #fff; font-weight: 700;
        padding: 12px; border-radius: 10px; font-size: 15px; border: none;
        cursor: pointer; transition: all 0.25s ease;
    }
    .btn-sky-full:hover { background: #1d4ed8; }
    .btn-sky-full:disabled { opacity: 0.65; cursor: wait; }
    .aurel-login-page .auth-input-wrap { position: relative; }
    .aurel-login-page .auth-password-toggle {
        position: absolute; inset-block: 0; right: 10px; display: flex; align-items: center;
        color: #4b5563; font-size: 12px; font-weight: 600;
    }
    .aurel-login-page .auth-password-toggle:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
    .aurel-login-page .auth-password-input { padding-right: 58px; }

    .btn-social {
        width: 100%; display: flex; align-items: center; justify-content: center; gap: 10px;
        min-height: 48px; padding: 12px; border-radius: 10px; font-size: 14px; font-weight: 600;
        cursor: pointer; transition: all 0.2s ease; border: 1.5px solid #E5E7EB;
        background: white; color: #1f2937;
    }
    .btn-social:hover { border-color: #6b7280; background: #f9fafb; }

    .tab-btn {
        flex: 1; padding: 8px 10px; font-size: 13px; font-weight: 600; border-radius: 9px;
        cursor: pointer; transition: all 0.2s ease; border: none; background: transparent;
        color: #4b5563; display: flex; align-items: center; justify-content: center; gap: 6px;
    }
    .tab-btn.active { background: #eff6ff; color: #1d4ed8; }

    .divider { display: flex; align-items: center; gap: 12px; color: #6b7280; font-size: 12px; }
    .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #E5E7EB; }

    /* OTP inputs */
    .otp-input {
        width: 44px; height: 52px; text-align: center; font-size: 22px; font-weight: 700;
        border: 1.5px solid #E5E7EB; border-radius: 12px; color: #1E3A5F;
        outline: none; transition: all 0.2s;
    }
    .otp-input:focus { border-color: #87CEFA; box-shadow: 0 0 0 3px rgba(135,206,250,0.15); }
    .otp-input.filled { border-color: #87CEFA; background: #EAF3FF; }

    /* Modal */
    .modal-overlay {
        position: fixed; inset: 0; background: rgba(58,74,90,0.55); z-index: 100;
        align-items: center; justify-content: center; padding: 16px;
    }
    .modal-box {
        background: white; border-radius: 20px; padding: 32px;
        width: 100%; max-width: 420px; animation: popIn 0.25s ease;
    }
    @keyframes popIn { from { opacity:0; transform:scale(0.95); } to { opacity:1; transform:scale(1); } }

    /* Phone prefix */
    .phone-wrap { display: flex; }
    .phone-prefix {
        display: flex; align-items: center; gap: 6px; padding: 12px;
        background: #EAF3FF; border: 1.5px solid #E5E7EB; border-right: none;
        border-radius: 12px 0 0 12px; font-size: 13px; font-weight: 600;
        color: #374151; white-space: nowrap; user-select: none;
    }
    .phone-number-input {
        flex: 1; border: 1.5px solid #E5E7EB; border-left: none;
        border-radius: 0 12px 12px 0; padding: 12px 14px;
        font-size: 14px; color: #3A4A5A; outline: none; transition: all 0.2s;
    }
    .phone-number-input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.14); }

    .step-indicator {
        display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 20px;
    }
    .step-dot {
        width: 8px; height: 8px; border-radius: 50%; background: #E5E7EB; transition: all 0.3s;
    }
    .step-dot.active { background: #87CEFA; width: 24px; border-radius: 4px; }
    .step-dot.done { background: #10B981; }

    .aurel-login-embedded .hv-layout-main { min-height: 100svh; padding: 0 !important; background: #fff; }
    .aurel-login-embedded .aurel-login-content { max-width: none; }
    .aurel-login-embedded .aurel-login-card { min-height: 100svh; padding: 24px 28px; border: 0; border-radius: 0; box-shadow: none; }
    .aurel-login-embedded .aurel-login-close { display: none; }

    @media (max-width: 520px) {
        .aurel-login-page .hv-layout-main { align-items: flex-start; min-height: 100svh; padding: 12px !important; }
        .aurel-login-page .aurel-login-card { padding: 20px 18px; border-radius: 18px; }
        .aurel-login-embedded .hv-layout-main { min-height: 100svh; padding: 0 !important; }
        .aurel-login-embedded .aurel-login-card { min-height: 100svh; padding: 20px 18px 28px; }
    }
</style>

<div class="aurel-login-content">

        <div class="aurel-login-card">
            <a href="{{ route('home') }}" class="aurel-login-close" aria-label="Đóng trang đăng nhập">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" stroke-width="1.8" stroke-linecap="round"/></svg>
            </a>
            <div class="aurel-login-logo">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2">
                @include('partials.brand-logo')
            </a>
            </div>

            <h1 id="login-title" class="aurel-login-title">Chào mừng đến với Angel</h1>
            <p id="login-subtitle" class="aurel-login-subtitle">Đăng nhập để tiếp tục hành trình của bạn.</p>

            <div id="error-msg" class="aurel-login-error hidden bg-red-50 border border-red-100 text-sm rounded-xl px-4 py-3 mb-4" role="alert" aria-live="polite"></div>

            <div id="login-panel">
            {{-- Tabs --}}
            <div class="flex gap-1 bg-slate-50 p-1 rounded-xl mb-4" role="group" aria-label="Chọn phương thức đăng nhập">
                <button type="button" class="tab-btn active" id="tab-email" onclick="switchTab('email')" aria-pressed="true">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    Email
                </button>
                <button type="button" class="tab-btn" id="tab-phone" onclick="switchTab('phone')" aria-pressed="false">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                    </svg>
                    Số điện thoại
                </button>
            </div>

            {{-- ===== PANEL: EMAIL ===== --}}
            <div id="panel-email">
                <div class="space-y-4">
                    <div>
                        <label for="email" class="block text-sm font-semibold text-gray-800 mb-1.5">Email</label>
                        <input id="email" name="email" type="email" autocomplete="email" required placeholder="email@gmail.com" class="auth-input">
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-1.5">
                            <label for="password" class="text-sm font-semibold text-gray-800">Mật khẩu</label>
                        </div>
                        <div class="auth-input-wrap">
                            <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Nhập mật khẩu" class="auth-input auth-password-input">
                            <button type="button" class="auth-password-toggle" data-password-toggle="password" aria-label="Hiện mật khẩu" aria-pressed="false">Hiện</button>
                        </div>
                        <div class="mt-1.5 text-right">
                            <button onclick="openForgotModal()" type="button" class="text-sm text-blue-700 hover:underline font-semibold">Quên mật khẩu?</button>
                        </div>
                    </div>
                    <button onclick="doLogin()" id="login-btn" type="button" class="btn-sky-full">Đăng nhập</button>
                </div>
            </div>

            {{-- ===== PANEL: SĐT ===== --}}
            <div id="panel-phone" class="hidden">
                <div class="space-y-4">
                    <div>
                        <label for="phone-number" class="block text-sm font-semibold text-gray-800 mb-1.5">Số điện thoại</label>
                        <div class="phone-wrap">
                            <div class="phone-prefix">🇻🇳 +84</div>
                            <input id="phone-number" type="tel" autocomplete="tel-national" placeholder="354313031" class="phone-number-input">
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-1.5">
                            <label for="phone-password" class="text-sm font-semibold text-gray-800">Mật khẩu</label>
                        </div>
                        <div class="auth-input-wrap">
                            <input id="phone-password" type="password" autocomplete="current-password" placeholder="Nhập mật khẩu" class="auth-input auth-password-input">
                            <button type="button" class="auth-password-toggle" data-password-toggle="phone-password" aria-label="Hiện mật khẩu" aria-pressed="false">Hiện</button>
                        </div>
                        <div class="mt-1.5 text-right">
                            <button onclick="openForgotModal()" type="button" class="text-sm text-blue-700 hover:underline font-semibold">Quên mật khẩu?</button>
                        </div>
                    </div>
                    <button onclick="doLoginPhone()" id="login-phone-btn" type="button" class="btn-sky-full">Đăng nhập</button>
                </div>
            </div>

            </div>

            <div id="register-panel" class="hidden">
                <form id="register-form" class="space-y-3.5" novalidate>
                    <div>
                        <label for="signup-name" class="block text-sm font-semibold text-gray-800 mb-1.5">Họ tên <span class="text-red-500">*</span></label>
                        <input id="signup-name" name="name" type="text" autocomplete="name" maxlength="100" required placeholder="Nguyễn Văn A" class="auth-input">
                        <p id="signup-name-error" class="hidden mt-1 text-xs text-red-600" role="alert"></p>
                    </div>
                    <div>
                        <label for="signup-email" class="block text-sm font-semibold text-gray-800 mb-1.5">Email <span class="text-red-500">*</span></label>
                        <input id="signup-email" name="email" type="email" autocomplete="email" required placeholder="email@gmail.com" class="auth-input">
                    </div>
                    <div>
                        <label for="signup-phone" class="block text-sm font-semibold text-gray-800 mb-1.5">Số điện thoại <span class="text-gray-500 font-normal">(không bắt buộc)</span></label>
                        <input id="signup-phone" name="phone" type="tel" autocomplete="tel" maxlength="20" placeholder="0901234567" class="auth-input">
                    </div>
                    <div>
                        <label for="signup-password" class="block text-sm font-semibold text-gray-800 mb-1.5">Mật khẩu <span class="text-red-500">*</span></label>
                        <div class="auth-input-wrap">
                            <input id="signup-password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required placeholder="Tối thiểu 8 ký tự" class="auth-input auth-password-input">
                            <button type="button" class="auth-password-toggle" data-password-toggle="signup-password" aria-label="Hiện mật khẩu" aria-pressed="false">Hiện</button>
                        </div>
                    </div>
                    <div>
                        <label for="signup-password-confirmation" class="block text-sm font-semibold text-gray-800 mb-1.5">Nhập lại mật khẩu <span class="text-red-500">*</span></label>
                        <input id="signup-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required placeholder="Nhập lại mật khẩu" class="auth-input">
                    </div>
                    <button type="submit" id="register-btn" class="btn-sky-full">Tạo tài khoản</button>
                    <p class="text-center text-xs text-gray-500">
                        Bằng cách đăng ký, bạn đồng ý với
                        <a href="#" class="text-blue-700 hover:underline">Điều khoản</a> và
                        <a href="#" class="text-blue-700 hover:underline">Chính sách bảo mật</a>.
                    </p>
                </form>
            </div>

            <div class="divider my-5">HOẶC</div>
            <div id="g_id_onload"
                data-client_id="{{ config('services.google.client_id') }}"
                data-context="signin"
                data-ux_mode="popup"
                data-callback="handleGoogleLogin"
                data-auto_prompt="false">
            </div>
            <div class="g_id_signin"
                data-type="standard"
                data-shape="rectangular"
                data-theme="outline"
                data-text="continue_with"
                data-size="large"
                data-logo_alignment="left"
                style="width: 100%;">
            </div>

            <p class="text-center text-sm text-gray-600 mt-4">
                <span id="login-register-prompt">Chưa có tài khoản?</span>
                <span id="register-login-prompt" class="hidden">Đã có tài khoản?</span>
                <button type="button" id="show-register" onclick="showRegisterPanel()" class="text-blue-700 font-semibold hover:underline">Đăng ký</button>
                <button type="button" id="show-login" onclick="showLoginPanel()" class="hidden text-blue-700 font-semibold hover:underline">Đăng nhập</button>
            </p>
        </div>
</div>

{{-- ===================================================== --}}
{{-- MODAL QUÊN MẬT KHẨU                                   --}}
{{-- Bước 1: Nhập email                                     --}}
{{-- Bước 2: Nhập OTP (gửi lại nếu chờ quá lâu)            --}}
{{-- → OTP đúng: redirect /reset-password?token=...&email   --}}
{{-- ===================================================== --}}
<div id="forgot-modal" class="modal-overlay hidden">
    <div class="modal-box">

        {{-- STEP INDICATOR --}}
        <div class="step-indicator" id="step-indicator">
            <div class="step-dot active" id="dot-1"></div>
            <div class="step-dot" id="dot-2"></div>
        </div>

        {{-- BƯỚC 1: Nhập email --}}
        <div id="forgot-step-1">
            <div class="text-center mb-6">
                <div class="w-14 h-14 bg-[#EAF3FF] rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-7 h-7 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-[#1E3A5F]">Quên mật khẩu?</h3>
                <p class="text-sm text-gray-600 mt-1">Nhập email để nhận mã OTP đặt lại</p>
            </div>

            <div id="err-step-1" class="hidden bg-red-50 text-red-600 text-sm rounded-xl px-4 py-3 mb-4"></div>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-[#3A4A5A] mb-1.5 uppercase tracking-wide">Email tài khoản</label>
                    <input id="forgot-email" type="email" placeholder="email@gmail.com" class="auth-input"
                        onkeypress="if(event.key==='Enter') submitForgotEmail()">
                </div>
                <button onclick="submitForgotEmail()" id="btn-send-otp" class="btn-sky-full">
                    Gửi mã OTP
                </button>
                <button onclick="closeForgotModal()" type="button"
                    class="w-full text-sm text-gray-600 hover:text-gray-800 transition py-2">
                    Hủy
                </button>
            </div>
        </div>

        {{-- BƯỚC 2: Nhập OTP --}}
        <div id="forgot-step-2" class="hidden">
            <div class="text-center mb-5">
                <div class="w-14 h-14 bg-[#EAF3FF] rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-7 h-7 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-[#1E3A5F]">Nhập mã OTP</h3>
                <p class="text-sm text-gray-600 mt-1">
                    Nếu email đã đăng ký, mã sẽ được gửi đến <strong id="otp-target-email" class="text-[#3A4A5A]"></strong>
                </p>
                <p id="otp-email-notice" class="mt-3 rounded-lg bg-blue-50 px-3 py-2 text-xs leading-relaxed text-blue-800" role="status">
                    Hãy kiểm tra Hộp thư đến. Nếu chưa thấy email, vui lòng kiểm tra thêm mục Thư rác hoặc Quảng cáo.
                </p>
            </div>

            <div id="err-step-2" class="hidden bg-red-50 text-red-600 text-sm rounded-xl px-4 py-3 mb-4"></div>

            {{-- 6 ô OTP --}}
            <div class="flex gap-2 justify-center mb-5">
                <input type="text" maxlength="1" class="otp-input" id="oi-0" oninput="otpNav(this,0)" onkeydown="otpBack(this,0,event)">
                <input type="text" maxlength="1" class="otp-input" id="oi-1" oninput="otpNav(this,1)" onkeydown="otpBack(this,1,event)">
                <input type="text" maxlength="1" class="otp-input" id="oi-2" oninput="otpNav(this,2)" onkeydown="otpBack(this,2,event)">
                <input type="text" maxlength="1" class="otp-input" id="oi-3" oninput="otpNav(this,3)" onkeydown="otpBack(this,3,event)">
                <input type="text" maxlength="1" class="otp-input" id="oi-4" oninput="otpNav(this,4)" onkeydown="otpBack(this,4,event)">
                <input type="text" maxlength="1" class="otp-input" id="oi-5" oninput="otpNav(this,5)" onkeydown="otpBack(this,5,event)">
            </div>

            <button onclick="verifyOtp()" id="btn-verify-otp" class="btn-sky-full mb-4">
                Xác nhận OTP
            </button>

            {{-- Đếm ngược + gửi lại --}}
            <div class="text-center text-xs text-gray-600">
                Chờ quá lâu?
                <button id="btn-resend" onclick="resendOtp()"
                    class="hidden text-blue-700 font-semibold hover:underline ml-1">
                    Gửi lại ngay
                </button>
                <span id="countdown-wrap" class="ml-1">
                    Gửi lại sau <span id="countdown-num" class="font-semibold text-[#3A4A5A]">60</span>s
                </span>
            </div>

            <button onclick="goBackStep1()" type="button"
                class="w-full text-sm text-gray-600 hover:text-gray-800 transition py-2 mt-3">
                ← Nhập lại email
            </button>
        </div>

    </div>
</div>

@endsection

@push('scripts')
<script src="https://accounts.google.com/gsi/client?hl=vi" async defer></script>
<script>
// ========================
// TAB SWITCH
// ========================
function switchTab(tab) {
    document.getElementById('panel-email').classList.toggle('hidden', tab !== 'email');
    document.getElementById('panel-phone').classList.toggle('hidden', tab !== 'phone');
    document.getElementById('tab-email').classList.toggle('active', tab === 'email');
    document.getElementById('tab-phone').classList.toggle('active', tab === 'phone');
    document.getElementById('tab-email').setAttribute('aria-pressed', String(tab === 'email'));
    document.getElementById('tab-phone').setAttribute('aria-pressed', String(tab === 'phone'));
    document.getElementById('error-msg').classList.add('hidden');
}

function setAuthMode(mode, focus = true) {
    const registering = mode === 'register';
    document.getElementById('login-panel').classList.toggle('hidden', registering);
    document.getElementById('register-panel').classList.toggle('hidden', !registering);
    document.getElementById('login-title').textContent = registering ? 'Tạo tài khoản Angel' : 'Chào mừng đến với Angel';
    document.getElementById('login-subtitle').textContent = registering
        ? 'Đăng ký để bắt đầu hành trình của bạn.'
        : 'Đăng nhập để tiếp tục hành trình của bạn.';
    document.getElementById('login-register-prompt').classList.toggle('hidden', registering);
    document.getElementById('register-login-prompt').classList.toggle('hidden', !registering);
    document.getElementById('show-register').classList.toggle('hidden', registering);
    document.getElementById('show-login').classList.toggle('hidden', !registering);
    document.getElementById('error-msg').classList.add('hidden');
    document.getElementById('signup-name-error').classList.add('hidden');
    document.getElementById('signup-name').removeAttribute('aria-invalid');

    if (focus) {
        document.getElementById(registering ? 'signup-name' : 'email').focus();
    }
}

function showRegisterPanel() {
    setAuthMode('register');
}

function showLoginPanel() {
    setAuthMode('login');
}

function showError(msg) {
    const el = document.getElementById('error-msg');
    el.textContent = msg || 'Đã xảy ra lỗi. Vui lòng thử lại.';
    el.classList.remove('hidden');
}

function safeAuthErrorMessage(message, fallback) {
    if (typeof message !== 'string' || /SQLSTATE|Connection:|SQL:|Base table or view not found|Integrity constraint/i.test(message)) {
        return fallback;
    }
    return message;
}

document.querySelectorAll('[data-password-toggle]').forEach(button => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.passwordToggle);
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.textContent = show ? 'Ẩn' : 'Hiện';
        button.setAttribute('aria-label', show ? 'Ẩn mật khẩu' : 'Hiện mật khẩu');
        button.setAttribute('aria-pressed', String(show));
    });
});

function finishCustomerLogin(data) {
    localStorage.setItem('token', data.token);
    localStorage.setItem('user', JSON.stringify(data.user));
    if (window.parent !== window) {
        window.parent.postMessage({ type: 'aurel-authenticated', user: data.user }, window.location.origin);
        return;
    }
    window.location.href = '{{ route("home") }}';
}

async function sendLoginRequest(url, payload, button, errorMessage) {
    if (button.disabled) return;
    button.disabled = true;
    button.textContent = 'Đang đăng nhập...';
    document.getElementById('error-msg').classList.add('hidden');

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (!response.ok) {
            showError(safeAuthErrorMessage(data.message, errorMessage));
            return;
        }
        if (!data.token || !data.user) {
            throw new Error('Phản hồi đăng nhập thiếu thông tin phiên.');
        }
        finishCustomerLogin(data);
    } catch (error) {
        console.error('Login request failed:', error);
        showError('Không thể kết nối để đăng nhập. Vui lòng thử lại.');
    } finally {
        button.disabled = false;
        button.textContent = 'Đăng nhập';
    }
}

// ========================
// ĐĂNG NHẬP EMAIL
// ========================
async function doLogin() {
    const emailInput = document.getElementById('email');
    if (!emailInput.value.trim()) { showError('Vui lòng nhập email.'); emailInput.focus(); return; }
    if (!emailInput.checkValidity()) { showError('Vui lòng nhập địa chỉ email hợp lệ.'); emailInput.focus(); return; }
    const password = document.getElementById('password').value;
    if (!password) { showError('Vui lòng nhập mật khẩu.'); document.getElementById('password').focus(); return; }
    await sendLoginRequest('/api/login', { email: emailInput.value.trim(), password }, document.getElementById('login-btn'), 'Email hoặc mật khẩu không đúng!');
}

// ========================
// ĐĂNG NHẬP SĐT + PASSWORD
// ========================
async function doLoginPhone() {
    const phone    = document.getElementById('phone-number').value.trim();
    const password = document.getElementById('phone-password').value;
    const btn      = document.getElementById('login-phone-btn');
    document.getElementById('error-msg').classList.add('hidden');

    if (!phone) { showError('Vui lòng nhập số điện thoại.'); document.getElementById('phone-number').focus(); return; }
    if (!password) { showError('Vui lòng nhập mật khẩu.'); document.getElementById('phone-password').focus(); return; }
    await sendLoginRequest('/api/auth/login-phone', { phone: '0' + phone.replace(/^0+/, ''), password }, btn, 'Số điện thoại hoặc mật khẩu không đúng!');
}

document.addEventListener('keypress', e => {
    if (e.key !== 'Enter') return;
    if (!document.getElementById('register-panel').classList.contains('hidden')) return;
    if (document.getElementById('tab-email').classList.contains('active')) doLogin();
    else doLoginPhone();
});

document.getElementById('register-form').addEventListener('submit', async event => {
    event.preventDefault();
    await doRegister();
});

async function doRegister() {
    const nameInput = document.getElementById('signup-name');
    const nameError = document.getElementById('signup-name-error');
    const emailInput = document.getElementById('signup-email');
    const phoneInput = document.getElementById('signup-phone');
    const passwordInput = document.getElementById('signup-password');
    const confirmationInput = document.getElementById('signup-password-confirmation');
    const button = document.getElementById('register-btn');
    const name = nameInput.value.trim();
    const email = emailInput.value.trim();
    const password = passwordInput.value;
    const confirmation = confirmationInput.value;

    document.getElementById('error-msg').classList.add('hidden');
    nameError.classList.add('hidden');
    nameInput.removeAttribute('aria-invalid');
    if (button.disabled) return;
    if (!name) {
        nameError.textContent = 'Họ tên là thông tin bắt buộc.';
        nameError.classList.remove('hidden');
        nameInput.setAttribute('aria-invalid', 'true');
        nameInput.focus();
        return;
    }
    if (!email || !emailInput.checkValidity()) {
        showError('Vui lòng nhập địa chỉ email hợp lệ.');
        emailInput.focus();
        return;
    }
    if (password.length < 8) {
        showError('Mật khẩu phải có ít nhất 8 ký tự.');
        passwordInput.focus();
        return;
    }
    if (password !== confirmation) {
        showError('Mật khẩu xác nhận không khớp.');
        confirmationInput.focus();
        return;
    }

    button.disabled = true;
    button.textContent = 'Đang tạo tài khoản...';
    try {
        const response = await fetch('/api/register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                name,
                email,
                phone: phoneInput.value.trim() || null,
                password
            })
        });
        const data = await response.json();
        if (!response.ok) {
            const messages = data.errors
                ? Object.values(data.errors).flat().map(message =>
                    safeAuthErrorMessage(message, 'Thông tin đăng ký chưa hợp lệ.')
                )
                : [safeAuthErrorMessage(data.message, 'Không thể tạo tài khoản. Vui lòng thử lại.')];
            showError(messages.join(' '));
            return;
        }
        if (!data.token || !data.user) {
            throw new Error('Phản hồi đăng ký thiếu thông tin phiên.');
        }
        finishCustomerLogin(data);
    } catch (error) {
        console.error('Registration request failed:', error);
        showError('Không thể kết nối để tạo tài khoản. Vui lòng thử lại.');
    } finally {
        button.disabled = false;
        button.textContent = 'Tạo tài khoản';
    }
}

document.getElementById('signup-name').addEventListener('input', event => {
    if (!event.target.value.trim()) return;
    event.target.removeAttribute('aria-invalid');
    document.getElementById('signup-name-error').classList.add('hidden');
});

async function handleGoogleLogin(response) {
    const errDiv = document.getElementById('error-msg');
    errDiv.classList.add('hidden');
    const btn = document.getElementById('login-btn');
    if (btn.disabled) return;
    btn.disabled = true;
    btn.textContent = 'Đang xác thực...';
    try {
        const res = await fetch('/api/auth/google', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ credential: response.credential })
        });
        const data = await res.json();
        if (!res.ok) {
            showError(data.message || 'Đăng nhập bằng Google thất bại!');
            return;
        }
        if (!data.token || !data.user) throw new Error('Phản hồi Google Login thiếu thông tin phiên.');
        finishCustomerLogin(data);
    } catch (error) {
        console.error('Google login failed:', error);
        showError('Không thể xác thực bằng Google. Vui lòng thử lại.');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Đăng nhập';
    }
}


// ========================
// MODAL QUÊN MẬT KHẨU
// ========================
let otpTimer = null;

function openForgotModal() {
    const modal = document.getElementById('forgot-modal');
    modal.classList.remove('hidden');
    modal.style.display = 'flex';
    goToStep(1);
    // Pre-fill email nếu đã nhập ở tab email
    const email = document.getElementById('email')?.value;
    if (email) document.getElementById('forgot-email').value = email;
    setTimeout(() => document.getElementById('forgot-email').focus(), 100);
}

function closeForgotModal() {
    document.getElementById('forgot-modal').classList.add('hidden');
    document.getElementById('forgot-modal').style.display = 'none';
    if (otpTimer) clearInterval(otpTimer);
}

function goToStep(step) {
    [1, 2].forEach(i => {
        document.getElementById('forgot-step-' + i).classList.toggle('hidden', i !== step);
        document.getElementById('dot-' + i).className = 'step-dot' + (i < step ? ' done' : i === step ? ' active' : '');
    });
    if (step === 2) clearOtp();
}

function goBackStep1() {
    if (otpTimer) clearInterval(otpTimer);
    goToStep(1);
}

// BƯỚC 1: Gửi OTP
async function submitForgotEmail() {
    const email = document.getElementById('forgot-email').value.trim();
    const btn   = document.getElementById('btn-send-otp');
    const err   = document.getElementById('err-step-1');
    err.classList.add('hidden');

    if (btn.disabled) return;
    const emailInput = document.getElementById('forgot-email');
    if (!email) { err.textContent = 'Vui lòng nhập email!'; err.classList.remove('hidden'); emailInput.focus(); return; }
    if (!emailInput.checkValidity()) { err.textContent = 'Vui lòng nhập địa chỉ email hợp lệ.'; err.classList.remove('hidden'); emailInput.focus(); return; }

    btn.textContent = 'Đang gửi OTP...';
    btn.disabled    = true;

    try {
        const res = await fetch('/api/forgot-password-otp', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ email })
        });
        const data = await res.json();
        if (!res.ok) {
            err.textContent = safeAuthErrorMessage(data.message, 'Không thể gửi mã OTP lúc này. Vui lòng thử lại sau.');
            err.classList.remove('hidden');
            return;
        }
        document.getElementById('otp-target-email').textContent = email;
        goToStep(2);
        startCountdown(60);
        setTimeout(() => document.getElementById('oi-0').focus(), 100);
    } catch (error) {
        console.error('Không thể gửi OTP đặt lại mật khẩu:', error);
        err.textContent = 'Không thể gửi mã OTP. Vui lòng thử lại.';
        err.classList.remove('hidden');
    } finally {
        btn.textContent = 'Gửi mã OTP';
        btn.disabled = false;
    }
}

// OTP navigation
function otpNav(input, index) {
    input.value = input.value.replace(/[^0-9]/g, '');
    input.classList.toggle('filled', !!input.value);
    if (input.value && index < 5) document.getElementById('oi-' + (index + 1)).focus();
    // Auto verify khi đủ 6 số
    if (getOtp().length === 6) verifyOtp();
}

function otpBack(input, index, e) {
    if (e.key === 'Backspace' && !input.value && index > 0) {
        document.getElementById('oi-' + (index - 1)).focus();
    }
}

function getOtp() {
    return [0,1,2,3,4,5].map(i => document.getElementById('oi-' + i).value).join('');
}

function clearOtp() {
    [0,1,2,3,4,5].forEach(i => {
        const el = document.getElementById('oi-' + i);
        if (el) { el.value = ''; el.classList.remove('filled'); }
    });
}

// BƯỚC 2: Verify OTP → redirect sang /reset-password
async function verifyOtp() {
    const otp   = getOtp();
    const email = document.getElementById('forgot-email').value.trim();
    const btn   = document.getElementById('btn-verify-otp');
    const err   = document.getElementById('err-step-2');
    err.classList.add('hidden');

    if (btn.disabled) return;
    if (otp.length < 6) { err.textContent = 'Vui lòng nhập đủ 6 số!'; err.classList.remove('hidden'); return; }

    btn.textContent = 'Đang xác nhận...';
    btn.disabled    = true;

    try {
        const res = await fetch('/api/forgot-password-verify-otp', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ email, otp })
        });
        const data = await res.json();
        if (res.ok) {
            if (otpTimer) clearInterval(otpTimer);
            showToast('✅ OTP hợp lệ! Đang chuyển hướng...');
            setTimeout(() => {
                window.location.href = '/reset-password/' + data.reset_token + '?email=' + encodeURIComponent(email);
            }, 800);
        } else {
            err.textContent = safeAuthErrorMessage(data.message, 'Không thể xác nhận mã OTP. Vui lòng thử lại.');
            err.classList.remove('hidden');
            clearOtp();
            document.getElementById('oi-0').focus();
        }
    } catch (error) {
        console.error('Không thể xác nhận OTP đặt lại mật khẩu:', error);
        err.textContent = 'Không thể xác nhận mã OTP. Vui lòng thử lại.';
        err.classList.remove('hidden');
    } finally {
        btn.textContent = 'Xác nhận OTP';
        btn.disabled = false;
    }
}

// Resend OTP
async function resendOtp() {
    const btn = document.getElementById('btn-resend');
    if (btn.disabled) return;
    clearOtp();
    document.getElementById('err-step-2').classList.add('hidden');
    document.getElementById('oi-0').focus();
    const email = document.getElementById('forgot-email').value.trim();
    btn.disabled = true;
    btn.textContent = 'Đang gửi...';
    try {
        const response = await fetch('/api/forgot-password-otp', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ email })
        });
        const data = await response.json();
        if (!response.ok) {
            if (response.status === 429) {
                const retryAfter = Number(response.headers.get('Retry-After')) || 60;
                startCountdown(retryAfter);
            }
            throw new Error(safeAuthErrorMessage(data.message, 'Không thể gửi lại OTP. Vui lòng thử lại sau.'));
        }
        showToast('Nếu email đã đăng ký, mã OTP sẽ được gửi. Hãy kiểm tra hộp thư.');
        startCountdown(60);
    } catch (error) {
        console.error('Không thể gửi lại OTP đặt lại mật khẩu:', error);
        const err = document.getElementById('err-step-2');
        err.textContent = error.message || 'Không thể gửi lại mã OTP. Vui lòng thử lại.';
        err.classList.remove('hidden');
    } finally {
        btn.textContent = 'Gửi lại ngay';
        btn.disabled = false;
    }
}

function startCountdown(seconds) {
    if (otpTimer) clearInterval(otpTimer);
    let remaining = seconds;
    document.getElementById('btn-resend').classList.add('hidden');
    document.getElementById('countdown-wrap').classList.remove('hidden');
    document.getElementById('countdown-num').textContent = remaining;

    otpTimer = setInterval(() => {
        remaining--;
        document.getElementById('countdown-num').textContent = remaining;
        if (remaining <= 0) {
            clearInterval(otpTimer);
            document.getElementById('btn-resend').classList.remove('hidden');
            document.getElementById('countdown-wrap').classList.add('hidden');
        }
    }, 1000);
}

// Đóng modal khi click overlay
document.getElementById('forgot-modal').addEventListener('click', function(e) {
    if (e.target === this) closeForgotModal();
});
document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    if (!document.getElementById('forgot-modal').classList.contains('hidden')) {
        closeForgotModal();
        return;
    }
    if (window.parent !== window) window.parent.postMessage({ type: 'aurel-close-login' }, window.location.origin);
});

const initialAuthMode = @json(request()->query('mode') === 'register' ? 'register' : 'login');
setAuthMode(initialAuthMode, false);

const loginToken = localStorage.getItem('token');
if (loginToken) {
    document.querySelector('.aurel-login-content').hidden = true;
    fetch('/api/me', { headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + loginToken } })
        .then(async response => {
            if (response.ok) {
                const user = await response.json();
                localStorage.setItem('user', JSON.stringify(user));
                if (window.parent !== window) {
                    window.parent.postMessage({ type: 'aurel-authenticated', user }, window.location.origin);
                } else {
                    window.location.replace('{{ route("home") }}');
                }
            } else if (response.status === 401 || response.status === 403) {
                localStorage.removeItem('token');
                localStorage.removeItem('user');
                document.querySelector('.aurel-login-content').hidden = false;
                if (window.parent !== window) window.parent.postMessage({ type: 'aurel-auth-invalid' }, window.location.origin);
            } else {
                console.error('Không thể xác minh phiên đăng nhập:', response.status);
                document.querySelector('.aurel-login-content').hidden = false;
            }
        })
        .catch(error => {
            console.error('Không thể xác minh phiên đăng nhập:', error);
            document.querySelector('.aurel-login-content').hidden = false;
        });
} else {
    document.getElementById(initialAuthMode === 'register' ? 'signup-name' : 'email').focus({ preventScroll: true });
}
</script>
@endpush
