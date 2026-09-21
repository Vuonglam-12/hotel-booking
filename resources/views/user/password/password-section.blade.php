{{-- ============================================================
    password-section.blade.php
    Giao diện đổi mật khẩu – 2 cột hiện đại (trái: form, phải: bảo mật + mẹo)
============================================================ --}}

<style>
    /* --- layout 2 cột --- */
    .password-two-columns {
        max-width: 1100px;
        margin: 0 auto;
        display: flex;
        gap: 24px;
        align-items: flex-start;
    }

    .password-left {
        flex: 0 0 60%;
        min-width: 0;
    }

    .password-right {
        flex: 0 0 40%;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 24px;
    }

    /* --- card chung --- */
    .password-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 16px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        overflow: hidden;
    }

    .card-header {
        padding: 20px 24px 0 24px;
    }

    .card-header h2 {
        font-size: 20px;
        font-weight: 700;
        color: var(--text-primary);
        margin-bottom: 4px;
    }

    .card-header p {
        font-size: 13px;
        color: var(--text-secondary);
        margin-bottom: 16px;
    }

    .card-divider {
        height: 1px;
        background: var(--border-light);
        margin: 0 24px;
    }

    .card-body {
        padding: 20px 24px 24px;
    }

    /* --- form fields --- */
    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 13.5px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 8px;
    }

    .form-group label i {
        color: var(--primary);
        font-size: 14px;
        width: 18px;
    }

    .input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .form-input {
        width: 100%;
        padding: 11px 40px 11px 12px;
        border: 1.5px solid var(--border);
        border-radius: 10px;
        font-size: 13.5px;
        background: #fff;
        transition: all 0.2s;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .pw-toggle {
        position: absolute;
        right: 12px;
        color: var(--text-muted);
        cursor: pointer;
        font-size: 16px;
        transition: color 0.2s;
    }

    .pw-toggle:hover {
        color: var(--primary);
    }

    /* --- strength meter --- */
    .strength-container {
        margin-top: 8px;
    }

    .strength-label {
        font-size: 12px;
        font-weight: 500;
        color: var(--text-secondary);
        margin-bottom: 6px;
        display: flex;
        justify-content: space-between;
    }

    .strength-bars {
        display: flex;
        gap: 6px;
    }

    .strength-segment {
        flex: 1;
        height: 4px;
        background: #e2e8f0;
        border-radius: 3px;
        transition: background 0.2s;
    }

    .strength-segment.active-1 { background: #EF4444; }
    .strength-segment.active-2 { background: #F97316; }
    .strength-segment.active-3 { background: #EAB308; }
    .strength-segment.active-4 { background: #22C55E; }

    /* --- alert inline --- */
    .alert-inline {
        display: none;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        border-radius: 10px;
        font-size: 13px;
        margin-bottom: 20px;
        border-left: 4px solid;
    }

    .alert-inline.show {
        display: flex;
    }

    .alert-success {
        background: #f0fdf4;
        color: #166534;
        border-left-color: #22c55e;
    }

    .alert-error {
        background: #fef2f2;
        color: #991b1b;
        border-left-color: #ef4444;
    }

    /* --- note box --- */
    .note-box {
        background: #EFF6FF;
        border-left: 3px solid var(--primary);
        border-radius: 12px;
        padding: 16px;
        margin-top: 12px;
    }

    .note-header {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 700;
        color: var(--primary-dark);
        margin-bottom: 12px;
    }

    .note-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .note-list li {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12.5px;
        color: var(--text-secondary);
        margin-bottom: 6px;
    }

    .note-list li i {
        color: #22c55e;
        font-size: 12px;
        width: 16px;
    }

    /* --- button --- */
    .btn-update {
        width: 100%;
        padding: 12px;
        background: #2563EB;
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: 600;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        cursor: pointer;
        transition: 0.2s;
        margin-top: 8px;
    }

    .btn-update:hover:not(:disabled) {
        background: #1D4ED8;
        transform: translateY(-1px);
    }

    .btn-update:disabled {
        opacity: 0.65;
        cursor: not-allowed;
    }

    /* --- right column cards --- */
    .security-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 0;
        border-bottom: 1px solid var(--border-light);
    }

    .security-item:last-child {
        border-bottom: none;
    }

    .security-left {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .security-icon {
        width: 40px;
        height: 40px;
        border-radius: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    .security-icon.blue { background: #dbeafe; color: #2563eb; }
    .security-icon.purple { background: #f3e8ff; color: #9333ea; }
    .security-icon.orange { background: #ffedd5; color: #ea580c; }

    .security-info h4 {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 2px;
    }

    .security-info p {
        font-size: 12px;
        color: var(--text-muted);
    }

    .badge-active {
        background: #dcfce7;
        color: #15803d;
        font-size: 11px;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: 30px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .chevron {
        color: var(--text-muted);
        font-size: 14px;
    }

    /* tips items */
    .tip-item {
        display: flex;
        gap: 14px;
        align-items: flex-start;
        margin-bottom: 18px;
    }

    .tip-item:last-child {
        margin-bottom: 0;
    }

    .tip-icon {
        width: 32px;
        height: 32px;
        border-radius: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        flex-shrink: 0;
    }

    .tip-icon.blue { background: #dbeafe; color: #2563eb; }
    .tip-icon.purple { background: #f3e8ff; color: #9333ea; }
    .tip-icon.green { background: #dcfce7; color: #16a34a; }
    .tip-icon.orange { background: #ffedd5; color: #ea580c; }

    .tip-text h5 {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 2px;
    }

    .tip-text p {
        font-size: 12px;
        color: var(--text-secondary);
        line-height: 1.4;
    }

    /* responsive */
    @media (max-width: 768px) {
        .password-two-columns {
            flex-direction: column;
        }
        .password-left, .password-right {
            flex: 1 1 100%;
        }
    }
</style>

<div class="password-two-columns">

    {{-- ================= CỘT TRÁI : FORM ĐỔI MẬT KHẨU ================= --}}
    <div class="password-left">
        <div class="password-card">
            <div class="card-header">
                <h2>Cập nhật mật khẩu</h2>
                <p>Đặt mật khẩu mới để bảo vệ tài khoản của bạn</p>
                <div class="card-divider"></div>
            </div>
            <div class="card-body">

                {{-- Alert inline --}}
                <div class="alert-inline alert-success" id="pwSuccess">
                    <i class="fa-solid fa-circle-check"></i>
                    <span id="pwSuccessMsg"></span>
                </div>
                <div class="alert-inline alert-error" id="pwError">
                    <i class="fa-solid fa-circle-xmark"></i>
                    <span id="pwErrorMsg"></span>
                </div>

                {{-- Mật khẩu hiện tại --}}
                <div class="form-group">
                    <label><i class="fa-solid fa-lock"></i> Mật khẩu hiện tại</label>
                    <div class="input-wrapper">
                        <input type="password" id="pwCurrent" class="form-input" placeholder="Nhập mật khẩu hiện tại">
                    </div>
                </div>

                {{-- Mật khẩu mới + strength meter --}}
                <div class="form-group">
                    <label><i class="fa-solid fa-key"></i> Mật khẩu mới</label>
                    <div class="input-wrapper">
                        <input type="password" id="pwNew" class="form-input" placeholder="Nhập mật khẩu mới">
                    </div>
                    <div class="strength-container" id="strengthContainer">
                        <div class="strength-label">
                            <span>Độ mạnh mật khẩu:</span>
                            <span id="strengthText">Yếu</span>
                        </div>
                        <div class="strength-bars" id="strengthBars">
                            <div class="strength-segment"></div>
                            <div class="strength-segment"></div>
                            <div class="strength-segment"></div>
                            <div class="strength-segment"></div>
                        </div>
                    </div>
                </div>

                {{-- Xác nhận mật khẩu mới --}}
                <div class="form-group">
                    <label><i class="fa-solid fa-circle-check"></i> Xác nhận mật khẩu mới</label>
                    <div class="input-wrapper">
                        <input type="password" id="pwConfirm" class="form-input" placeholder="Nhập lại mật khẩu mới">
                    </div>
                </div>

                {{-- Nút cập nhật --}}
                <button class="btn-update" id="btnSavePw" onclick="savePassword()">
                    <i class="fa-solid fa-lock"></i> Cập nhật mật khẩu
                </button>

                {{-- Box ghi chú --}}
                <div class="note-box">
                    <div class="note-header">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>Lưu ý khi đặt mật khẩu</span>
                    </div>
                    <ul class="note-list">
                        <li><i class="fa-solid fa-check"></i> Ít nhất 8 ký tự</li>
                        <li><i class="fa-solid fa-check"></i> Bao gồm chữ hoa, chữ thường</li>
                        <li><i class="fa-solid fa-check"></i> Bao gồm số và ký tự đặc biệt</li>
                        <li><i class="fa-solid fa-check"></i> Không sử dụng thông tin cá nhân dễ đoán</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- ================= CỘT PHẢI : BẢO MẬT + MẸO ================= --}}
    <div class="password-right">

        {{-- Card Bảo mật tài khoản --}}
        <div class="password-card">
            <div class="card-header">
                <h2><i class="fa-solid fa-shield-halved" style="margin-right: 6px;"></i> Bảo mật tài khoản</h2>
                <p>Các tính năng bảo mật giúp bảo vệ tài khoản</p>
                <div class="card-divider"></div>
            </div>
            <div class="card-body" style="padding-top: 8px;">
                {{-- 2FA --}}
                <div class="security-item">
                    <div class="security-left">
                        <div class="security-icon blue"><i class="fa-solid fa-shield-halved"></i></div>
                        <div class="security-info">
                            <h4>Xác thực 2 lớp (2FA)</h4>
                            <p>Thêm một lớp bảo mật cho tài khoản</p>
                        </div>
                    </div>
                    <div><span class="badge-active"><i class="fa-solid fa-check"></i> Đã bật</span></div>
                </div>
                {{-- Thiết bị đăng nhập --}}
                <div class="security-item">
                    <div class="security-left">
                        <div class="security-icon purple"><i class="fa-solid fa-desktop"></i></div>
                        <div class="security-info">
                            <h4>Thiết bị đăng nhập</h4>
                            <p>Quản lý các thiết bị đã đăng nhập</p>
                        </div>
                    </div>
                    <div class="chevron"><i class="fa-solid fa-chevron-right"></i></div>
                </div>
                {{-- Phiên đăng nhập --}}
                <div class="security-item">
                    <div class="security-left">
                        <div class="security-icon orange"><i class="fa-solid fa-eye"></i></div>
                        <div class="security-info">
                            <h4>Phiên đăng nhập</h4>
                            <p>Xem và quản lý các phiên đăng nhập</p>
                        </div>
                    </div>
                    <div class="chevron"><i class="fa-solid fa-chevron-right"></i></div>
                </div>
            </div>
        </div>

        {{-- Card Mẹo bảo mật --}}
        <div class="password-card">
            <div class="card-header">
                <h2><i class="fa-regular fa-lightbulb"></i> Mẹo bảo mật</h2>
                <div class="card-divider"></div>
            </div>
            <div class="card-body">
                <div class="tip-item">
                    <div class="tip-icon blue"><i class="fa-solid fa-shield-halved"></i></div>
                    <div class="tip-text">
                        <h5>Sử dụng mật khẩu mạnh</h5>
                        <p>Kết hợp chữ hoa, chữ thường, số và ký tự đặc biệt</p>
                    </div>
                </div>
                <div class="tip-item">
                    <div class="tip-icon purple"><i class="fa-solid fa-user-secret"></i></div>
                    <div class="tip-text">
                        <h5>Không chia sẻ mật khẩu</h5>
                        <p>Không chia sẻ mật khẩu với bất kỳ ai</p>
                    </div>
                </div>
                <div class="tip-item">
                    <div class="tip-icon green"><i class="fa-solid fa-right-from-bracket"></i></div>
                    <div class="tip-text">
                        <h5>Đăng xuất khi không dùng</h5>
                        <p>Đăng xuất khỏi các thiết bị công cộng</p>
                    </div>
                </div>
                <div class="tip-item">
                    <div class="tip-icon orange"><i class="fa-solid fa-rotate"></i></div>
                    <div class="tip-text">
                        <h5>Cập nhật mật khẩu định kỳ</h5>
                        <p>Thay đổi mật khẩu thường xuyên để bảo mật tốt hơn</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('user.password.password-script')