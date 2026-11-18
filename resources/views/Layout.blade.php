<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Angel') — Đặt Phòng Thông Minh</title>
    @include('partials.site-metadata')
    @include('partials.accommodation-images')

    {{-- System typography: friendly, rounded sans-serif --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- Swiper CSS (Dành cho hiệu ứng Carousel) --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    {{-- Vite compile Tailwind CSS --}}
    @vite(['resources/css/app.css', 'resources/css/site-shell.css'])

    <style>
        /* =========================================================
           1. GLOBAL VARIABLES & TYPOGRAPHY
           ========================================================= */
        :root {
            --text-main: #1E293B;       /* Slate 800 - Text chính */
            --text-muted: #64748B;      /* Slate 500 - Text phụ */
            --text-heading: #0F172A;    /* Slate 900 - Tiêu đề */
            
            --accent-primary: #3B82F6;  /* Blue 500 */
            --accent-secondary: #14B8A6; /* Teal 500 */
            --brand-gradient: linear-gradient(135deg, var(--accent-primary) 0%, var(--accent-secondary) 100%);
        }

        :root { --font-sans: 'Nunito Sans', system-ui, -apple-system, sans-serif; }
        *, *::before, *::after { font-family: var(--font-sans); }
        body { 
            color: var(--text-main);
            background: #F8FAFC; 
            overflow-x: hidden;
        }
        h1, h2, h3, h4, .font-display { font-family: var(--font-sans); letter-spacing: 0; }
        /* Chỉ tô màu heading trong content, không ảnh hưởng logo/navbar/footer */
        main h1, main h2, main h3, main h4 {
            color: var(--text-heading);
        }

        /* =========================================================
           2. UTILITIES & GLASSMORPHISM
           ========================================================= */
        .btn-primary {
            background: var(--brand-gradient);
            color: white;
            font-weight: 600;
            border-radius: 9999px;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
            transform: translateY(-2px);
        }
        .click-effect:active { transform: scale(0.96); transition: transform 0.1s; }

        /* Custom Scrollbar */
        .custom-scroll { scrollbar-width: thin; scrollbar-color: var(--accent-primary) transparent; }
        .custom-scroll::-webkit-scrollbar { width: 5px; height: 5px; }
        .custom-scroll::-webkit-scrollbar-track { background: transparent; }
        .custom-scroll::-webkit-scrollbar-thumb { background: var(--accent-primary); border-radius: 10px; }

        #toast { animation: slideUp 0.3s ease; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        #global-loading {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(4px);
            z-index: 99999; display: none; align-items: center; justify-content: center;
        }
        #global-loading.show { display: flex; }
        .global-spinner {
            width: 50px; height: 50px; border: 4px solid #E2E8F0;
            border-top-color: var(--accent-primary); border-radius: 50%;
            animation: globalSpin 0.8s linear infinite;
        }
        @keyframes globalSpin { to { transform: rotate(360deg); } }
    </style>
    @stack('styles')
</head>

<body class="@yield('body-class')" @yield('body-attributes')>
    @include('partials.layout.header')

    {{-- MAIN CONTENT --}}
    <main class="hv-layout-main pt-16">
        @yield('content')
    </main>

    @include('partials.layout.footer')

    {{-- Toast --}}
    <div id="toast" class="fixed bottom-4 right-4 z-[9999] hidden">
        <div class="bg-slate-800 text-white px-6 py-3 rounded-xl shadow-2xl border border-slate-600 text-sm font-medium flex items-center gap-2" id="toast-msg">
            <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span></span>
        </div>
    </div>

    {{-- Global Loading --}}
    <div id="global-loading"><div class="global-spinner"></div></div>

    {{-- SCRIPTS LOGIC --}}
    <script>
        const token = localStorage.getItem('token');

        function renderGuestHeader() {
            const navAuth = document.getElementById('nav-auth');
            if (!navAuth) return;
            navAuth.innerHTML = `
                <button type="button" class="hv-auth-link hv-auth-link--primary" data-customer-login-open aria-haspopup="dialog" aria-controls="customer-login-dialog">Đăng nhập</button>
            `;
            const guestLinks = document.querySelector('[data-guest-menu-links]');
            if (guestLinks) guestLinks.hidden = false;
        }

        function renderAuthenticatedHeader(authUser) {
            const navAuth = document.getElementById('nav-auth');
            if (!navAuth) return;
            const escapeHtml = value => {
                const element = document.createElement('span');
                element.textContent = String(value ?? '');
                return element.innerHTML;
            };
            const name = escapeHtml(authUser.name || 'User');
            const initial = escapeHtml((authUser.name || 'User').charAt(0).toUpperCase());
            const avatar = typeof authUser.avatar_url === 'string' ? escapeHtml(authUser.avatar_url) : '';
            const adminItem = authUser.role === 'admin' ? `
                <a href="/admin/login" class="dropdown-item" onclick="closeUserDropdown()">
                    Quản trị Admin
                </a>
                <div style="height:1px;background:rgba(0,0,0,0.05);margin:4px 0;"></div>
            ` : '';
            navAuth.innerHTML = `
                <div class="relative" id="notif-bell-wrap">
                    <button type="button" class="notif-bell-btn hv-icon-button" id="notif-bell" onclick="toggleNotifDropdown(event)" aria-label="Thông báo" aria-haspopup="true" aria-expanded="false" aria-controls="notif-dropdown">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 01-3 3H9a3 3 0 01-3-3v-1m6 0h6"/></svg>
                        <span class="notif-badge" id="notif-badge"></span>
                    </button>
                    <div id="notif-dropdown" class="notif-dropdown" role="region" aria-label="Thông báo">
                        <div class="hv-notif-heading">
                            <span class="hv-notif-title">Thông báo</span>
                            <button type="button" onclick="markAllRead()">Đánh dấu đã đọc</button>
                        </div>
                        <div id="notif-list" class="custom-scroll">
                            <div class="text-center py-8 text-slate-400 text-sm">Đang tải...</div>
                        </div>
                        <div id="notif-footer"></div>
                    </div>
                </div>
                <div class="relative" id="user-dropdown-wrap">
                    <button type="button" id="user-menu-toggle" onclick="toggleUserDropdown(event)" class="hv-account-button" aria-label="Mở menu tài khoản" aria-haspopup="true" aria-expanded="false" aria-controls="user-dropdown-menu">
                        <div id="nav-avatar-wrap">
                            ${avatar
                                ? `<img id="nav-avatar-img" src="${avatar}" class="w-full h-full object-cover" alt="">`
                                : `<span id="nav-avatar-initial">${initial}</span>`
                            }
                        </div>
                        <span class="hv-account-name">${name}</span>
                        <svg class="hv-account-chevron" viewBox="0 0 20 20" fill="none" stroke="currentColor" aria-hidden="true"><path d="m5 7.5 5 5 5-5" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <div id="user-dropdown-menu" class="nav-dropdown-menu">
                        <div class="hv-account-greeting">
                            <p>Xin chào</p>
                            <p>${name}</p>
                        </div>
                        <a href="/profile" class="dropdown-item">Hồ sơ của tôi</a>
                        <a href="/bookings" class="dropdown-item">Đơn phòng của tôi</a>
                        <a href="/itineraries" class="dropdown-item">Lịch trình của tôi</a>
                        ${adminItem}
                        <button type="button" onclick="logout()" class="dropdown-item hv-logout text-left">Đăng xuất</button>
                    </div>
                </div>
            `;
            const guestLinks = document.querySelector('[data-guest-menu-links]');
            if (guestLinks) guestLinks.hidden = true;
            if (typeof loadNotifications === 'function') loadNotifications();
        }

        window.renderGuestHeader = renderGuestHeader;
        window.renderAuthenticatedHeader = renderAuthenticatedHeader;

        // ===== CÁC SỰ KIỆN DROPDOWN =====
        let _notifOpen = false;
        let _userDropOpen = false;

        function positionHeaderDropdown(button, menu, width) {
            const rect = button.getBoundingClientRect();
            const right = Math.min(rect.right, window.innerWidth - 16);
            const maxWidth = Math.min(width, window.innerWidth - 32, right - 16);
            const left = right - maxWidth;

            menu.style.position = 'fixed';
            menu.style.top = `${rect.bottom + 8}px`;
            menu.style.right = 'auto';
            menu.style.left = `${left}px`;
            menu.style.width = `${maxWidth}px`;
            menu.style.maxHeight = `calc(100dvh - ${rect.bottom + 24}px)`;
        }

        function resetHeaderDropdownPosition(menu) {
            menu.style.position = '';
            menu.style.top = '';
            menu.style.right = '';
            menu.style.left = '';
            menu.style.width = '';
            menu.style.maxHeight = '';
        }

        function closeNotificationDropdown() {
            _notifOpen = false;
            const menu = document.getElementById('notif-dropdown');
            if (menu) {
                menu.classList.remove('open');
                resetHeaderDropdownPosition(menu);
            }
            document.getElementById('notif-bell')?.setAttribute('aria-expanded', 'false');
        }

        function toggleNotifDropdown(e) {
            e.stopPropagation();
            const open = !_notifOpen;
            closeUserDropdown();
            closeMobileMenu();
            window.closeShellNavigationMenus?.();
            const dd = document.getElementById('notif-dropdown');
            _notifOpen = open;
            if (open) positionHeaderDropdown(document.getElementById('notif-bell'), dd, 360);
            else resetHeaderDropdownPosition(dd);
            dd.classList.toggle('open', _notifOpen);
            document.getElementById('notif-bell').setAttribute('aria-expanded', String(_notifOpen));
            if (_notifOpen && typeof loadNotifications === 'function') loadNotifications();
        }

        function toggleUserDropdown(e) {
            e.stopPropagation();
            const open = !_userDropOpen;
            closeNotificationDropdown();
            closeMobileMenu();
            window.closeShellNavigationMenus?.();
            _userDropOpen = open;
            const menu = document.getElementById('user-dropdown-menu');
            if (menu) {
                if (_userDropOpen) positionHeaderDropdown(document.getElementById('user-menu-toggle'), menu, 240);
                else resetHeaderDropdownPosition(menu);
                menu.classList.toggle('open', _userDropOpen);
            }
            document.getElementById('user-menu-toggle')?.setAttribute('aria-expanded', String(_userDropOpen));
        }

        function closeUserDropdown() {
            _userDropOpen = false;
            const menu = document.getElementById('user-dropdown-menu');
            if (menu) {
                menu.classList.remove('open');
                resetHeaderDropdownPosition(menu);
            }
            document.getElementById('user-menu-toggle')?.setAttribute('aria-expanded', 'false');
        }

        document.addEventListener('click', function(e) {
            const userWrap = document.getElementById('user-dropdown-wrap');
            if (userWrap && !userWrap.contains(e.target)) closeUserDropdown();
            
            const notifWrap = document.getElementById('notif-bell-wrap');
            if (notifWrap && !notifWrap.contains(e.target)) {
                closeNotificationDropdown();
            }
        });

        // ===== CÁC HÀM TIỆN ÍCH KHÁC =====
        function toggleMobileMenu() {
            const mobileMenu = document.getElementById('mobile-menu');
            const open = !mobileMenu.classList.contains('open');
            if (open) {
                closeUserDropdown();
                closeNotificationDropdown();
                window.closeShellNavigationMenus?.();
            }
            mobileMenu.classList.toggle('open', open);
            const button = document.getElementById('mobile-menu-toggle');
            button.setAttribute('aria-expanded', String(open));
            button.setAttribute('aria-label', open ? 'Đóng menu điều hướng' : 'Mở menu điều hướng');
        }
        function closeMobileMenu() {
            document.getElementById('mobile-menu')?.classList.remove('open');
            const button = document.getElementById('mobile-menu-toggle');
            button?.setAttribute('aria-expanded', 'false');
            button?.setAttribute('aria-label', 'Mở menu điều hướng');
        }
        function showGlobalLoading() { document.getElementById('global-loading').classList.add('show'); }
        function hideGlobalLoading() { document.getElementById('global-loading').classList.remove('show'); }
        function showToast(msg, duration = 3000) {
            const toast = document.getElementById('toast');
            toast.querySelector('span').textContent = msg;
            toast.classList.remove('hidden');
            setTimeout(() => toast.classList.add('hidden'), duration);
        }
        async function api(url, options = {}) {
            const token = localStorage.getItem('token');
            return fetch('/api' + url, { ...options, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', ...(token ? { 'Authorization': 'Bearer ' + token } : {}), ...options.headers } });
        }
        const _layoutApi = api;
        if (token) {
            _layoutApi('/me').then(async response => {
                if (response.ok) {
                    const verifiedUser = await response.json();
                    localStorage.setItem('user', JSON.stringify(verifiedUser));
                    renderAuthenticatedHeader(verifiedUser);
                    return;
                }
                if (response.status === 401 || response.status === 403) {
                    localStorage.removeItem('token');
                    localStorage.removeItem('user');
                    renderGuestHeader();
                    return;
                }
                console.error('Không thể xác minh phiên đăng nhập:', response.status);
            }).catch(error => console.error('Không thể xác minh phiên đăng nhập:', error));
        }
        async function logout() {
            await _layoutApi('/logout', { method: 'POST' });
            localStorage.removeItem('token'); localStorage.removeItem('user');
            window.location.href = '/';
        }
        
        // Hàm này sẽ được gọi sau khi người dùng cập nhật thông tin hồ sơ để đồng bộ avatar mới lên navbar mà không cần reload
        function updateNavAvatar(avatarUrl, name) {
            const wrap = document.getElementById('nav-avatar-wrap');
            if (!wrap) return;
            if (avatarUrl) {
                wrap.innerHTML = `<img id="nav-avatar-img" src="${avatarUrl}" class="w-full h-full object-cover">`;
            } else {
                const initial = (name || '?').charAt(0).toUpperCase();
                wrap.innerHTML = `<span id="nav-avatar-initial">${initial}</span>`;
            }
        }
    </script>

    @include('components.notification-script')
    @if(trim($__env->yieldContent('header-search', 'enabled')) !== 'hidden')
        @include('partials.layout.search-script')
    @endif
    @include('partials.layout.header-script')
    
    {{-- Các Script thêm từ trang con --}}
    @stack('scripts')
</body>
</html>
