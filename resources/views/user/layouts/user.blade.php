<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Tài khoản') — HolidayViet</title>

    {{-- Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- Icons --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    @yield('styles')

    <style>
        /* ============================================================
           CSS VARIABLES – đồng bộ với Home (xanh dương + vàng accent)
        ============================================================ */
        :root {
            --primary:    #3B82F6;    /* xanh dương logo */
            --primary-light: #60a5fa;
            --primary-dark:  #2563eb;
            --accent:     #FBBF24;    /* vàng cam */
            --accent-light: #fcd34d;
            --cyan-400:   #22d3ee;
            
            --bg-main:     #F0F9FF;    /* xanh nhạt như home */
            --bg-sidebar:  #ffffff;
            --bg-card:     #ffffff;

            --text-primary:   #0f172a;
            --text-secondary: #475569;
            --text-muted:     #94a3b8;

            --border:      #e2e8f0;
            --border-light: #f1f5f9;

            --sidebar-w:   260px;
            --topbar-h:    68px;

            --shadow-sm:   0 1px 3px rgba(0,0,0,.05), 0 1px 2px rgba(0,0,0,.03);
            --shadow-md:   0 4px 16px rgba(0,0,0,.06);
            --shadow-lg:   0 8px 32px rgba(0,0,0,.1);

            --radius:      14px;
            --radius-sm:   8px;
            --radius-lg:   20px;

            --font-heading: 'Nunito Sans', system-ui, sans-serif;
            --font-body:    'Nunito Sans', system-ui, sans-serif;

            --transition:  all .22s cubic-bezier(.4,0,.2,1);
        }

        /* ============================================================
           RESET & BASE
        ============================================================ */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: var(--font-body);
            font-size: 14px;
            color: var(--text-primary);
            background: var(--bg-main);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }
        a { text-decoration: none; color: inherit; }
        button { cursor: pointer; border: none; background: none; font-family: var(--font-body); }
        input, textarea, select { font-family: var(--font-body); }

        /* ============================================================
           LAYOUT SHELL
        ============================================================ */
        .user-layout { display: flex; min-height: 100vh; }

        /* ============================================================
           SIDEBAR (nền trắng, bóng nhẹ)
        ============================================================ */
        .sidebar {
            position: fixed;
            top: 0; left: 0;
            width: var(--sidebar-w);
            height: 100vh;
            background: var(--bg-sidebar);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: var(--transition);
            overflow: hidden;
        }

        /* Logo – giống hệt trang chủ */
        .sidebar-logo {
            padding: 22px 24px 18px;
            border-bottom: 1px solid var(--border-light);
            flex-shrink: 0;
        }
        .logo-link {
            display: flex;
            align-items: center;
            gap: 10px;
            transition: opacity 0.2s;
        }
        .logo-link:hover { opacity: 0.85; }
        .logo-svg {
            width: 36px;
            height: 36px;
            filter: drop-shadow(0 1px 2px rgba(0,0,0,0.1));
        }
        .logo-text {
            display: flex;
            align-items: baseline;
            letter-spacing: -0.02em;
            font-family: var(--font-heading);
            font-weight: 700;
            font-size: 20px;
        }
        .logo-text-holiday {
            color: #0f172a;
        }
        .logo-text-viet {
            color: var(--primary);
            margin-left: 2px;
        }

        /* User card */
        .sidebar-user {
            padding: 14px 14px 10px;
            border-bottom: 1px solid var(--border-light);
            flex-shrink: 0;
        }
        .sidebar-avatar-wrap {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 12px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: var(--transition);
        }
        .sidebar-avatar-wrap:hover { background: var(--border-light); }

        .sidebar-avatar {
            width: 44px; height: 44px;
            border-radius: 50%; object-fit: cover;
            flex-shrink: 0;
            border: 2px solid var(--border);
            display: none;
        }
        .sidebar-avatar-initial {
            width: 44px; height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; font-weight: 600; color: #fff;
            flex-shrink: 0;
        }
        .sidebar-user-info { overflow: hidden; }
        .sidebar-user-name {
            font-size: 13.5px; font-weight: 600; color: var(--text-primary);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .sidebar-user-email {
            font-size: 11.5px; color: var(--text-muted);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            margin-top: 1px;
        }

        /* Nav */
        .sidebar-nav {
            flex: 1;
            padding: 10px 12px 8px;
            overflow-y: auto;
        }
        .sidebar-nav-label {
            font-size: 10px; font-weight: 600;
            text-transform: uppercase; letter-spacing: .08em;
            color: var(--text-muted);
            padding: 10px 12px 6px;
        }
        .sidebar-nav-item {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            font-size: 13.5px; font-weight: 500;
            transition: var(--transition);
            margin-bottom: 2px;
            position: relative;
        }
        .sidebar-nav-item:hover {
            background: var(--border-light);
            color: var(--text-primary);
        }
        .sidebar-nav-item.active {
            background: rgba(59,130,246,0.08);
            color: var(--primary);
            font-weight: 600;
        }
        .sidebar-nav-item.active::before {
            content: '';
            position: absolute; left: 0; top: 50%; transform: translateY(-50%);
            width: 3px; height: 60%;
            background: var(--primary);
            border-radius: 0 4px 4px 0;
        }
        .sidebar-nav-icon {
            width: 36px; height: 36px;
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            font-size: 15px; flex-shrink: 0;
            background: var(--border-light);
            transition: var(--transition);
        }
        .sidebar-nav-item:hover .sidebar-nav-icon,
        .sidebar-nav-item.active .sidebar-nav-icon {
            background: linear-gradient(135deg, var(--primary), var(--cyan-400));
            color: #fff;
        }

        /* Logout */
        .sidebar-footer {
            padding: 12px;
            border-top: 1px solid var(--border-light);
            flex-shrink: 0;
        }
        .sidebar-logout {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            color: #ef4444; font-size: 13.5px; font-weight: 500;
            transition: var(--transition); width: 100%;
        }
        .sidebar-logout:hover { background: #fef2f2; }
        .sidebar-logout .sidebar-nav-icon { background: #fee2e2; color: #ef4444; }
        .sidebar-logout:hover .sidebar-nav-icon { background: #ef4444; color: #fff; }

        /* ============================================================
           TOPBAR – giống header home (trắng, trong suốt, bóng nhẹ)
        ============================================================ */
        .topbar {
            position: fixed;
            top: 0; left: var(--sidebar-w); right: 0;
            height: var(--topbar-h);
            background: #ffffff;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            z-index: 90;
            gap: 16px;
            box-shadow: var(--shadow-sm);
        }
        .topbar-left { display: flex; align-items: center; gap: 12px; }
        .topbar-hamburger {
            display: none;
            width: 36px; height: 36px;
            border-radius: var(--radius-sm);
            align-items: center; justify-content: center;
            color: var(--text-secondary); font-size: 18px;
            transition: var(--transition);
        }
        .topbar-hamburger:hover { background: var(--border-light); color: var(--text-primary); }
        .topbar-title {
            font-family: var(--font-heading);
            font-size: 20px; font-weight: 600;
            background: linear-gradient(135deg, var(--text-primary), var(--primary));
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .topbar-right { display: flex; align-items: center; gap: 8px; }

        /* Nút Home & Admin */
        .topbar-btn {
            display: flex; align-items: center; gap: 7px;
            padding: 8px 14px;
            border-radius: var(--radius-sm);
            font-size: 13px; font-weight: 500;
            color: var(--text-secondary);
            border: 1px solid var(--border);
            transition: var(--transition);
        }
        .topbar-btn:hover {
            background: var(--border-light);
            color: var(--primary);
            border-color: var(--primary-light);
        }

        .topbar-admin-btn {
            display: none;
            align-items: center; gap: 7px;
            padding: 8px 14px;
            border-radius: var(--radius-sm);
            font-size: 13px; font-weight: 500;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            transition: var(--transition);
            box-shadow: 0 2px 6px rgba(59,130,246,0.3);
        }
        .topbar-admin-btn:hover {
            opacity: .9;
            box-shadow: 0 4px 12px rgba(59,130,246,0.4);
            transform: translateY(-1px);
        }


        .topbar-avatar-initial {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; font-weight: 600; color: #fff; flex-shrink: 0;
        }
        .topbar-user-name {
            font-size: 13px; font-weight: 500; color: var(--text-primary);
            max-width: 120px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        /* ============================================================
           MAIN CONTENT
        ============================================================ */
        .main-content {
            margin-left: var(--sidebar-w);
            margin-top: var(--topbar-h);
            min-height: calc(100vh - var(--topbar-h));
            padding: 32px 28px;
            flex: 1;
        }

        /* ============================================================
           TOAST (đồng bộ màu xanh – vàng)
        ============================================================ */
        .toast-container {
            position: fixed; bottom: 24px; right: 24px;
            z-index: 9999;
            display: flex; flex-direction: column; gap: 10px;
            pointer-events: none;
        }
        .toast {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 18px;
            background: #fff;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            border-left: 4px solid var(--primary);
            min-width: 280px; max-width: 360px;
            pointer-events: all;
            animation: toastIn .3s cubic-bezier(.34,1.56,.64,1);
        }
        .toast.toast-success { border-left-color: #22c55e; }
        .toast.toast-error   { border-left-color: #ef4444; }
        .toast.toast-warning { border-left-color: var(--accent); }
        .toast-icon {
            width: 32px; height: 32px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; flex-shrink: 0;
        }
        .toast-success .toast-icon { background: #dcfce7; color: #16a34a; }
        .toast-error   .toast-icon { background: #fee2e2; color: #dc2626; }
        .toast-warning .toast-icon { background: #fef9c3; color: #ca8a04; }
        .toast:not(.toast-success):not(.toast-error):not(.toast-warning) .toast-icon {
            background: rgba(59,130,246,.12); color: var(--primary);
        }
        .toast-body { flex: 1; }
        .toast-title { font-size: 13px; font-weight: 600; color: var(--text-primary); }
        .toast-msg   { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
        .toast-close { color: var(--text-muted); font-size: 16px; line-height: 1; padding: 2px; flex-shrink: 0; }
        .toast-close:hover { color: var(--text-primary); }
        .toast.hide { animation: toastOut .25s ease forwards; }
        @keyframes toastIn {
            from { opacity: 0; transform: translateX(100%) scale(.9); }
            to   { opacity: 1; transform: translateX(0) scale(1); }
        }
        @keyframes toastOut {
            from { opacity: 1; transform: translateX(0); }
            to   { opacity: 0; transform: translateX(100%); }
        }

        /* ============================================================
           CONFIRM MODAL
        ============================================================ */
        .modal-overlay {
            position: fixed; inset: 0;
            background: rgba(15,23,42,.45);
            backdrop-filter: blur(4px);
            z-index: 1000;
            display: none; 
            align-items: center; 
            justify-content: center;
            opacity: 0; visibility: hidden;
            transition: var(--transition);
        }

        /* Thêm class open để show */
        .modal-overlay.open {
            display: flex;
        }
        
        .modal-overlay.open { opacity: 1; visibility: visible; }
        .modal-box {
            background: #fff;
            border-radius: var(--radius-lg);
            padding: 28px;
            width: 100%; max-width: 400px;
            box-shadow: var(--shadow-lg);
            transform: scale(.95) translateY(10px);
            transition: var(--transition);
        }
        .modal-overlay.open .modal-box { transform: scale(1) translateY(0); }
        .modal-icon {
            width: 52px; height: 52px; border-radius: 50%;
            background: #fee2e2; color: #ef4444;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; margin: 0 auto 16px;
        }
        .modal-icon.warning { background: #fef9c3; color: var(--accent); }
        .modal-title {
            font-family: var(--font-heading);
            font-size: 18px; font-weight: 600;
            text-align: center; color: var(--text-primary);
        }
        .modal-desc {
            font-size: 13.5px; color: var(--text-secondary);
            text-align: center; margin-top: 8px; line-height: 1.6;
        }
        .modal-actions { display: flex; gap: 10px; margin-top: 24px; }
        .modal-btn {
            flex: 1; padding: 11px;
            border-radius: var(--radius-sm);
            font-size: 13.5px; font-weight: 600;
            transition: var(--transition);
        }
        .modal-btn-cancel { border: 1.5px solid var(--border); color: var(--text-secondary); }
        .modal-btn-cancel:hover { background: var(--border-light); }
        .modal-btn-confirm {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            box-shadow: 0 2px 8px rgba(59,130,246,0.3);
        }
        .modal-btn-confirm:hover {
            box-shadow: 0 4px 16px rgba(59,130,246,0.4);
            transform: translateY(-1px);
        }

        /* ============================================================
           MOBILE & RESPONSIVE
        ============================================================ */
        .sidebar-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(15,23,42,.4);
            z-index: 99;
            backdrop-filter: blur(2px);
        }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); box-shadow: var(--shadow-lg); }
            .sidebar-overlay { display: block; opacity: 0; visibility: hidden; transition: var(--transition); }
            .sidebar-overlay.open { opacity: 1; visibility: visible; }
            .topbar { left: 0; }
            .topbar-hamburger { display: flex; }
            .topbar-user-name { display: none; }
            .main-content { margin-left: 0; padding: 20px 16px; }
        }
        @media (max-width: 480px) {
            .topbar { padding: 0 16px; }
            .topbar-btn span { display: none; }
            .topbar-btn { padding: 8px 10px; }
        }
    </style>
</head>
<body>

{{-- ============================================================
     SIDEBAR
============================================================ --}}
<aside class="sidebar" id="sidebar">

    {{-- Logo --}}
    <div class="sidebar-logo">
        <a href="{{ route('home') }}" class="logo-link">
            <div class="logo-svg">
                <svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M20 80C20 80 25 25 55 10C55 10 45 40 45 80H20Z" fill="#ffffff" stroke="#3B82F6" stroke-width="1.5"/>
                    <path d="M50 75C50 75 55 35 75 20C75 20 65 45 65 75H50Z" fill="#3B82F6"/>
                    <path d="M40 85C60 85 85 65 85 35" stroke="#FBBF24" stroke-width="3" stroke-linecap="round"/>
                    <path d="M55 70L65 75M60 60L75 65M68 50L82 52M75 40L88 38" stroke="#FBBF24" stroke-width="3" stroke-linecap="round"/>
                    <path d="M15 85C35 78 65 78 85 85" stroke="#FBBF24" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </div>
            <div class="logo-text">
                <span class="logo-text-holiday">Holiday</span>
                <span class="logo-text-viet">Viet</span>
            </div>
        </a>
    </div>

    {{-- User card --}}
    <div class="sidebar-user">
        <a href="/profile" class="sidebar-avatar-wrap">
            <img src="" alt="Avatar" class="sidebar-avatar" id="sidebarAvatarImg">
            <div class="sidebar-avatar-initial" id="sidebarAvatarInitial">?</div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"  id="sidebarUserName">Đang tải...</div>
                <div class="sidebar-user-email" id="sidebarUserEmail"></div>
            </div>
        </a>
    </div>

    {{-- Nav --}}
    <nav class="sidebar-nav">
        <div class="sidebar-nav-label">Tài khoản</div>

        <a href="/profile" class="sidebar-nav-item" data-nav="profile">
            <div class="sidebar-nav-icon"><i class="fa-regular fa-user"></i></div>
            Thông tin cá nhân
        </a>

        <a href="/password" class="sidebar-nav-item" data-nav="password">
            <div class="sidebar-nav-icon"><i class="fa-solid fa-lock"></i></div>
            Đổi mật khẩu
        </a>

        <div class="sidebar-nav-label" style="margin-top:8px;">Hoạt động</div>

        <a href="/bookings" class="sidebar-nav-item" data-nav="bookings">
            <div class="sidebar-nav-icon"><i class="fa-regular fa-calendar-check"></i></div>
            Lịch sử đặt phòng
        </a>

        <a href="/itineraries" class="sidebar-nav-item" data-nav="itineraries">
            <div class="sidebar-nav-icon"><i class="fa-solid fa-route"></i></div>
            Lịch trình AI
        </a>

        <div class="sidebar-nav-label" style="margin-top:8px;">Hệ thống</div>

        <a href="/settings" class="sidebar-nav-item" data-nav="settings">
            <div class="sidebar-nav-icon"><i class="fa-solid fa-gear"></i></div>
            Cài đặt
        </a>

        {{-- Chỉ hiện khi is_admin = true --}}
        <a href="/admin" class="sidebar-nav-item" data-nav="admin" id="adminNavItem" style="display:none; margin-top:4px; background:rgba(239,68,68,0.07); color:#dc2626;">
            <div class="sidebar-nav-icon" style="color:#dc2626;"><i class="fa-solid fa-shield-halved"></i></div>
            Quản trị Admin
        </a>
        
    </nav>

    {{-- Logout --}}
    <div class="sidebar-footer">
        <button class="sidebar-logout" onclick="confirmLogout()">
            <div class="sidebar-nav-icon"><i class="fa-solid fa-arrow-right-from-bracket"></i></div>
            Đăng xuất
        </button>
    </div>
</aside>

{{-- Mobile overlay --}}
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

{{-- MAIN CONTENT --}}
<main class="main-content">
    @yield('content')
</main>

{{-- TOAST --}}
<div class="toast-container" id="toastContainer"></div>

{{-- CONFIRM MODAL --}}
<div class="modal-overlay" id="confirmModal">
    <div class="modal-box">
        <div class="modal-icon" id="confirmModalIcon"><i class="fa-solid fa-triangle-exclamation"></i></div>
        <div class="modal-title" id="confirmModalTitle">Xác nhận</div>
        <div class="modal-desc"  id="confirmModalDesc"></div>
        <div class="modal-actions">
            <button class="modal-btn modal-btn-cancel" onclick="closeConfirmModal()">Hủy</button>
            <button class="modal-btn modal-btn-confirm" id="confirmModalOkBtn">Xác nhận</button>
        </div>
    </div>
</div>

<script>
    /* ============================================================
    AUTH GUARD & GLOBAL FUNCTIONS (giữ nguyên logic cũ)
    Chỉ thay đổi màu sắc, không ảnh hưởng chức năng
    ============================================================ */
    (function() {
        const token = localStorage.getItem('token');
        if (!token) { window.location.href = '/login'; return; }
        try {
            const user = JSON.parse(localStorage.getItem('user') || '{}');
            if (!user.id) window.location.href = '/login';
        } catch(e) { window.location.href = '/login'; }
    })();

    function api(path, opts = {}) {
        return fetch('/api' + path, {
            ...opts,
            headers: {
                'Content-Type':  'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('token'),
                'Accept':        'application/json',
                ...(opts.headers || {})
            }
        });
    }

    function loadUserUI() {
        const user = JSON.parse(localStorage.getItem('user') || '{}');
        if (user.name) {
            const initial = user.name.charAt(0).toUpperCase();

            // Sidebar — luôn có
            const sidebarName    = document.getElementById('sidebarUserName');
            const sidebarEmail   = document.getElementById('sidebarUserEmail');
            const sidebarImg     = document.getElementById('sidebarAvatarImg');
            const sidebarInitial = document.getElementById('sidebarAvatarInitial');

            // Topbar — có thể không có (optional)
            const topbarName     = document.getElementById('topbarUserName');
            const topbarImg      = document.getElementById('topbarAvatarImg');
            const topbarInitial  = document.getElementById('topbarAvatarInitial');

            if (sidebarName)  sidebarName.textContent  = user.name;
            if (sidebarEmail) sidebarEmail.textContent = user.email || '';
            if (topbarName)   topbarName.textContent   = user.name; // ← không crash nếu null

            if (user.avatar_url) {
                if (sidebarImg)     { sidebarImg.src = user.avatar_url; sidebarImg.style.display = 'block'; }
                if (sidebarInitial)   sidebarInitial.style.display = 'none';
                if (topbarImg)      { topbarImg.src = user.avatar_url; topbarImg.style.display = 'block'; }
                if (topbarInitial)    topbarInitial.style.display = 'none';
            } else {
                if (sidebarInitial) sidebarInitial.textContent = initial;
                if (topbarInitial)  topbarInitial.textContent  = initial;
            }
        }

        const adminNavItem = document.getElementById('adminNavItem');
        if (adminNavItem) {
            adminNavItem.style.display = (user.is_admin === true) ? 'flex' : 'none';
        }
    }

    function setActiveNav() {
        const path = window.location.pathname;
        const hash = window.location.hash;
        document.querySelectorAll('.sidebar-nav-item[data-nav]').forEach(el => el.classList.remove('active'));

        let active = 'profile';
        if (path.includes('/bookings'))    active = 'bookings';
        if (path.includes('/itineraries')) active = 'itineraries';
        if (path.includes('/settings'))    active = 'settings';
        if (hash === '#password')          active = 'password';

        const el = document.querySelector(`[data-nav="${active}"]`);
        if (el) el.classList.add('active');
    }

    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('open');
    }
    function closeSidebar() {
        document.getElementById('sidebar').classList.remove('open');
        document.getElementById('sidebarOverlay').classList.remove('open');
    }

    function showToast(message, type = 'info', subtitle = '') {
        const icons = {
            success: 'fa-check',
            error:   'fa-xmark',
            warning: 'fa-triangle-exclamation',
            info:    'fa-circle-info',
        };
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <div class="toast-icon"><i class="fa-solid ${icons[type] || icons.info}"></i></div>
            <div class="toast-body">
                <div class="toast-title">${message}</div>
                ${subtitle ? `<div class="toast-msg">${subtitle}</div>` : ''}
            </div>
            <button class="toast-close" onclick="this.closest('.toast').remove()"><i class="fa-solid fa-xmark"></i></button>
        `;
        document.getElementById('toastContainer').appendChild(toast);
        setTimeout(() => {
            toast.classList.add('hide');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    let _confirmCallback = null;
    function showConfirmModal(title, desc, onConfirm, options = {}) {
        document.getElementById('confirmModalTitle').textContent = title;
        document.getElementById('confirmModalDesc').textContent  = desc;
        _confirmCallback = onConfirm;
        const okBtn = document.getElementById('confirmModalOkBtn');
        okBtn.textContent = options.confirmText || 'Xác nhận';
        const icon = document.getElementById('confirmModalIcon');
        icon.className = options.type === 'warning' ? 'modal-icon warning' : 'modal-icon';
        icon.innerHTML = options.type === 'warning'
            ? '<i class="fa-solid fa-triangle-exclamation"></i>'
            : '<i class="fa-solid fa-trash"></i>';
        document.getElementById('confirmModal').classList.add('open');
    }
    function closeConfirmModal() {
        document.getElementById('confirmModal').classList.remove('open');
        _confirmCallback = null;
    }
    document.getElementById('confirmModalOkBtn').addEventListener('click', function() {
        if (_confirmCallback) _confirmCallback();
        closeConfirmModal();
    });
    document.getElementById('confirmModal').addEventListener('click', function(e) {
        if (e.target === this) closeConfirmModal();
    });

    function confirmLogout() {
        showConfirmModal('Đăng xuất', 'Bạn có chắc muốn đăng xuất khỏi tài khoản không?', doLogout, { confirmText: 'Đăng xuất', type: 'warning' });
    }
    async function doLogout() {
        try { await api('/auth/logout', { method: 'POST' }); } catch(e) {}
        localStorage.removeItem('token');
        localStorage.removeItem('user');
        window.location.href = '/login';
    }

    document.addEventListener('DOMContentLoaded', function() {
        loadUserUI();
        setActiveNav();
    });
</script>

@yield('scripts')

</body>
</html>