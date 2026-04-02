<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Hotel Booking') — Đặt Phòng Khách Sạn</title>

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    {{-- Vite compile Tailwind CSS --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --soft-sky: #87CEFA;
            --soft-sky-dark: #7BC4F5;
            --soft-bg: #EAF3FF;
            --white: #FFFFFF;
            --soft-gray: #C9D3DD;
            --dark-soft: #3A4A5A;
            --dark-soft-light: #5A6A7A;
        }

        * { font-family: 'Inter', sans-serif; }
        body { font-family: 'Inter', sans-serif; background: var(--soft-bg); }
        .font-display { font-family: 'Playfair Display', serif; }

        .text-primary   { color: var(--dark-soft); }
        .text-secondary { color: var(--soft-gray); }
        .text-accent    { color: var(--soft-sky); }

        .btn-primary {
            background: var(--soft-sky);
            color: var(--dark-soft);
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background: var(--soft-sky-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(135,206,250,0.4);
        }
        .btn-primary:active { transform: translateY(0); }

        .btn-outline {
            border: 1.5px solid var(--soft-sky);
            color: var(--dark-soft);
            background: transparent;
            transition: all 0.3s ease;
        }
        .btn-outline:hover {
            background: var(--soft-sky);
            color: white;
            transform: translateY(-2px);
        }

        /* NAVBAR */
        .navbar-glass {
            background: rgba(58, 74, 90, 0.97);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(135,206,250,0.15);
        }

        .nav-link {
            color: var(--soft-gray);
            transition: all 0.2s ease;
            position: relative;
        }
        .nav-link:hover { color: var(--soft-sky); }
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: -4px; left: 0;
            width: 0; height: 2px;
            background: var(--soft-sky);
            transition: width 0.3s ease;
        }
        .nav-link:hover::after { width: 100%; }

        /* DROPDOWN */
        .nav-dropdown {
            position: relative;
        }
        .nav-dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #2A3A4A;
            border: 1px solid rgba(135,206,250,0.15);
            border-radius: 14px;
            padding: 8px;
            min-width: 200px;
            box-shadow: 0 20px 40px -10px rgba(0,0,0,0.4);
            z-index: 100;
        }
        .nav-dropdown:hover .nav-dropdown-menu { display: block; }
        .nav-dropdown-menu::before {
            content: '';
            position: absolute;
            top: -6px; left: 50%;
            transform: translateX(-50%);
            width: 12px; height: 12px;
            background: #2A3A4A;
            border-left: 1px solid rgba(135,206,250,0.15);
            border-top: 1px solid rgba(135,206,250,0.15);
            transform: translateX(-50%) rotate(45deg);
        }
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            color: var(--soft-gray);
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .dropdown-item:hover {
            background: rgba(135,206,250,0.1);
            color: var(--soft-sky);
        }
        .dropdown-item svg { flex-shrink: 0; }

        /* BADGE mới */
        .badge-new {
            background: #F59E0B;
            color: white;
            font-size: 9px;
            font-weight: 700;
            padding: 1px 5px;
            border-radius: 4px;
            letter-spacing: 0.5px;
        }

        /* Card hover */
        .card-hover {
            transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
        }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 30px -10px rgba(58,74,90,0.15);
        }

        /* Footer */
        .footer-bg {
            background: linear-gradient(135deg, var(--dark-soft) 0%, #2A3A4A 100%);
        }

        .click-effect:active {
            transform: scale(0.98);
            transition: transform 0.1s ease;
        }

        #toast { animation: slideUp 0.3s ease; }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        p, .text-content { line-height: 1.6; }
        h1, h2, h3, h4   { line-height: 1.3; }

        /* Mobile menu */
        #mobile-menu {
            display: none;
            background: #2A3A4A;
            border-top: 1px solid rgba(135,206,250,0.1);
        }
        #mobile-menu.open { display: block; }
        .mobile-nav-link {
            display: flex; align-items: center gap-3;
            padding: 12px 20px;
            color: var(--soft-gray);
            font-size: 14px; font-weight: 500;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            transition: all 0.2s;
            text-decoration: none;
        }
        .mobile-nav-link:hover {
            background: rgba(135,206,250,0.08);
            color: var(--soft-sky);
        }
    </style>
</head>
<body class="bg-[#EAF3FF]">

    {{-- NAVBAR --}}
    <nav class="navbar-glass fixed top-0 left-0 right-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">

                {{-- Logo --}}
                <a href="{{ route('home') }}" class="flex items-center gap-2 hover:opacity-90 transition click-effect">
                    <svg class="w-8 h-8 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    <span class="font-display text-2xl font-bold text-[#87CEFA]">Hotel</span>
                    <span class="font-display text-2xl font-light text-white">Booking</span>
                </a>

                {{-- Nav Links Desktop --}}
                <div class="hidden lg:flex items-center gap-6">

                    {{-- Khách sạn --}}
                    <a href="{{ route('home') }}" class="nav-link text-sm font-medium flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        Khách sạn
                    </a>

                    {{-- Địa điểm (dropdown) --}}
                    <div class="nav-dropdown">
                        <button class="nav-link text-sm font-medium flex items-center gap-1.5 bg-transparent border-0 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            Địa điểm
                            <svg class="w-3 h-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="nav-dropdown-menu">
                            <a href="{{ route('home') }}?city=Hà Nội" class="dropdown-item">
                                <svg class="w-4 h-4 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                                Hà Nội
                            </a>
                            <a href="{{ route('home') }}?city=Hồ Chí Minh" class="dropdown-item">
                                <svg class="w-4 h-4 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                                Hồ Chí Minh
                            </a>
                            <a href="{{ route('home') }}?city=Đà Nẵng" class="dropdown-item">
                                <svg class="w-4 h-4 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                                Đà Nẵng
                            </a>
                            <a href="{{ route('home') }}?city=Phú Quốc" class="dropdown-item">
                                <svg class="w-4 h-4 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                                Phú Quốc
                            </a>
                            <a href="{{ route('home') }}?city=Hội An" class="dropdown-item">
                                <svg class="w-4 h-4 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                                Hội An
                            </a>
                        </div>
                    </div>

                    {{-- Lịch trình --}}
                    <a href="/dashboard#itineraries" class="nav-link text-sm font-medium flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                        </svg>
                        Lịch trình
                    </a>

                    {{-- Ưu đãi --}}
                    <a href="/deals" class="nav-link text-sm font-medium flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                        </svg>
                        Ưu đãi
                        <span class="badge-new">HOT</span>
                    </a>

                    {{-- Khám phá (dropdown: Blog + Về chúng tôi + Liên hệ) --}}
                    <div class="nav-dropdown">
                        <button class="nav-link text-sm font-medium flex items-center gap-1.5 bg-transparent border-0 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"></path>
                            </svg>
                            Khám phá
                            <svg class="w-3 h-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div class="nav-dropdown-menu">
                            <a href="/blog" class="dropdown-item">
                                <svg class="w-4 h-4 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path>
                                </svg>
                                Blog / Tin tức
                            </a>
                            <a href="/about" class="dropdown-item">
                                <svg class="w-4 h-4 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Về chúng tôi
                            </a>
                            <a href="/contact" class="dropdown-item">
                                <svg class="w-4 h-4 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                                Liên hệ
                            </a>
                        </div>
                    </div>

                </div>

                {{-- Auth Buttons --}}
                <div class="flex items-center gap-3" id="nav-auth">
                    <a href="{{ route('login') }}" class="text-[#C9D3DD] hover:text-white text-sm font-medium transition flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                        </svg>
                        Đăng nhập
                    </a>
                    <a href="{{ route('register') }}" class="btn-primary px-4 py-2 rounded-lg text-sm flex items-center gap-1 click-effect">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                        </svg>
                        Đăng ký
                    </a>
                </div>

                {{-- Mobile hamburger --}}
                <button onclick="toggleMobileMenu()" class="lg:hidden text-[#C9D3DD] hover:text-white ml-3" id="hamburger">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>

            </div>
        </div>

        {{-- Mobile Menu --}}
        <div id="mobile-menu">
            <a href="{{ route('home') }}" class="mobile-nav-link">
                <svg class="w-4 h-4 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                Khách sạn
            </a>
            <a href="{{ route('home') }}?city=Hà Nội" class="mobile-nav-link">
                <svg class="w-4 h-4 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                Địa điểm
            </a>
            <a href="/dashboard#itineraries" class="mobile-nav-link">
                <svg class="w-4 h-4 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                Lịch trình
            </a>
            <a href="/deals" class="mobile-nav-link">
                <svg class="w-4 h-4 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                Ưu đãi / Khuyến mãi
            </a>
            <a href="/blog" class="mobile-nav-link">
                <svg class="w-4 h-4 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path></svg>
                Blog / Tin tức
            </a>
            <a href="/about" class="mobile-nav-link">
                <svg class="w-4 h-4 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Về chúng tôi
            </a>
            <a href="/contact" class="mobile-nav-link">
                <svg class="w-4 h-4 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                Liên hệ
            </a>
        </div>
    </nav>

    {{-- MAIN CONTENT --}}
    <main class="pt-16">
        @yield('content')
    </main>

    {{-- FOOTER --}}
    <footer class="footer-bg text-[#C9D3DD] mt-20">
        <div class="max-w-7xl mx-auto px-4 py-12">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="col-span-1 md:col-span-2">
                    <div class="flex items-center gap-2 mb-4">
                        <svg class="w-8 h-8 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        <span class="font-display text-2xl font-bold text-[#87CEFA]">Hotel</span>
                        <span class="font-display text-2xl font-light text-white">Booking</span>
                    </div>
                    <p class="text-sm leading-relaxed">Hệ thống đặt phòng khách sạn thông minh với AI chatbot gợi ý lịch trình du lịch tại Việt Nam.</p>
                    {{-- Social links --}}
                    <div class="flex gap-3 mt-4">
                        <a href="#" class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center hover:bg-[#87CEFA]/20 transition">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        </a>
                        <a href="#" class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center hover:bg-[#87CEFA]/20 transition">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 01.083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.632-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24.009 12.017 24c6.624 0 11.99-5.367 11.99-11.988C24.007 5.367 18.641.001 12.017.001z"/></svg>
                        </a>
                    </div>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-3 text-sm uppercase tracking-wider">Khám phá</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('home') }}?city=Hà Nội"    class="hover:text-[#87CEFA] transition flex items-center gap-2"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>Hà Nội</a></li>
                        <li><a href="{{ route('home') }}?city=Hồ Chí Minh" class="hover:text-[#87CEFA] transition flex items-center gap-2"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>Hồ Chí Minh</a></li>
                        <li><a href="{{ route('home') }}?city=Đà Nẵng"   class="hover:text-[#87CEFA] transition flex items-center gap-2"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>Đà Nẵng</a></li>
                        <li><a href="{{ route('home') }}?city=Phú Quốc"  class="hover:text-[#87CEFA] transition flex items-center gap-2"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>Phú Quốc</a></li>
                        <li><a href="/deals"                              class="hover:text-[#87CEFA] transition flex items-center gap-2"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>Ưu đãi</a></li>
                        <li><a href="/blog"                               class="hover:text-[#87CEFA] transition flex items-center gap-2"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>Blog</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-3 text-sm uppercase tracking-wider">Hỗ trợ</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="/contact" class="hover:text-[#87CEFA] transition flex items-center gap-2"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>Liên hệ</a></li>
                        <li><a href="/about"   class="hover:text-[#87CEFA] transition flex items-center gap-2"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>Về chúng tôi</a></li>
                        <li><a href="#"        class="hover:text-[#87CEFA] transition flex items-center gap-2"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>Chính sách</a></li>
                        <li><a href="#"        class="hover:text-[#87CEFA] transition flex items-center gap-2"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>FAQ</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-[#5A6A7A] mt-8 pt-6 text-center text-sm">
                <p>© {{ date('Y') }} Hotel Booking System. Build by NguyenTranQuocAnh.</p>
            </div>
        </div>
    </footer>

    {{-- Toast --}}
    <div id="toast" class="fixed bottom-4 right-4 z-50 hidden">
        <div class="bg-[#3A4A5A] text-white px-6 py-3 rounded-xl shadow-2xl border border-[#87CEFA] text-sm font-medium flex items-center gap-2" id="toast-msg">
            <svg class="w-4 h-4 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span></span>
        </div>
    </div>

    <script>
        const token = localStorage.getItem('token');

        if (token) {
            document.getElementById('nav-auth').innerHTML = `
                <a href="/dashboard" class="nav-link text-sm font-medium flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    Dashboard
                </a>
                <button onclick="logout()" class="btn-primary px-4 py-2 rounded-lg text-sm flex items-center gap-1 click-effect">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    Đăng xuất
                </button>
            `;
        }

        async function logout() {
            await fetch('/api/logout', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('token'),
                    'Accept': 'application/json'
                }
            });
            localStorage.removeItem('token');
            localStorage.removeItem('user');
            window.location.href = '/';
        }

        function showToast(msg, duration = 3000) {
            const toast    = document.getElementById('toast');
            const toastMsg = document.getElementById('toast-msg');
            toastMsg.innerHTML = `
                <svg class="w-4 h-4 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span>${msg}</span>
            `;
            toast.classList.remove('hidden');
            setTimeout(() => toast.classList.add('hidden'), duration);
        }

        async function api(url, options = {}) {
            const token = localStorage.getItem('token');
            return fetch('/api' + url, {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    ...(token ? { 'Authorization': 'Bearer ' + token } : {}),
                    ...options.headers,
                }
            });
        }

        function toggleMobileMenu() {
            const menu = document.getElementById('mobile-menu');
            menu.classList.toggle('open');
        }
    </script>

    @stack('scripts')
</body>
</html>