<!-- <!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hotel Booking — Đặt phòng khách sạn 5 sao Việt Nam</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --soft-sky: #87CEFA;
            --soft-sky-dark: #7BC4F5;
            --soft-bg: #EAF3FF;
            --soft-gray: #C9D3DD;
            --dark-soft: #3A4A5A;
        }
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { background: linear-gradient(135deg, var(--soft-bg) 0%, #fff 100%); min-height: 100vh; }
        .font-display { font-family: 'Playfair Display', serif; }

        .btn-primary {
            background: var(--soft-sky); color: var(--dark-soft); font-weight: 600;
            border: none; cursor: pointer; transition: all 0.3s ease;
            display: inline-flex; align-items: center; gap: 8px;
            padding: 12px 28px; border-radius: 12px; font-size: 14px;
            text-decoration: none;
        }
        .btn-primary:hover { background: var(--soft-sky-dark); transform: translateY(-2px); box-shadow: 0 10px 20px -5px rgba(135,206,250,0.4); }

        .btn-outline {
            border: 1.5px solid var(--soft-sky); color: var(--dark-soft);
            background: transparent; transition: all 0.3s ease;
            display: inline-flex; align-items: center; gap: 8px;
            padding: 12px 28px; border-radius: 12px; font-size: 14px;
            text-decoration: none; font-weight: 500;
        }
        .btn-outline:hover { background: var(--soft-sky); color: white; transform: translateY(-2px); }

        /* HERO */
        .hero { background: linear-gradient(135deg, var(--soft-sky) 0%, var(--soft-bg) 50%, #fff 100%); position: relative; overflow: hidden; }
        .hero-blob-1 { position: absolute; top: -80px; left: -80px; width: 400px; height: 400px; border-radius: 50%; background: radial-gradient(circle, rgba(135,206,250,0.3), transparent); }
        .hero-blob-2 { position: absolute; bottom: -100px; right: -80px; width: 500px; height: 500px; border-radius: 50%; background: radial-gradient(circle, rgba(201,211,221,0.2), transparent); }

        /* STATS */
        .stat-card { background: rgba(255,255,255,0.6); backdrop-filter: blur(8px); border-radius: 20px; padding: 24px; text-align: center; transition: transform 0.3s ease; }
        .stat-card:hover { transform: translateY(-4px); }

        /* FEATURES */
        .feature-card { background: white; border-radius: 20px; padding: 28px; border: 1px solid rgba(201,211,221,0.3); box-shadow: 0 4px 16px -4px rgba(58,74,90,0.08); transition: all 0.4s ease; text-align: center; }
        .feature-card:hover { transform: translateY(-8px); box-shadow: 0 20px 30px -10px rgba(58,74,90,0.15); }
        .icon-wrap { width: 64px; height: 64px; background: var(--soft-bg); border-radius: 50%; display: flex; align-items: center; justify-center; margin: 0 auto 16px; transition: all 0.3s ease; }
        .feature-card:hover .icon-wrap { background: var(--soft-sky); }
        .feature-card:hover .icon-wrap svg { color: white; }

        /* WHY US */
        .why-card { display: flex; align-items: flex-start; gap: 16px; padding: 20px; background: white; border-radius: 16px; border: 1px solid rgba(135,206,250,0.2); }

        /* CTA */
        .cta-section { background: linear-gradient(135deg, #87CEFA 0%, #EAF3FF 100%); }

        /* FOOTER */
        footer { background: linear-gradient(135deg, #3A4A5A 0%, #2A3A4A 100%); color: #C9D3DD; }

        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .animate-in { animation: fadeInUp 0.7s ease forwards; }
        .delay-1 { animation-delay: 0.1s; opacity: 0; }
        .delay-2 { animation-delay: 0.2s; opacity: 0; }
        .delay-3 { animation-delay: 0.3s; opacity: 0; }
    </style>
</head>
<body>

    {{-- MINI NAVBAR (chỉ dùng cho welcome page, không dùng layout.blade.php) --}}
    <nav style="background: rgba(58,74,90,0.95); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(135,206,250,0.15);" class="fixed top-0 left-0 right-0 z-50">
        <div style="max-width: 1280px; margin: 0 auto; padding: 0 24px; display: flex; align-items: center; justify-content: space-between; height: 64px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <svg style="width: 32px; height: 32px; color: #87CEFA;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                <span class="font-display" style="font-size: 22px; font-weight: 700; color: #87CEFA;">Hotel</span>
                <span class="font-display" style="font-size: 22px; font-weight: 300; color: white;">Booking</span>
            </div>
            <div style="display: flex; align-items: center; gap: 12px;">
                <a href="{{ route('login') }}" style="color: #C9D3DD; font-size: 14px; font-weight: 500; text-decoration: none; transition: color 0.2s;" onmouseover="this.style.color='white'" onmouseout="this.style.color='#C9D3DD'">Đăng nhập</a>
                <a href="{{ route('register') }}" class="btn-primary" style="padding: 8px 20px; font-size: 13px;">Đăng ký miễn phí</a>
            </div>
        </div>
    </nav>

    {{-- HERO SECTION --}}
    <section class="hero" style="padding: 120px 24px 80px;">
        <div class="hero-blob-1"></div>
        <div class="hero-blob-2"></div>
        <div style="max-width: 900px; margin: 0 auto; text-align: center; position: relative;">

            <p class="animate-in" style="font-size: 12px; font-weight: 700; letter-spacing: 3px; color: #87CEFA; text-transform: uppercase; margin-bottom: 16px;">
                📍 Khám phá Việt Nam
            </p>

            <h1 class="font-display animate-in delay-1" style="font-size: clamp(40px, 6vw, 72px); font-weight: 700; color: #1E3A5F; margin-bottom: 24px; line-height: 1.15;">
                Trải nghiệm<br>
                <span style="color: #87CEFA;">đẳng cấp 5 sao</span>
            </h1>

            <p class="animate-in delay-2" style="font-size: 17px; color: #5A6A7A; max-width: 560px; margin: 0 auto 40px; line-height: 1.7;">
                Đặt phòng khách sạn tốt nhất Việt Nam với AI chatbot gợi ý lịch trình cá nhân hóa
            </p>

            <div class="animate-in delay-3" style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                <a href="{{ route('home') }}" class="btn-primary">
                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    Khám phá ngay
                </a>
                <a href="#features" class="btn-outline">
                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Tìm hiểu thêm
                </a>
            </div>

            {{-- Stats --}}
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 64px;">
                <div class="stat-card">
                    <p class="font-display" style="font-size: 36px; font-weight: 700; color: #1E3A5F;">500+</p>
                    <p style="font-size: 13px; color: #5A6A7A; margin-top: 6px;">🏨 Khách sạn</p>
                </div>
                <div class="stat-card">
                    <p class="font-display" style="font-size: 36px; font-weight: 700; color: #1E3A5F;">15+</p>
                    <p style="font-size: 13px; color: #5A6A7A; margin-top: 6px;">📍 Thành phố</p>
                </div>
                <div class="stat-card">
                    <p class="font-display" style="font-size: 36px; font-weight: 700; color: #1E3A5F;">24/7</p>
                    <p style="font-size: 13px; color: #5A6A7A; margin-top: 6px;">🤖 Hỗ trợ AI</p>
                </div>
            </div>
        </div>
    </section>

    {{-- FEATURES SECTION --}}
    <section id="features" style="padding: 80px 24px; max-width: 1280px; margin: 0 auto;">
        <div style="text-align: center; margin-bottom: 48px;">
            <h2 class="font-display" style="font-size: 36px; font-weight: 700; color: #1E3A5F; margin-bottom: 12px;">Tính năng nổi bật</h2>
            <p style="color: #5A6A7A; max-width: 500px; margin: 0 auto;">Trải nghiệm đặt phòng thông minh với những tính năng hiện đại</p>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 24px;">
            <div class="feature-card">
                <div class="icon-wrap">
                    <svg style="width: 28px; height: 28px; color: #87CEFA;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <h3 style="font-weight: 600; color: #1E3A5F; margin-bottom: 8px;">Tìm kiếm thông minh</h3>
                <p style="color: #5A6A7A; font-size: 13px; line-height: 1.6;">Lọc theo tên, thành phố, số sao và giá cả</p>
            </div>
            <div class="feature-card">
                <div class="icon-wrap">
                    <svg style="width: 28px; height: 28px; color: #87CEFA;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <h3 style="font-weight: 600; color: #1E3A5F; margin-bottom: 8px;">AI Chatbot</h3>
                <p style="color: #5A6A7A; font-size: 13px; line-height: 1.6;">Trợ lý AI gợi ý lịch trình du lịch cá nhân hóa</p>
            </div>
            <div class="feature-card">
                <div class="icon-wrap">
                    <svg style="width: 28px; height: 28px; color: #87CEFA;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                    </svg>
                </div>
                <h3 style="font-weight: 600; color: #1E3A5F; margin-bottom: 8px;">Thanh toán đa dạng</h3>
                <p style="color: #5A6A7A; font-size: 13px; line-height: 1.6;">VNPay, chuyển khoản QR, tiền mặt tại quầy</p>
            </div>
            <div class="feature-card">
                <div class="icon-wrap">
                    <svg style="width: 28px; height: 28px; color: #87CEFA;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <h3 style="font-weight: 600; color: #1E3A5F; margin-bottom: 8px;">Hóa đơn PDF tự động</h3>
                <p style="color: #5A6A7A; font-size: 13px; line-height: 1.6;">Gửi hóa đơn PDF qua email ngay sau đặt phòng</p>
            </div>
        </div>
    </section>

    {{-- WHY US SECTION --}}
    <section style="padding: 60px 24px; background: white;">
        <div style="max-width: 1000px; margin: 0 auto;">
            <div style="text-align: center; margin-bottom: 48px;">
                <h2 class="font-display" style="font-size: 32px; font-weight: 700; color: #1E3A5F; margin-bottom: 12px;">Tại sao chọn chúng tôi?</h2>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                <div class="why-card">
                    <div style="width: 44px; height: 44px; background: #EAF3FF; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <span style="font-size: 20px;">🏆</span>
                    </div>
                    <div>
                        <h4 style="font-weight: 600; color: #1E3A5F; margin-bottom: 4px;">Giá tốt nhất</h4>
                        <p style="font-size: 13px; color: #5A6A7A; line-height: 1.6;">Cam kết giá tốt nhất, hoàn tiền nếu tìm được giá rẻ hơn</p>
                    </div>
                </div>
                <div class="why-card">
                    <div style="width: 44px; height: 44px; background: #EAF3FF; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <span style="font-size: 20px;">🔒</span>
                    </div>
                    <div>
                        <h4 style="font-weight: 600; color: #1E3A5F; margin-bottom: 4px;">Thanh toán an toàn</h4>
                        <p style="font-size: 13px; color: #5A6A7A; line-height: 1.6;">Bảo mật HMAC-SHA512, chống gian lận giao dịch</p>
                    </div>
                </div>
                <div class="why-card">
                    <div style="width: 44px; height: 44px; background: #EAF3FF; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <span style="font-size: 20px;">⚡</span>
                    </div>
                    <div>
                        <h4 style="font-weight: 600; color: #1E3A5F; margin-bottom: 4px;">Đặt phòng tức thì</h4>
                        <p style="font-size: 13px; color: #5A6A7A; line-height: 1.6;">Xác nhận ngay lập tức, email hóa đơn trong vài giây</p>
                    </div>
                </div>
                <div class="why-card">
                    <div style="width: 44px; height: 44px; background: #EAF3FF; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <span style="font-size: 20px;">🤖</span>
                    </div>
                    <div>
                        <h4 style="font-weight: 600; color: #1E3A5F; margin-bottom: 4px;">AI hỗ trợ 24/7</h4>
                        <p style="font-size: 13px; color: #5A6A7A; line-height: 1.6;">Chatbot AI lên lịch trình du lịch theo sở thích</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- CTA SECTION --}}
    <section class="cta-section" style="padding: 80px 24px; text-align: center;">
        <h2 class="font-display" style="font-size: 36px; font-weight: 700; color: #1E3A5F; margin-bottom: 12px;">Sẵn sàng trải nghiệm?</h2>
        <p style="color: #5A6A7A; margin-bottom: 32px; max-width: 480px; margin-left: auto; margin-right: auto;">Tham gia cùng hàng ngàn du khách đã tin tưởng và trải nghiệm dịch vụ</p>
        <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
            <a href="{{ route('register') }}" class="btn-primary">
                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                </svg>
                Đăng ký miễn phí
            </a>
            <a href="{{ route('home') }}" class="btn-outline">
                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                Xem khách sạn
            </a>
        </div>
    </section>

    {{-- FOOTER --}}
    <footer style="padding: 32px 24px; text-align: center;">
        <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 12px;">
            <svg style="width: 24px; height: 24px; color: #87CEFA;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>
            <span class="font-display" style="font-size: 18px; font-weight: 700; color: #87CEFA;">Hotel</span>
            <span class="font-display" style="font-size: 18px; font-weight: 300; color: white;">Booking</span>
        </div>
        <p style="font-size: 12px; color: #C9D3DD;">© {{ date('Y') }} Hotel Booking System. Build by NguyenTranQuocAnh.</p>
    </footer>

</body>
</html> -->