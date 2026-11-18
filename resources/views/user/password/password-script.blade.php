<script>
let passwordSubmitting = false;

function checkPasswordStrength(password) {
    let score = 0;
    if (password.length >= 8) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;
    return score;
}

function updateStrengthBar(password) {
    const score = checkPasswordStrength(password);
    document.querySelectorAll('.strength-segment').forEach((segment, index) => segment.classList.toggle('active', index < score));
    document.getElementById('strengthText').textContent = !password ? 'Chưa nhập' : ['Yếu', 'Yếu', 'Trung bình', 'Khá mạnh', 'Mạnh'][score];
}

function clearPasswordErrors() {
    ['pwCurrent', 'pwNew'].forEach(id => {
        const input = document.getElementById(id);
        input.removeAttribute('aria-invalid');
        document.getElementById(id + 'Error').textContent = '';
    });
    _clearAlerts('pw');
}

function setPasswordError(inputId, message) {
    const input = document.getElementById(inputId);
    input.setAttribute('aria-invalid', 'true');
    document.getElementById(inputId + 'Error').textContent = message;
}

function validatePasswordForm(current, newPassword) {
    let firstInvalid = null;
    const invalid = (id, message) => {
        setPasswordError(id, message);
        if (!firstInvalid) firstInvalid = document.getElementById(id);
    };

    if (!current) invalid('pwCurrent', 'Vui lòng nhập mật khẩu hiện tại.');
    else if (current.length > 4096) invalid('pwCurrent', 'Mật khẩu hiện tại không hợp lệ.');
    if (!newPassword) invalid('pwNew', 'Vui lòng nhập mật khẩu mới.');
    else if (newPassword.length < 8) invalid('pwNew', 'Mật khẩu mới phải có ít nhất 8 ký tự.');
    else if (newPassword.length > 72) invalid('pwNew', 'Mật khẩu mới không được vượt quá 72 ký tự.');
    else if (newPassword === current) invalid('pwNew', 'Mật khẩu mới phải khác mật khẩu hiện tại.');

    firstInvalid?.focus();
    return !firstInvalid;
}

function applyPasswordApiErrors(data) {
    const errors = data?.errors || {};
    const mapping = {current_password: 'pwCurrent', new_password: 'pwNew', new_password_confirmation: 'pwNew'};
    let firstInvalid = null;
    Object.entries(mapping).forEach(([field, inputId]) => {
        const message = Array.isArray(errors[field]) ? errors[field][0] : errors[field];
        if (message) {
            setPasswordError(inputId, message);
            if (!firstInvalid) firstInvalid = document.getElementById(inputId);
        }
    });
    if (!firstInvalid && typeof data?.message === 'string' && data.message.toLowerCase().includes('mật khẩu hiện tại')) {
        setPasswordError('pwCurrent', data.message);
        firstInvalid = document.getElementById('pwCurrent');
    }
    firstInvalid?.focus();
    return Boolean(firstInvalid);
}

async function savePassword() {
    if (passwordSubmitting) return;
    const current = document.getElementById('pwCurrent').value;
    const newPassword = document.getElementById('pwNew').value;
    const form = document.getElementById('passwordForm');
    const button = document.getElementById('btnSavePw');

    clearPasswordErrors();
    if (!validatePasswordForm(current, newPassword)) {
        _showAlert('pw', 'error', 'Vui lòng kiểm tra lại các trường được đánh dấu.');
        return;
    }

    passwordSubmitting = true;
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    form.setAttribute('aria-busy', 'true');
    button.textContent = 'Đang cập nhật…';

    try {
        const response = await api('/me/password', {
            method: 'POST',
            body: JSON.stringify({
                current_password: current,
                new_password: newPassword,
                new_password_confirmation: newPassword
            })
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            applyPasswordApiErrors(data);
            const message = accountErrorMessage(data, 'Chưa thể cập nhật mật khẩu.');
            _showAlert('pw', 'error', message);
            showToast(message, 'error');
            return;
        }

        ['pwCurrent', 'pwNew'].forEach(id => { document.getElementById(id).value = ''; });
        updateStrengthBar('');
        _showAlert('pw', 'success', data.message || 'Đổi mật khẩu thành công!');
        showToast('Đổi mật khẩu thành công!', 'success');
    } catch (error) {
        const message = 'Lỗi kết nối. Dữ liệu đã nhập được giữ lại để bạn thử lại.';
        _showAlert('pw', 'error', message);
        showToast(message, 'error');
    } finally {
        passwordSubmitting = false;
        button.disabled = false;
        button.removeAttribute('aria-busy');
        form.removeAttribute('aria-busy');
        button.innerHTML = 'Cập nhật mật khẩu ' + accountIcon('arrow-right');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.getElementById('pwNew');
    newPassword.addEventListener('input', function() { updateStrengthBar(this.value); });
    ['pwCurrent', 'pwNew'].forEach(id => {
        document.getElementById(id).addEventListener('input', function() {
            this.removeAttribute('aria-invalid');
            document.getElementById(id + 'Error').textContent = '';
        });
    });
    updateStrengthBar(newPassword.value);
});
</script>
