{{-- resources/views/admin/partials/topbar.blade.php --}}
<header class="admin-topbar">

    {{-- Brand / Logo --}}
    <a href="{{ route('home') }}" class="topbar-brand">
        <svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" class="brand-svg">
            <path d="M20 80C20 80 25 25 55 10C55 10 45 40 45 80H20Z" fill="#0F172A"/>
            <path d="M50 75C50 75 55 35 75 20C75 20 65 45 65 75H50Z" fill="#2563EB"/>
            <path d="M40 85C60 85 85 65 85 35" stroke="#F59E0B" stroke-width="3" stroke-linecap="round"/>
            <path d="M55 70L65 75M60 60L75 65M68 50L82 52M75 40L88 38" stroke="#F59E0B" stroke-width="2.5" stroke-linecap="round"/>
            <path d="M15 85C35 78 65 78 85 85" stroke="#F59E0B" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span class="brand-name">Holiday<strong>Viet</strong></span>
        <span class="brand-tag">Admin</span>
    </a>

    <div class="topbar-divider"></div>

    <div style="flex:1"></div>

    {{-- Right actions --}}
    <div class="topbar-actions">

        <button class="tb-icon-btn" title="Thông báo" id="notifBtn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>
            </svg>
            <span class="tb-notif-dot js-notif-dot" style="display:none"></span>
        </button>

        <div class="tb-v-divider"></div>

        {{-- Avatar + dropdown --}}
        <div class="tb-avatar-wrap" id="tbAvatarWrap">
            <button class="tb-avatar-btn" id="tbAvatarBtn">
                <div class="tb-avatar js-avatar-initials">AD</div>
                <div class="tb-avatar-info">
                    <span class="tb-avatar-name js-staff-name">Đang tải...</span>
                    <span class="tb-avatar-role js-staff-role">—</span>
                </div>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" class="tb-chevron">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </button>

            <div class="tb-dropdown" id="tbDropdown">
                <div class="tb-dropdown-header">
                    <div class="tb-dropdown-avatar js-avatar-initials">AD</div>
                    <div>
                        <div class="tb-dropdown-name js-staff-name">—</div>
                        <div class="tb-dropdown-role js-staff-role">—</div>
                    </div>
                </div>

                <div class="tb-dropdown-divider"></div>

                <a href="#" class="tb-dropdown-item">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                    Hồ sơ của tôi
                </a>
                <a href="#" class="tb-dropdown-item">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>
                    </svg>
                    Cài đặt
                </a>

                <div class="tb-dropdown-divider"></div>

                <div class="tb-dropdown-item tb-dropdown-danger" onclick="adminLogout()">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>
                    </svg>
                    Đăng xuất
                </div>
            </div>
        </div>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const btn  = document.getElementById('tbAvatarBtn');
    const menu = document.getElementById('tbDropdown');
    if (!btn || !menu) return;

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        const open = menu.classList.toggle('open');
        btn.classList.toggle('active', open);
    });

    document.addEventListener('click', function () {
        menu.classList.remove('open');
        btn.classList.remove('active');
    });

    document.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            document.getElementById('adminSearchInput')?.focus();
        }
    });
});
</script>