@extends('layout')

@section('title', 'Đăng ký')

@section('content')

<style>
    .auth-input {
        width: 100%;
        border: 1.5px solid #E5E7EB;
        border-radius: 12px;
        padding: 12px 16px;
        font-size: 14px;
        color: #3A4A5A;
        transition: all 0.2s ease;
        outline: none;
    }
    .auth-input:focus {
        border-color: #87CEFA;
        box-shadow: 0 0 0 3px rgba(135,206,250,0.15);
    }
    .btn-sky-full {
        width: 100%;
        background: #87CEFA;
        color: #1E3A5F;
        font-weight: 700;
        padding: 13px;
        border-radius: 12px;
        font-size: 14px;
        border: none;
        cursor: pointer;
        transition: all 0.25s ease;
    }
    .btn-sky-full:hover {
        background: #7BC4F5;
        transform: translateY(-1px);
        box-shadow: 0 8px 20px -4px rgba(135,206,250,0.5);
    }
    .btn-sky-full:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

    .btn-social {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 12px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1.5px solid #E5E7EB;
        background: white;
        color: #3A4A5A;
    }
    .btn-social:hover {
        border-color: #87CEFA;
        background: #EAF3FF;
        transform: translateY(-1px);
    }

    .divider {
        display: flex;
        align-items: center;
        gap: 12px;
        color: #C9D3DD;
        font-size: 12px;
    }
    .divider::before, .divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: #E5E7EB;
    }

    .strength-bar {
        height: 4px;
        border-radius: 2px;
        transition: all 0.3s ease;
        flex: 1;
    }
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
            <p class="text-[#C9D3DD] mt-2 text-sm">Tạo tài khoản miễn phí</p>
        </div>

        {{-- Card --}}
        <div class="bg-white rounded-2xl p-8 shadow-lg border border-[#EAF3FF]">
            <h2 class="font-display text-2xl font-bold text-[#1E3A5F] mb-6">Đăng ký tài khoản</h2>

            <div id="g_id_onload"
                data-client_id="829842960470-itgpqhmc91rqk4oosurbc1ufvc5el3kq.apps.googleusercontent.com"
                data-context="signup"
                data-ux_mode="popup"
                data-callback="handleGoogleLogin"
                data-auto_prompt="false">
            </div>
            <div class="g_id_signin"
                data-type="standard"
                data-shape="rectangular"
                data-theme="outline"
                data-text="signup_with"
                data-size="large"
                data-logo_alignment="left"
                style="width: 100%;">
            </div>

            <div class="divider mb-4">HOẶC ĐĂNG KÝ BẰNG EMAIL</div>

            <div id="error-msg" class="hidden bg-red-50 border border-red-100 text-red-600 text-sm rounded-xl px-4 py-3 mb-4"></div>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-[#3A4A5A] mb-1.5 uppercase tracking-wide">Họ tên <span class="text-red-400">*</span></label>
                    <input id="name" type="text" placeholder="Nguyễn Văn A" class="auth-input">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-[#3A4A5A] mb-1.5 uppercase tracking-wide">Email <span class="text-red-400">*</span></label>
                    <input id="email" type="email" placeholder="email@gmail.com" class="auth-input">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-[#3A4A5A] mb-1.5 uppercase tracking-wide">Số điện thoại</label>
                    <input id="phone" type="tel" placeholder="0901234567" class="auth-input">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-[#3A4A5A] mb-1.5 uppercase tracking-wide">Mật khẩu <span class="text-red-400">*</span></label>
                    <input id="password" type="password" placeholder="Tối thiểu 6 ký tự" class="auth-input" oninput="checkStrength(this.value)">
                    {{-- Password strength --}}
                    <div class="flex gap-1 mt-2" id="strength-bars">
                        <div class="strength-bar bg-[#E5E7EB]" id="bar-1"></div>
                        <div class="strength-bar bg-[#E5E7EB]" id="bar-2"></div>
                        <div class="strength-bar bg-[#E5E7EB]" id="bar-3"></div>
                        <div class="strength-bar bg-[#E5E7EB]" id="bar-4"></div>
                    </div>
                    <p class="text-xs text-[#C9D3DD] mt-1" id="strength-label"></p>
                </div>

                <button onclick="doRegister()" id="register-btn" class="btn-sky-full mt-2">
                    Tạo tài khoản
                </button>
            </div>

            <p class="text-center text-xs text-[#C9D3DD] mt-4">
                Bằng cách đăng ký, bạn đồng ý với
                <a href="#" class="text-[#87CEFA] hover:underline">Điều khoản</a> &
                <a href="#" class="text-[#87CEFA] hover:underline">Chính sách bảo mật</a>
            </p>

            <p class="text-center text-sm text-[#C9D3DD] mt-5">
                Đã có tài khoản?
                <a href="{{ route('login') }}" class="text-[#87CEFA] font-semibold hover:underline">Đăng nhập</a>
            </p>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://accounts.google.com/gsi/client" async defer></script>
<script>
function checkStrength(password) {
    const bars   = [1,2,3,4].map(i => document.getElementById('bar-' + i));
    const label  = document.getElementById('strength-label');
    const colors = { 1: '#EF4444', 2: '#F59E0B', 3: '#87CEFA', 4: '#10B981' };
    const labels = { 0: '', 1: 'Yếu', 2: 'Trung bình', 3: 'Khá mạnh', 4: 'Mạnh' };

    let score = 0;
    if (password.length >= 6)  score++;
    if (password.length >= 10) score++;
    if (/[A-Z]/.test(password) || /[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;

    bars.forEach((bar, i) => {
        bar.style.background = i < score ? colors[score] : '#E5E7EB';
    });
    label.textContent = labels[score];
    label.style.color = colors[score] || '#C9D3DD';
}

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

async function doRegister() {
    const name     = document.getElementById('name').value.trim();
    const email    = document.getElementById('email').value.trim();
    const phone    = document.getElementById('phone').value.trim();
    const password = document.getElementById('password').value;
    const btn      = document.getElementById('register-btn');
    const errDiv   = document.getElementById('error-msg');

    errDiv.classList.add('hidden');

    if (!name || !email || !password) {
        errDiv.textContent = 'Vui lòng nhập đầy đủ thông tin bắt buộc!';
        errDiv.classList.remove('hidden');
        return;
    }
    if (password.length < 6) {
        errDiv.textContent = 'Mật khẩu phải có ít nhất 6 ký tự!';
        errDiv.classList.remove('hidden');
        return;
    }

    btn.textContent = 'Đang tạo tài khoản...';
    btn.disabled    = true;

    const res  = await fetch('/api/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ name, email, phone, password })
    });
    const data = await res.json();

    if (res.ok) {
        localStorage.setItem('token', data.token);
        localStorage.setItem('user', JSON.stringify(data.user));
        showToast('🎉 Chào mừng, ' + data.user.name + '! Tài khoản của bạn đã được tạo thành công.');
        setTimeout(() => {
            window.location.href = '{{ route("home") }}';
        }, 1200);
    } else {
        const errors = data.errors
            ? Object.values(data.errors).flat().join(', ')
            : (data.message || 'Đăng ký thất bại!');
        errDiv.textContent = errors;
        errDiv.classList.remove('hidden');
        btn.textContent = 'Tạo tài khoản';
        btn.disabled    = false;
    }
}
</script>
@endpush