@extends('user.layouts.user')
@section('title', 'Cài đặt')

@section('content')
<div class="page-header">
    <div>
        <p class="eyebrow">TRẢI NGHIỆM Angel</p>
        <h1>Cài đặt</h1>
        <p class="page-description">Xem cấu hình hệ thống và điều chỉnh trải nghiệm trên thiết bị này.</p>
    </div>
</div>

<div id="settingsLoadState" class="loading-state" role="status">
    @include('user.partials.icon', ['name'=>'spinner']) <span>Đang tải cài đặt…</span>
</div>

<div id="settingsContent" class="settings-content" hidden>
    <div class="inline-alert error" id="settingsLoadError" role="alert"><span id="settingsLoadErrorMsg"></span></div>

    <div class="settings-grid">
        <div class="settings-main">
            <section class="card" aria-labelledby="notificationSettingsTitle">
                <div class="card-header">
                    <div>
                        <h2 id="notificationSettingsTitle">Thông báo</h2>
                        <p>Thông tin cần thiết để quản lý đặt phòng và bảo vệ tài khoản.</p>
                    </div>
                    @include('user.partials.icon', ['name'=>'mail'])
                </div>
                <div class="card-body settings-notification-body">
                    <div class="settings-notification-row">
                        <span class="settings-notification-icon">@include('user.partials.icon', ['name'=>'shield'])</span>
                        <div>
                            <h3>Thông báo giao dịch và bảo mật</h3>
                            <p>Booking, Payment, Invoice và các cảnh báo bảo mật quan trọng.</p>
                        </div>
                        <span class="settings-required-badge">Luôn bật</span>
                    </div>

                    <div class="settings-empty-preference" role="status">
                        @include('user.partials.icon', ['name'=>'info'])
                        <div>
                            <h3>Chưa có tùy chọn thông báo quảng bá</h3>
                            <p>Angel hiện chưa vận hành kênh gửi ưu đãi tùy chọn, vì vậy chưa có preference cần lưu cho tài khoản.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card section-gap" aria-labelledby="displaySettingsTitle">
                <div class="card-header">
                    <div>
                        <h2 id="displaySettingsTitle">Hiển thị và chuyển động</h2>
                        <p>Tùy chọn chỉ lưu trên trình duyệt này.</p>
                    </div>
                    @include('user.partials.icon', ['name'=>'motion'])
                </div>
                <div class="card-body">
                    <div class="settings-row">
                        <div>
                            <h3><label for="accountMotion">Hiệu ứng chuyển động</label></h3>
                            <p id="motionHint">Giảm hiệu ứng chuyển cảnh trong khu vực tài khoản.</p>
                        </div>
                        <select class="form-select" id="accountMotion" aria-describedby="motionHint motionStatus">
                            <option value="system">Theo thiết bị</option>
                            <option value="reduced">Giảm chuyển động</option>
                        </select>
                    </div>
                    <p class="settings-save-status" id="motionStatus" role="status" aria-live="polite"></p>
                </div>
            </section>
        </div>

        <aside class="account-aside" aria-label="Thông tin hệ thống">
            <section class="card settings-system-card">
                <div class="card-header">
                    <div><h2>Thông tin hệ thống</h2><p>Cấu hình hiện hành của Angel.</p></div>
                    @include('user.partials.icon', ['name'=>'settings-2'])
                </div>
                <div class="settings-system-list">
                    <div><span>Ngôn ngữ</span><strong>Tiếng Việt</strong></div>
                    <div><span>Tiền tệ</span><strong>VND · ₫</strong></div>
                    <div><span>Giao diện</span><strong>Sáng</strong></div>
                </div>
            </section>
        </aside>
    </div>
</div>
@endsection

@section('scripts')
<script>
function showSettingsContent() {
    document.getElementById('settingsLoadState').hidden = true;
    document.getElementById('settingsContent').hidden = false;
}

function setMotionStatus(message, type = '') {
    const status = document.getElementById('motionStatus');
    status.textContent = message;
    status.className = 'settings-save-status' + (type ? ' ' + type : '');
}

function loadSettings() {
    const select = document.getElementById('accountMotion');
    try {
        const stored = localStorage.getItem('aurel_account_motion');
        const value = stored === 'reduced' ? 'reduced' : 'system';
        select.value = value;
        applyAccountMotion(value);
    } catch (error) {
        select.value = 'system';
        applyAccountMotion('system');
        document.getElementById('settingsLoadErrorMsg').textContent = 'Không thể đọc tùy chọn trên trình duyệt. Cài đặt mặc định đang được sử dụng.';
        document.getElementById('settingsLoadError').classList.add('show');
    } finally {
        showSettingsContent();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const select = document.getElementById('accountMotion');
    select.addEventListener('change', () => {
        applyAccountMotion(select.value);
        try {
            localStorage.setItem('aurel_account_motion', select.value);
            setMotionStatus('Đã lưu trên trình duyệt này.', 'success');
        } catch (error) {
            setMotionStatus('Đã áp dụng cho trang hiện tại. Trình duyệt không cho phép lưu tùy chọn.', 'error');
        }
    });
    loadSettings();
});
</script>
@endsection
