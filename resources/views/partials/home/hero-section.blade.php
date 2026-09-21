@php
    $heroSlides = [
        [
            'img'     => 'bannerhome{hero} (1).jpg',
            'eyebrow' => 'Khám phá Việt Nam',
            'title'   => 'Đặt phòng thông minh<br>cho <span class="text-[#60A5FA]">mọi hành trình</span>',
            'sub'     => 'Hơn 20 khách sạn & resort cao cấp trên toàn quốc, giá tốt nhất mỗi ngày.',
        ],
        [
            'img'     => 'bannerhome{hero} (2).jpg',
            'eyebrow' => 'Nghỉ dưỡng đẳng cấp',
            'title'   => 'Resort ven biển<br><span class="text-[#60A5FA]">view triệu đô</span>',
            'sub'     => 'Từ Đà Nẵng, Nha Trang đến Phú Quốc — thức dậy cùng tiếng sóng.',
        ],
        [
            'img'     => 'bannerhome{hero} (3).jpg',
            'eyebrow' => 'Lịch trình AI',
            'title'   => 'Để AI lên kế hoạch<br><span class="text-[#60A5FA]">chuyến đi của bạn</span>',
            'sub'     => 'Nhập điểm đến và ngân sách — nhận ngay lịch trình chi tiết từng ngày.',
        ],
        [
            'img'     => 'bannerhome{hero} (4).jpg',
            'eyebrow' => 'Ưu đãi mỗi ngày',
            'title'   => 'Flash Sale<br><span class="text-[#60A5FA]">giảm đến 50%</span>',
            'sub'     => 'Săn deal giới hạn, thanh toán VNPAY bảo mật, hoá đơn PDF tự động.',
        ],
    ];
@endphp

<style>
    /* ===== Override hiệu ứng: trượt ngang phải → trái (thay cho fade) ===== */
    .hero-wrapper { margin-bottom: 90px; }

    #heroCarousel .hero-slide {
        opacity: 1;
        transform: translateX(100%);
        transition: transform .85s cubic-bezier(.65, 0, .35, 1);
        pointer-events: none;
        z-index: 1;
        will-change: transform;
    }
    #heroCarousel .hero-slide.active {
        pointer-events: auto;
        z-index: 2;
    }
    /* Nội dung chữ trượt chậm hơn ảnh một nhịp cho có chiều sâu */
    #heroCarousel .hero-slide .hero-inner {
        opacity: 0;
        transform: translateY(24px);
        transition: opacity .6s ease .25s, transform .6s ease .25s;
    }
    #heroCarousel .hero-slide.active .hero-inner {
        opacity: 1;
        transform: translateY(0);
    }

    .hero-eyebrow {
        display: inline-flex; align-items: center; gap: 8px;
        background: rgba(255,255,255,.14);
        border: 1px solid rgba(255,255,255,.28);
        backdrop-filter: blur(8px);
        color: #fff; font-size: 12px; font-weight: 600;
        letter-spacing: 1.2px; text-transform: uppercase;
        padding: 6px 14px; border-radius: 99px; margin-bottom: 18px;
    }
    .hero-title {
        font-family: var(--font-sans, 'Nunito Sans', sans-serif);
        color: #fff; font-weight: 800; line-height: 1.15;
        font-size: clamp(2rem, 4.2vw, 3.4rem);
        text-shadow: 0 2px 16px rgba(0,0,0,.45);
    }
    .hero-sub {
        color: rgba(255,255,255,.85);
        font-size: 15px; margin-top: 14px; max-width: 520px;
        text-shadow: 0 1px 8px rgba(0,0,0,.5);
    }
    .hero-cta-row { display: flex; gap: 12px; margin-top: 26px; flex-wrap: wrap; }
    .hero-btn-ghost {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 12px 24px; border-radius: 9999px;
        border: 1px solid rgba(255,255,255,.45);
        color: #fff; font-weight: 600; font-size: 14px;
        backdrop-filter: blur(6px); transition: all .25s;
    }
    .hero-btn-ghost:hover { background: rgba(255,255,255,.16); transform: translateY(-2px); }

    /* ===== SEGMENTED PILL SEARCH BAR (Airbnb-style) ===== */
    .pill-bar {
        position: relative;
        display: flex;
        align-items: center;
        background: #EBEBEB;
        border-radius: 9999px;
        padding: 6px;
        box-shadow: 0 20px 45px -15px rgba(15, 23, 42, .35);
    }

    /* Indicator trượt — chính là "sliding highlight" */
    .pill-bg {
        position: absolute;
        top: 6px;
        left: 0;
        height: calc(100% - 12px);
        width: 0;
        background: #fff;
        border-radius: 9999px;
        box-shadow: 0 6px 20px rgba(15, 23, 42, .18),
                    0 2px 6px rgba(15, 23, 42, .10);
        opacity: 0;
        pointer-events: none;
        z-index: 0;
        transition: transform .34s cubic-bezier(.4, 0, .2, 1),
                    width .34s cubic-bezier(.4, 0, .2, 1),
                    opacity .22s ease;
        will-change: transform, width;
    }
    .pill-bg.visible { opacity: 1; }

    /* Từng field */
    .sf {
        position: relative;
        z-index: 1;
        flex: 1;
        min-width: 0;
        padding: 10px 24px;
        border-radius: 9999px;
        cursor: pointer;
        transition: background .2s;
    }
    .sf:hover:not(.active) { background: rgba(255, 255, 255, .55); }

    .sf label {
        display: block;
        font-size: 11.5px;
        font-weight: 700;
        color: #64748B;
        letter-spacing: .02em;
        pointer-events: none;
        transition: color .2s;
    }
    .sf.active label { color: #0F172A; }

    .sf input,
    .sf select {
        width: 100%;
        border: none;
        outline: none;
        background: transparent;
        font-size: 14px;
        font-weight: 600;
        color: #1E293B;
        padding: 2px 0;
        cursor: pointer;
    }
    .sf input::placeholder,
    .sf select.is-empty { color: #94A3B8; font-weight: 500; }

    .sf-guests { cursor: default; }
    .sf-guests-trigger {
        display: block;
        width: 100%;
        padding: 2px 0;
        border: 0;
        outline: 0;
        background: transparent;
        color: #1E293B;
        font-size: 14px;
        font-weight: 600;
        text-align: left;
        cursor: pointer;
    }
    .sf-guests-placeholder { color: #94A3B8; font-weight: 500; }
    .guests-panel {
        position: absolute;
        top: calc(100% + 18px);
        right: 0;
        z-index: 130;
        display: none;
        width: min(360px, calc(100vw - 32px));
        padding: 8px 0;
        background: #fff;
        border: 1px solid #E2E8F0;
        border-radius: 18px;
        box-shadow: 0 20px 45px rgba(15, 23, 42, .18);
    }
    .sf-guests.is-open .guests-panel { display: block; }
    .guests-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        padding: 14px 18px;
        border-bottom: 1px solid #F1F5F9;
    }
    .guests-row-last { border-bottom: 0; }
    .guests-row-text { min-width: 0; }
    .guests-row-title { color: #1E293B; font-size: 14px; font-weight: 700; }
    .guests-row-sub { margin-top: 3px; color: #94A3B8; font-size: 12px; }
    .guests-row-link { color: #2563EB; }
    .guests-ctrl { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
    .guests-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px;
        height: 30px;
        border: 1px solid #CBD5E1;
        border-radius: 50%;
        background: #fff;
        color: #334155;
        font-size: 18px;
        line-height: 1;
        cursor: pointer;
        transition: background .2s, border-color .2s, color .2s;
    }
    .guests-btn:hover:not(:disabled) { border-color: #2563EB; background: #EFF6FF; color: #2563EB; }
    .guests-btn:disabled { cursor: not-allowed; opacity: .4; }
    .guests-count { min-width: 16px; color: #1E293B; font-size: 14px; font-weight: 700; text-align: center; }

    /* ===== "When" field ===== */
    .sf-dates-trigger {
        display: block;
        width: 100%;
        padding: 2px 24px 2px 0;
        border: 0;
        outline: 0;
        background: transparent;
        color: #1E293B;
        font-size: 14px;
        font-weight: 600;
        text-align: left;
        cursor: pointer;
    }
    .sf-clear-btn {
        position: absolute;
        top: 30px;
        right: 16px;
        z-index: 2;
        width: 20px;
        height: 20px;
        padding: 0;
        border: 0;
        border-radius: 50%;
        background: #E2E8F0;
        color: #475569;
        font-size: 16px;
        line-height: 18px;
        cursor: pointer;
    }
    .sf-clear-btn:hover { background: #CBD5E1; color: #0F172A; }
    .dates-panel {
        position: absolute;
        top: calc(100% + 18px);
        left: 50%;
        z-index: 130;
        display: none;
        width: min(680px, calc(100vw - 32px));
        padding: 16px;
        transform: translateX(-50%);
        background: #fff;
        border: 1px solid #E2E8F0;
        border-radius: 20px;
        box-shadow: 0 20px 50px rgba(15, 23, 42, .2);
    }
    .dates-panel.open { display: block; animation: datesPop .18s ease; }
    @keyframes datesPop { from { opacity: 0; transform: translate(-50%, -6px); } to { opacity: 1; transform: translate(-50%, 0); } }
    .dates-tabs { display: flex; justify-content: center; margin-bottom: 14px; }
    .dates-tabs-inner { display: inline-flex; padding: 4px; border-radius: 999px; background: #F1F5F9; }
    .dates-tab, .flex-chip {
        border: 0;
        border-radius: 999px;
        background: transparent;
        color: #64748B;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }
    .dates-tab { padding: 8px 18px; }
    .dates-tab.active, .flex-chip.active { background: #fff; color: #1E293B; box-shadow: 0 2px 8px rgba(15, 23, 42, .1); }
    .dates-cal-wrap { display: flex; align-items: center; gap: 8px; }
    .dates-cal-months { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); flex: 1; gap: 24px; }
    .cal-nav { flex: 0 0 32px; width: 32px; height: 32px; border: 0; border-radius: 50%; background: #F8FAFC; color: #334155; font-size: 24px; line-height: 1; cursor: pointer; }
    .cal-nav:hover:not(:disabled) { background: #E0F2FE; color: #0369A1; }
    .cal-nav:disabled { cursor: not-allowed; opacity: .35; }
    .cal-month-title { margin-bottom: 12px; color: #1E293B; font-size: 14px; font-weight: 700; text-align: center; }
    .cal-weekdays, .cal-days { display: grid; grid-template-columns: repeat(7, 1fr); text-align: center; }
    .cal-weekdays { margin-bottom: 6px; color: #94A3B8; font-size: 11px; font-weight: 700; }
    .cal-cell { position: relative; display: flex; justify-content: center; align-items: center; height: 34px; }
    .cal-cell.in-range { background: #EFF6FF; }
    .cal-cell.range-start { border-radius: 17px 0 0 17px; background: #EFF6FF; }
    .cal-cell.range-end { border-radius: 0 17px 17px 0; background: #EFF6FF; }
    .cal-day { position: relative; z-index: 1; width: 32px; height: 32px; padding: 0; border: 0; border-radius: 50%; background: transparent; color: #334155; font-size: 12px; cursor: pointer; }
    .cal-day:hover:not(:disabled) { background: #E0F2FE; color: #0369A1; }
    .cal-day.is-selected { background: #2563EB; color: #fff; font-weight: 700; }
    .cal-day.is-disabled { color: #CBD5E1; cursor: not-allowed; }
    .cal-day.is-empty { pointer-events: none; }
    .dates-flex-row { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; margin-top: 16px; }
    .flex-chip { padding: 8px 12px; border: 1px solid #E2E8F0; }
    .flex-chip.active { border-color: #BFDBFE; background: #EFF6FF; color: #1D4ED8; box-shadow: none; }

    /* Vạch ngăn — tự ẩn khi nằm cạnh ô active */
    .sf-div {
        width: 1px;
        height: 30px;
        background: #D4D4D4;
        flex-shrink: 0;
        transition: opacity .22s;
    }
    .sf-div.hide { opacity: 0; }

    /* Nút search */
    .pill-btn {
        flex-shrink: 0;
        z-index: 1;
        display: flex;
        align-items: center;
        gap: 8px;
        height: 48px;
        padding: 0 18px;
        margin-left: 6px;
        border: none;
        border-radius: 9999px;
        background: var(--brand-gradient);
        color: #fff;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(59, 130, 246, .38);
        transition: transform .2s, box-shadow .2s;
    }
    .pill-btn:hover { transform: scale(1.04); box-shadow: 0 8px 22px rgba(59, 130, 246, .5); }
    .pill-btn:active { transform: scale(.97); }

        /* ===== "Who" field — trigger ===== */
    .sf-guests-trigger {
        all: unset;
        display: block;
        width: 100%;
        cursor: pointer;
    }
    .sf-guests-placeholder { font-size: 14px; font-weight: 500; color: #94A3B8; }
    .sf-guests-value       { font-size: 14px; font-weight: 600; color: #1E293B; }

    /* ===== Popover panel ===== */
    .guests-panel {
        display: none;
        position: absolute;
        top: calc(100% + 18px);
        right: 0;
        width: 360px;
        background: #fff;
        border-radius: 24px;
        padding: 4px 24px;
        box-shadow: 0 20px 50px -12px rgba(15, 23, 42, .28),
                    0 0 0 1px rgba(15, 23, 42, .04);
        z-index: 130;
    }
    .guests-panel.open {
        display: block;
        animation: guestsPop .18s ease;
    }
    @keyframes guestsPop {
        from { opacity: 0; transform: translateY(-6px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .guests-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20px 0;
        border-bottom: 1px solid #EBEBEB;
    }
    .guests-row-last { border-bottom: none; }
    .guests-row-title { font-size: 15px; font-weight: 600; color: #222; }
    .guests-row-sub   { font-size: 13px; color: #717171; margin-top: 2px; }
    .guests-row-link  { text-decoration: underline; cursor: pointer; }

    .guests-ctrl { display: flex; align-items: center; gap: 16px; }
    .guests-btn {
        width: 32px; height: 32px;
        border-radius: 50%;
        border: 1px solid #B0B0B0;
        background: #fff;
        color: #717171;
        font-size: 16px;
        line-height: 1;
        display: flex; align-items: center; justify-content: center;
        cursor: pointer;
        transition: all .15s;
    }
    .guests-btn:hover:not(:disabled) { border-color: #222; color: #222; }
    .guests-btn:disabled { opacity: .3; cursor: not-allowed; }
    .guests-count { min-width: 16px; text-align: center; font-size: 15px; font-weight: 500; color: #222; }

    @media (max-width: 480px) {
        .guests-panel { width: 92vw; right: -50px; }
    }

    @media (max-width: 768px) {
        .pill-bar { flex-wrap: wrap; border-radius: 24px; gap: 4px; }
        .pill-bg  { display: none; }          /* mobile: bỏ indicator, dùng nền trực tiếp */
        .sf       { flex: 1 1 45%; border-radius: 16px; }
        .sf.active { background: #fff; box-shadow: 0 4px 12px rgba(15,23,42,.12); }
        .sf-div   { display: none; }
        .pill-btn { width: 100%; margin-left: 0; justify-content: center; }
    }

        /* ===== "When" field — clear (×) button ===== */
    .sf-clear-btn {
        all: unset;
        position: absolute;
        right: 2px;
        top: 50%;
        transform: translateY(-50%);
        width: 24px; height: 24px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 50%;
        color: #222;
        font-size: 15px;
        cursor: pointer;
        z-index: 2;
        transition: background .15s;
    }
    .sf-clear-btn:hover { background: #EBEBEB; }
    .sf-clear-btn.hidden { display: none; }
    .sf-dates { position: relative; padding-right: 28px; }

    /* ===== Dates popover ===== */
    .dates-panel {
        display: none;
        position: absolute;
        top: calc(100% + 18px);
        left: 50%;
        transform: translateX(-50%);
        width: 720px;
        max-width: 92vw;
        max-height: 80vh;
        overflow-y: auto;
        background: #fff;
        border-radius: 24px;
        padding: 24px 28px 20px;
        box-shadow: 0 20px 50px -12px rgba(15, 23, 42, .28),
                    0 0 0 1px rgba(15, 23, 42, .04);
        z-index: 130;
    }
    .dates-panel.open { display: block; animation: guestsPop .18s ease; }

    .dates-tabs { display: flex; justify-content: center; margin-bottom: 22px; }
    .dates-tabs-inner {
        display: inline-flex;
        background: #EBEBEB;
        border-radius: 9999px;
        padding: 4px;
    }
    .dates-tab {
        all: unset;
        padding: 9px 22px;
        border-radius: 9999px;
        font-size: 13.5px;
        font-weight: 600;
        color: #6B6B6B;
        cursor: pointer;
        transition: all .18s;
    }
    .dates-tab.active { background: #fff; color: #222; box-shadow: 0 1px 4px rgba(0,0,0,.12); }

    .dates-cal-wrap {
        position: relative;
        display: flex;
        gap: 40px;
        justify-content: center;
    }
    .cal-nav {
        all: unset;
        position: absolute;
        top: 6px;
        width: 30px; height: 30px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 50%;
        border: 1px solid #DDDDDD;
        color: #222;
        font-size: 16px;
        cursor: pointer;
        transition: background .15s;
    }
    .cal-nav:hover { background: #F5F5F5; }
    .cal-nav:disabled { opacity: .3; cursor: not-allowed; }
    .cal-nav-prev { left: 0; }
    .cal-nav-next { right: 0; }

    .cal-month { width: 300px; flex-shrink: 0; }
    .cal-month-title { text-align: center; font-size: 15px; font-weight: 700; color: #222; margin-bottom: 18px; }
    .cal-weekdays {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        margin-bottom: 4px;
    }
    .cal-weekdays span { text-align: center; font-size: 11px; font-weight: 600; color: #717171; padding-bottom: 6px; }

    .cal-days { display: grid; grid-template-columns: repeat(7, 1fr); }
    .cal-cell { display: flex; align-items: center; justify-content: center; padding: 2px 0; }
    .cal-cell.in-range    { background: #EBEBEB; }
    .cal-cell.range-start { background: linear-gradient(to right, transparent 50%, #EBEBEB 50%); }
    .cal-cell.range-end   { background: linear-gradient(to right, #EBEBEB 50%, transparent 50%); }

    .cal-day {
        all: unset;
        box-sizing: border-box;
        width: 36px; height: 36px;
        display: flex; align-items: center; justify-content: center;
        font-size: 13.5px;
        color: #222;
        border-radius: 50%;
        cursor: pointer;
        transition: background .12s;
    }
    .cal-day:hover:not(.is-disabled):not(.is-empty) { background: #F0F0F0; }
    .cal-day.is-empty    { cursor: default; visibility: hidden; }
    .cal-day.is-disabled { color: #DDDDDD; cursor: not-allowed; }
    .cal-day.is-selected { background: #0F172A; color: #fff; font-weight: 700; }

    .dates-flex-row {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: center;
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid #EBEBEB;
    }
    .flex-chip {
        all: unset;
        padding: 9px 16px;
        border-radius: 9999px;
        border: 1px solid #DDDDDD;
        font-size: 13px;
        font-weight: 600;
        color: #222;
        cursor: pointer;
        transition: all .15s;
    }
    .flex-chip:hover { border-color: #222; }
    .flex-chip.active { border-color: #222; background: #222; color: #fff; }

    @media (max-width: 768px) {
        .dates-panel    { width: 94vw; padding: 20px 16px; }
        .dates-cal-wrap { flex-direction: column; gap: 24px; }
        .cal-month      { width: 100%; }
    }
</style>

<div class="hero-wrapper">

    {{-- ===== CAROUSEL ===== --}}
    <div class="hero-container" id="heroCarousel">

        @foreach ($heroSlides as $i => $slide)
            <div class="hero-slide {{ $i === 0 ? 'active' : '' }}">
                <img src="{{ asset('image/banner_home/' . rawurlencode($slide['img'])) }}"
                     alt="HolidayViet banner {{ $i + 1 }}"
                     loading="{{ $i === 0 ? 'eager' : 'lazy' }}">
                <div class="hero-gradient"></div>

                <div class="hero-content">
                    <div class="hero-inner">
                        <span class="hero-eyebrow">
                            <span class="w-1.5 h-1.5 rounded-full bg-[#60A5FA]"></span>
                            {{ $slide['eyebrow'] }}
                        </span>
                        <h1 class="hero-title">{!! $slide['title'] !!}</h1>
                        <p class="hero-sub">{{ $slide['sub'] }}</p>
                        <div class="hero-cta-row">
                            <a href="#allHotelsGrid" class="btn-primary px-7 py-3 text-sm click-effect">
                                Tìm khách sạn ngay
                            </a>
                            <a href="/itineraries" class="hero-btn-ghost click-effect">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                Tạo lịch trình AI
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        {{-- Mũi tên --}}
        <button class="hero-arrow hero-arrow-prev" id="heroPrev" aria-label="Previous slide">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
        </button>
        <button class="hero-arrow hero-arrow-next" id="heroNext" aria-label="Next slide">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
        </button>

        {{-- Dots --}}
        <div class="hero-dots" id="heroDots">
            @foreach ($heroSlides as $i => $slide)
                <span class="hero-dot {{ $i === 0 ? 'active' : '' }}" data-index="{{ $i }}"></span>
            @endforeach
        </div>
    </div>

        {{-- ===== SEARCH BAR ===== --}}
    <div class="search-wrapper">
        <form class="pill-bar" id="heroSearchBar" method="GET" action="{{ route('home') }}" autocomplete="off">

            {{-- indicator trượt --}}
            <span class="pill-bg" id="pillBg"></span>

            <div class="sf" data-sf>
                <label>Where</label>
                <input type="text" id="searchInput" name="search"
                       value="{{ request('search') }}"
                       placeholder="Search destinations, hotels, or activities">
                <div id="searchDropdown"
                     class="hidden absolute left-0 top-[calc(100%+18px)] w-[420px] max-h-[380px] overflow-y-auto bg-white rounded-2xl shadow-2xl border border-slate-100 z-[120]"></div>
            </div>

            <span class="sf-div" data-div></span>

            <div class="sf sf-dates" data-sf id="sfDates">
                <label>When</label>
                <button type="button" class="sf-dates-trigger" id="datesTrigger" aria-expanded="false" aria-controls="datesPanel">
                    <span id="datesDisplay" class="sf-guests-placeholder">Add dates</span>
                </button>
                <button type="button" class="sf-clear-btn hidden" id="datesClearBtn" aria-label="Clear dates">×</button>

                <input type="hidden" name="check_in"  id="checkInInput"  value="{{ request('check_in') }}">
                <input type="hidden" name="check_out" id="checkOutInput" value="{{ request('check_out') }}">
                
                                <div class="dates-panel" id="datesPanel">
                    <div class="dates-tabs">
                        <div class="dates-tabs-inner">
                            <button type="button" class="dates-tab active" data-tab="exact">Dates</button>
                            <button type="button" class="dates-tab" data-tab="flexible">Flexible</button>
                        </div>
                    </div>

                    <div class="dates-cal-wrap">
                      <button type="button" class="cal-nav cal-nav-prev" id="calPrev" aria-label="Previous month">‹</button>
                      <div class="dates-cal-months" id="calMonths"></div>
                      <button type="button" class="cal-nav cal-nav-next" id="calNext" aria-label="Next month">›</button>
                    </div>

                    <div class="dates-flex-row" id="datesFlexRow">
                        <button type="button" class="flex-chip active" data-flex="0">Exact dates</button>
                        <button type="button" class="flex-chip" data-flex="1">± 1 day</button>
                        <button type="button" class="flex-chip" data-flex="2">± 2 days</button>
                        <button type="button" class="flex-chip" data-flex="3">± 3 days</button>
                        <button type="button" class="flex-chip" data-flex="7">± 7 days</button>
                        <button type="button" class="flex-chip" data-flex="14">± 14 days</button>
                    </div>
                </div>
            </div>

            <span class="sf-div" data-div></span>

            <div class="sf sf-guests" data-sf id="sfGuests">
                <label>Who</label>
                <button type="button" class="sf-guests-trigger" id="guestsTrigger" aria-expanded="false" aria-controls="guestsPanel">
                    <span id="guestsDisplay" class="sf-guests-placeholder">Add guests</span>
                </button>

                <input type="hidden" name="guests" id="guestsTotalInput" value="{{ request('guests') }}">
                <input type="hidden" name="adults" id="adultsInput" value="{{ request('adults', 0) }}">
                <input type="hidden" name="children" id="childrenInput" value="{{ request('children', 0) }}">
                <input type="hidden" name="infants" id="infantsInput" value="{{ request('infants', 0) }}">
                <input type="hidden" name="pets" id="petsInput" value="{{ request('pets', 0) }}">

                <div class="guests-panel" id="guestsPanel">
                    <div class="guests-row" data-counter="adults">
                        <div class="guests-row-text">
                            <div class="guests-row-title">Adults</div>
                            <div class="guests-row-sub">Ages 13 or above</div>
                        </div>
                        <div class="guests-ctrl">
                            <button type="button" class="guests-btn" data-action="minus" aria-label="Remove adult">−</button>
                            <span class="guests-count" data-count>0</span>
                            <button type="button" class="guests-btn" data-action="plus" aria-label="Add adult">+</button>
                        </div>
                    </div>

                    <div class="guests-row" data-counter="children">
                        <div class="guests-row-text">
                            <div class="guests-row-title">Children</div>
                            <div class="guests-row-sub">Ages 2 – 12</div>
                        </div>
                        <div class="guests-ctrl">
                            <button type="button" class="guests-btn" data-action="minus" aria-label="Remove child">−</button>
                            <span class="guests-count" data-count>0</span>
                            <button type="button" class="guests-btn" data-action="plus" aria-label="Add child">+</button>
                        </div>
                    </div>

                    <div class="guests-row" data-counter="infants">
                        <div class="guests-row-text">
                            <div class="guests-row-title">Infants</div>
                            <div class="guests-row-sub">Under 2</div>
                        </div>
                        <div class="guests-ctrl">
                            <button type="button" class="guests-btn" data-action="minus" aria-label="Remove infant">−</button>
                            <span class="guests-count" data-count>0</span>
                            <button type="button" class="guests-btn" data-action="plus" aria-label="Add infant">+</button>
                        </div>
                    </div>

                    <div class="guests-row guests-row-last" data-counter="pets">
                        <div class="guests-row-text">
                            <div class="guests-row-title">Pets</div>
                            <div class="guests-row-sub guests-row-link">Bringing a service animal?</div>
                        </div>
                        <div class="guests-ctrl">
                            <button type="button" class="guests-btn" data-action="minus" aria-label="Remove pet">−</button>
                            <span class="guests-count" data-count>0</span>
                            <button type="button" class="guests-btn" data-action="plus" aria-label="Add pet">+</button>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="pill-btn click-effect">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <span class="hidden sm:inline">Tìm kiếm</span>
            </button>
        </form>
    </div>
</div>
