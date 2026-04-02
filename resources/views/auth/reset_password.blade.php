@extends('layout')

@section('title', 'Đặt lại mật khẩu')

@section('content')
<div class="min-h-screen flex items-center justify-center" style="background: #EAF3FF;">
    <div class="bg-white rounded-2xl shadow-sm border border-[#C9D3DD]/30 p-8 w-full max-w-md">

        <div class="text-center mb-6">
            <div class="w-14 h-14 rounded-full bg-[#EAF3FF] flex items-center justify-center mx-auto mb-3">
                <svg class="w-7 h-7 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-[#1E3A5F]">Đặt lại mật khẩu</h1>
            <p class="text-sm text-[#C9D3DD] mt-1">Nhập mật khẩu mới cho tài khoản của bạn</p>
        </div>

        <div id="error-msg" class="hidden bg-red-50 text-red-700 text-sm rounded-xl px-4 py-3 mb-4"></div>
        <div id="success-msg" class="hidden bg-green-50 text-green-700 text-sm rounded-xl px-4 py-3 mb-4"></div>

        <div class="space-y-4">
            <div>
                <label class="text-xs font-medium text-[#3A4A5A] block mb-1">Mật khẩu mới</label>
                <input type="password" id="password" placeholder="Tối thiểu 6 ký tự"
                    class="w-full border border-[#C9D3DD] rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#87CEFA]">
            </div>
            <div>
                <label class="text-xs font-medium text-[#3A4A5A] block mb-1">Xác nhận mật khẩu</label>
                <input type="password" id="password_confirmation" placeholder="Nhập lại mật khẩu mới"
                    class="w-full border border-[#C9D3DD] rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#87CEFA]">
            </div>

            <button onclick="submitReset()" id="submit-btn"
                class="w-full py-3 rounded-xl text-sm font-semibold text-[#1E3A5F] transition"
                style="background: #87CEFA;">
                Đặt lại mật khẩu
            </button>

            <p class="text-center text-sm text-[#C9D3DD]">
                Nhớ mật khẩu rồi?
                <a href="/login" class="text-[#87CEFA] font-semibold hover:underline">Đăng nhập</a>
            </p>
        </div>
    </div>
</div>

<script>
    // Lấy token và email từ URL
    const token = '{{ $token }}';
    const email = '{{ $email }}';

    async function submitReset() {
        const password             = document.getElementById('password').value;
        const passwordConfirmation = document.getElementById('password_confirmation').value;
        const errDiv               = document.getElementById('error-msg');
        const succDiv              = document.getElementById('success-msg');
        const btn                  = document.getElementById('submit-btn');

        errDiv.classList.add('hidden');
        succDiv.classList.add('hidden');

        if (!password || password.length < 6) {
            errDiv.textContent = 'Mật khẩu phải có ít nhất 6 ký tự!';
            errDiv.classList.remove('hidden');
            return;
        }
        if (password !== passwordConfirmation) {
            errDiv.textContent = 'Mật khẩu xác nhận không khớp!';
            errDiv.classList.remove('hidden');
            return;
        }

        btn.textContent = 'Đang xử lý...';
        btn.disabled    = true;

        const res = await fetch('/api/reset-password', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
            },
            body: JSON.stringify({
                email:                 email,
                token:                 token,
                password:              password,
                password_confirmation: passwordConfirmation,
            })
        });

        const data = await res.json();

        if (res.ok) {
            succDiv.textContent = data.message + ' Đang chuyển hướng...';
            succDiv.classList.remove('hidden');
            // Chuyển về trang login sau 2 giây
            setTimeout(() => { window.location.href = '/login'; }, 2000);
        } else {
            errDiv.textContent = data.message || 'Đặt lại mật khẩu thất bại!';
            errDiv.classList.remove('hidden');
            btn.textContent = 'Đặt lại mật khẩu';
            btn.disabled    = false;
        }
    }
</script>
@endsection