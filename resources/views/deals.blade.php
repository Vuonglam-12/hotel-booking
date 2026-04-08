@extends('layout')

@section('title', 'Ưu đãi & Khuyến mãi')

@section('content')

<style>
    /* BANNER */
    .deals-banner {
        position: relative; height: 380px; overflow: hidden;
    }
    .deals-banner-bg {
        position: absolute; inset: 0;
        background-image: url('{{ asset("image/bannerdeal.jpg") }}');
        background-size: cover; background-position: center;
    }
    .deals-banner-overlay {
        position: absolute; inset: 0;
        background: linear-gradient(to right, rgba(10,20,40,0.88) 0%, rgba(10,20,40,0.55) 50%, rgba(10,20,40,0.15) 100%);
    }
    .deals-banner-content {
        position: relative; z-index: 10; height: 100%;
        display: flex; align-items: center; padding: 0 5%;
    }

    /* Cards */
    .hotel-card,
    .deal-card {
        transition: all 0.4s cubic-bezier(0.4,0,0.2,1);
        cursor: pointer;
    }
    .hotel-card:hover,
    .deal-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 25px 40px -12px rgba(58,74,90,0.2);
    }

    /* Discount badge */
    .discount-badge {
        position: absolute; top: 12px; left: 12px;
        background: linear-gradient(135deg, #EF4444, #F59E0B);
        color: white; font-size: 12px; font-weight: 800;
        padding: 4px 10px; border-radius: 20px;
        letter-spacing: 0.3px;
    }

    /* Timer */
    .timer-box {
        background: rgba(255,255,255,0.12); backdrop-filter: blur(8px);
        border: 1px solid rgba(255,255,255,0.2);
        border-radius: 10px; padding: 8px 12px; text-align: center; min-width: 52px;
    }
    .timer-num { color: #FCD34D; font-size: 24px; font-weight: 900; font-family: monospace; line-height: 1; }
    .timer-unit { color: rgba(255,255,255,0.6); font-size: 9px; font-weight: 600; text-transform: uppercase; margin-top: 2px; }

    /* Section title */
    .section-title {
        font-size: 22px; font-weight: 800; color: #1E3A5F;
        display: flex; align-items: center; gap: 8px;
    }
    .section-sub { color: #C9D3DD; font-size: 13px; margin-top: 2px; }

    /* Destination card */
    .dest-card {
        position: relative; border-radius: 16px; overflow: hidden;
        height: 160px; cursor: pointer;
        transition: all 0.3s ease;
    }
    .dest-card:hover { transform: translateY(-4px); box-shadow: 0 16px 32px -8px rgba(58,74,90,0.25); }
    .dest-card img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
    .dest-card:hover img { transform: scale(1.08); }
    .dest-overlay {
        position: absolute; inset: 0;
        background: linear-gradient(to top, rgba(10,20,40,0.75) 0%, transparent 60%);
    }
    .dest-info { position: absolute; bottom: 0; left: 0; right: 0; padding: 12px 14px; }

    /* Combo card */
    .combo-card,
    .hotel-card,
    .deal-card {
        transition: all 0.4s cubic-bezier(0.4,0,0.2,1);
        cursor: pointer;
    }
    .combo-card:hover,
    .hotel-card:hover,
    .deal-card:hover { transform: translateY(-8px); box-shadow: 0 25px 40px -12px rgba(58,74,90,0.2); }

    /* Voucher card */
    .voucher-card {
        background: linear-gradient(135deg, #1E3A5F, #0F3460);
        border-radius: 16px; padding: 20px 24px;
        display: flex; align-items: center; justify-content: space-between;
        position: relative; overflow: hidden;
    }
    .voucher-card::before {
        content: ''; position: absolute;
        right: -30px; top: 50%; transform: translateY(-50%);
        width: 80px; height: 80px; border-radius: 50%;
        background: rgba(255,255,255,0.05);
    }
    .voucher-dashed {
        border-left: 2px dashed rgba(255,255,255,0.2);
        padding-left: 20px; margin-left: 20px;
    }

    /* Filter tabs */
    .filter-tab {
        padding: 8px 18px; border-radius: 20px; font-size: 13px; font-weight: 600;
        cursor: pointer; transition: all 0.2s; border: 1.5px solid #E5E7EB;
        background: white; color: #6B7280;
    }
    .filter-tab.active, .filter-tab:hover {
        background: #87CEFA; color: #1E3A5F; border-color: #87CEFA;
    }

    /* Price strikethrough */
    .price-original { color: #9CA3AF; font-size: 12px; text-decoration: line-through; }
    .price-sale { color: #87CEFA; font-size: 18px; font-weight: 800; }

    /* Countdown inline */
    .inline-countdown { display: flex; align-items: center; gap: 6px; }
    .inline-timer-box {
        background: #1E3A5F; color: #FCD34D;
        font-size: 13px; font-weight: 800; font-family: monospace;
        padding: 3px 8px; border-radius: 6px;
    }

    @media (max-width: 768px) { .deals-banner { height: 260px; } }
</style>

{{-- ===== BANNER ===== --}}
<div class="deals-banner">
    <div class="deals-banner-bg"></div>
    <div class="deals-banner-overlay"></div>
    <div class="deals-banner-content">
        <div>
            <div style="display:inline-flex;align-items:center;gap:6px;background:linear-gradient(135deg,#F59E0B,#EF4444);color:white;font-size:12px;font-weight:700;padding:5px 14px;border-radius:20px;text-transform:uppercase;margin-bottom:14px;">
                🎁 Ưu Đãi Tuyệt Vời
            </div>
            <p style="color:#FCD34D;font-size:clamp(14px,2vw,18px);font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">
                Trải nghiệm hoàn hảo
            </p>
            <h1 style="color:white;font-size:clamp(28px,4.5vw,52px);font-weight:900;font-family:'Playfair Display',serif;line-height:1.1;margin-bottom:12px;">
                Ưu đãi tuyệt vời,<br>
                <span style="background:linear-gradient(135deg,#F59E0B,#FBBF24);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">giảm đến 50%</span>
            </h1>
            <p style="color:rgba(255,255,255,0.75);font-size:clamp(13px,1.4vw,15px);margin-bottom:20px;max-width:380px;">
                Khám phá hàng ngàn ưu đãi độc quyền, giá tốt nhất cho chuyến đi của bạn
            </p>
            {{-- Countdown inline --}}
            <div class="inline-countdown mb-5">
                <span style="color:rgba(255,255,255,0.7);font-size:12px;font-weight:600;">Flash sale cuối tuần còn:</span>
                <div class="inline-timer-box" id="b-h">00</div>
                <span style="color:#FCD34D;font-weight:700;">:</span>
                <div class="inline-timer-box" id="b-m">00</div>
                <span style="color:#FCD34D;font-weight:700;">:</span>
                <div class="inline-timer-box" id="b-s">00</div>
            </div>
        </div>
    </div>
</div>

{{-- ===== MAIN CONTENT ===== --}}
<div class="max-w-7xl mx-auto px-4 py-10 space-y-14">

    {{-- ===== FLASH SALE ===== --}}
    <section>
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div>
                <h2 class="section-title">⚡ Flash Sale Cuối Tuần</h2>
                <p class="section-sub">Giá giảm mạnh – Số lượng có hạn, đặt ngay kẻo lỡ!</p>
            </div>
            {{-- Filter tabs --}}
            <div class="flex gap-2 flex-wrap">
                <button class="filter-tab active" onclick="filterDeals('all', this)">Tất cả</button>
                @foreach($locations->take(4) as $loc)
                <button class="filter-tab" onclick="filterDeals('{{ $loc->name }}', this)">{{ $loc->name }}</button>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5" id="flash-grid">
            @foreach($flashSale as $hotel)
            <div class="hotel-card deal-card bg-white rounded-2xl overflow-hidden shadow-sm border border-[#EAF3FF]"
                onclick="window.location.href='/hotels/{{ $hotel->id }}'"
                data-city="{{ $hotel->location->name }}">
                <div class="relative h-40 overflow-hidden bg-[#EAF3FF]">
                    <img src="https://picsum.photos/seed/{{ $hotel->id }}d/400/300" alt="{{ $hotel->name }}"
                        class="w-full h-full object-cover transition duration-500 hover:scale-110">
                    <div class="discount-badge">-{{ $hotel->discount }}%</div>
                    <div class="absolute top-3 right-3 bg-white/90 backdrop-blur-sm px-2 py-1 rounded-lg flex items-center gap-1 shadow-sm">
                        <svg class="w-3 h-3 text-[#87CEFA]" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                        <span class="text-[#3A4A5A] text-xs font-bold">{{ $hotel->avg_rating }}</span>
                    </div>
                </div>
                <div class="p-5">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1">
                            <h3 class="font-semibold text-[#1E3A5F] text-base leading-snug mb-1 truncate">{{ $hotel->name }}</h3>
                            <p class="text-[#C9D3DD] text-xs mt-1.5 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                {{ $hotel->location->name }}
                            </p>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <svg class="w-4 h-4 text-[#87CEFA]" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                            <span class="text-sm font-semibold text-[#3A4A5A]">{{ $hotel->avg_rating }}</span>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-[#EAF3FF] flex items-center justify-between">
                        <div>
                            <p class="text-xs text-[#C9D3DD]">Giá từ</p>
                            <p class="font-bold text-[#87CEFA] text-lg">{{ number_format($hotel->rooms_min_price) }}<span class="text-xs font-normal text-[#C9D3DD]">đ/đêm</span></p>
                            <p class="price-original">{{ number_format($hotel->original_price) }}đ</p>
                        </div>
                        <button class="btn-primary px-4 py-2 rounded-xl text-sm font-semibold">Xem chi tiết</button>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </section>

    {{-- ===== ƯU ĐÃI NỔI BẬT ===== --}}
    <section>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="section-title">☆ Ưu đãi nổi bật</h2>
                <p class="section-sub">Những deal tốt nhất được chọn lọc dành riêng cho bạn</p>
            </div>
            <a href="{{ route('home') }}" class="text-sm text-[#87CEFA] hover:underline font-semibold flex items-center gap-1">
                Xem tất cả →
            </a>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
            @foreach($featured as $hotel)
            <div class="hotel-card deal-card bg-white rounded-2xl overflow-hidden shadow-sm border border-[#EAF3FF]"
                onclick="window.location.href='/hotels/{{ $hotel->id }}'">
                <div class="relative h-40 overflow-hidden bg-[#EAF3FF]">
                    <img src="https://picsum.photos/seed/{{ $hotel->id }}f/400/300" alt="{{ $hotel->name }}"
                        class="w-full h-full object-cover transition duration-500 hover:scale-110">
                    <div class="discount-badge">-{{ $hotel->discount }}%</div>
                </div>
                <div class="p-5">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1">
                            <h3 class="font-semibold text-[#1E3A5F] text-base leading-snug mb-1 truncate">{{ $hotel->name }}</h3>
                            <p class="text-[#C9D3DD] text-xs mt-1.5 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                {{ $hotel->location->name }}
                            </p>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <svg class="w-4 h-4 text-[#87CEFA]" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                            <span class="text-sm font-semibold text-[#3A4A5A]">{{ $hotel->avg_rating }}</span>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-[#EAF3FF] flex items-center justify-between">
                        <div>
                            <p class="text-xs text-[#C9D3DD]">Giá gốc</p>
                            <p class="price-original">{{ number_format($hotel->original_price) }}đ</p>
                            <p class="font-bold text-[#87CEFA] text-lg">{{ number_format($hotel->rooms_min_price) }}<span class="text-xs font-normal text-[#C9D3DD]">đ/đêm</span></p>
                        </div>
                        <button class="btn-primary px-4 py-2 rounded-xl text-sm font-semibold">Đặt ngay</button>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </section>

    {{-- ===== VOUCHER SECTION ===== --}}
    <section>
        <h2 class="section-title mb-6">🎟 Mã giảm giá của bạn</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="voucher-card">
                <div>
                    <p class="text-white/60 text-xs uppercase tracking-widest mb-1">Ưu đãi mùa hè</p>
                    <p class="text-white text-2xl font-black tracking-widest">SUMMER50</p>
                    <p class="text-white/70 text-xs mt-2">Giảm 50% — Tối đa 500.000đ</p>
                    <p class="text-[#FCD34D] text-xs mt-1">HSD: 31/08/2026</p>
                </div>
                <div class="voucher-dashed">
                    <p class="text-white/60 text-xs mb-2">Điều kiện</p>
                    <p class="text-white text-xs">Đơn từ 1.000.000đ</p>
                    <p class="text-white text-xs">Áp dụng KS 4-5 sao</p>
                    <button onclick="copyVoucher('SUMMER50')" class="mt-3 bg-[#87CEFA] text-white text-xs font-bold px-4 py-2 rounded-lg hover:bg-[#7BC4F5] transition">
                        Sao chép
                    </button>
                </div>
            </div>
            <div class="voucher-card" style="background: linear-gradient(135deg, #0F3460, #1a5276);">
                <div>
                    <p class="text-white/60 text-xs uppercase tracking-widest mb-1">Khách hàng mới</p>
                    <p class="text-white text-2xl font-black tracking-widest">WELCOME10</p>
                    <p class="text-white/70 text-xs mt-2">Giảm 10% — Tối đa 200.000đ</p>
                    <p class="text-[#FCD34D] text-xs mt-1">HSD: 31/07/2026</p>
                </div>
                <div class="voucher-dashed">
                    <p class="text-white/60 text-xs mb-2">Điều kiện</p>
                    <p class="text-white text-xs">Lần đặt phòng đầu tiên</p>
                    <p class="text-white text-xs">Áp dụng tất cả KS</p>
                    <button onclick="copyVoucher('WELCOME10')" class="mt-3 bg-[#87CEFA] text-[#1E3A5F] text-xs font-bold px-4 py-2 rounded-lg hover:bg-[#7BC4F5] transition">
                        Sao chép
                    </button>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== THEO ĐIỂM ĐẾN ===== --}}
    <section>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="section-title">📍 Ưu đãi theo điểm đến</h2>
                <p class="section-sub">Khám phá các ưu đãi hấp dẫn tại những điểm đến yêu thích</p>
            </div>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            @foreach($destinations as $dest)
            <a href="{{ route('home') }}?city={{ urlencode($dest->name) }}" class="dest-card block">
                <img src="https://picsum.photos/seed/dest{{ $dest->id }}/300/200" alt="{{ $dest->name }}">
                <div class="dest-overlay"></div>
                <div class="dest-info">
                    <div style="background:linear-gradient(135deg,#EF4444,#F59E0B);color:white;font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;display:inline-block;margin-bottom:4px;">
                        Giảm đến {{ $dest->discount }}%
                    </div>
                    <p class="text-white text-sm font-bold">{{ $dest->name }}</p>
                    <p class="text-white/70 text-xs">{{ $dest->hotels_count }} khách sạn</p>
                </div>
            </a>
            @endforeach
        </div>
    </section>

    {{-- ===== COMBO TIẾT KIỆM ===== --}}
    <section>
        <div class="mb-6">
            <h2 class="section-title">📦 Combo tiết kiệm</h2>
            <p class="section-sub">Tiết kiệm hơn khi đặt gói dịch vụ</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @php
                $comboLabels = [
                    ['icon'=>'🌅', 'tag'=>'Tiết kiệm 20%', 'title'=>'Nghỉ dưỡng + Ăn sáng', 'desc'=>'Bữa sáng hàng ngày cho 2 người'],
                    ['icon'=>'🗺️', 'tag'=>'Tiết kiệm 35%', 'title'=>'Khám phá điểm đến', 'desc'=>'Bao gồm vé tham quan nổi tiếng'],
                    ['icon'=>'🚗', 'tag'=>'Tiết kiệm 15%', 'title'=>'Thoải mái di chuyển', 'desc'=>'Đón sân bay 2 chiều'],
                ];
            @endphp
            @foreach($combos as $i => $hotel)
            @php $label = $comboLabels[$i] ?? $comboLabels[0]; @endphp
            <div class="combo-card" onclick="window.location.href='/hotels/{{ $hotel->id }}'">
                <div class="relative h-48 overflow-hidden">
                    <img src="https://picsum.photos/seed/combo{{ $hotel->id }}/500/300" alt="{{ $hotel->name }}"
                        class="w-full h-full object-cover transition duration-500 hover:scale-105">
                    <div style="position:absolute;top:12px;left:12px;background:linear-gradient(135deg,#1E3A5F,#0F3460);color:#FCD34D;font-size:11px;font-weight:700;padding:4px 10px;border-radius:12px;">
                        {{ $label['icon'] }} {{ $label['tag'] }}
                    </div>
                </div>
                <div class="p-5">
                    <h3 class="font-bold text-[#1E3A5F] text-base mb-1">{{ $label['title'] }}</h3>
                    <p class="text-[#C9D3DD] text-xs mb-1">{{ $label['desc'] }}</p>
                    <p class="text-[#87CEFA] text-xs font-semibold mb-3">🏨 {{ $hotel->name }} — {{ $hotel->location->name }}</p>
                    <div class="flex items-center justify-between border-t border-[#EAF3FF] pt-3">
                        <div>
                            <p class="text-[#C9D3DD] text-xs">Từ</p>
                            <p class="text-[#1E3A5F] text-lg font-black">{{ number_format($hotel->rooms_min_price ?? 0) }}<span class="text-xs font-normal text-[#C9D3DD]">đ/đêm</span></p>
                        </div>
                        <button class="bg-[#87CEFA] text-[#1E3A5F] text-xs font-bold px-4 py-2 rounded-xl hover:bg-[#7BC4F5] transition">
                            Chọn combo
                        </button>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </section>

</div>

@endsection

@push('scripts')
<script>
// Countdown cuối tuần
function updateCountdown() {
    const now = new Date();
    const day = now.getDay();
    const daysToSun = day === 0 ? 0 : 7 - day;
    const sunday = new Date(now);
    sunday.setDate(now.getDate() + daysToSun);
    sunday.setHours(23, 59, 59, 0);
    const diff = sunday - now;
    if (diff <= 0) return;
    const h = Math.floor(diff / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    const s = Math.floor((diff % 60000) / 1000);
    const pad = n => String(n).padStart(2, '0');
    ['b-h','b-m','b-s'].forEach((id, i) => {
        const el = document.getElementById(id);
        if (el) el.textContent = pad([h,m,s][i]);
    });
}
updateCountdown();
setInterval(updateCountdown, 1000);

// Filter flash sale theo thành phố
function filterDeals(city, btn) {
    document.querySelectorAll('.filter-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('#flash-grid .deal-card').forEach(card => {
        const cardCity = card.dataset.city || '';
        card.style.display = (city === 'all' || cardCity.includes(city)) ? '' : 'none';
    });
}

// Copy voucher
function copyVoucher(code) {
    navigator.clipboard.writeText(code).then(() => {
        showToast('✅ Đã sao chép mã: ' + code);
    }).catch(() => {
        showToast('Mã: ' + code);
    });
}
</script>
@endpush