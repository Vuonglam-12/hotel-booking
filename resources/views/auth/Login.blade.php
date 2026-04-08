@extends('layout')

@section('title', 'Đăng nhập')

@section('content')

<style>
    .auth-input {
        width: 100%; border: 1.5px solid #E5E7EB; border-radius: 12px;
        padding: 12px 16px; font-size: 14px; color: #3A4A5A;
        transition: all 0.2s ease; outline: none; box-sizing: border-box;
    }
    .auth-input:focus { border-color: #87CEFA; box-shadow: 0 0 0 3px rgba(135,206,250,0.15); }

    .btn-sky-full {
        width: 100%; background: #87CEFA; color: #1E3A5F; font-weight: 700;
        padding: 13px; border-radius: 12px; font-size: 14px; border: none;
        cursor: pointer; transition: all 0.25s ease;
    }
    .btn-sky-full:hover { background: #7BC4F5; transform: translateY(-1px); box-shadow: 0 8px 20px -4px rgba(135,206,250,0.5); }
    .btn-sky-full:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

    .btn-social {
        width: 100%; display: flex; align-items: center; justify-content: center; gap: 10px;
        padding: 12px; border-radius: 12px; font-size: 14px; font-weight: 500;
        cursor: pointer; transition: all 0.2s ease; border: 1.5px solid #E5E7EB;
        background: white; color: #3A4A5A;
    }
    .btn-social:hover { border-color: #87CEFA; background: #EAF3FF; transform: translateY(-1px); }

    .tab-btn {
        flex: 1; padding: 10px; font-size: 13px; font-weight: 600; border-radius: 30px;
        cursor: pointer; transition: all 0.2s ease; border: none; background: transparent;
        color: #C9D3DD; display: flex; align-items: center; justify-content: center; gap: 6px;
    }
    .tab-btn.active { background: #87CEFA; color: #1E3A5F; box-shadow: 0 4px 12px -2px rgba(135,206,250,0.4); }

    .divider { display: flex; align-items: center; gap: 12px; color: #C9D3DD; font-size: 12px; }
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
        color: #3A4A5A; white-space: nowrap; user-select: none;
    }
    .phone-number-input {
        flex: 1; border: 1.5px solid #E5E7EB; border-left: none;
        border-radius: 0 12px 12px 0; padding: 12px 14px;
        font-size: 14px; color: #3A4A5A; outline: none; transition: all 0.2s;
    }
    .phone-number-input:focus { border-color: #87CEFA; box-shadow: 0 0 0 3px rgba(135,206,250,0.15); }

    .step-indicator {
        display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 20px;
    }
    .step-dot {
        width: 8px; height: 8px; border-radius: 50%; background: #E5E7EB; transition: all 0.3s;
    }
    .step-dot.active { background: #87CEFA; width: 24px; border-radius: 4px; }
    .step-dot.done { background: #10B981; }
</style>

<div class="min-h-screen flex items-center justify-center py-12 px-4"
    style="background: linear-gradient(135deg, #EAF3FF 0%, #fff 60%, #EAF3FF 100%);">
    <div class="w-full max-w-md">

        {{-- Logo --}}
        <div class="text-center mb-8">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2">
                <svg class="w-9 h-9 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                <span class="font-display text-3xl font-bold text-[#87CEFA]">Hotel</span>
                <span class="font-display text-3xl font-light text-[#3A4A5A]">Booking</span>
            </a>
            <p class="text-[#C9D3DD] mt-2 text-sm">Đăng nhập để tiếp tục</p>
        </div>

        <div class="bg-white rounded-2xl p-8 shadow-lg border border-[#EAF3FF]">
            <h2 class="font-display text-2xl font-bold text-[#1E3A5F] mb-5">Chào mừng trở lại</h2>

            {{-- Tabs --}}
            <div class="flex gap-1 bg-[#EAF3FF] p-1 rounded-xl mb-5">
                <button class="tab-btn active" id="tab-email" onclick="switchTab('email')">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                    Email
                </button>
                <button class="tab-btn" id="tab-phone" onclick="switchTab('phone')">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                    </svg>
                    Số điện thoại
                </button>
            </div>

            <div id="error-msg" class="hidden bg-red-50 border border-red-100 text-red-600 text-sm rounded-xl px-4 py-3 mb-4"></div>

            {{-- ===== PANEL: EMAIL ===== --}}
            <div id="panel-email">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-[#3A4A5A] mb-1.5 uppercase tracking-wide">Email</label>
                        <input id="email" type="email" placeholder="email@gmail.com" class="auth-input">
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-1.5">
                            <label class="text-xs font-semibold text-[#3A4A5A] uppercase tracking-wide">Mật khẩu</label>
                            <button onclick="openForgotModal()" type="button" class="text-xs text-[#87CEFA] hover:underline font-medium">Quên mật khẩu?</button>
                        </div>
                        <input id="password" type="password" placeholder="••••••••" class="auth-input">
                    </div>
                    <button onclick="doLogin()" id="login-btn" class="btn-sky-full">Đăng nhập</button>
                </div>
            </div>

            {{-- ===== PANEL: SĐT ===== --}}
            <div id="panel-phone" class="hidden">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-[#3A4A5A] mb-1.5 uppercase tracking-wide">Số điện thoại</label>
                        <div class="phone-wrap">
                            <div class="phone-prefix">🇻🇳 +84</div>
                            <input id="phone-number" type="tel" placeholder="354313031" class="phone-number-input">
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-1.5">
                            <label class="text-xs font-semibold text-[#3A4A5A] uppercase tracking-wide">Mật khẩu</label>
                            <button onclick="openForgotModal()" type="button" class="text-xs text-[#87CEFA] hover:underline font-medium">Quên mật khẩu?</button>
                        </div>
                        <input id="phone-password" type="password" placeholder="••••••••" class="auth-input">
                    </div>
                    <button onclick="doLoginPhone()" id="login-phone-btn" class="btn-sky-full">Đăng nhập</button>
                </div>
            </div>

            <div class="divider my-5">HOẶC</div>
            <div id="g_id_onload"
                data-client_id="829842960470-itgpqhmc91rqk4oosurbc1ufvc5el3kq.apps.googleusercontent.com"
                data-context="signin"
                data-ux_mode="popup"
                data-callback="handleGoogleLogin"
                data-auto_prompt="false">
            </div>
            <div class="g_id_signin"
                data-type="standard"
                data-shape="rectangular"
                data-theme="outline"
                data-text="signin_with"
                data-size="large"
                data-logo_alignment="left"
                style="width: 100%;">
            </div>

            <p class="text-center text-sm text-[#C9D3DD] mt-5">
                Chưa có tài khoản?
                <a href="{{ route('register') }}" class="text-[#87CEFA] font-semibold hover:underline">Đăng ký ngay</a>
            </p>
        </div>
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
                <p class="text-sm text-[#C9D3DD] mt-1">Nhập email để nhận mã OTP đặt lại</p>
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
                    class="w-full text-sm text-[#C9D3DD] hover:text-[#3A4A5A] transition py-2">
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
                <p class="text-sm text-[#C9D3DD] mt-1">
                    Đã gửi đến <strong id="otp-target-email" class="text-[#3A4A5A]"></strong>
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
            <div class="text-center text-xs text-[#C9D3DD]">
                Chờ quá lâu?
                <button id="btn-resend" onclick="resendOtp()"
                    class="hidden text-[#87CEFA] font-semibold hover:underline ml-1">
                    Gửi lại ngay
                </button>
                <span id="countdown-wrap" class="ml-1">
                    Gửi lại sau <span id="countdown-num" class="font-semibold text-[#3A4A5A]">60</span>s
                </span>
            </div>

            <button onclick="goBackStep1()" type="button"
                class="w-full text-sm text-[#C9D3DD] hover:text-[#3A4A5A] transition py-2 mt-3">
                ← Nhập lại email
            </button>
        </div>

    </div>
</div>

@endsection

@push('scripts')
<script src="https://accounts.google.com/gsi/client" async defer></script>
<script>
// ========================
// TAB SWITCH
// ========================
function switchTab(tab) {
    document.getElementById('panel-email').classList.toggle('hidden', tab !== 'email');
    document.getElementById('panel-phone').classList.toggle('hidden', tab !== 'phone');
    document.getElementById('tab-email').classList.toggle('active', tab === 'email');
    document.getElementById('tab-phone').classList.toggle('active', tab === 'phone');
    document.getElementById('error-msg').classList.add('hidden');
}

function showError(msg) {
    const el = document.getElementById('error-msg');
    el.textContent = msg;
    el.classList.remove('hidden');
}

// ========================
// ĐĂNG NHẬP EMAIL
// ========================
async function doLogin() {
    const email    = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const btn      = document.getElementById('login-btn');
    document.getElementById('error-msg').classList.add('hidden');

    if (!email || !password) { showError('Vui lòng nhập đầy đủ!'); return; }

    btn.textContent = 'Đang đăng nhập...';
    btn.disabled    = true;

    const res  = await fetch('/api/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ email, password })
    });
    const data = await res.json();

    if (res.ok) {
        localStorage.setItem('token', data.token);
        localStorage.setItem('user', JSON.stringify(data.user));
        showToast('👋 Chào mừng trở lại, ' + data.user.name + '!');
        setTimeout(() => window.location.href = '{{ route("home") }}', 1200);
    } else {
        showError(data.message || 'Email hoặc mật khẩu không đúng!');
        btn.textContent = 'Đăng nhập';
        btn.disabled    = false;
    }
}

// ========================
// ĐĂNG NHẬP SĐT + PASSWORD
// ========================
async function doLoginPhone() {
    const phone    = document.getElementById('phone-number').value.trim();
    const password = document.getElementById('phone-password').value;
    const btn      = document.getElementById('login-phone-btn');
    document.getElementById('error-msg').classList.add('hidden');

    if (!phone || !password) { showError('Vui lòng nhập đầy đủ!'); return; }

    btn.textContent = 'Đang đăng nhập...';
    btn.disabled    = true;

    const res  = await fetch('/api/auth/login-phone', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ phone: '0' + phone.replace(/^0+/, ''), password })
    });
    const data = await res.json();

    if (res.ok) {
        localStorage.setItem('token', data.token);
        localStorage.setItem('user', JSON.stringify(data.user));
        showToast('👋 Chào mừng trở lại, ' + data.user.name + '!');
        setTimeout(() => window.location.href = '{{ route("home") }}', 1200);
    } else {
        showError(data.message || 'Số điện thoại hoặc mật khẩu không đúng!');
        btn.textContent = 'Đăng nhập';
        btn.disabled    = false;
    }
}

document.addEventListener('keypress', e => {
    if (e.key !== 'Enter') return;
    if (document.getElementById('tab-email').classList.contains('active')) doLogin();
    else doLoginPhone();
});

async function handleGoogleLogin(response) {
    const errDiv = document.getElementById('error-msg');
    errDiv.classList.add('hidden');

    // Gọi lên API của Tí vừa tạo ở Bước 2
    const res = await fetch('/api/auth/google', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json', 
            'Accept': 'application/json' 
        },
        body: JSON.stringify({ credential: response.credential })
    });
    
    const data = await res.json();

    if (res.ok) {
        localStorage.setItem('token', data.token);
        localStorage.setItem('user', JSON.stringify(data.user));
        showToast('👋 Chào mừng, ' + data.user.name + '!');
        setTimeout(() => window.location.href = '{{ route("home") }}', 1200);
    } else {
        showError(data.message || 'Đăng nhập bằng Google thất bại!');
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

    if (!email) { err.textContent = 'Vui lòng nhập email!'; err.classList.remove('hidden'); return; }

    btn.textContent = 'Đang gửi OTP...';
    btn.disabled    = true;

    const res  = await fetch('/api/forgot-password-otp', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ email })
    });
    const data = await res.json();

    btn.textContent = 'Gửi mã OTP';
    btn.disabled    = false;

    if (res.ok) {
        document.getElementById('otp-target-email').textContent = email;
        goToStep(2);
        startCountdown(60);
        setTimeout(() => document.getElementById('oi-0').focus(), 100);
    } else {
        err.textContent = data.message || 'Email không tồn tại!';
        err.classList.remove('hidden');
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

    if (otp.length < 6) { err.textContent = 'Vui lòng nhập đủ 6 số!'; err.classList.remove('hidden'); return; }

    btn.textContent = 'Đang xác nhận...';
    btn.disabled    = true;

    const res  = await fetch('/api/forgot-password-verify-otp', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ email, otp })
    });
    const data = await res.json();

    btn.textContent = 'Xác nhận OTP';
    btn.disabled    = false;

    if (res.ok) {
        // OTP đúng → redirect sang trang đặt lại mật khẩu
        if (otpTimer) clearInterval(otpTimer);
        showToast('✅ OTP hợp lệ! Đang chuyển hướng...');
        setTimeout(() => {
            window.location.href = '/reset-password/' + data.reset_token + '?email=' + encodeURIComponent(email);
        }, 800);
    } else {
        err.textContent = data.message || 'Mã OTP không đúng!';
        err.classList.remove('hidden');
        clearOtp();
        document.getElementById('oi-0').focus();
    }
}

// Resend OTP
async function resendOtp() {
    clearOtp();
    document.getElementById('err-step-2').classList.add('hidden');
    document.getElementById('oi-0').focus();
    // Gọi lại API gửi OTP
    const email = document.getElementById('forgot-email').value.trim();
    await fetch('/api/forgot-password-otp', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ email })
    });
    showToast('📧 Đã gửi lại mã OTP!');
    startCountdown(60);
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
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeForgotModal(); });
</script>
@endpush