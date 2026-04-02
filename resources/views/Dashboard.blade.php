@extends('layout')

@section('title', 'Dashboard')

@section('content')

<style>
    :root {
        --sky: #87CEFA;
        --sky-dark: #5BB8F5;
        --navy: #1E3A5F;
        --soft-bg: #EAF3FF;
        --dark: #3A4A5A;
        --gray: #C9D3DD;
        --gold: #F59E0B;
    }
    .stat-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px -8px rgba(135,206,250,0.3);
    }
    .tab-btn {
        position: relative;
        transition: color 0.2s ease;
    }
    .tab-btn.active {
        color: var(--navy);
        font-weight: 600;
    }
    .tab-btn.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0; right: 0;
        height: 2px;
        background: var(--sky);
        border-radius: 2px;
    }
    .booking-card {
        transition: box-shadow 0.2s ease;
    }
    .booking-card:hover {
        box-shadow: 0 8px 24px -8px rgba(58,74,90,0.12);
    }
    .btn-sky {
        background: var(--sky);
        color: var(--navy);
        font-weight: 600;
        transition: all 0.2s ease;
    }
    .btn-sky:hover {
        background: var(--sky-dark);
        transform: translateY(-1px);
    }
    .spinner {
        width: 28px; height: 28px;
        border: 3px solid var(--soft-bg);
        border-top-color: var(--sky);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        margin: 0 auto 12px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* Invoice button */
    .btn-invoice {
        background: #EAF3FF;
        color: var(--navy);
        font-size: 11px;
        padding: 4px 10px;
        border-radius: 8px;
        border: 1px solid var(--sky);
        transition: all 0.2s;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .btn-invoice:hover {
        background: var(--sky);
    }
</style>

{{-- HERO HEADER --}}
<div style="background: linear-gradient(135deg, #1A2F4A 0%, #0F3460 60%, #1E3A5F 100%);" class="py-10 relative overflow-hidden">
    {{-- decorative circles --}}
    <div class="absolute top-0 right-0 w-64 h-64 rounded-full opacity-5" style="background: var(--sky); transform: translate(30%, -30%);"></div>
    <div class="absolute bottom-0 left-0 w-48 h-48 rounded-full opacity-5" style="background: var(--sky); transform: translate(-30%, 30%);"></div>

    <div class="max-w-7xl mx-auto px-4 relative">
        {{-- User info --}}
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-2xl font-bold text-white shadow-lg"
                    style="background: linear-gradient(135deg, var(--sky), #5BB8F5);" id="user-avatar">?</div>
                <div>
                    <p class="text-white/50 text-xs uppercase tracking-widest">Xin chào trở lại</p>
                    <h1 class="text-2xl font-bold text-white mt-0.5" id="welcome-name">...</h1>
                </div>
            </div>
            <a href="/" class="btn-sky text-sm px-5 py-2.5 rounded-xl flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Đặt phòng mới
            </a>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-8">
            <div class="stat-card bg-white/8 rounded-2xl p-5 border border-white/10 cursor-pointer" onclick="showTab('bookings')" style="background: rgba(255,255,255,0.08);">
                <p class="text-3xl font-bold text-white" id="stat-bookings">—</p>
                <p class="text-white/50 text-xs mt-2 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    Đặt phòng
                </p>
            </div>
            <div class="stat-card bg-white/8 rounded-2xl p-5 border border-white/10 cursor-pointer" onclick="showTab('wishlist')" style="background: rgba(255,255,255,0.08);">
                <p class="text-3xl font-bold text-white" id="stat-wishlist">—</p>
                <p class="text-white/50 text-xs mt-2 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                    Yêu thích
                </p>
            </div>
            <div class="stat-card bg-white/8 rounded-2xl p-5 border border-white/10 cursor-pointer" onclick="showTab('reviews')" style="background: rgba(255,255,255,0.08);">
                <p class="text-3xl font-bold text-white" id="stat-reviews">—</p>
                <p class="text-white/50 text-xs mt-2 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>
                    Đánh giá
                </p>
            </div>
            <div class="stat-card bg-white/8 rounded-2xl p-5 border border-white/10 cursor-pointer" onclick="showTab('itineraries')" style="background: rgba(255,255,255,0.08);">
                <p class="text-3xl font-bold text-white" id="stat-itineraries">—</p>
                <p class="text-white/50 text-xs mt-2 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                    Lịch trình
                </p>
            </div>
        </div>
    </div>
</div>

{{-- TABS --}}
<div class="bg-white border-b border-[#EAF3FF] sticky top-16 z-30 shadow-sm">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex gap-0 overflow-x-auto">
            <button onclick="showTab('bookings')" id="tab-bookings"
                class="tab-btn active px-5 py-4 text-sm text-[#3A4A5A] whitespace-nowrap flex items-center gap-2">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3" y="4" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M8 2V6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M16 2V6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M3 10H21" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M8 14H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M14 14H16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M8 18H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M14 18H16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>Đặt phòng</span>
            </button>
            <button onclick="showTab('wishlist')" id="tab-wishlist"
                class="tab-btn px-5 py-4 text-sm text-[#C9D3DD] whitespace-nowrap flex items-center gap-2">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 21.35L10.55 20.03C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>Yêu thích</span>
            </button>
            <button onclick="showTab('reviews')" id="tab-reviews"
                class="tab-btn px-5 py-4 text-sm text-[#C9D3DD] whitespace-nowrap flex items-center gap-2">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L15 8.5L22 9.5L17 14L18.5 21L12 17.5L5.5 21L7 14L2 9.5L9 8.5L12 2Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>Đánh giá</span>
            </button>
            <button onclick="showTab('itineraries')" id="tab-itineraries"
                class="tab-btn px-5 py-4 text-sm text-[#C9D3DD] whitespace-nowrap flex items-center gap-2">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="12" cy="9" r="2.5" stroke="currentColor" stroke-width="1.5"/>
                </svg>
                <span>Lịch trình</span>
            </button>
        </div>
    </div>
</div>

{{-- PANELS --}}
<div class="max-w-7xl mx-auto px-4 py-8">

    {{-- BOOKINGS --}}
    <div id="panel-bookings">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-[#1E3A5F]">Lịch sử đặt phòng</h2>
        </div>
        <div id="bookings-list">
            <div class="text-center py-16 text-[#C9D3DD]"><div class="spinner"></div>Đang tải...</div>
        </div>
    </div>

    {{-- WISHLIST --}}
    <div id="panel-wishlist" class="hidden">
        <h2 class="text-xl font-bold text-[#1E3A5F] mb-6">Khách sạn yêu thích</h2>
        <div id="wishlist-list" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="col-span-3 text-center py-16 text-[#C9D3DD]"><div class="spinner"></div>Đang tải...</div>
        </div>
    </div>

    {{-- REVIEWS --}}
    <div id="panel-reviews" class="hidden">
        <h2 class="text-xl font-bold text-[#1E3A5F] mb-6">Đánh giá của tôi</h2>
        <div id="reviews-list">
            <div class="text-center py-16 text-[#C9D3DD]"><div class="spinner"></div>Đang tải...</div>
        </div>
    </div>

    {{-- ITINERARIES --}}
    <div id="panel-itineraries" class="hidden">
        {{-- AI Generator --}}
        <div class="rounded-2xl p-6 mb-6 border border-[#87CEFA]/30" style="background: linear-gradient(135deg, #EAF3FF, #F0F7FF);">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0">
                    <svg class="w-10 h-10" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3" y="8" width="18" height="12" rx="2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M8 8V6C8 4.9 8.9 4 10 4H14C15.1 4 16 4.9 16 6V8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="9" cy="13" r="1" fill="currentColor"/>
                        <circle cx="15" cy="13" r="1" fill="currentColor"/>
                        <path d="M9 17H15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-[#1E3A5F] mb-1">Tạo lịch trình bằng AI</h3>
                    <p class="text-[#C9D3DD] text-sm mb-4">Nhập điểm đến — AI tự lên lịch trình chi tiết!</p>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                        <input id="trip-city" type="text" placeholder="Thành phố (VD: Đà Nẵng)"
                            class="col-span-2 border border-[#C9D3DD] rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#87CEFA] bg-white">
                        <input id="trip-days" type="number" placeholder="Số ngày" min="1" max="14" value="3"
                            class="border border-[#C9D3DD] rounded-xl px-3 py-2 text-sm focus:outline-none bg-white">
                        <input id="trip-budget" type="number" placeholder="Budget" value="5000000"
                            class="border border-[#C9D3DD] rounded-xl px-3 py-2 text-sm focus:outline-none bg-white">
                        <button onclick="generateItinerary()" id="gen-btn"
                            class="btn-sky px-4 py-2 rounded-xl text-sm flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M13 3L14.5 6.5L18 8L14.5 9.5L13 13L11.5 9.5L8 8L11.5 6.5L13 3Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M5 15L6.5 18.5L10 20L6.5 21.5L5 25L3.5 21.5L0 20L3.5 18.5L5 15Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M19 15L20.5 18.5L24 20L20.5 21.5L19 25L17.5 21.5L14 20L17.5 18.5L19 15Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span>Tạo ngay</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <h2 class="text-xl font-bold text-[#1E3A5F] mb-6">Lịch trình của tôi</h2>
        <div id="itineraries-list">
            <div class="text-center py-16 text-[#C9D3DD]"><div class="spinner"></div>Đang tải...</div>
        </div>
    </div>

</div>

</div>

@endsection

@push('scripts')
<script>

const user  = JSON.parse(localStorage.getItem('user') || '{}');
if (!token) { window.location.href = '/login'; }
if (user.name) {
    document.getElementById('welcome-name').textContent = user.name;
    document.getElementById('user-avatar').textContent  = user.name.charAt(0).toUpperCase();
}

// =====================
// LOAD STATS — fix đúng field theo từng API response
// =====================
async function loadStats() {
    try {
        const [b, w, r, i] = await Promise.all([
            api('/bookings/my').then(r => r.json()),
            api('/wishlist').then(r => r.json()),
            api('/reviews/my').then(r => r.json()),
            api('/itineraries').then(r => r.json()),
        ]);

        // /bookings/my → paginate → { data: [...], total: N }
        document.getElementById('stat-bookings').textContent    = b?.total ?? b?.data?.length ?? 0;
        // /wishlist → array trực tiếp hoặc { data: [...] }
        document.getElementById('stat-wishlist').textContent    = Array.isArray(w) ? w.length : (w?.data?.length ?? 0);
        // /reviews/my → array trực tiếp hoặc { data: [...] }
        document.getElementById('stat-reviews').textContent     = Array.isArray(r) ? r.length : (r?.data?.length ?? 0);
        // /itineraries → array trực tiếp hoặc { data: [...] }
        document.getElementById('stat-itineraries').textContent = Array.isArray(i) ? i.length : (i?.data?.length ?? 0);
    } catch(e) {
        console.error('loadStats error:', e);
    }
}
loadStats();

// =====================
// TABS
// =====================
function showTab(tab) {
    ['bookings','wishlist','reviews','itineraries'].forEach(t => {
        document.getElementById('panel-' + t).classList.add('hidden');
        const btn = document.getElementById('tab-' + t);
        btn.classList.remove('active');
        btn.classList.add('text-[#C9D3DD]');
        btn.classList.remove('text-[#3A4A5A]');
    });
    document.getElementById('panel-' + tab).classList.remove('hidden');
    const active = document.getElementById('tab-' + tab);
    active.classList.add('active');
    active.classList.remove('text-[#C9D3DD]');
    active.classList.add('text-[#3A4A5A]');

    if (tab === 'bookings')    loadBookings();
    if (tab === 'wishlist')    loadWishlist();
    if (tab === 'reviews')     loadReviews();
    if (tab === 'itineraries') loadItineraries();
}

function emptyState(icon, msg, link, linkText, sub = '') {
    return `<div class="text-center py-16 text-[#C9D3DD]">
        <p class="text-5xl mb-3">${icon}</p>
        <p class="font-medium text-[#3A4A5A]">${msg}</p>
        ${sub ? `<p class="text-sm mt-1 text-[#C9D3DD]">${sub}</p>` : ''}
        ${link ? `<a href="${link}" class="text-sm text-[#87CEFA] underline mt-3 inline-block">${linkText}</a>` : ''}
    </div>`;
}

// =====================
// BOOKINGS
// =====================
async function loadBookings() {
    const list = document.getElementById('bookings-list');
    list.innerHTML = '<div class="text-center py-16 text-[#C9D3DD]"><div class="spinner"></div>Đang tải...</div>';
    const res  = await api('/bookings/my');
    const data = await res.json();
    const items = data?.data || [];
    function emptyState(icon, title, linkUrl, linkText) {
        // Check if icon is an SVG string or emoji
        const iconHtml = icon.startsWith('<svg') ? icon : `<span class="text-6xl mb-4">${icon}</span>`;
        
        return `
            <div class="text-center py-12">
                ${iconHtml}
                <p class="text-[#3A4A5A] mb-4">${title}</p>
                <a href="${linkUrl}" class="inline-block bg-[#87CEFA] text-white px-6 py-2 rounded-xl hover:bg-[#6BB5E0] transition">${linkText}</a>
            </div>
        `;
    }

    const sc = {
        pending:   { c: 'bg-amber-50 text-amber-600 border border-amber-200',   l: 'Chờ thanh toán' },
        confirmed: { c: 'bg-green-50 text-green-700 border border-green-200',   l: 'Đã xác nhận' },
        cancelled: { c: 'bg-red-50 text-red-600 border border-red-200',         l: 'Đã huỷ' },
        completed: { c: 'bg-[#EAF3FF] text-[#1E3A5F] border border-[#87CEFA]/30', l: 'Hoàn thành' },
        checked_in:  { c: 'bg-blue-50 text-blue-700 border border-blue-200',    l: 'Đang ở' },
        checked_out: { c: 'bg-gray-50 text-gray-600 border border-gray-200',    l: 'Đã trả phòng' },
    };

    list.innerHTML = `<div class="space-y-3">` + items.map(b => {
        const s = sc[b.status] || sc.pending;
        const checkIn  = b.check_in?.split('T')[0];
        const checkOut = b.check_out?.split('T')[0];
        return `
        <div class="booking-card bg-white rounded-2xl p-5 shadow-sm border border-[#EAF3FF] flex items-start gap-4">
            <div class="w-16 h-16 rounded-xl overflow-hidden shrink-0 bg-[#EAF3FF]">
                <img src="https://picsum.photos/seed/${b.hotel_id}/100/100" class="w-full h-full object-cover">
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1 flex-wrap">
                    <span class="text-xs font-mono text-[#C9D3DD]">#${b.id}</span>
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium ${s.c}">${s.l}</span>
                </div>
                <h3 class="font-semibold text-[#1E3A5F] truncate">${b.hotel?.name || 'Khách sạn'}</h3>
                <p class="text-xs text-[#C9D3DD] mt-1">
                    📅 ${checkIn} → ${checkOut}
                    &nbsp;•&nbsp; 👥 ${b.num_guests} khách
                </p>
                <div class="flex items-center gap-2 mt-2 flex-wrap">
                    ${b.status !== 'cancelled' ? `
                    <button onclick="downloadInvoice(${b.id})" class="btn-invoice">
                        📄 Hóa đơn PDF
                    </button>` : ''}
                </div>
            </div>
            <div class="text-right shrink-0">
                <p class="font-bold text-[#87CEFA] text-sm">${new Intl.NumberFormat('vi-VN').format(b.total_price)}đ</p>
                ${b.status === 'pending' ? `
                <button onclick="payBooking(${b.id})"
                    class="btn-sky text-xs px-3 py-1.5 rounded-lg mt-2 block w-full">
                    💳 Thanh toán
                </button>` : ''}
                ${['pending','confirmed'].includes(b.status) ? `
                <button onclick="cancelBooking(${b.id})"
                    class="text-xs text-red-400 hover:text-red-600 mt-1 block w-full text-right transition">
                    Huỷ
                </button>` : ''}
            </div>
        </div>`;
    }).join('') + `</div>`;
}

async function payBooking(id) {
    const r = await api('/payments/create', { method: 'POST', body: JSON.stringify({ booking_id: id }) });
    const d = await r.json();
    if (d.payment_url) window.location.href = d.payment_url;
    else showToast(d.message || 'Lỗi tạo URL thanh toán');
}

async function cancelBooking(id) {
    if (!confirm('Huỷ booking này?')) return;
    await api(`/bookings/${id}/cancel`, { method: 'PUT' });
    showToast('Đã huỷ booking!');
    loadBookings();
    loadStats();
}

// Mở PDF invoice trong tab mới
function downloadInvoice(bookingId) {
    const token = localStorage.getItem('token');
    // Fetch rồi tạo blob URL để mở PDF
    api(`/invoices/${bookingId}/pdf`)
        .then(res => {
            if (!res.ok) { showToast('Chưa có hóa đơn cho booking này'); return null; }
            return res.blob();
        })
        .then(blob => {
            if (!blob) return;
            const url = URL.createObjectURL(blob);
            window.open(url, '_blank');
            setTimeout(() => URL.revokeObjectURL(url), 10000);
        })
        .catch(() => showToast('Lỗi tải hóa đơn'));
}

// =====================
// WISHLIST
// =====================
async function loadWishlist() {
    const list = document.getElementById('wishlist-list');
    list.innerHTML = '<div class="col-span-3 text-center py-16 text-[#C9D3DD]"><div class="spinner"></div>Đang tải...</div>';
    const res  = await api('/wishlist');
    const raw  = await res.json();
    const data = Array.isArray(raw) ? raw : (raw?.data || []);
    if (!data.length) {
        list.innerHTML = `<div class="col-span-3">${emptyState('❤️', 'Chưa có KS yêu thích', '/', 'Khám phá ngay →')}</div>`;
        return;
    }
    list.innerHTML = data.map(w => `
        <div class="bg-white rounded-2xl overflow-hidden shadow-sm border border-[#EAF3FF] booking-card">
            <div class="relative h-36 overflow-hidden bg-[#EAF3FF]">
                <img src="https://picsum.photos/seed/${w.hotel_id}/400/200" class="w-full h-full object-cover">
                <button onclick="removeWishlist(${w.hotel_id}, this)"
                    class="absolute top-2 right-2 bg-white/90 w-7 h-7 rounded-full flex items-center justify-center text-red-400 text-sm shadow hover:bg-red-50 transition">♥</button>
            </div>
            <div class="p-4">
                <h3 class="font-semibold text-[#1E3A5F] text-sm truncate">${w.hotel?.name || ''}</h3>
                <p class="text-xs text-[#C9D3DD] mt-1">📍 ${w.hotel?.location?.name || ''}</p>
                <a href="/hotels/${w.hotel_id}" class="btn-sky text-xs px-3 py-1.5 rounded-lg mt-3 inline-block">Xem phòng →</a>
            </div>
        </div>`).join('');
}

async function removeWishlist(id, btn) {
    await api(`/wishlist/${id}`, { method: 'DELETE' });
    btn.closest('.bg-white').remove();
    showToast('Đã xóa khỏi yêu thích');
    loadStats();
}

// =====================
// REVIEWS
// =====================
async function loadReviews() {
    const list = document.getElementById('reviews-list');
    list.innerHTML = '<div class="text-center py-16 text-[#C9D3DD]"><div class="spinner"></div>Đang tải...</div>';
    const res  = await api('/reviews/my');
    const raw  = await res.json();
    const data = Array.isArray(raw) ? raw : (raw?.data || []);
    if (!data.length) {
        list.innerHTML = emptyState('⭐', 'Chưa có đánh giá nào', '/', 'Đặt phòng để review →');
        return;
    }
    list.innerHTML = `<div class="space-y-3">` + data.map(r => `
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-[#EAF3FF] booking-card">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                    <h3 class="font-semibold text-[#1E3A5F]">${r.hotel?.name || 'Khách sạn'}</h3>
                    <div class="flex items-center gap-1 mt-1">
                        ${Array(r.rating).fill('<span class="text-amber-400 text-sm">★</span>').join('')}
                        ${Array(5 - r.rating).fill('<span class="text-[#C9D3DD] text-sm">★</span>').join('')}
                        <span class="text-xs text-[#C9D3DD] ml-1">${r.rating}/5</span>
                    </div>
                    ${r.comment ? `<p class="text-sm text-[#3A4A5A] mt-2 italic">"${r.comment}"</p>` : ''}
                    <p class="text-xs text-[#C9D3DD] mt-2">${r.created_at?.split('T')[0]}</p>
                </div>
                <button onclick="deleteReview(${r.id}, this)"
                    class="text-xs text-[#C9D3DD] hover:text-red-500 border border-[#EAF3FF] hover:border-red-200 px-3 py-1.5 rounded-xl shrink-0 transition">
                    Xóa
                </button>
            </div>
        </div>`).join('') + `</div>`;
}

async function deleteReview(id, btn) {
    if (!confirm('Xóa đánh giá này?')) return;
    await api(`/reviews/${id}`, { method: 'DELETE' });
    btn.closest('.bg-white').remove();
    showToast('Đã xóa đánh giá');
    loadStats();
}

// =====================
// ITINERARIES
// =====================
async function loadItineraries() {
    const list = document.getElementById('itineraries-list');
    list.innerHTML = '<div class="text-center py-16 text-[#C9D3DD]"><div class="spinner"></div>Đang tải...</div>';
    const res  = await api('/itineraries');
    const raw  = await res.json();
    const data = Array.isArray(raw) ? raw : (raw?.data || []);
    if (!data.length) {
        list.innerHTML = emptyState('🗺', 'Chưa có lịch trình', null, null, 'Thử tạo bằng AI ở trên!');
        return;
    }
    const sc = {
        draft:     { c: 'bg-gray-50 text-gray-600 border border-gray-200',        l: 'Bản nháp' },
        confirmed: { c: 'bg-green-50 text-green-700 border border-green-200',     l: 'Đã xác nhận' },
        completed: { c: 'bg-[#EAF3FF] text-[#1E3A5F] border border-[#87CEFA]/30', l: 'Hoàn thành' },
    };
    list.innerHTML = `<div class="space-y-3">` + data.map(it => {
        const s = sc[it.status] || sc.draft;
        return `
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-[#EAF3FF] booking-card">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium ${s.c}">${s.l}</span>
                        <span class="text-xs text-[#C9D3DD]">${it.total_days} ngày</span>
                    </div>
                    <h3 class="font-semibold text-[#1E3A5F]">${it.title}</h3>
                    <p class="text-xs text-[#C9D3DD] mt-1">
                        ${it.start_date ? '📅 ' + it.start_date.split('T')[0] : '📅 Chưa đặt ngày'}
                        ${it.estimated_budget ? ' &nbsp;•&nbsp; 💰 ' + new Intl.NumberFormat('vi-VN').format(it.estimated_budget) + 'đ' : ''}
                    </p>
                </div>
                <span class="text-[#C9D3DD] text-sm shrink-0">${it.items?.length || 0} hđ</span>
            </div>
            ${it.items?.length ? `
            <div class="mt-4 pt-4 border-t border-[#EAF3FF] space-y-1.5">
                ${it.items.slice(0, 3).map(item => `
                <div class="flex items-center gap-2 text-xs text-[#3A4A5A]">
                    <span class="w-5 h-5 rounded-full bg-[#EAF3FF] text-[#87CEFA] flex items-center justify-center font-bold shrink-0">${item.day_number}</span>
                    <span class="text-[#C9D3DD] shrink-0">${item.start_time?.substring(0,5) || ''}</span>
                    <span class="truncate">${item.title}</span>
                </div>`).join('')}
                ${it.items.length > 3 ? `<p class="text-xs text-[#C9D3DD] pl-7">+${it.items.length - 3} hoạt động khác</p>` : ''}
            </div>` : ''}
        </div>`;
    }).join('') + `</div>`;
}

async function generateItinerary() {
    const city   = document.getElementById('trip-city').value.trim();
    const days   = document.getElementById('trip-days').value;
    const budget = document.getElementById('trip-budget').value;
    const btn    = document.getElementById('gen-btn');
    if (!city) { showToast('Vui lòng nhập thành phố!'); return; }
    btn.textContent = '⏳ AI đang lên lịch...';
    btn.disabled    = true;
    const res  = await api('/itineraries/generate', {
        method: 'POST',
        body: JSON.stringify({ city, days: parseInt(days), budget: parseInt(budget) })
    });
    const data = await res.json();
    btn.textContent = '✨ Tạo ngay';
    btn.disabled    = false;
    if (res.ok) { showToast('AI đã tạo lịch trình! 🗺️'); loadItineraries(); loadStats(); }
    else showToast('Lỗi: ' + (data.message || 'Thử lại!'));
}

// Load bookings khi vào trang
loadBookings();
</script>
@endpush