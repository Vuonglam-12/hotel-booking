{{-- resources/views/profile-script.blade.php --}}
<script>
/* ============================================================
   profile-script.blade.php
   Dùng: api(), showToast(), showConfirmModal() từ user.blade.php
============================================================ */

/* ============================================================
   LOAD PROFILE — gọi /api/me và điền vào form + hero + stats
============================================================ */
let profileSupportsDob = false;
let profileConfirmedAvatarUrl = '';
let profilePreviewObjectUrl = null;
async function loadProfile() {
    const state = document.getElementById('profileLoadState');
    state.className = ''; state.innerHTML = accountLoading('Đang tải hồ sơ…'); state.hidden = false;
    document.getElementById('btnSaveProfile').disabled = true;
    try {
        const res  = await api('/me');
        const data = await res.json();

        if (!res.ok) {
            if (res.status === 401) { window.location.href = '/login'; return; }
            throw new Error('Không thể tải hồ sơ.');
        }

        // Điền form
        document.getElementById('profileName').value    = data.name    || '';
        document.getElementById('profileEmail').value   = data.email   || '';
        document.getElementById('profilePhone').value   = data.phone   || '';
        profileSupportsDob = data.profile_fields?.dob === true;
        document.getElementById('profileDob').disabled = !profileSupportsDob;
        document.getElementById('profileDob').value = profileSupportsDob ? (data.dob || '') : '';

        // Account summary
        document.getElementById('profileAccountName').textContent = data.name || 'Tài khoản Angel';
        document.getElementById('profileAccountEmail').textContent = data.email || 'Chưa có email đăng nhập';
        profileConfirmedAvatarUrl = data.avatar_url || '';
        _renderProfileAvatar(data.avatar_url, data.name);

        // Booking count is shown only when the API returns a real non-negative integer.
        const bookingCount = Number(data.bookings);
        document.getElementById('stat-bookings').textContent = Number.isSafeInteger(bookingCount) && bookingCount >= 0 ? bookingCount : '—';

        // Sync localStorage
        let stored = {};
        try { stored = JSON.parse(localStorage.getItem('user') || '{}'); } catch {}
        localStorage.setItem('user', JSON.stringify({ ...stored, ...data }));
        loadUserUI();
        state.hidden = true; document.getElementById('profileContent').hidden = false; document.getElementById('btnSaveProfile').disabled = false;
    } catch (e) {
        document.getElementById('profileContent').hidden = true;
        state.innerHTML = '<div class="empty-state error-state">' + accountIcon('circle-alert') + '<h2>Chưa thể tải hồ sơ</h2><p>Vui lòng kiểm tra kết nối và thử lại.</p><button type="button" class="button secondary" onclick="loadProfile()">Thử lại</button></div>';
    }
}

/* ============================================================
   RENDER AVATAR trong profile page
============================================================ */
function _renderProfileAvatar(avatarUrl, name) {
    const img     = document.getElementById('profileAvatarImg');
    const initial = document.getElementById('profileAvatarInitial');
    const letter  = (name || '?').charAt(0).toUpperCase();

    if (avatarUrl && avatarUrl !== '') {
        img.src = avatarUrl;
        img.style.display = 'block';
        initial.style.display = 'none';

        img.onerror = () => {
            img.removeAttribute('src');
            img.style.display = 'none';
            initial.textContent = letter;
            initial.style.display = 'flex';
        };
    } else {
        img.removeAttribute('src');
        initial.textContent = letter;
        initial.style.display = 'flex';
        img.style.display = 'none';
    }
}

/* ============================================================
   SAVE PROFILE — PUT /api/me
============================================================ */
async function saveProfile() {
    const name    = document.getElementById('profileName').value.trim();
    const phone   = document.getElementById('profilePhone').value.trim();
    const dob     = document.getElementById('profileDob').value;
    const btn     = document.getElementById('btnSaveProfile');

    _clearAlerts('profile');

    if (!name) { _showAlert('profile', 'error', 'Vui lòng nhập họ và tên!'); return; }
    if (profileSupportsDob && !document.getElementById('profileDob').validity.valid) {
        _showAlert('profile', 'error', 'Ngày sinh phải hợp lệ và không sau ngày hiện tại.'); return;
    }

    btn.disabled = true;
    btn.textContent = 'Đang lưu…';

    try {
        const res  = await api('/me', {
            method: 'PUT',
            body: JSON.stringify({ name, phone, ...(profileSupportsDob ? { dob } : {}) })
        });
        const data = await res.json();

        if (res.ok) {
            // Cập nhật localStorage
            const stored = JSON.parse(localStorage.getItem('user') || '{}');
            const updated = { ...stored, name, phone, ...(profileSupportsDob ? { dob } : {}) };
            if (data.user) Object.assign(updated, data.user);
            localStorage.setItem('user', JSON.stringify(updated));

            // Cập nhật sidebar + topbar (hàm từ user.blade.php)
            if (typeof loadUserUI === 'function') loadUserUI();

            document.getElementById('profileAccountName').textContent = name;
            _renderProfileAvatar(updated.avatar_url, name);

            _showAlert('profile', 'success', data.message || 'Cập nhật thành công!');
            showToast('Lưu thành công!', 'success');
        } else {
            _showAlert('profile', 'error', accountErrorMessage(data, 'Cập nhật thất bại!'));
            showToast(data.message || 'Cập nhật thất bại', 'error');
        }
    } catch (e) {
        _showAlert('profile', 'error', 'Lỗi kết nối, vui lòng thử lại!');
        showToast('Lỗi kết nối', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Lưu thay đổi ' + accountIcon('arrow-right');
    }
}

/* ============================================================
   AVATAR UPLOAD — POST /api/me/avatar
============================================================ */
async function handleAvatarUpload(input) {
    const file = input.files[0];
    if (!file) return;

    if (file.size > 2 * 1024 * 1024) {
        input.value = '';
        showToast('Ảnh đại diện không được vượt quá 2 MB.', 'error'); return;
    }
    if (!['image/jpeg','image/png','image/webp'].includes(file.type)) {
        input.value = '';
        showToast('Chỉ chấp nhận ảnh JPG, PNG hoặc WebP.', 'error'); return;
    }

    const uploadButton = document.getElementById('avatarUploadButton');
    const status = document.getElementById('profileAvatarStatus');
    let stored = {};
    try { stored = JSON.parse(localStorage.getItem('user') || '{}'); } catch {}
    const fallbackName = document.getElementById('profileName').value || stored.name || '';

    if (profilePreviewObjectUrl) URL.revokeObjectURL(profilePreviewObjectUrl);
    profilePreviewObjectUrl = URL.createObjectURL(file);
    _renderProfileAvatar(profilePreviewObjectUrl, fallbackName);
    uploadButton.disabled = true;
    uploadButton.setAttribute('aria-busy', 'true');
    uploadButton.textContent = 'Đang tải ảnh…';
    status.className = 'profile-avatar-status';
    status.textContent = 'Đang tải ảnh đại diện lên Angel…';

    // Upload lên server
    const formData = new FormData();
    formData.append('avatar', file);

    try {
        const res = await fetch('/api/me/avatar', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('token'),
                'Accept': 'application/json'
            },
            body: formData
        });
        const data = await res.json();

        if (res.ok && data.avatar_url) {
            stored.avatar_url = data.avatar_url;
            localStorage.setItem('user', JSON.stringify(stored));
            profileConfirmedAvatarUrl = data.avatar_url;
            _renderProfileAvatar(data.avatar_url, stored.name);
            if (typeof loadUserUI === 'function') loadUserUI();
            status.classList.add('success');
            status.textContent = 'Ảnh đại diện đã được cập nhật.';
            showToast('Cập nhật ảnh thành công!', 'success');
        } else {
            throw new Error(accountErrorMessage(data, 'Chưa thể cập nhật ảnh đại diện.'));
        }
    } catch (e) {
        _renderProfileAvatar(profileConfirmedAvatarUrl, fallbackName);
        status.classList.add('error');
        status.textContent = 'Ảnh xem trước đã được hoàn tác vì tải lên thất bại.';
        showToast(e instanceof TypeError ? 'Lỗi kết nối khi tải ảnh. Vui lòng thử lại.' : e.message, 'error');
    } finally {
        if (profilePreviewObjectUrl) URL.revokeObjectURL(profilePreviewObjectUrl);
        profilePreviewObjectUrl = null;
        uploadButton.disabled = false;
        uploadButton.removeAttribute('aria-busy');
        uploadButton.innerHTML = accountIcon('camera') + ' Thay ảnh đại diện';
        input.value = '';
    }
}

document.addEventListener('DOMContentLoaded', loadProfile);
</script>
