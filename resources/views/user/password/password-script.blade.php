<script>
/* ============================================================
    password-script.blade.php
    Xử lý đổi mật khẩu + thanh đo độ mạnh mật khẩu
    Dùng: api(), showToast() từ user.blade.php
============================================================ */

// ---- Hiển thị/ẩn mật khẩu ----
function togglePw(inputId, iconEl) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        iconEl.classList.remove('fa-eye');
        iconEl.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        iconEl.classList.remove('fa-eye-slash');
        iconEl.classList.add('fa-eye');
    }
}

// ---- Alert inline ----
function _showAlert(prefix, type, msg) {
    const el = document.getElementById(prefix + (type === 'success' ? 'Success' : 'Error'));
    const msg_el = document.getElementById(prefix + (type === 'success' ? 'SuccessMsg' : 'ErrorMsg'));
    if (!el) return;
    el.classList.remove('show');
    void el.offsetWidth;
    if (msg_el) msg_el.textContent = msg;
    el.classList.add('show');
}

function _clearAlerts(prefix) {
    ['Success', 'Error'].forEach(t => {
        const el = document.getElementById(prefix + t);
        if (el) el.classList.remove('show');
    });
}

// ---- Độ mạnh mật khẩu ----
function checkPasswordStrength(password) {
    let score = 0;
    if (password.length >= 8) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;
    return score;
}

function updateStrengthBar(password) {
    const segments = document.querySelectorAll('.strength-segment');
    const strengthText = document.getElementById('strengthText');
    if (!segments.length || !strengthText) return;

    const score = checkPasswordStrength(password);
    // Xóa tất cả class active cũ
    segments.forEach(seg => {
        seg.classList.remove('active-1', 'active-2', 'active-3', 'active-4');
    });
    // Set màu cho các đoạn tương ứng
    for (let i = 0; i < segments.length; i++) {
        if (i < score) {
            segments[i].classList.add(`active-${score}`);
        } else {
            segments[i].style.background = '#e2e8f0';
        }
    }
    // Cập nhật label
    if (score <= 1) strengthText.textContent = 'Yếu';
    else if (score === 2) strengthText.textContent = 'Trung bình';
    else if (score === 3) strengthText.textContent = 'Khá mạnh';
    else strengthText.textContent = 'Mạnh';

    // Đảm bảo màu cho từng thanh active (fix hiển thị)
    for (let i = 0; i < score; i++) {
        if (score === 1) segments[i].style.background = '#EF4444';
        if (score === 2) segments[i].style.background = '#F97316';
        if (score === 3) segments[i].style.background = '#EAB308';
        if (score === 4) segments[i].style.background = '#22C55E';
    }
}

// ---- API: Đổi mật khẩu ----
async function savePassword() {
    const current = document.getElementById('pwCurrent').value;
    const newPw = document.getElementById('pwNew').value;
    const confirm = document.getElementById('pwConfirm').value;
    const btn = document.getElementById('btnSavePw');

    _clearAlerts('pw');

    if (!current || !newPw || !confirm) {
        _showAlert('pw', 'error', 'Vui lòng nhập đầy đủ thông tin!');
        return;
    }
    if (newPw.length < 6) {
        _showAlert('pw', 'error', 'Mật khẩu mới tối thiểu 6 ký tự!');
        return;
    }
    if (newPw !== confirm) {
        _showAlert('pw', 'error', 'Mật khẩu xác nhận không khớp!');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang cập nhật...';

    try {
        const res = await api('/me/password', {
            method: 'POST',
            body: JSON.stringify({
                current_password: current,
                new_password: newPw,
                new_password_confirmation: confirm
            })
        });
        const data = await res.json();

        if (res.ok) {
            document.getElementById('pwCurrent').value = '';
            document.getElementById('pwNew').value = '';
            document.getElementById('pwConfirm').value = '';
            // Reset strength bars
            updateStrengthBar('');
            _showAlert('pw', 'success', data.message || 'Đổi mật khẩu thành công!');
            showToast('Đổi mật khẩu thành công!', 'success');
        } else {
            _showAlert('pw', 'error', data.message || 'Đổi mật khẩu thất bại!');
            showToast(data.message || 'Đổi mật khẩu thất bại', 'error');
        }
    } catch (e) {
        _showAlert('pw', 'error', 'Lỗi kết nối, vui lòng thử lại!');
        showToast('Lỗi kết nối', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-lock"></i> Cập nhật mật khẩu';
    }
}

// ---- Khởi tạo lắng nghe sự kiện cho strength meter ----
document.addEventListener('DOMContentLoaded', function() {
    const pwNew = document.getElementById('pwNew');
    if (pwNew) {
        pwNew.addEventListener('input', function() {
            updateStrengthBar(this.value);
        });
        // Gọi lần đầu để hiển thị mặc định (Yếu)
        updateStrengthBar(pwNew.value);
    }
});
</script>