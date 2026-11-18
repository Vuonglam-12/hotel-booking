{{-- resources/views/admin/layouts/admin.blade.php --}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — Angel Admin</title>
    <meta name="robots" content="noindex,noarchive">

    <link href="{{ asset('fonts/admin/be-vietnam-pro.css') }}" rel="stylesheet">

    @vite(['resources/css/app.css'])

    <meta name="admin-icon-sprite" content="{{ asset('vendor/lucide/aurel-admin.svg') }}">
    <script>
        try {
            document.documentElement.classList.toggle('sidebar-collapsed', localStorage.getItem('aurel_admin_sidebar_collapsed') === '1');
        } catch (_) {}
    </script>
    @include('admin.partials.styles')

    @include('admin.partials.room-type-validation-assets')
    @stack('styles')
    @include('admin.partials.content-styles')
    @include('admin.partials.semantic-styles')
</head>
<body class="admin-ui">
    <a class="admin-skip-link" href="#adminMain">Đến nội dung chính</a>

    {{-- Auth verify overlay --}}
    <div class="auth-overlay" id="authOverlay" role="status" aria-live="polite">
        <div class="auth-spinner" id="authSpinner" aria-hidden="true"></div>
        <p id="authMessage">Đang xác minh phiên đăng nhập...</p>
        <button type="button" class="auth-retry" id="authRetry" hidden>Thử lại</button>
    </div>

    <div id="admin-shell" hidden>
    {{-- Sidebar --}}
    @include('admin.partials.sidebar')

    {{-- Main --}}
    <main class="admin-main" id="adminMain" tabindex="-1">
        <div class="admin-content-context">
            <button type="button" class="admin-icon-button sb-mobile-open" id="sidebarOpen" aria-label="Mở menu điều hướng" aria-controls="adminSidebar" aria-expanded="false">
                @include('admin.partials.icon', ['name' => 'menu'])
            </button>
            <nav class="admin-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('admin.dashboard') }}">Quản trị</a>
                @include('admin.partials.icon', ['name' => 'arrow-right'])
                <span aria-current="page">@yield('title', 'Dashboard')</span>
            </nav>
        </div>
        <div class="admin-page-body">
            @yield('content')
        </div>
    </main>
    <button type="button" class="sb-backdrop" id="sidebarBackdrop" aria-label="Đóng menu điều hướng" tabindex="-1" hidden></button>
    <div class="sb-tooltip" id="adminSidebarTooltip" role="tooltip" hidden></div>
    </div>

    {{-- Toast --}}
    <div class="admin-toast" id="adminToast" role="status" aria-live="polite">
        <span id="adminToastIcon" aria-hidden="true"></span>
        <span id="adminToastMsg"></span>
        <button type="button" class="admin-toast-close" id="adminToastClose" aria-label="Đóng thông báo">×</button>
    </div>
    <dialog class="admin-confirm" id="adminConfirm" aria-labelledby="adminConfirmTitle" aria-describedby="adminConfirmMessage" data-admin-tone="danger">
        <span class="admin-confirm-icon" id="adminConfirmIcon" aria-hidden="true"></span>
        <h2 id="adminConfirmTitle">Xác nhận thao tác</h2>
        <p id="adminConfirmMessage"></p>
        <div class="admin-confirm-actions">
            <button type="button" class="admin-action" data-admin-action="cancel" data-admin-tone="neutral" id="adminConfirmCancel">Hủy</button>
            <button type="button" class="admin-action admin-confirm-submit" id="adminConfirmSubmit">Xác nhận</button>
        </div>
    </dialog>

    <script>
        // ══════════════════════════════════════
        // AUTH GUARD — verify token với server
        // ══════════════════════════════════════
        let adminVerified = false;

        function clearAdminSession() {
            localStorage.removeItem('admin_token');
            localStorage.removeItem('admin_account');
        }

        function rejectAdminSession() {
            adminVerified = false;
            document.getElementById('admin-shell').hidden = true;
            document.getElementById('authOverlay').classList.remove('hide');
            clearAdminSession();
            window.location.replace('/admin/login');
        }

        window.adminAuthReady = (async function authGuard() {
            const token = localStorage.getItem('admin_token');

            if (!token) {
                rejectAdminSession();
                return false;
            }

            try {
                const res = await fetch('/api/admin/me', {
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json'
                    }
                });

                if (res.status === 401 || res.status === 403) {
                    rejectAdminSession();
                    return false;
                }

                if (!res.ok) throw new Error('Admin verification unavailable');

                const account = await res.json();
                if (account.role !== 'admin') throw new Error('Invalid admin verification response');

                localStorage.setItem('admin_account', JSON.stringify(account));
                renderAdminUI(account);
                adminVerified = true;
                document.getElementById('admin-shell').hidden = false;
                document.getElementById('authOverlay').classList.add('hide');
                return true;

            } catch (err) {
                // Preserve the token on temporary failures, but keep the Admin UI locked.
                document.getElementById('authSpinner').hidden = true;
                document.getElementById('authMessage').textContent = 'Chưa thể xác minh phiên đăng nhập. Vui lòng kiểm tra kết nối và thử lại.';
                document.getElementById('authRetry').hidden = false;
                return false;
            }
        })();

        document.getElementById('authRetry').addEventListener('click', () => window.location.reload());

        // ══ Render Admin info into the existing UI ══
        function renderAdminUI(account) {
            const initials = (account.name || 'AD').split(' ').map(w => w[0]).slice(-2).join('').toUpperCase();

            // Topbar avatar
            document.querySelectorAll('.js-avatar-initials').forEach(el => el.textContent = initials);
            document.querySelectorAll('.js-admin-name').forEach(el => el.textContent = account.name || '—');
            document.querySelectorAll('.js-admin-role').forEach(el => el.textContent = account.role || '—');
        }

        // ══ Helper: gọi API admin với Bearer token ══
        window.adminApi = async function(endpoint, options = {}) {
            if (!await window.adminAuthReady || !adminVerified) {
                throw new Error('Phiên Admin chưa được xác minh');
            }
            const token = localStorage.getItem('admin_token');
            const response = await fetch('/api/admin' + endpoint, {
                ...options,
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Accept': 'application/json',
                    ...(typeof FormData !== 'undefined' && options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    ...(options.headers || {})
                }
            });
            if (response.status === 401 || response.status === 403) rejectAdminSession();
            return response;
        };

        // ══ Logout ══
        window.adminLogout = async function() {
            try {
                await adminApi('/logout', { method: 'POST' });
            } catch (_) {}
            rejectAdminSession();
        };

    </script>

    @include('admin.partials.navigation-script')
    @include('admin.partials.semantic-script')
    @stack('scripts')
</body>
</html>
