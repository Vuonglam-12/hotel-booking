<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'HolidayViet') — Đặt Phòng Thông Minh</title>

    {{-- System typography: friendly, rounded sans-serif --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- Swiper CSS (Dành cho hiệu ứng Carousel) --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    {{-- Vite compile Tailwind CSS --}}
    @vite(['resources/css/app.css'])

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

        /* =========================================================
           3. NAVBAR DARK GLASS
           ========================================================= */
        .navbar-glass {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s ease;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.875rem; font-weight: 500;
            display: flex; align-items: center; gap: 6px;
            padding: 8px 12px; border-radius: 8px;
            transition: all 0.2s;
        }
        .nav-link:hover { color: white; background: rgba(255, 255, 255, 0.1); }

        /* Dropdown chung (Kính trắng) */
        .nav-dropdown { position: relative; }

        .nav-dropdown-menu {
            display: none; position: absolute; top: calc(100%); left: 50%; transform: translateX(-50%);
            width: 220px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 16px; padding: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            z-index: 100;
        }
        .nav-dropdown:hover .nav-dropdown-menu { display: block; animation: dropFade 0.2s ease; }
        .dropdown-item {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px; border-radius: 10px;
            color: var(--text-main); font-size: 0.875rem; font-weight: 500;
            transition: all 0.2s;
        }

        .dropdown-item:hover { background: rgba(59, 130, 246, 0.1); color: var(--accent-primary); }
        @keyframes dropFade { from { opacity: 0; transform: translate(-50%, 10px); } to { opacity: 1; transform: translate(-50%, 0); } }

        /* Dropdown Scroll Spy */
        .dropdown-sticky-header {
            position: sticky; top: 0; background: rgba(255,255,255,0.9); backdrop-filter: blur(5px);
            z-index: 5; padding: 8px 12px; margin: -8px -8px 4px -8px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--accent-primary);
        }
        .active-scroll-spy { background: rgba(59, 130, 246, 0.1) !important; color: var(--accent-primary) !important; border-left: 3px solid var(--accent-primary); padding-left: 9px; }

        /* =========================================================
           4. NOTIFICATION BELL 
           ========================================================= */
        .notif-bell-btn {
            position: relative; width: 38px; height: 38px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: rgba(255,255,255,0.8); background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.2s; cursor: pointer;
        }
        .notif-bell-btn:hover { background: rgba(255,255,255,0.15); color: white; }
        .notif-badge {
            position: absolute; top: -2px; right: -2px;
            min-width: 18px; height: 18px; padding: 0 4px;
            background: #EF4444; border-radius: 9px;
            font-size: 10px; font-weight: 700; color: white;
            display: none; align-items: center; justify-content: center;
            border: 2px solid #0F172A;
        }
        .notif-badge.show { display: flex; }

        .notif-dropdown {
            position: absolute;
            top: calc(100% + 12px); 
            right: 1;
            width: 360px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            border: 1px solid rgba(255,255,255,0.6);
            z-index: 9999; display: none; overflow: hidden; transform-origin: top right;
        }
        .notif-dropdown.open { display: block; animation: notifPop 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        @keyframes notifPop { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        
        .notif-item {
            display: flex; align-items: flex-start; gap: 12px; padding: 14px 16px;
            border-bottom: 1px solid rgba(0,0,0,0.05); cursor: pointer; transition: background 0.15s;
        }
        .notif-item:hover { background: rgba(59, 130, 246, 0.05); }
        .notif-item.unread { background: rgba(59, 130, 246, 0.08); }
        .notif-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--accent-primary); flex-shrink: 0; margin-top: 6px; }
        .notif-item.read .notif-dot { background: transparent; border: 2px solid #CBD5E1; }

        /* =========================================================
           5. MISC (Footer, Mobile Menu, Toast, Loading)
           ========================================================= */
        footer { background: rgba(15, 23, 42, 0.95); border-top: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.7); }
        #mobile-menu { display: none; background: rgba(15, 23, 42, 0.95); border-top: 1px solid rgba(255,255,255,0.1); }
        #mobile-menu.open { display: block; }
        .mobile-nav-link {
            display: flex; align-items: center; gap: 12px; padding: 12px 20px;
            color: rgba(255,255,255,0.8); font-size: 14px; font-weight: 500;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .mobile-nav-link:hover { background: rgba(255,255,255,0.05); color: white; }

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
</head>

<body>
    {{-- NAVBAR --}}
    <nav class="navbar-glass fixed top-0 left-0 right-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">

                {{-- Logo --}}
                <a href="{{ route('home') }}" class="flex items-center gap-2 hover:opacity-80 transition click-effect">
                    <div class="relative w-9 h-9" style="filter: drop-shadow(0 1px 3px rgba(0,0,0,0.4));">
                        <svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full filter drop-shadow-sm">
                            <path d="M20 80C20 80 25 25 55 10C55 10 45 40 45 80H20Z" fill="rgba(255,255,255,0.90)"/>
                            <path d="M50 75C50 75 55 35 75 20C75 20 65 45 65 75H50Z" fill="#2563EB"/>
                            <path d="M40 85C60 85 85 65 85 35" stroke="#F59E0B" stroke-width="3" stroke-linecap="round"/>
                            <path d="M55 70L65 75M60 60L75 65M68 50L82 52M75 40L88 38" stroke="#F59E0B" stroke-width="2.5" stroke-linecap="round"/>
                            <path d="M15 85C35 78 65 78 85 85" stroke="#F59E0B" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>

                    <div class="flex items-baseline tracking-tight">
                        <span class="font-display text-2xl font-bold text-white">Holiday</span>
                        <span class="font-display text-2xl font-bold ml-0.5" style="color:#60A5FA">Viet</span>
                    </div>
                </a>

                {{-- Nav Links Desktop --}}
                <div class="hidden lg:flex items-center gap-6">
                    <!-- LINK VỀ CHÚNG TÔI ĐÃ FIX /#about -->
                    <a href="/#about" class="nav-link">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                        Về chúng tôi
                    </a>

                    <a href="{{ route('home') }}" class="nav-link">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        Khách sạn
                    </a>

                    {{-- Địa điểm dropdown --}}
                    <div class="nav-dropdown">
                        <button class="nav-link bg-transparent border-0 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Địa điểm <svg class="w-3 h-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div class="nav-dropdown-menu custom-scroll">
                            <a href="#" data-city="Hà Nội" class="dropdown-item city-filter-link">Hà Nội</a>
                            <a href="#" data-city="Hồ Chí Minh" class="dropdown-item city-filter-link">Hồ Chí Minh</a>
                            <a href="#" data-city="Đà Nẵng" class="dropdown-item city-filter-link">Đà Nẵng</a>
                            <a href="#" data-city="Phú Quốc" class="dropdown-item city-filter-link">Phú Quốc</a>
                            <a href="#" data-city="Nha Trang" class="dropdown-item city-filter-link">Nha Trang</a>
                            <a href="#" data-city="Hội An" class="dropdown-item city-filter-link">Hội An</a>
                            <a href="#" data-city="Đà Lạt" class="dropdown-item city-filter-link">Đà Lạt</a>
                            <a href="#" data-city="Huế" class="dropdown-item city-filter-link">Huế</a>
                        </div>
                    </div>  

                    <a href="/deals" class="nav-link">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                        Ưu đãi <span class="bg-amber-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded ml-1">HOT</span>
                    </a>

                    {{-- Khám phá dropdown --}}
                    <div class="nav-dropdown">
                        
                        <button class="nav-link bg-transparent border-0 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/></svg>
                            Khám phá <svg class="w-3 h-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </button>

                        <div class="nav-dropdown-menu">
                            <a href="/blog" class="dropdown-item">Blog / Tin tức</a>
                            <a href="/contact" class="dropdown-item">Liên hệ</a>
                        </div>
                    </div>
                </div>

                {{-- Right: Auth + Notification Bell --}}
                <div class="flex items-center gap-3" id="nav-auth">
                    <a href="{{ route('login') }}" class="text-slate-300 hover:text-white text-sm font-medium transition">Đăng nhập</a>
                    <a href="{{ route('register') }}" class="btn-primary px-5 py-2 text-sm click-effect">Đăng ký</a>
                </div>

                {{-- Mobile hamburger --}}
                <button onclick="toggleMobileMenu()" class="lg:hidden text-slate-300 hover:text-white ml-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </div>

        {{-- Mobile Menu --}}
        <div id="mobile-menu">
            <a href="{{ route('home') }}" class="mobile-nav-link">Khách sạn</a>
            <a href="{{ route('home') }}?city=Hà Nội" class="mobile-nav-link">Địa điểm</a>
            <a href="/itineraries" class="mobile-nav-link">Lịch trình AI</a>
            <a href="/deals" class="mobile-nav-link">Ưu đãi</a>
            <a href="/#about" class="mobile-nav-link">Về chúng tôi</a>
        </div>
    </nav>

    {{-- MAIN CONTENT --}}
    <main class="pt-16">
        @yield('content')
    </main>

    {{-- FOOTER --}}
    <footer class="mt-20">
        <div class="max-w-7xl mx-auto px-4 py-12">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="col-span-1 md:col-span-2">
                    <a href="{{ route('home') }}" class="flex items-center gap-2 mb-4 hover:opacity-80 transition">
                        <div class="relative w-8 h-8 flex-shrink-0">
                            <svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full h-full">
                                <path d="M20 80C20 80 25 25 55 10C55 10 45 40 45 80H20Z" fill="rgba(255,255,255,0.90)"/>
                                <path d="M50 75C50 75 55 35 75 20C75 20 65 45 65 75H50Z" fill="#2563EB"/>
                                <path d="M40 85C60 85 85 65 85 35" stroke="#F59E0B" stroke-width="3" stroke-linecap="round"/>
                                <path d="M55 70L65 75M60 60L75 65M68 50L82 52M75 40L88 38" stroke="#F59E0B" stroke-width="2.5" stroke-linecap="round"/>
                                <path d="M15 85C35 78 65 78 85 85" stroke="#F59E0B" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <div class="flex items-baseline tracking-tight">
                            <span class="font-display text-2xl font-bold text-white">Holiday</span>
                            <span class="font-display text-2xl font-bold ml-0.5" style="color:#60A5FA">Viet</span>
                        </div>
                    </a>
                    <p class="text-sm leading-relaxed">Hành trình du lịch của bạn bắt đầu từ việc lựa chọn nơi lưu trú phù hợp. Chúng tôi mang đến giải pháp đặt phòng khách sạn thông minh và tự động.</p>
                </div>
                <div>
                    <h4 class="font-semibold mb-3 text-sm uppercase tracking-wider text-white">Khám phá</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('home') }}?city=Hà Nội" class="hover:text-blue-400 transition">Hà Nội</a></li>
                        <li><a href="{{ route('home') }}?city=Đà Nẵng" class="hover:text-blue-400 transition">Đà Nẵng</a></li>
                        <li><a href="/deals" class="hover:text-blue-400 transition">Ưu đãi</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-3 text-sm uppercase tracking-wider text-white">Hỗ trợ</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="/contact" class="hover:text-blue-400 transition">Liên hệ</a></li>
                        <li><a href="/#about" class="hover:text-blue-400 transition">Về chúng tôi</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-slate-700 mt-8 pt-6 text-center text-sm opacity-70">
                <p>© {{ date('Y') }} HolidayViet. Built by Nguyễn Trần Quốc Anh.</p>
            </div>
        </div>
    </footer>

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

        // ===== JS RENDER UI KHI ĐÃ LOGIN (CHUÔNG & USER) =====
        if (token) {
            const _rawUser  = localStorage.getItem('user');
            const _authUser = _rawUser ? JSON.parse(_rawUser) : {};
            const _name     = _authUser.name || 'User';
            const _initial  = _name.charAt(0).toUpperCase();

            const _isAdminEmail = _authUser.role === 'admin';
            const _adminItem = _isAdminEmail ? `
                <a href="/admin/login" class="dropdown-item click-effect text-amber-500 hover:text-amber-600" onclick="closeUserDropdown()">
                    Quản trị Admin <span class="ml-auto text-[9px] bg-amber-100 px-1.5 py-0.5 rounded">ADMIN</span>
                </a>
                <div style="height:1px;background:rgba(0,0,0,0.05);margin:4px 0;"></div>
            ` : '';

            // ĐÂY LÀ CHỖ FIX LỖI CHUÔNG! UI Dropdown được bọc trực tiếp trong thẻ relative của cái chuông.
            document.getElementById('nav-auth').innerHTML = `
                <!-- KHỐI CHUÔNG THÔNG BÁO -->
                <div class="relative" id="notif-bell-wrap">
                    <button class="notif-bell-btn" id="notif-bell" onclick="toggleNotifDropdown(event)">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 01-3 3H9a3 3 0 01-3-3v-1m6 0h6"/></svg>
                        <span class="notif-badge" id="notif-badge"></span>
                    </button>
                    
                    <!-- MENU THÔNG BÁO THẢ XUỐNG -->
                    <div id="notif-dropdown" class="notif-dropdown">
                        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100 bg-white/50">
                            <span class="font-bold text-slate-800 text-sm">Thông báo</span>
                            <button onclick="markAllRead()" class="text-xs text-blue-600 hover:text-blue-700 font-medium">Đánh dấu đã đọc</button>
                        </div>
                        <div id="notif-list" class="custom-scroll" style="max-height:380px; overflow-y:auto;">
                            <div class="text-center py-8 text-slate-400 text-sm">Đang tải...</div>
                        </div>
                        <div class="border-t border-slate-100 px-4 py-3 text-center bg-white/50">
                            <div class="border-t border-slate-100 px-4 py-3 text-center bg-white/50" id="notif-footer">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KHỐI USER PROFILE -->
                <div class="relative" id="user-dropdown-wrap">
                    <button onclick="toggleUserDropdown(event)" class="flex items-center gap-2 px-2 py-1 rounded-full border border-slate-600 hover:bg-slate-700 transition click-effect">
                        <div class="w-7 h-7 rounded-full overflow-hidden bg-blue-500 flex items-center justify-center text-sm font-bold text-white" id="nav-avatar-wrap">
                            ${_authUser.avatar_url 
                                ? `<img id="nav-avatar-img" src="${_authUser.avatar_url}" class="w-full h-full object-cover">`
                                : `<span id="nav-avatar-initial">${_initial}</span>`
                            }
                        </div>
                        <span class="text-sm font-medium text-white hidden sm:block pr-1">${_name}</span>
                    </button>
                    <div id="user-dropdown-menu" class="nav-dropdown-menu" style="right:0; left:auto; transform:none;">
                        <div class="px-3 py-2 mb-1 border-b border-slate-100">
                            <p class="text-[11px] text-slate-500 font-medium uppercase">Xin chào</p>
                            <p class="text-slate-800 font-bold text-sm truncate">${_name}</p>
                        </div>
                        <a href="/profile" class="dropdown-item">Hồ sơ của tôi</a>
                        <a href="/bookings" class="dropdown-item">Đơn phòng của tôi</a>
                        <a href="/itineraries" class="dropdown-item">Lịch trình AI</a>
                        ${_adminItem}
                        <button onclick="logout()" class="dropdown-item w-full text-left text-red-500 hover:text-red-600 hover:bg-red-50">Đăng xuất</button>
                    </div>
                </div>
            `;
        }

        // ===== CÁC SỰ KIỆN DROPDOWN =====
        let _notifOpen = false;
        let _userDropOpen = false;

        function toggleNotifDropdown(e) {
            e.stopPropagation();
            if(_userDropOpen) closeUserDropdown();
            const dd = document.getElementById('notif-dropdown');
            _notifOpen = !_notifOpen;
            dd.classList.toggle('open', _notifOpen);
            if (_notifOpen && typeof loadNotifications === 'function') loadNotifications();
        }

        function toggleUserDropdown(e) {
            e.stopPropagation();
            if(_notifOpen) { _notifOpen = false; document.getElementById('notif-dropdown').classList.remove('open'); }
            _userDropOpen = !_userDropOpen;
            const menu = document.getElementById('user-dropdown-menu');
            if (menu) menu.style.display = _userDropOpen ? 'block' : 'none';
        }

        function closeUserDropdown() {
            _userDropOpen = false;
            const menu = document.getElementById('user-dropdown-menu');
            if (menu) menu.style.display = 'none';
        }

        document.addEventListener('click', function(e) {
            const userWrap = document.getElementById('user-dropdown-wrap');
            if (userWrap && !userWrap.contains(e.target)) closeUserDropdown();
            
            const notifWrap = document.getElementById('notif-bell-wrap');
            if (notifWrap && !notifWrap.contains(e.target)) {
                _notifOpen = false;
                const dd = document.getElementById('notif-dropdown');
                if(dd) dd.classList.remove('open');
            }
        });

        // ===== CÁC HÀM TIỆN ÍCH KHÁC =====
        function toggleMobileMenu() { document.getElementById('mobile-menu').classList.toggle('open'); }
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
        async function logout() {
            await api('/logout', { method: 'POST' });
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
    
    {{-- Các Script thêm từ trang con --}}
    @stack('scripts')
</body>
</html>