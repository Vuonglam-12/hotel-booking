{{-- resources/views/admin/layouts/admin.blade.php --}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — HolidayViet Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* ══════════════════════════════════════
           DESIGN TOKENS
        ══════════════════════════════════════ */
        :root {
            /* Brand */
            --blue:       #2563EB;
            --blue-dark:  #1D4ED8;
            --blue-soft:  #EFF6FF;
            --gold:       #F59E0B;

            /* Sidebar (dark) */
            --sb-bg:      #0F172A;
            --sb-border:  rgba(255,255,255,0.06);
            --sb-hover:   rgba(255,255,255,0.05);
            --sb-active:  rgba(37,99,235,0.18);
            --sb-text:    rgba(255,255,255,0.55);
            --sb-text-hi: rgba(255,255,255,0.92);

            /* Topbar (white) */
            --tb-bg:      #ffffff;
            --tb-border:  #E2E8F0;

            /* Page */
            --bg:         #F1F5F9;
            --dark:       #0F172A;
            --gray:       #64748B;
            --gray-lt:    #94A3B8;
            --border:     #E2E8F0;
            --white:      #ffffff;

            /* Layout */
            --sidebar-w:  232px;
            --topbar-h:   56px;

            /* Shadows */
            --shadow-sm:  0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md:  0 4px 16px rgba(0,0,0,0.08);
            --shadow-lg:  0 20px 40px -8px rgba(0,0,0,0.14);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            height: 100%;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--dark);
            -webkit-font-smoothing: antialiased;
        }

        /* ══════════════════════════════════════
           TOPBAR — trắng, sạch, có border dưới
        ══════════════════════════════════════ */
        .admin-topbar {
            position: fixed; top: 0; left: 0; right: 0;
            height: var(--topbar-h);
            background: var(--tb-bg);
            border-bottom: 1px solid var(--tb-border);
            display: flex; align-items: center;
            padding: 0 20px 0 0;
            z-index: 100;
            box-shadow: var(--shadow-sm);
        }

        /* Brand — nằm trong vùng sidebar */
        .topbar-brand {
            width: var(--sidebar-w);
            flex-shrink: 0;
            display: flex; align-items: center;
            padding: 0 18px;
            height: 100%;
            gap: 10px;
            text-decoration: none;
            border-right: 1px solid var(--tb-border);
        }
        .topbar-brand:hover { opacity: 0.82; }
        .brand-svg { width: 28px; height: 28px; }
        .brand-name {
            font-size: 15px; font-weight: 500;
            color: var(--dark); letter-spacing: -0.01em;
        }
        .brand-name strong { font-weight: 800; color: var(--blue); }
        .brand-tag {
            font-size: 9px; font-weight: 700;
            letter-spacing: 0.7px; text-transform: uppercase;
            color: var(--blue);
            background: var(--blue-soft);
            border: 1px solid rgba(37,99,235,0.2);
            padding: 2px 7px; border-radius: 99px;
        }

        /* Topbar divider */
        .topbar-divider { width: 1px; height: 20px; background: var(--border); margin: 0 16px; }

        /* Search */
        .topbar-search-wrap {
            position: relative;
            display: flex; align-items: center;
        }
        .search-icon {
            position: absolute; left: 11px;
            color: var(--gray-lt); pointer-events: none;
        }
        .topbar-search {
            height: 34px; width: 260px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0 36px 0 32px;
            font-size: 13px; font-family: inherit; color: var(--dark);
            outline: none; transition: all 0.18s;
        }
        .topbar-search::placeholder { color: var(--gray-lt); }
        .topbar-search:focus {
            border-color: var(--blue);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
            width: 300px;
        }
        .search-kbd {
            position: absolute; right: 9px;
            font-size: 10px; font-weight: 600;
            color: var(--gray-lt);
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 1px 5px;
            font-family: inherit;
            pointer-events: none;
        }
        .topbar-search:focus + .search-kbd { display: none; }

        /* Right actions */
        .topbar-actions {
            display: flex; align-items: center; gap: 6px;
            padding-right: 4px;
        }
        .tb-icon-btn {
            width: 34px; height: 34px;
            border-radius: 8px; border: 1px solid var(--border);
            background: var(--white); cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            color: var(--gray); transition: all 0.15s;
            position: relative;
        }
        .tb-icon-btn:hover { background: var(--bg); color: var(--dark); border-color: #CBD5E1; }
        .tb-notif-dot {
            position: absolute; top: 7px; right: 7px;
            width: 6px; height: 6px;
            background: #EF4444; border-radius: 50%;
            border: 1.5px solid var(--white);
        }
        .tb-v-divider { width: 1px; height: 20px; background: var(--border); margin: 0 4px; }

        /* Avatar button */
        .tb-avatar-wrap { position: relative; }
        .tb-avatar-btn {
            display: flex; align-items: center; gap: 9px;
            padding: 5px 10px 5px 6px;
            border: 1px solid var(--border);
            border-radius: 8px; background: var(--white);
            cursor: pointer; transition: all 0.15s;
        }
        .tb-avatar-btn:hover { background: var(--bg); border-color: #CBD5E1; }
        .tb-avatar-btn.active { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37,99,235,0.1); }
        .tb-avatar {
            width: 26px; height: 26px; border-radius: 50%; flex-shrink: 0;
            background: linear-gradient(135deg, #2563EB, #7C3AED);
            color: #fff; font-size: 10px; font-weight: 800;
            display: flex; align-items: center; justify-content: center;
        }
        .tb-avatar-info { display: flex; flex-direction: column; gap: 1px; text-align: left; }
        .tb-avatar-name { font-size: 12px; font-weight: 600; color: var(--dark); white-space: nowrap; max-width: 100px; overflow: hidden; text-overflow: ellipsis; }
        .tb-avatar-role { font-size: 10px; color: var(--gray); }
        .tb-chevron { color: var(--gray-lt); transition: transform 0.2s; }
        .tb-avatar-btn.active .tb-chevron { transform: rotate(180deg); }

        /* Dropdown */
        .tb-dropdown {
            position: absolute; top: calc(100% + 8px); right: 0;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 12px; padding: 6px;
            min-width: 200px;
            box-shadow: var(--shadow-lg);
            z-index: 300;
            opacity: 0; visibility: hidden;
            transform: translateY(-6px) scale(0.98);
            transform-origin: top right;
            transition: all 0.18s cubic-bezier(0.16,1,0.3,1);
        }
        .tb-dropdown.open {
            opacity: 1; visibility: visible;
            transform: translateY(0) scale(1);
        }
        .tb-dropdown-header {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 10px 10px;
        }
        .tb-dropdown-avatar {
            width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
            background: linear-gradient(135deg, #2563EB, #7C3AED);
            color: #fff; font-size: 11px; font-weight: 800;
            display: flex; align-items: center; justify-content: center;
        }
        .tb-dropdown-name { font-size: 13px; font-weight: 700; color: var(--dark); }
        .tb-dropdown-role { font-size: 11px; color: var(--gray); margin-top: 1px; }
        .tb-dropdown-divider { height: 1px; background: var(--border); margin: 4px 0; }
        .tb-dropdown-item {
            display: flex; align-items: center; gap: 9px;
            padding: 8px 10px; border-radius: 7px;
            font-size: 13px; font-weight: 500; color: var(--dark);
            cursor: pointer; transition: background 0.12s;
            text-decoration: none;
        }
        .tb-dropdown-item:hover { background: var(--bg); }
        .tb-dropdown-item svg { color: var(--gray); flex-shrink: 0; }
        .tb-dropdown-danger { color: #EF4444 !important; }
        .tb-dropdown-danger:hover { background: #FEF2F2 !important; }
        .tb-dropdown-danger svg { color: #EF4444 !important; }

        /* ══════════════════════════════════════
           SIDEBAR — đen đậm kiểu Supabase
        ══════════════════════════════════════ */
        .admin-sidebar {
            position: fixed; top: var(--topbar-h); left: 0; bottom: 0;
            width: var(--sidebar-w);
            background: var(--sb-bg);
            border-right: 1px solid var(--sb-border);
            display: flex; flex-direction: column;
            overflow-y: auto; overflow-x: hidden;
            z-index: 90;
            scrollbar-width: none;
        }
        .admin-sidebar::-webkit-scrollbar { display: none; }

        .sb-nav { padding: 12px 10px; flex: 1; }

        .sb-group { margin-bottom: 4px; }
        .sb-group-label {
            font-size: 10px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 0.8px;
            color: rgba(255,255,255,0.25);
            padding: 8px 10px 4px;
            display: block;
        }

        .sb-link {
            display: flex; align-items: center; gap: 9px;
            padding: 7px 10px; border-radius: 7px;
            font-size: 13px; font-weight: 500;
            color: var(--sb-text);
            text-decoration: none;
            transition: all 0.14s;
            position: relative; cursor: pointer;
        }
        .sb-link:hover { background: var(--sb-hover); color: var(--sb-text-hi); }
        .sb-link.active {
            background: var(--sb-active);
            color: #93C5FD;
        }
        .sb-link.active .sb-icon-wrap { color: #60A5FA; }
        .sb-link-dim { opacity: 0.5; }
        .sb-link-dim:hover { opacity: 0.75; }

        .sb-icon-wrap {
            width: 28px; height: 28px;
            display: flex; align-items: center; justify-content: center;
            border-radius: 6px;
            flex-shrink: 0;
            color: rgba(255,255,255,0.4);
            transition: color 0.14s;
        }
        .sb-link:hover .sb-icon-wrap { color: rgba(255,255,255,0.75); }

        .sb-badge {
            margin-left: auto;
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 18px; height: 18px;
            font-size: 10px; font-weight: 700;
            border-radius: 99px; padding: 0 5px;
        }
        .sb-badge-red   { background: #EF4444; color: #fff; }
        .sb-badge-amber { background: #F59E0B; color: #fff; }

        .sb-soon {
            margin-left: auto;
            font-size: 9px; font-weight: 700;
            color: rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            padding: 2px 6px; border-radius: 99px;
            letter-spacing: 0.4px; text-transform: uppercase;
        }

        .sb-separator { height: 1px; background: var(--sb-border); margin: 8px 10px; }

        /* User footer */
        .sb-user {
            border-top: 1px solid var(--sb-border);
            padding: 12px 14px;
            display: flex; align-items: center; gap: 10px;
            cursor: pointer; transition: background 0.14s;
        }
        .sb-user:hover { background: var(--sb-hover); }
        .sb-user-avatar {
            width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
            background: linear-gradient(135deg, #2563EB, #7C3AED);
            color: #fff; font-size: 11px; font-weight: 800;
            display: flex; align-items: center; justify-content: center;
        }
        .sb-user-info { flex: 1; min-width: 0; }
        .sb-user-name { font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.85); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sb-user-role { font-size: 10px; color: rgba(255,255,255,0.3); margin-top: 1px; }
        .sb-user-logout { color: rgba(255,255,255,0.2); flex-shrink: 0; transition: color 0.14s; }
        .sb-user:hover .sb-user-logout { color: rgba(255,255,255,0.5); }

        /* ══════════════════════════════════════
           MAIN CONTENT
        ══════════════════════════════════════ */
        .admin-main {
            margin-left: var(--sidebar-w);
            margin-top: var(--topbar-h);
            min-height: calc(100vh - var(--topbar-h));
            padding: 28px;
            background: var(--bg);
        }

        /* ══════════════════════════════════════
           AUTH OVERLAY
        ══════════════════════════════════════ */
        .auth-overlay {
            position: fixed; inset: 0;
            background: var(--bg);
            display: flex; align-items: center; justify-content: center;
            z-index: 9999; transition: opacity 0.3s;
        }
        .auth-overlay.hide { opacity: 0; pointer-events: none; }
        .auth-spinner {
            width: 34px; height: 34px;
            border: 2.5px solid rgba(37,99,235,0.15);
            border-top-color: var(--blue);
            border-radius: 50%;
            animation: spin 0.65s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ══════════════════════════════════════
           TOAST
        ══════════════════════════════════════ */
        .admin-toast {
            position: fixed; bottom: 24px; right: 24px;
            background: var(--dark); color: #fff;
            padding: 11px 16px; border-radius: 10px;
            font-size: 13px; font-weight: 500;
            box-shadow: var(--shadow-lg);
            z-index: 9999;
            display: none; align-items: center; gap: 8px;
            animation: slideUp 0.25s ease;
            border: 1px solid rgba(255,255,255,0.06);
        }
        .admin-toast.show { display: flex; }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
    </style>

    @stack('styles')
</head>
<body>

    {{-- Auth verify overlay --}}
    <div class="auth-overlay" id="authOverlay">
        <div class="auth-spinner"></div>
    </div>

    {{-- Topbar --}}
    @include('admin.partials.topbar')

    {{-- Sidebar --}}
    @include('admin.partials.sidebar')

    {{-- Main --}}
    <main class="admin-main">
        @yield('content')
    </main>

    {{-- Toast --}}
    <div class="admin-toast" id="adminToast">
        <span id="adminToastMsg"></span>
    </div>

    <script>
        // ══════════════════════════════════════
        // AUTH GUARD — verify token với server
        // ══════════════════════════════════════
        (async function authGuard() {
            const token = localStorage.getItem('admin_token');

            if (!token) {
                window.location.href = '/admin/login';
                return;
            }

            try {
                const res = await fetch('/api/admin/me', {
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json'
                    }
                });

                if (!res.ok) {
                    // Token hết hạn hoặc không hợp lệ
                    localStorage.removeItem('admin_token');
                    localStorage.removeItem('admin_staff');
                    window.location.href = '/admin/login';
                    return;
                }

                const staff = await res.json();

                // Cập nhật lại staff info mới nhất từ server
                localStorage.setItem('admin_staff', JSON.stringify(staff));

                // Render tên + avatar lên UI
                renderStaffUI(staff);

            } catch (err) {
                // Network error — vẫn cho vào nhưng dùng cached data
                const cached = JSON.parse(localStorage.getItem('admin_staff') || '{}');
                if (cached.name) renderStaffUI(cached);
            } finally {
                // Ẩn overlay
                const overlay = document.getElementById('authOverlay');
                overlay.classList.add('hide');
                setTimeout(() => overlay.remove(), 350);
            }
        })();

        // ══ Render staff info vào UI ══
        function renderStaffUI(staff) {
            const initials = (staff.name || 'AD').split(' ').map(w => w[0]).slice(-2).join('').toUpperCase();

            // Topbar avatar
            document.querySelectorAll('.js-avatar-initials').forEach(el => el.textContent = initials);
            document.querySelectorAll('.js-staff-name').forEach(el => el.textContent = staff.name || '—');
            document.querySelectorAll('.js-staff-role').forEach(el => el.textContent = staff.role || '—');
        }

        // ══ Helper: gọi API admin với Bearer token ══
        window.adminApi = function(endpoint, options = {}) {
            const token = localStorage.getItem('admin_token');
            return fetch('/api/admin' + endpoint, {
                ...options,
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    ...(options.headers || {})
                }
            });
        };

        // ══ Toast helper ══
        window.adminToast = function(msg, duration = 3000) {
            const toast = document.getElementById('adminToast');
            document.getElementById('adminToastMsg').textContent = msg;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), duration);
        };

        // ══ Logout ══
        window.adminLogout = async function() {
            try {
                await adminApi('/logout', { method: 'POST' });
            } catch (_) {}
            localStorage.removeItem('admin_token');
            localStorage.removeItem('admin_staff');
            window.location.href = '/admin/login';
        };

        // ══ Avatar dropdown toggle ══
        document.addEventListener('DOMContentLoaded', function() {
            const avatarBtn = document.getElementById('tbAvatarBtn');
            const avatarMenu = document.getElementById('tbAvatarMenu');
            if (avatarBtn && avatarMenu) {
                avatarBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    avatarMenu.classList.toggle('open');
                });
                document.addEventListener('click', function() {
                    avatarMenu.classList.remove('open');
                });
            }
        });
    </script>

    @stack('scripts')
</body>
</html>