@extends('layout')

@section('title', 'Trang chủ')

@section('content')

<style>
    .hotel-card {
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
    }
    .hotel-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 25px 40px -12px rgba(58, 74, 90, 0.2);
    }
    .wishlist-btn { transition: all 0.2s ease; cursor: pointer; }
    .wishlist-btn:hover { transform: scale(1.2); }
    .filter-hover:hover { border-color: #87CEFA; background: #EAF3FF; }

    @keyframes slideUp {
        from { opacity: 0; transform: translateY(50px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .modal-show { animation: slideUp 0.3s ease; }

    .chatbot-header { background: linear-gradient(135deg, #87CEFA 0%, #7BC4F5 100%); }
    .chatbot-message-bot { background: #EAF3FF; color: #3A4A5A; border-radius: 12px 12px 12px 4px; }
    .chatbot-message-user { background: linear-gradient(135deg, #87CEFA, #7BC4F5); color: white; border-radius: 12px 12px 4px 12px; }
    .chatbot-input:focus { border-color: #87CEFA; box-shadow: 0 0 0 3px rgba(135,206,250,0.1); outline: none; }

    /* BANNER */
    .banner-section {
        position: relative;
        height: 420px;
        overflow: hidden;
        border-radius: 0;
    }
    @media (max-width: 768px) { .banner-section { height: 280px; } }

    .banner-bg {
        position: absolute; inset: 0;
        background-image: url('{{ asset("image/banner.jpg") }}');
        background-size: cover;
        background-position: center;
        transition: transform 8s ease;
    }
    .banner-bg:hover { transform: scale(1.03); }

    /* Overlay gradient trái → phải như ảnh 2 */
    .banner-overlay {
        position: absolute; inset: 0;
        background: linear-gradient(
            to right,
            rgba(10, 20, 40, 0.88) 0%,
            rgba(10, 20, 40, 0.65) 45%,
            rgba(10, 20, 40, 0.1) 100%
        );
    }

    .banner-content {
        position: relative; z-index: 10;
        height: 100%;
        display: flex;
        align-items: center;
        padding: 0 5%;
    }

    .banner-badge {
        display: inline-flex; align-items: center; gap: 6px;
        background: linear-gradient(135deg, #F59E0B, #EF4444);
        color: white; font-size: 12px; font-weight: 700;
        padding: 5px 14px; border-radius: 20px;
        text-transform: uppercase; letter-spacing: 0.5px;
        margin-bottom: 16px;
    }

    .banner-title-sub {
        color: #FCD34D;
        font-size: clamp(16px, 2.5vw, 22px);
        font-weight: 700;
        letter-spacing: 1px;
        text-transform: uppercase;
        margin-bottom: 4px;
    }

    .banner-title-main {
        color: white;
        font-size: clamp(32px, 5vw, 58px);
        font-weight: 900;
        line-height: 1.1;
        margin-bottom: 12px;
        font-family: 'Playfair Display', serif;
    }

    .banner-title-main span {
        background: linear-gradient(135deg, #F59E0B, #FBBF24);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .banner-subtitle {
        color: rgba(255,255,255,0.75);
        font-size: clamp(13px, 1.5vw, 16px);
        margin-bottom: 28px;
        max-width: 420px;
    }

    .banner-cta {
        display: inline-flex; align-items: center; gap: 8px;
        background: linear-gradient(135deg, #F59E0B, #EF4444);
        color: white; font-weight: 700; font-size: 15px;
        padding: 14px 32px; border-radius: 50px;
        text-decoration: none;
        transition: all 0.3s ease;
        box-shadow: 0 8px 24px rgba(239,68,68,0.4);
    }
    .banner-cta:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 32px rgba(239,68,68,0.5);
    }

    /* Countdown cuối tuần */
    .countdown-wrap {
        position: absolute;
        bottom: 28px; right: 5%;
        display: flex; align-items: center; gap: 12px;
        z-index: 10;
    }
    @media (max-width: 640px) { .countdown-wrap { display: none; } }

    .countdown-label {
        color: rgba(255,255,255,0.7);
        font-size: 12px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.5px;
        text-align: right;
    }

    .countdown-boxes {
        display: flex; gap: 8px;
    }

    .countdown-box {
        background: rgba(255,255,255,0.12);
        backdrop-filter: blur(8px);
        border: 1px solid rgba(255,255,255,0.2);
        border-radius: 12px;
        padding: 10px 14px;
        text-align: center;
        min-width: 60px;
    }

    .countdown-num {
        color: #FCD34D;
        font-size: 28px; font-weight: 900;
        line-height: 1; font-family: monospace;
    }

    .countdown-unit {
        color: rgba(255,255,255,0.6);
        font-size: 10px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.5px;
        margin-top: 2px;
    }

    /* Features strip dưới banner */
    .feature-strip {
        display: flex; align-items: center; gap: 6px;
        flex-wrap: wrap; margin-top: 20px;
    }
    .feature-chip {
        display: inline-flex; align-items: center; gap: 5px;
        background: rgba(255,255,255,0.12);
        border: 1px solid rgba(255,255,255,0.2);
        color: white; font-size: 12px; font-weight: 500;
        padding: 5px 12px; border-radius: 20px;
    }
</style>

{{-- SEARCH HERO --}}
<section style="background: linear-gradient(135deg, #87CEFA 0%, #EAF3FF 50%, #FFFFFF 100%);" class="relative overflow-hidden">
    <div class="absolute inset-0 opacity-20">
        <div class="absolute top-10 left-10 w-64 h-64 rounded-full" style="background: radial-gradient(circle, #87CEFA, transparent);"></div>
        <div class="absolute bottom-0 right-20 w-80 h-80 rounded-full" style="background: radial-gradient(circle, #C9D3DD, transparent);"></div>
    </div>

    <div class="relative max-w-7xl mx-auto px-4 py-14 text-center">
        <p class="text-[#3A4A5A] text-xs font-bold tracking-widest uppercase mb-3 flex items-center justify-center gap-2">
            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
            </svg>
            KHÁM PHÁ VIỆT NAM
        </p>
        <h1 class="font-display text-4xl md:text-5xl font-bold text-[#1E3A5F] mb-4" style="line-height: 1.2;">
            Tìm khách sạn <span style="color: #87CEFA;">hoàn hảo</span>
        </h1>
        <p class="text-[#5A6A7A] text-base mb-8 max-w-lg mx-auto">{{ $hotels->total() }}+ khách sạn trên khắp Việt Nam, giá tốt nhất mỗi ngày</p>

        {{-- SEARCH BOX --}}
        <form action="{{ route('home') }}" method="GET" class="max-w-3xl mx-auto">
            <div class="bg-white rounded-2xl p-2 flex flex-col md:flex-row gap-2 shadow-xl" style="box-shadow: 0 10px 30px rgba(58,74,90,0.1);">
                <div class="flex-1 relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#C9D3DD]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Tên khách sạn..."
                        class="w-full pl-10 pr-4 py-3 rounded-xl text-[#3A4A5A] text-sm focus:outline-none focus:ring-2 focus:ring-[#87CEFA]">
                </div>
                <div class="relative flex-1">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#C9D3DD]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <select name="city" class="w-full pl-10 pr-4 py-3 rounded-xl text-[#3A4A5A] text-sm focus:outline-none bg-[#EAF3FF] border border-[#C9D3DD] filter-hover transition">
                        <option value="">Tất cả thành phố</option>
                        @foreach($locations as $location)
                            <option value="{{ $location->name }}" {{ request('city') == $location->name ? 'selected' : '' }}>
                                {{ $location->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="relative flex-1">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#87CEFA]" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                    </svg>
                    <select name="star" class="w-full pl-10 pr-4 py-3 rounded-xl text-[#3A4A5A] text-sm focus:outline-none bg-[#EAF3FF] border border-[#C9D3DD] filter-hover transition">
                        <option value="">Số sao</option>
                        <option value="5" {{ request('star') == 5 ? 'selected' : '' }}>5 sao ⭐⭐⭐⭐⭐</option>
                        <option value="4" {{ request('star') == 4 ? 'selected' : '' }}>4 sao ⭐⭐⭐⭐</option>
                        <option value="3" {{ request('star') == 3 ? 'selected' : '' }}>3 sao ⭐⭐⭐</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary px-7 py-3 rounded-xl text-sm whitespace-nowrap click-effect font-semibold flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    Tìm kiếm
                </button>
            </div>
        </form>
    </div>
</section>

{{-- ===== FLASH SALE BANNER ===== --}}
<div class="max-w-7xl mx-auto px-4 mt-8 mb-2">
    <div class="banner-section rounded-2xl overflow-hidden shadow-2xl">

        {{-- Background ảnh --}}
        <div class="banner-bg"></div>

        {{-- Overlay --}}
        <div class="banner-overlay"></div>

        {{-- Content bên trái --}}
        <div class="banner-content">
            <div>
                {{-- Badge --}}
                <div class="banner-badge">
                    🔥 Flash Sale Cuối Tuần
                </div>

                {{-- Tiêu đề --}}
                <p class="banner-title-sub">Mùa hè rực rỡ</p>
                <h2 class="banner-title-main">
                    Giảm đến <span>50%</span>
                    <span style="font-size: 0.5em; color: #F59E0B; -webkit-text-fill-color: #F59E0B;">✦</span>
                </h2>

                {{-- Mô tả --}}
                <p class="banner-subtitle">
                    Ưu đãi khủng cho hàng trăm khách sạn cao cấp toàn quốc — chỉ đến hết Chủ nhật!
                </p>

                {{-- Features --}}
                <div class="feature-strip">
                    <div class="feature-chip">🏆 Giá tốt nhất</div>
                    <div class="feature-chip">✅ Hủy miễn phí</div>
                    <div class="feature-chip">⚡ Đặt ngay, trả sau</div>
                </div>

                {{-- CTA Button --}}
                <div class="mt-6">
                    <a href="/deals" class="banner-cta">
                        XEM ƯU ĐÃI NGAY
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        {{-- Countdown cuối tuần — góc phải dưới --}}
        <div class="countdown-wrap">
            <div class="countdown-label">
                Ưu đãi kết thúc<br>sau
            </div>
            <div class="countdown-boxes">
                <div class="countdown-box">
                    <div class="countdown-num" id="cd-hours">00</div>
                    <div class="countdown-unit">Giờ</div>
                </div>
                <div class="countdown-box">
                    <div class="countdown-num" id="cd-mins">00</div>
                    <div class="countdown-unit">Phút</div>
                </div>
                <div class="countdown-box">
                    <div class="countdown-num" id="cd-secs">00</div>
                    <div class="countdown-unit">Giây</div>
                </div>
            </div>
        </div>

    </div>
</div>

{{-- HOTEL LISTING --}}
<section class="max-w-7xl mx-auto px-4 py-10">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="font-display text-2xl font-bold text-[#1E3A5F] flex items-center gap-2">
                @if(request('city'))
                    <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="12" cy="9" r="2.5" stroke="currentColor" stroke-width="1.5"/>
                    </svg>
                    <span>Khách sạn tại {{ request('city') }}</span>
                @elseif(request('search'))
                    <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="1.5"/><path d="M21 21L16.65 16.65" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                    <span>Kết quả: "{{ request('search') }}"</span>
                @else
                    <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none"><path d="M12 2L15 8.5L22 9.5L17 14L18.5 21L12 17.5L5.5 21L7 14L2 9.5L9 8.5L12 2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span>Khách sạn nổi bật</span>
                @endif
            </h2>
            <p class="text-[#C9D3DD] text-sm mt-1">{{ $hotels->total() }} khách sạn được tìm thấy</p>
        </div>

        @if(request()->hasAny(['search', 'city', 'star']))
            <a href="{{ route('home') }}" class="text-sm text-[#C9D3DD] hover:text-[#87CEFA] underline flex items-center gap-1 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                Xóa bộ lọc
            </a>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($hotels as $hotel)
        <div class="hotel-card bg-white rounded-2xl overflow-hidden shadow-sm border border-[#C9D3DD]/30 click-effect"
            onclick="showHotelDetail({{ $hotel->id }}, '{{ addslashes($hotel->name) }}', '{{ addslashes($hotel->location->name) }}', {{ $hotel->star_rating }}, {{ $hotel->rooms_min_price ?? 0 }}, '{{ addslashes($hotel->description ?? '') }}')">

            <div class="relative h-52 bg-[#EAF3FF] overflow-hidden">
                <img src="https://picsum.photos/seed/{{ $hotel->id }}/800/600" alt="{{ $hotel->name }}"
                    class="w-full h-full object-cover transition duration-500 hover:scale-110">
                <div class="absolute top-3 left-3">
                    <div class="bg-white/95 backdrop-blur-sm px-2 py-1 rounded-lg flex items-center gap-1 shadow-sm">
                        <svg class="w-3 h-3 text-[#87CEFA]" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                        </svg>
                        <span class="text-[#3A4A5A] text-xs font-bold">{{ $hotel->star_rating }} Sao</span>
                    </div>
                </div>
                <button onclick="event.stopPropagation(); toggleWishlist({{ $hotel->id }}, this)"
                    class="absolute top-3 right-3 bg-white/95 backdrop-blur-sm w-8 h-8 rounded-full flex items-center justify-center text-[#C9D3DD] hover:text-red-500 transition wishlist-btn shadow-sm"
                    data-hotel="{{ $hotel->id }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                    </svg>
                </button>
            </div>

            <div class="p-5">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1">
                        <h3 class="font-semibold text-[#1E3A5F] text-base leading-snug">{{ $hotel->name }}</h3>
                        <p class="text-[#C9D3DD] text-xs mt-1.5 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            {{ $hotel->location->name }}
                        </p>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <svg class="w-4 h-4 text-[#87CEFA]" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                        </svg>
                        <span class="text-sm font-semibold text-[#3A4A5A]">{{ $hotel->avg_rating }}</span>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-[#EAF3FF] flex items-center justify-between">
                    <div>
                        @if($hotel->rooms_min_price)
                            <p class="text-xs text-[#C9D3DD]">Giá từ</p>
                            <p class="font-bold text-[#87CEFA] text-lg">{{ number_format($hotel->rooms_min_price) }}<span class="text-xs font-normal text-[#C9D3DD]">/đêm</span></p>
                        @else
                            <p class="text-xs text-[#C9D3DD]">Liên hệ</p>
                        @endif
                    </div>
                    <span class="text-xs btn-primary px-4 py-2 rounded-lg font-semibold inline-flex items-center gap-1">
                        Xem chi tiết
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </span>
                </div>
            </div>
        </div>
        @empty
        <div class="col-span-3 text-center py-20 text-[#C9D3DD]">
            <p class="text-6xl mb-4 flex justify-center">
                <svg class="w-16 h-16 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </p>
            <p class="text-lg text-[#3A4A5A]">Không tìm thấy khách sạn phù hợp</p>
            <a href="{{ route('home') }}" class="text-[#87CEFA] underline mt-2 inline-block">Xem tất cả</a>
        </div>
        @endforelse
    </div>

    <div class="mt-10">
        {{ $hotels->withQueryString()->links() }}
    </div>
</section>

{{-- MODAL --}}
<div id="hotelModal" class="fixed inset-0 bg-[#3A4A5A]/50 z-50 hidden items-center justify-center p-4" onclick="closeModal()">
    <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto modal-show" onclick="event.stopPropagation()">
        <div class="sticky top-0 bg-white border-b border-[#EAF3FF] p-4 flex justify-between items-center">
            <h3 id="modalTitle" class="font-display text-xl font-bold text-[#1E3A5F]"></h3>
            <button onclick="closeModal()" class="text-[#C9D3DD] hover:text-[#3A4A5A] text-2xl leading-none">&times;</button>
        </div>
        <div class="p-6">
            <div id="modalContent"></div>
            <div class="mt-6 flex gap-3">
                <button onclick="closeModal()" class="flex-1 px-4 py-2 border border-[#C9D3DD] rounded-xl text-[#3A4A5A] hover:bg-[#EAF3FF] transition text-sm">Đóng</button>
                <button id="bookNowBtn" class="flex-1 btn-primary px-4 py-2 rounded-xl font-semibold click-effect text-sm">Đặt phòng ngay →</button>
            </div>
        </div>
    </div>
</div>

{{-- CHATBOT --}}
<div class="fixed bottom-6 right-6 z-40">
    <button onclick="toggleChat()" class="btn-primary w-14 h-14 rounded-full flex items-center justify-center text-2xl shadow-xl hover:scale-110 transition click-effect" title="Chat với AI">
        💬
    </button>
</div>

<div id="chat-popup" class="fixed bottom-24 right-6 z-40 hidden w-96">
    <div class="bg-white rounded-2xl shadow-2xl border border-[#C9D3DD]/30 overflow-hidden">
        <div class="chatbot-header px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 bg-white rounded-full flex items-center justify-center">
                    <span class="text-lg">🤖</span>
                </div>
                <div>
                    <p class="text-white font-semibold text-sm">AI Assistant</p>
                    <p class="text-white/80 text-xs">● Online</p>
                </div>
            </div>
            <button onclick="toggleChat()" class="text-white/70 hover:text-white text-lg transition">✕</button>
        </div>
        <div id="chat-messages" class="h-80 overflow-y-auto p-4 space-y-3 bg-[#EAF3FF]/30">
            <div class="flex gap-2">
                <div class="w-8 h-8 bg-[#87CEFA] rounded-full flex items-center justify-center shrink-0">
                    <span class="text-sm">🤖</span>
                </div>
                <div class="chatbot-message-bot px-3 py-2 text-sm max-w-[85%] shadow-sm">
                    Xin chào! Tôi có thể giúp bạn tìm khách sạn hoặc lên lịch trình du lịch. Bạn muốn đi đâu? 🌟
                </div>
            </div>
        </div>
        <div class="p-3 border-t border-[#C9D3DD]/30 bg-white">
            <div class="flex gap-2">
                <input id="chat-input" type="text" placeholder="Nhập tin nhắn..."
                    class="chatbot-input flex-1 text-sm border border-[#C9D3DD] rounded-xl px-3 py-2 transition"
                    onkeypress="if(event.key==='Enter') sendChat()">
                <button onclick="sendChat()" class="btn-primary px-4 py-2 rounded-xl text-sm click-effect font-semibold">Gửi</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// ========================
// COUNTDOWN CUỐI TUẦN
// Đếm đến 23:59:59 Chủ nhật tuần hiện tại
// ========================
function updateCountdown() {
    const now     = new Date();
    const day     = now.getDay(); // 0=CN, 1=T2...6=T7
    // Số ngày còn đến Chủ nhật (nếu hôm nay là CN thì = 0)
    const daysToSun = day === 0 ? 0 : 7 - day;
    const sunday  = new Date(now);
    sunday.setDate(now.getDate() + daysToSun);
    sunday.setHours(23, 59, 59, 0);

    const diff = sunday - now;
    if (diff <= 0) { updateCountdown(); return; }

    const h = Math.floor(diff / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    const s = Math.floor((diff % 60000) / 1000);

    document.getElementById('cd-hours').textContent = String(h).padStart(2, '0');
    document.getElementById('cd-mins').textContent  = String(m).padStart(2, '0');
    document.getElementById('cd-secs').textContent  = String(s).padStart(2, '0');
}
updateCountdown();
setInterval(updateCountdown, 1000);

// ========================
// MODAL
// ========================
function showHotelDetail(id, name, location, star, price, description) {
    document.getElementById('modalTitle').textContent = name;
    document.getElementById('modalContent').innerHTML = `
        <div class="space-y-4">
            <div class="bg-[#EAF3FF] p-4 rounded-xl space-y-2">
                <p class="text-sm text-[#3A4A5A]">📍 <strong>Địa điểm:</strong> ${location}</p>
                <p class="text-sm text-[#3A4A5A]">${'⭐'.repeat(star)} <strong>${star} sao</strong></p>
            </div>
            <div>
                <h4 class="font-semibold text-[#1E3A5F] mb-2 text-sm">Mô tả</h4>
                <p class="text-[#5A6A7A] text-sm leading-relaxed">${description || 'Khách sạn sang trọng với dịch vụ đẳng cấp, tiện nghi hiện đại.'}</p>
            </div>
            <div class="bg-[#EAF3FF] p-4 rounded-xl">
                <p class="text-xs text-[#C9D3DD] mb-1">Giá từ</p>
                <p class="text-2xl font-bold text-[#87CEFA]">${price > 0 ? new Intl.NumberFormat('vi-VN').format(price) + 'đ' : 'Liên hệ'}<span class="text-sm font-normal text-[#C9D3DD]">/đêm</span></p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <span class="px-3 py-1 bg-[#EAF3FF] text-[#3A4A5A] rounded-full text-xs">✓ Wifi miễn phí</span>
                <span class="px-3 py-1 bg-[#EAF3FF] text-[#3A4A5A] rounded-full text-xs">✓ Bể bơi</span>
                <span class="px-3 py-1 bg-[#EAF3FF] text-[#3A4A5A] rounded-full text-xs">✓ Spa</span>
                <span class="px-3 py-1 bg-[#EAF3FF] text-[#3A4A5A] rounded-full text-xs">✓ Nhà hàng</span>
            </div>
        </div>`;
    document.getElementById('bookNowBtn').onclick = () => window.location.href = `/hotels/${id}`;
    const modal = document.getElementById('hotelModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeModal() {
    const modal = document.getElementById('hotelModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

// ========================
// CHATBOT
// ========================
let chatSessionId = null;

function toggleChat() {
    const popup = document.getElementById('chat-popup');
    popup.classList.toggle('hidden');
    if (!popup.classList.contains('hidden') && !chatSessionId) startChatSession();
}

async function startChatSession() {
    const token = localStorage.getItem('token');
    if (!token) { addChatMessage('bot', 'Vui lòng <a href="/login" class="underline font-semibold">đăng nhập</a> để chat với AI!'); return; }
    const res  = await api('/chat/start', { method: 'POST' });
    const data = await res.json();
    chatSessionId = data.session_id;
}

async function sendChat() {
    const input = document.getElementById('chat-input');
    const msg   = input.value.trim();
    if (!msg) return;
    addChatMessage('user', msg);
    input.value = '';
    if (!chatSessionId) { addChatMessage('bot', 'Vui lòng <a href="/login" class="underline font-semibold">đăng nhập</a>!'); return; }
    addChatMessage('bot', '⏳ Đang trả lời...');
    const res  = await api(`/chat/${chatSessionId}/message`, { method: 'POST', body: JSON.stringify({ message: msg }) });
    const data = await res.json();
    document.getElementById('chat-messages').lastElementChild.remove();
    addChatMessage('bot', data.message);
}

function addChatMessage(role, text) {
    const messages = document.getElementById('chat-messages');
    const isBot    = role === 'bot';
    const div      = document.createElement('div');
    div.className  = `flex gap-2 ${isBot ? '' : 'flex-row-reverse'}`;
    div.innerHTML  = `
        ${isBot ? '<div class="w-8 h-8 bg-[#87CEFA] rounded-full flex items-center justify-center shrink-0"><span class="text-sm">🤖</span></div>' : ''}
        <div class="px-3 py-2 text-sm max-w-[85%] shadow-sm ${isBot ? 'chatbot-message-bot' : 'chatbot-message-user'}">${text}</div>`;
    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
}

async function toggleWishlist(hotelId, btn) {
    const token = localStorage.getItem('token');
    if (!token) { showToast('Vui lòng đăng nhập để lưu yêu thích!'); return; }
    const res  = await api(`/wishlist/${hotelId}/toggle`, { method: 'POST' });
    const data = await res.json();
    const fill = data.liked ? 'currentColor' : 'none';
    btn.innerHTML = `<svg class="w-4 h-4" fill="${fill}" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>`;
    btn.style.color = data.liked ? '#ef4444' : '';
    showToast(data.liked ? '❤️ Đã lưu yêu thích' : 'Đã bỏ yêu thích');
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
</script>
@endpush