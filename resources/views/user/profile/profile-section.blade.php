{{-- resources/views/profile-section.blade.php --}}
<style>
    /* ============================================================
       PROFILE PAGE STYLES - TÔNG XANH HIỆN ĐẠI
    ============================================================ */
    :root {
        --primary: #3B82F6;
        --primary-dark: #2563EB;
        --primary-light: #60A5FA;
        --accent: #FBBF24;
        --bg-card: #ffffff;
        --border: #E2E8F0;
        --border-light: #F1F5F9;
        --text-primary: #0F172A;
        --text-secondary: #334155;
        --text-muted: #64748B;
        --radius: 16px;
        --radius-sm: 12px;
        --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
        --transition: all 0.2s ease;
    }

    .profile-container {
        max-width: 1280px;
        margin: 0 auto;
        padding: 24px;
    }

    /* Hero Banner */
    .hero-banner {
        position: relative;
        margin-bottom: 28px;
        border-radius: 20px;
        overflow: hidden;
    }
    .hero-cover {
        width: 100%;
        height: 260px;
        object-fit: cover;
        display: block;
    }
    .hero-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.65) 100%);
        pointer-events: none;
    }
    .hero-content {
        position: absolute;
        bottom: 24px;
        left: 32px;
        right: 32px;
        display: flex;
        align-items: center;
        gap: 24px;
        z-index: 2;
    }
    .hero-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        border: 4px solid white;
        object-fit: cover;
        background: white;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        display: none;
    }
    .hero-avatar-initial {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 38px;
        font-weight: 700;
        color: white;
        border: 4px solid white;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .camera-btn {
        position: absolute;
        bottom: 4px;
        right: 4px;
        background: white;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: var(--shadow);
        transition: var(--transition);
        color: var(--text-secondary);
    }
    .camera-btn:hover {
        background: var(--primary-light);
        color: white;
    }
    .hero-info {
        color: white;
        text-shadow: 0 1px 3px rgba(0,0,0,0.3);
        flex: 1;
    }
    .hero-name {
        font-size: 26px;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 6px;
    }
    .hero-badge {
        background: rgba(255,255,255,0.9);
        color: var(--primary-dark);
        padding: 4px 12px;
        border-radius: 40px;
        font-size: 12px;
        font-weight: 700;
    }
    .hero-meta {
        display: flex;
        gap: 28px;
        font-size: 13.5px;
        font-weight: 500;
    }
    .hero-meta span {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    /* Stats row */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 32px;
    }
    .stat-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 18px 16px;
        display: flex;
        align-items: center;
        gap: 14px;
        transition: var(--transition);
    }
    .stat-card:hover {
        border-color: var(--primary-light);
        box-shadow: var(--shadow);
        transform: translateY(-2px);
    }
    .stat-icon {
        width: 48px;
        height: 48px;
        background: rgba(59,130,246,0.1);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: var(--primary);
    }
    .stat-label { font-size: 12px; color: var(--text-muted); margin-bottom: 2px; }
    .stat-value { font-size: 20px; font-weight: 700; color: var(--text-primary); line-height: 1.2; }

    /* Main grid */
    .profile-grid {
        display: grid;
        grid-template-columns: 1fr 380px;
        gap: 24px;
        align-items: start;
    }

    /* Cards */
    .pcard {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 28px;
        margin-bottom: 24px;
    }
    .pcard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        border-bottom: 1px solid var(--border-light);
        padding-bottom: 12px;
    }
    .pcard-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--text-primary);
    }

    /* Form */
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    .form-group.full { grid-column: span 2; }
    .form-label {
        font-size: 12px;
        font-weight: 600;
        color: var(--text-muted);
        margin-bottom: 6px;
        display: block;
    }
    .form-input {
        width: 100%;
        padding: 10px 14px;
        border: 1.5px solid var(--border);
        border-radius: 12px;
        font-size: 14px;
        transition: var(--transition);
        background: #fff;
    }
    .form-input:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
    }
    .form-input:disabled {
        background: var(--border-light);
        color: var(--text-muted);
    }
    .btn-save {
        background: var(--primary);
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 40px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        transition: var(--transition);
        margin-top: 20px;
    }

    /* Password section */
    .pw-input-wrap {
        position: relative;
    }
    .pw-toggle {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: var(--text-muted);
    }
    .btn-save-pw {
        width: 100%;
        background: var(--primary);
        color: white;
        border: none;
        padding: 12px;
        border-radius: 40px;
        font-weight: 600;
        margin-top: 12px;
        cursor: pointer;
    }

    /* Right column */
    .payment-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid var(--border-light);
    }
    .payment-left {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .payment-logo {
        width: 44px;
        height: 28px;
        background: #f1f5f9;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 11px;
    }
    .notif-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid var(--border-light);
    }
    .toggle-switch {
        position: relative;
        width: 44px;
        height: 24px;
    }
    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .toggle-slider {
        position: absolute;
        cursor: pointer;
        inset: 0;
        background: #cbd5e1;
        border-radius: 34px;
        transition: 0.3s;
    }
    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background: white;
        border-radius: 50%;
        transition: 0.3s;
    }
    input:checked + .toggle-slider {
        background: var(--primary);
    }
    input:checked + .toggle-slider:before {
        transform: translateX(20px);
    }
    .pref-row {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid var(--border-light);
        cursor: pointer;
    }

    /* Activity */
    .activity-list {
        max-height: 320px;
        overflow-y: auto;
    }
    .activity-item {
        display: flex;
        gap: 12px;
        padding: 12px 0;
        border-bottom: 1px solid var(--border-light);
    }
    .activity-icon {
        width: 36px;
        height: 36px;
        background: rgba(59,130,246,0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--primary);
    }
    .alert-inline {
        display: none;
        padding: 10px 14px;
        border-radius: 12px;
        margin-bottom: 16px;
        font-size: 13px;
    }
    .alert-success { background: #dcfce7; color: #15803d; }
    .alert-error { background: #fee2e2; color: #b91c1c; }
    .alert-inline.show { display: flex; align-items: center; gap: 8px; }

    @media (max-width: 900px) {
        .profile-grid { grid-template-columns: 1fr; }
        .stats-row { grid-template-columns: 1fr 1fr; }
        .hero-content { flex-direction: column; align-items: flex-start; left: 20px; bottom: 20px; }
        .hero-meta { flex-wrap: wrap; gap: 12px; }
    }
</style>

<div class="profile-container">
    {{-- HERO BANNER --}}
    <div class="hero-banner">
        <img src="{{ asset('image/banner-profile.png') }}" class="hero-cover" alt="Cover">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <div class="hero-avatar-wrapper" style="position: relative;">
                <img id="profileAvatarImg" class="hero-avatar" src="">
                <div id="profileAvatarInitial" class="hero-avatar-initial">?</div>
                <label for="avatarFileInput" class="camera-btn"><i class="fas fa-camera"></i></label>
                <input type="file" id="avatarFileInput" accept="image/*" style="display:none" onchange="handleAvatarUpload(this)">
            </div>
            <div class="hero-info">
                <div class="hero-name">
                    <span id="heroDisplayName">Khách hàng</span>
                    <span class="hero-badge">Thành viên Bạc</span>
                </div>
                <div class="hero-meta">
                    <span><i class="fas fa-phone-alt"></i> <span id="heroPhone">---</span></span>
                    <span><i class="fas fa-envelope"></i> <span id="heroEmail">---</span></span>
                    <span><i class="far fa-calendar-alt"></i> Tham gia: <span id="heroMemberSince">12/2023</span></span>
                </div>
            </div>
        </div>
    </div>

    {{-- STATS ROW --}}
    <div class="stats-row">
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-star"></i></div><div><div class="stat-label">Điểm thưởng</div><div class="stat-value" id="stat-points">0</div></div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-calendar-check"></i></div><div><div class="stat-label">Đơn đặt phòng</div><div class="stat-value" id="stat-bookings">0</div></div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-ticket-alt"></i></div><div><div class="stat-label">Voucher</div><div class="stat-value" id="stat-vouchers">0</div></div></div>
        <div class="stat-card"><div class="stat-icon"><i class="fas fa-crown"></i></div><div><div class="stat-label">Hạng thành viên</div><div class="stat-value" id="stat-rank">Bạc</div></div></div>
    </div>

    {{-- MAIN GRID 2 CỘT --}}
    <div class="profile-grid">
        {{-- Cột trái --}}
        <div>
            {{-- Thông tin cá nhân --}}
            <div class="pcard">
                <div class="pcard-header"><div class="pcard-title">Thông tin cá nhân</div><span style="font-size:12px; background:#e6f7e6; padding:4px 8px; border-radius:20px;">Đã xác minh</span></div>
                <div class="alert-inline alert-success" id="profileSuccess"><i class="fas fa-check-circle"></i> <span id="profileSuccessMsg"></span></div>
                <div class="alert-inline alert-error" id="profileError"><i class="fas fa-exclamation-circle"></i> <span id="profileErrorMsg"></span></div>

            <div class="form-grid">
                <div class="form-group full"><label class="form-label">Họ và tên</label><input type="text" id="profileName" class="form-input" placeholder="Họ tên"></div>
                <div class="form-group"><label class="form-label">Email</label><input type="email" id="profileEmail" class="form-input" disabled></div>
                <div class="form-group"><label class="form-label">Số điện thoại</label><input type="tel" id="profilePhone" class="form-input"></div>
                <div class="form-group full"><label class="form-label">Ngày sinh</label><input type="date" id="profileDob" class="form-input"></div>
            </div>
                <button class="btn-save" id="btnSaveProfile" onclick="saveProfile()"><i class="fas fa-save"></i> Lưu thay đổi</button>
            </div>

            {{-- Hoạt động gần đây --}}
            <div class="pcard">
                <div class="pcard-header"><div class="pcard-title">Hoạt động gần đây</div><button class="btn-activity-more" onclick="viewAllActivity()" style="background:none; border:none; color:var(--primary); font-weight:600;">Xem tất cả</button></div>
                <div id="activityList" class="activity-list">Đang tải...</div>
            </div>
        </div>

        {{-- Cột phải --}}
        <div>
            <div class="pcard">
                <div class="pcard-header"><div class="pcard-title">Thanh toán</div><span style="font-size:12px; color:var(--primary); cursor:pointer;"><i class="fas fa-plus"></i> Thêm</span></div>
                <div class="payment-item"><div class="payment-left"><div class="payment-logo" style="background:#1a1f71; color:white;">VISA</div><div><strong>Visa **** 1234</strong><div>Hết hạn 12/26</div></div></div><span class="badge">Mặc định</span></div>
                <div class="payment-item"><div class="payment-left"><div class="payment-logo" style="background:#ae2070; color:white;">MoMo</div><div><strong>MoMo **** 5678</strong><div>Nguyễn Văn A</div></div></div></div>
            </div>

            <div class="pcard">
                <div class="pcard-header"><div class="pcard-title">Cài đặt thông báo</div></div>
                <div class="notif-row"><div><div class="notif-row-label">Thông báo đặt phòng</div><div class="notif-row-sub">Nhận xác nhận & cập nhật</div></div><label class="toggle-switch"><input type="checkbox" id="notifBooking" checked onchange="saveNotifSettings()"><span class="toggle-slider"></span></label></div>
                <div class="notif-row"><div><div class="notif-row-label">Khuyến mãi & ưu đãi</div><div class="notif-row-sub">Flash sale, voucher</div></div><label class="toggle-switch"><input type="checkbox" id="notifPromo" checked onchange="saveNotifSettings()"><span class="toggle-slider"></span></label></div>
                <div class="notif-row"><div><div class="notif-row-label">Thông báo hệ thống</div><div class="notif-row-sub">Bảo trì, tính năng mới</div></div><label class="toggle-switch"><input type="checkbox" id="notifSystem" onchange="saveNotifSettings()"><span class="toggle-slider"></span></label></div>
            </div>

            <div class="pcard">
                <div class="pcard-header"><div class="pcard-title">Tùy chọn</div></div>
                <div class="pref-row"><span>Ngôn ngữ</span><span>Tiếng Việt <i class="fas fa-chevron-right"></i></span></div>
                <div class="pref-row"><span>Đơn vị tiền tệ</span><span>VND <i class="fas fa-chevron-right"></i></span></div>
                <div class="pref-row"><span>Chế độ hiển thị</span><span>Sáng <i class="fas fa-chevron-right"></i></span></div>
            </div>
        </div>
    </div>
</div>