{{-- resources/views/profile-script.blade.php --}}
<script>
/* ============================================================
   profile-script.blade.php
   Dùng: api(), showToast(), showConfirmModal() từ user.blade.php
============================================================ */

/* ============================================================
   LOAD PROFILE — gọi /api/me và điền vào form + hero + stats
============================================================ */
async function loadProfile() {
    try {
        const res  = await api('/me');
        const data = await res.json();

        if (!res.ok) {
            if (res.status === 401) { window.location.href = '/login'; return; }
            showToast('Không thể tải thông tin!', 'error'); return;
        }

        // Điền form
        document.getElementById('profileName').value    = data.name    || '';
        document.getElementById('profileEmail').value   = data.email   || '';
        document.getElementById('profilePhone').value   = data.phone   || '';
        document.getElementById('profileDob').value     = data.dob     || '';

        // Hero info
        document.getElementById('heroDisplayName').innerText = data.name || 'Người dùng';
        document.getElementById('heroPhone').innerText       = data.phone || '---';
        document.getElementById('heroEmail').innerText       = data.email || '---';
        if (data.created_at) {
            const d = new Date(data.created_at);
            if (!isNaN(d.getTime())) {
                document.getElementById('heroMemberSince').innerText = `${d.getMonth()+1}/${d.getFullYear()}`;
            }
        }

        // Avatar
        _renderProfileAvatar(data.avatar_url, data.name);

        // Stats
        if (data.points   !== undefined) document.getElementById('stat-points').innerText   = data.points;
        if (data.bookings !== undefined) document.getElementById('stat-bookings').innerText = data.bookings;
        if (data.vouchers !== undefined) document.getElementById('stat-vouchers').innerText = data.vouchers;

        // Sync localStorage
        const stored = JSON.parse(localStorage.getItem('user') || '{}');
        localStorage.setItem('user', JSON.stringify({ ...stored, ...data }));

    } catch (e) {
        showToast('Lỗi kết nối!', 'error');
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

        // Thêm: nếu ảnh load lỗi thì fallback về initial
        img.onerror = () => {
            img.style.display = 'none';
            initial.textContent = letter;
            initial.style.display = 'flex';
            console.warn('[Avatar] Broken URL:', avatarUrl)};
    } else {
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

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang lưu...';

    try {
        const res  = await api('/me', {
            method: 'PUT',
            body: JSON.stringify({ name, phone, dob})
        });
        const data = await res.json();

        if (res.ok) {
            // Cập nhật localStorage
            const stored = JSON.parse(localStorage.getItem('user') || '{}');
            const updated = { ...stored, name, phone, dob};
            if (data.user) Object.assign(updated, data.user);
            localStorage.setItem('user', JSON.stringify(updated));

            // Cập nhật sidebar + topbar (hàm từ user.blade.php)
            if (typeof loadUserUI === 'function') loadUserUI();

            // Reload lại hero info
            document.getElementById('heroDisplayName').innerText = name;
            document.getElementById('heroPhone').innerText = phone || '---';
            _renderProfileAvatar(updated.avatar_url, name);

            _showAlert('profile', 'success', data.message || 'Cập nhật thành công!');
            showToast('Lưu thành công!', 'success');
        } else {
            _showAlert('profile', 'error', data.message || 'Cập nhật thất bại!');
            showToast(data.message || 'Cập nhật thất bại', 'error');
        }
    } catch (e) {
        _showAlert('profile', 'error', 'Lỗi kết nối, vui lòng thử lại!');
        showToast('Lỗi kết nối', 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Lưu thay đổi';
    }
}

/* ============================================================
   SAVE PASSWORD — POST /api/me/password
============================================================ */
async function savePassword() {
    const current = document.getElementById('pwCurrent').value;
    const newPw   = document.getElementById('pwNew').value;
    const confirm = document.getElementById('pwConfirm').value;
    const btn     = document.getElementById('btnSavePw');

    _clearAlerts('pw');

    if (!current || !newPw || !confirm) {
        _showAlert('pw', 'error', 'Vui lòng nhập đầy đủ thông tin!'); return;
    }
    if (newPw.length < 6) {
        _showAlert('pw', 'error', 'Mật khẩu mới tối thiểu 6 ký tự!'); return;
    }
    if (newPw !== confirm) {
        _showAlert('pw', 'error', 'Mật khẩu xác nhận không khớp!'); return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Đang cập nhật...';

    try {
        const res  = await api('/me/password', {
            method: 'POST',
            body: JSON.stringify({
                current_password:      current,
                new_password:          newPw,
                new_password_confirmation: confirm
            })
        });
        const data = await res.json();

        if (res.ok) {
            document.getElementById('pwCurrent').value = '';
            document.getElementById('pwNew').value     = '';
            document.getElementById('pwConfirm').value = '';
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
        btn.innerHTML = '<i class="fa-solid fa-shield-halved"></i> Cập nhật mật khẩu';
    }
}

/* ============================================================
   AVATAR UPLOAD — POST /api/me/avatar
============================================================ */
async function handleAvatarUpload(input) {
    const file = input.files[0];
    if (!file) return;

    if (file.size > 2 * 1024 * 1024) {
        showToast('Ảnh tối đa 2MB!', 'error'); return;
    }
    if (!file.type.startsWith('image/')) {
        showToast('Chỉ chấp nhận file ảnh!', 'error'); return;
    }

    // Preview ngay lập tức
    const reader = new FileReader();
    reader.onload = (e) => {
        _renderProfileAvatar(e.target.result, null);
        // Cũng update sidebar + topbar
        const stored = JSON.parse(localStorage.getItem('user') || '{}');
        stored.avatar_url = e.target.result;
        localStorage.setItem('user', JSON.stringify(stored));
        if (typeof loadUserUI === 'function') loadUserUI();
    };
    reader.readAsDataURL(file);

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
            const stored = JSON.parse(localStorage.getItem('user') || '{}');
            stored.avatar_url = data.avatar_url;
            localStorage.setItem('user', JSON.stringify(stored));
            _renderProfileAvatar(data.avatar_url, stored.name);
            if (typeof loadUserUI === 'function') loadUserUI();
            showToast('Cập nhật ảnh thành công!', 'success');
        } else {
            showToast(data.message || 'Upload thất bại!', 'error');
        }
    } catch (e) {
        showToast('Lỗi kết nối khi upload ảnh!', 'error');
    }

    input.value = '';
}

/* ============================================================
   TOGGLE PASSWORD VISIBILITY
============================================================ */
function togglePw(inputId, icon) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

/* ============================================================
   NOTIFICATION SETTINGS — lưu vào localStorage
============================================================ */
function saveNotifSettings() {
    const settings = {
        booking: document.getElementById('notifBooking')?.checked || false,
        promo:   document.getElementById('notifPromo')?.checked || false,
        system:  document.getElementById('notifSystem')?.checked || false,
    };
    localStorage.setItem('notif_settings', JSON.stringify(settings));
    showToast('Đã lưu cài đặt thông báo', 'success');
}

function loadNotifSettings() {
    try {
        const s = JSON.parse(localStorage.getItem('notif_settings') || '{}');
        if (s.booking !== undefined) document.getElementById('notifBooking').checked = s.booking;
        if (s.promo   !== undefined) document.getElementById('notifPromo').checked   = s.promo;
        if (s.system  !== undefined) document.getElementById('notifSystem').checked  = s.system;
    } catch(e) {}
}

/* ============================================================
   SCROLL TO PASSWORD SECTION khi vào từ sidebar #password
============================================================ */
function scrollToPasswordIfNeeded() {
    if (window.location.hash === '#password') {
        const el = document.getElementById('section-password');
        if (el) {
            setTimeout(() => el.scrollIntoView({ behavior: 'smooth', block: 'start' }), 300);
        }
    }
}

/* ============================================================
   HELPERS — alert inline
============================================================ */
function _showAlert(prefix, type, msg) {
    const el  = document.getElementById(prefix + (type === 'success' ? 'Success' : 'Error'));
    const msg_el = document.getElementById(prefix + (type === 'success' ? 'SuccessMsg' : 'ErrorMsg'));
    if (!el) return;
    el.classList.remove('show');
    void el.offsetWidth; // reflow
    if (msg_el) msg_el.textContent = msg;
    el.classList.add('show');
}

function _clearAlerts(prefix) {
    ['Success', 'Error'].forEach(t => {
        const el = document.getElementById(prefix + t);
        if (el) el.classList.remove('show');
    });
}

/* ============================================================
   ACTIVITY LOG — Hoạt động gần đây
============================================================ */
function loadActivityLog() {
    const activityList = document.getElementById('activityList');
    if (!activityList) return;

    let activities = JSON.parse(localStorage.getItem('activities') || '[]');
    if (activities.length === 0) {
        const fakeActivities = [
            { type: 'login', title: 'Đăng nhập hệ thống', detail: 'Chrome trên Windows', time: '2 phút trước', icon: 'fa-sign-in-alt' },
            { type: 'profile', title: 'Cập nhật hồ sơ', detail: 'Thay đổi ảnh đại diện', time: '1 giờ trước', icon: 'fa-user-edit' },
            { type: 'booking', title: 'Đặt phòng thành công', detail: 'Khách sạn Sunrise Hà Nội', time: '3 giờ trước', icon: 'fa-bed' },
            { type: 'password', title: 'Thay đổi mật khẩu', detail: 'Cập nhật mật khẩu', time: '2 ngày trước', icon: 'fa-shield-alt' },
        ];
        localStorage.setItem('activities', JSON.stringify(fakeActivities));
        activities = fakeActivities;
    }

    const recent = activities.slice(0, 5);
    activityList.innerHTML = recent.map(act => `
        <div class="activity-item">
            <div class="activity-icon"><i class="fas ${act.icon}"></i></div>
            <div><div class="activity-title">${act.title}</div><div class="activity-sub">${act.detail}</div><div class="activity-time">${act.time}</div></div>
        </div>
    `).join('');
}

function viewAllActivity() {
    const acts = JSON.parse(localStorage.getItem('activities') || '[]');
    let modalHtml = `<div style="position:fixed; inset:0; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:1000;" onclick="this.remove()"><div style="background:white; border-radius:20px; max-width:500px; width:90%; padding:24px;"><h3 style="margin-bottom:16px;">Lịch sử hoạt động</h3><div style="max-height:60vh; overflow:auto;">`;
    acts.forEach(act => {
        modalHtml += `<div style="display:flex; gap:12px; padding:10px 0; border-bottom:1px solid #eee;"><div style="width:36px; height:36px; background:#eef2ff; border-radius:50%; display:flex; align-items:center; justify-content:center;"><i class="fas ${act.icon}"></i></div><div><div style="font-weight:600">${act.title}</div><div style="font-size:12px; color:#555">${act.detail}</div><div style="font-size:11px; color:#888">${act.time}</div></div></div>`;
    });
    modalHtml += `</div><button style="margin-top:20px; background:#3b82f6; color:white; border:none; padding:8px 20px; border-radius:40px;" onclick="this.closest('div').remove()">Đóng</button></div></div>`;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

/* ============================================================
   INIT
============================================================ */
document.addEventListener('DOMContentLoaded', function () {
    loadProfile();
    loadActivityLog();
    loadNotifSettings();
    scrollToPasswordIfNeeded();
});
</script>