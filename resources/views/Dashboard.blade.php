@extends('layout')

@section('title', 'Dashboard')

@section('content')

<style>
    :root {
        --sky: #87CEFA; --sky-dark: #5BB8F5; --navy: #1E3A5F;
        --soft-bg: #EAF3FF; --dark: #3A4A5A; --gray: #C9D3DD;
    }
    .stat-card { transition: transform 0.2s ease, box-shadow 0.2s ease; }
    .stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px -8px rgba(135,206,250,0.3); }
    .tab-btn { position: relative; transition: color 0.2s ease; }
    .tab-btn.active { color: var(--navy); font-weight: 600; }
    .tab-btn.active::after {
        content: ''; position: absolute; bottom: -1px; left: 0; right: 0;
        height: 2px; background: var(--sky); border-radius: 2px;
    }
    .booking-card { transition: box-shadow 0.2s ease; }
    .booking-card:hover { box-shadow: 0 8px 24px -8px rgba(58,74,90,0.12); }
    .btn-sky { background: var(--sky); color: var(--navy); font-weight: 600; transition: all 0.2s ease; }
    .btn-sky:hover { background: var(--sky-dark); transform: translateY(-1px); }
    .spinner {
        width: 28px; height: 28px; border: 3px solid var(--soft-bg);
        border-top-color: var(--sky); border-radius: 50%;
        animation: spin 0.8s linear infinite; margin: 0 auto 12px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .btn-invoice {
        background: #EAF3FF; color: var(--navy); font-size: 11px;
        padding: 4px 10px; border-radius: 8px; border: 1px solid var(--sky);
        transition: all 0.2s; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;
    }
    .btn-invoice:hover { background: var(--sky); }
    .profile-input {
        width: 100%; border: 1.5px solid #E5E7EB; border-radius: 12px;
        padding: 11px 14px; font-size: 14px; color: #3A4A5A;
        transition: all 0.2s; outline: none; box-sizing: border-box;
    }
    .profile-input:focus { border-color: var(--sky); box-shadow: 0 0 0 3px rgba(135,206,250,0.15); }
    .profile-input:disabled { background: #F9FAFB; color: #9CA3AF; cursor: not-allowed; }

    /* City autocomplete */
    #city-dropdown { scrollbar-width: thin; scrollbar-color: #C9D3DD transparent; }
    #city-dropdown::-webkit-scrollbar { width: 4px; }
    #city-dropdown::-webkit-scrollbar-thumb { background: #C9D3DD; border-radius: 99px; }
    .city-tag { transition: all 0.15s; }
    .city-tag:hover { background: #EAF3FF !important; border-color: #87CEFA !important; }

    /* City card thumbnail buttons */
    .city-card-btn { -webkit-tap-highlight-color: transparent; }
    .city-card-btn:focus { outline: none; }
    #cityHotelPanel { animation: slideDown 0.2s ease; }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-8px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    #cityHotelList::-webkit-scrollbar { width: 4px; }
    #cityHotelList::-webkit-scrollbar-track { background: transparent; }
    #cityHotelList::-webkit-scrollbar-thumb { background: #C9D3DD; border-radius: 99px; }

    /* Itinerary modal scroll */
    #itin-modal-body { scrollbar-width: thin; scrollbar-color: #C9D3DD transparent; }
    #itin-modal-body::-webkit-scrollbar { width: 4px; }
    #itin-modal-body::-webkit-scrollbar-thumb { background: #C9D3DD; border-radius: 99px; }

    /* Star rating */
    .star-rating { display: flex; flex-direction: row-reverse; gap: 2px; }
    .star-rating input { display: none; }
    .star-rating label {
        font-size: 22px; color: #C9D3DD; cursor: pointer;
        transition: color 0.1s;
    }
    .star-rating input:checked ~ label,
    .star-rating label:hover,
    .star-rating label:hover ~ label { color: #F59E0B; }

    /* Review form */
    .review-form { border-top: 1px solid #EAF3FF; margin-top: 12px; padding-top: 12px; }
    .review-textarea {
        width: 100%; border: 1.5px solid #E5E7EB; border-radius: 10px;
        padding: 9px 12px; font-size: 13px; color: #3A4A5A;
        resize: none; outline: none; transition: border-color 0.2s;
        box-sizing: border-box; min-height: 72px;
    }
    .review-textarea:focus { border-color: #87CEFA; }

    /* Notification panel */
    .notif-panel-item {
        display: flex; align-items: flex-start; gap: 10px;
        padding: 12px 0; border-bottom: 1px solid #EAF3FF;
    }
    .notif-panel-item:last-child { border-bottom: none; }
    .notif-panel-item.unread { background: linear-gradient(90deg,#EAF3FF 0%,transparent 100%); padding-left: 10px; border-radius: 8px; }


    /* Custom Toast Notification */
    .custom-toast {
        position: fixed;
        bottom: 24px;
        right: 24px;
        z-index: 9999;
        display: flex;
        align-items: center;
        gap: 12px;
        background: white;
        padding: 12px 20px;
        border-radius: 16px;
        box-shadow: 0 10px 40px -8px rgba(0,0,0,0.2);
        border-left: 4px solid #87CEFA;
        animation: toastSlideIn 0.3s ease;
        max-width: 380px;
        pointer-events: auto;
    }

    .custom-toast.success { border-left-color: #10B981; }
    .custom-toast.error { border-left-color: #EF4444; }
    .custom-toast.warning { border-left-color: #F59E0B; }
    .custom-toast.info { border-left-color: #87CEFA; }

    .custom-toast-icon {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
    }

    .custom-toast.success .custom-toast-icon { background: #D1FAE5; color: #059669; }
    .custom-toast.error .custom-toast-icon { background: #FEE2E2; color: #DC2626; }
    .custom-toast.warning .custom-toast-icon { background: #FEF3C7; color: #D97706; }
    .custom-toast.info .custom-toast-icon { background: #EAF3FF; color: #1E3A5F; }

    .custom-toast-content {
        flex: 1;
        font-size: 14px;
        color: #1E3A5F;
        font-weight: 500;
    }

    .custom-toast-close {
        width: 24px;
        height: 24px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: #C9D3DD;
        transition: all 0.2s;
    }

    .custom-toast-close:hover {
        background: #F1F5F9;
        color: #3A4A5A;
    }

    @keyframes toastSlideIn {
        from {
            opacity: 0;
            transform: translateX(100px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes toastSlideOut {
        to {
            opacity: 0;
            transform: translateX(100px);
        }
    }

    /* Autocomplete Dropdown - Giống Home */
    #city-dropdown {
        scrollbar-width: thin;
        scrollbar-color: #C9D3DD transparent;
        max-height: 380px;
        overflow-y: auto;
    }
    #city-dropdown::-webkit-scrollbar { width: 5px; }
    #city-dropdown::-webkit-scrollbar-track { background: transparent; }
    #city-dropdown::-webkit-scrollbar-thumb { background: #C9D3DD; border-radius: 99px; }

    .ac-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 16px;
        cursor: pointer;
        transition: background 0.15s ease;
        border-bottom: 1px solid #F1F5F9;
    }
    .ac-item:last-child { border-bottom: none; }
    .ac-item:hover {
        background: #EAF3FF;
    }
    .ac-img-wrap {
        width: 52px;
        height: 44px;
        border-radius: 10px;
        overflow: hidden;
        flex-shrink: 0;
        background: #EAF3FF;
    }
    .ac-img-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .ac-info { flex: 1; min-width: 0; }
    .ac-name {
        font-size: 14px;
        font-weight: 600;
        color: #1E3A5F;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .ac-name mark {
        background: none;
        color: #0E5ED8;
        font-weight: 700;
    }
    .ac-location {
        font-size: 12px;
        color: #94A3B8;
        margin-top: 2px;
        display: flex;
        align-items: center;
        gap: 3px;
    }
    .ac-price {
        font-size: 13px;
        font-weight: 700;
        color: #87CEFA;
        flex-shrink: 0;
        text-align: right;
    }
    .ac-empty {
        padding: 24px 16px;
        text-align: center;
        color: #94A3B8;
        font-size: 14px;
    }
</style>

{{-- HERO --}}
<div style="background: linear-gradient(135deg, #1A2F4A 0%, #0F3460 60%, #1E3A5F 100%);" class="py-10 relative overflow-hidden">
    <div class="absolute top-0 right-0 w-64 h-64 rounded-full opacity-5" style="background: var(--sky); transform: translate(30%,-30%);"></div>
    <div class="absolute bottom-0 left-0 w-48 h-48 rounded-full opacity-5" style="background: var(--sky); transform: translate(-30%,30%);"></div>
    <div class="max-w-7xl mx-auto px-4 relative">
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Đặt phòng mới
            </a>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-8">
            <div class="stat-card rounded-2xl p-5 border border-white/10 cursor-pointer" onclick="showTab('bookings')" style="background:rgba(255,255,255,0.08);">
                <p class="text-3xl font-bold text-white" id="stat-bookings">—</p>
                <p class="text-white/50 text-xs mt-2 flex items-center gap-1.5"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>Đặt phòng</p>
            </div>
            <div class="stat-card rounded-2xl p-5 border border-white/10 cursor-pointer" onclick="showTab('wishlist')" style="background:rgba(255,255,255,0.08);">
                <p class="text-3xl font-bold text-white" id="stat-wishlist">—</p>
                <p class="text-white/50 text-xs mt-2 flex items-center gap-1.5"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>Yêu thích</p>
            </div>
            <div class="stat-card rounded-2xl p-5 border border-white/10 cursor-pointer" onclick="showTab('reviews')" style="background:rgba(255,255,255,0.08);">
                <p class="text-3xl font-bold text-white" id="stat-reviews">—</p>
                <p class="text-white/50 text-xs mt-2 flex items-center gap-1.5"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>Đánh giá</p>
            </div>
            <div class="stat-card rounded-2xl p-5 border border-white/10 cursor-pointer" onclick="showTab('itineraries')" style="background:rgba(255,255,255,0.08);">
                <p class="text-3xl font-bold text-white" id="stat-itineraries">—</p>
                <p class="text-white/50 text-xs mt-2 flex items-center gap-1.5"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>Lịch trình</p>
            </div>
        </div>
    </div>
</div>

{{-- TABS --}}
<div class="bg-white border-b border-[#EAF3FF] sticky top-16 z-30 shadow-sm">
    <div class="max-w-7xl mx-auto px-4">
<div class="flex gap-0 overflow-x-auto">
    <button onclick="showTab('bookings')" id="tab-bookings" class="tab-btn active px-5 py-4 text-sm text-[#3A4A5A] whitespace-nowrap flex items-center gap-2">
    `        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Đặt phòng
                <span id="tab-bookings-badge" class="hidden text-xs bg-red-500 text-white px-1.5 py-0.5 rounded-full leading-none"></span>
            </button>
            <button onclick="showTab('wishlist')" id="tab-wishlist" class="tab-btn px-5 py-4 text-sm text-[#C9D3DD] whitespace-nowrap flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                Yêu thích
                <span id="tab-wishlist-badge" class="hidden text-xs bg-red-500 text-white px-1.5 py-0.5 rounded-full leading-none"></span>
            </button>
            <button onclick="showTab('reviews')" id="tab-reviews" class="tab-btn px-5 py-4 text-sm text-[#C9D3DD] whitespace-nowrap flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                Đánh giá
                <span id="tab-reviews-badge" class="hidden text-xs bg-red-500 text-white px-1.5 py-0.5 rounded-full leading-none"></span>
            </button>
            <button onclick="showTab('itineraries')" id="tab-itineraries" class="tab-btn px-5 py-4 text-sm text-[#C9D3DD] whitespace-nowrap flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                Lịch trình
                <span id="tab-itineraries-badge" class="hidden text-xs bg-red-500 text-white px-1.5 py-0.5 rounded-full leading-none"></span>
            </button>
            <button onclick="showTab('notifications')" id="tab-notifications" class="tab-btn px-5 py-4 text-sm text-[#C9D3DD] whitespace-nowrap flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 01-3 3H9a3 3 0 01-3-3v-1m6 0h6"/></svg>
                Thông báo
                <span id="tab-notif-badge" class="hidden text-xs bg-red-500 text-white px-1.5 py-0.5 rounded-full leading-none"></span>
            </button>
            <button onclick="showTab('profile')" id="tab-profile" class="tab-btn px-5 py-4 text-sm text-[#C9D3DD] whitespace-nowrap flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                Tài khoản
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
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background: linear-gradient(135deg, #87CEFA, #5BB8F5);">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <div class="flex-1">
                    <h3 class="text-lg font-bold text-[#1E3A5F] mb-0.5">Tạo lịch trình bằng AI</h3>
                    <p class="text-[#C9D3DD] text-sm mb-4">Nhập điểm đến — AI tự lên kế hoạch chi tiết từng ngày!</p>
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                        <div class="md:col-span-3 relative" id="cityAutocompleteWrap">
                            <div class="absolute left-3 top-1/2 -translate-y-1/2 text-[#87CEFA] pointer-events-none z-10">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <input id="trip-city" type="text" placeholder="Thành phố (VD: Đà Nẵng)"
                                class="w-full pl-9 pr-3 py-2.5 border border-[#C9D3DD] rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#87CEFA] bg-white" autocomplete="off">
                            <div id="city-dropdown" class="absolute left-0 right-0 top-[calc(100%+4px)] bg-white border border-[#EAF3FF] rounded-xl shadow-xl z-50 hidden overflow-hidden" style="max-height:220px;overflow-y:auto;"></div>
                        </div>
                        <div class="md:col-span-2">
                            <select id="trip-days" class="w-full px-3 py-2.5 border border-[#C9D3DD] rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#87CEFA] bg-white text-[#3A4A5A]">
                                <option value="1">1 ngày</option><option value="2">2 ngày</option>
                                <option value="3" selected>3 ngày</option><option value="4">4 ngày</option>
                                <option value="5">5 ngày</option><option value="7">7 ngày</option>
                                <option value="10">10 ngày</option><option value="14">14 ngày</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <input id="trip-start-date" type="date" class="w-full px-3 py-2.5 border border-[#C9D3DD] rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#87CEFA] bg-white text-[#3A4A5A]">
                        </div>
                        <div class="md:col-span-2">
                            <input id="trip-end-date" type="date" class="w-full px-3 py-2.5 border border-[#C9D3DD] rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#87CEFA] bg-white text-[#3A4A5A]" placeholder="Ngày kết thúc">
                        </div>
                        <div class="md:col-span-2">
                            <select id="trip-budget" class="w-full px-3 py-2.5 border border-[#C9D3DD] rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#87CEFA] bg-white text-[#3A4A5A]">
                                <option value="2000000">2 triệu</option><option value="5000000" selected>5 triệu</option>
                                <option value="10000000">10 triệu</option><option value="20000000">20 triệu</option>
                                <option value="50000000">50 triệu</option>
                            </select>
                        </div>
                        <div class="md:col-span-1">
                            <button onclick="generateItinerary()" id="gen-btn"
                                class="w-full btn-sky px-2 py-2.5 rounded-xl text-sm font-semibold flex items-center justify-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                Tạo
                            </button>
                        </div>
                    </div>
                    {{-- ===== GỢI Ý ĐỊA ĐIỂM NHANH (card ảnh) ===== --}}
                    <div class="mt-4">
                        <span class="text-xs text-[#C9D3DD] font-semibold uppercase tracking-wide">📍 Địa điểm nổi bật</span>
                        <div class="flex gap-3 mt-2 overflow-x-auto pb-1" style="scrollbar-width:none;">
                            @php
                                $quickCities = [
                                    ['name'=>'Đà Nẵng',  'seed'=>'danang',  'emoji'=>'🏖️'],
                                    ['name'=>'Hà Nội',   'seed'=>'hanoi',   'emoji'=>'🏛️'],
                                    ['name'=>'Hội An',   'seed'=>'hoian',   'emoji'=>'🏮'],
                                    ['name'=>'Phú Quốc', 'seed'=>'phuquoc', 'emoji'=>'🌴'],
                                    ['name'=>'Nha Trang','seed'=>'nhatrang','emoji'=>'🐚'],
                                    ['name'=>'Sài Gòn',  'seed'=>'saigon',  'emoji'=>'🌆'],
                                ];
                            @endphp
                            @foreach($quickCities as $qCity)
                            <button onclick="openCityHotelPanel('{{ $qCity['name'] }}', '{{ $qCity['seed'] }}')"
                                class="city-card-btn shrink-0 relative rounded-2xl overflow-hidden cursor-pointer group border-2 border-transparent hover:border-[#87CEFA] transition-all duration-200 shadow-sm focus:outline-none"
                                style="width:90px;height:72px;"
                                title="{{ $qCity['name'] }}">
                                <img src="https://picsum.photos/seed/{{ $qCity['seed'] }}/180/144"
                                    alt="{{ $qCity['name'] }}"
                                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
                                <div class="absolute bottom-0 left-0 right-0 p-1.5 text-center">
                                    <span class="text-white text-[10px] font-bold leading-tight block drop-shadow">{{ $qCity['emoji'] }} {{ $qCity['name'] }}</span>
                                </div>
                            </button>
                            @endforeach
                        </div>
                    </div>

                    {{-- ===== PANEL DANH SÁCH KHÁCH SẠN THEO ĐỊA ĐIỂM ===== --}}
                    <div id="cityHotelPanel" class="hidden mt-4 bg-white rounded-2xl border border-[#EAF3FF] shadow-lg overflow-hidden">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-[#EAF3FF]" style="background:linear-gradient(135deg,#EAF3FF,#F0F7FF);">
                            <div class="flex items-center gap-2">
                                <div class="w-9 h-9 rounded-xl overflow-hidden shrink-0 bg-[#EAF3FF]">
                                    <img id="cityPanelImgSrc" src="" alt="" class="w-full h-full object-cover">
                                </div>
                                <div>
                                    <p class="text-xs text-[#C9D3DD] font-medium uppercase tracking-wide">Khách sạn tại</p>
                                    <p id="cityPanelTitle" class="text-sm font-bold text-[#1E3A5F]"></p>
                                </div>
                            </div>
                            <button onclick="closeCityHotelPanel()"
                                class="w-7 h-7 rounded-full flex items-center justify-center text-[#C9D3DD] hover:text-[#3A4A5A] hover:bg-[#EAF3FF] transition text-xl leading-none">×</button>
                        </div>
                        <div id="cityHotelList" class="divide-y divide-[#F1F5F9]" style="max-height:280px;overflow-y:auto;scrollbar-width:thin;scrollbar-color:#C9D3DD transparent;">
                            <div class="flex items-center justify-center gap-2 py-8 text-[#C9D3DD] text-sm">
                                <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                Đang tải khách sạn...
                            </div>
                        </div>
                        <div class="px-4 py-2.5 bg-[#F8FAFC] border-t border-[#F1F5F9] flex items-center justify-between">
                            <span class="text-xs text-[#94A3B8]">Hoặc tự nhập tên khách sạn vào ô bên trên ↑</span>
                            <button onclick="closeCityHotelPanel()" class="text-xs text-[#87CEFA] hover:text-[#0E5ED8] font-semibold transition">Đóng</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <h2 class="text-xl font-bold text-[#1E3A5F] mb-4">Lịch trình của tôi</h2>
        <div id="itineraries-list">
            <div class="text-center py-16 text-[#C9D3DD]"><div class="spinner"></div>Đang tải...</div>
        </div>
    </div>

    {{-- NOTIFICATIONS --}}
    <div id="panel-notifications" class="hidden">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-[#1E3A5F]">Thông báo của tôi</h2>
            <div class="flex items-center gap-3">
                <button onclick="markAllReadDashboard()" class="text-sm text-[#87CEFA] hover:underline">Đánh dấu tất cả đã đọc</button>
                <button onclick="deleteAllNotifications()" class="text-sm text-red-400 hover:text-red-600 hover:underline">Xóa tất cả</button>
            </div>
        </div>
        <div id="notifications-list">
            <div class="text-center py-16 text-[#C9D3DD]"><div class="spinner"></div>Đang tải...</div>
        </div>
    </div>

    {{-- PROFILE — Thông tin tài khoản --}}
    <div id="panel-profile" class="hidden">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            {{-- Thông tin cá nhân --}}
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-[#EAF3FF]">
                <h2 class="text-lg font-bold text-[#1E3A5F] mb-5 flex items-center gap-2">
                    <svg class="w-5 h-5 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Thông tin cá nhân
                </h2>
                <div id="profile-success" class="hidden bg-green-50 text-green-700 text-sm rounded-xl px-4 py-3 mb-4"></div>
                <div id="profile-error"   class="hidden bg-red-50 text-red-600 text-sm rounded-xl px-4 py-3 mb-4"></div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-[#3A4A5A] mb-1.5 uppercase tracking-wide">Họ tên</label>
                        <input id="profile-name" type="text" class="profile-input" placeholder="Nguyễn Văn A">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[#3A4A5A] mb-1.5 uppercase tracking-wide">Email</label>
                        <input id="profile-email" type="email" class="profile-input" placeholder="email@gmail.com">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[#3A4A5A] mb-1.5 uppercase tracking-wide">Số điện thoại</label>
                        <input id="profile-phone" type="tel" class="profile-input" placeholder="0901234567">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[#3A4A5A] mb-1.5 uppercase tracking-wide">Ngày sinh</label>
                        <input id="profile-dob" type="date" class="profile-input">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[#3A4A5A] mb-1.5 uppercase tracking-wide">Giới tính</label>
                        <select id="profile-gender" class="profile-input">
                            <option value="">-- Chọn giới tính --</option>
                            <option value="male">Nam</option>
                            <option value="female">Nữ</option>
                            <option value="other">Khác</option>
                        </select>
                    </div>
                    <button onclick="saveProfile()" id="btn-save-profile" class="btn-sky w-full py-3 rounded-xl text-sm font-semibold">Lưu thay đổi</button>
                </div>
            </div>

            {{-- Đổi mật khẩu --}}
            <div class="bg-white rounded-2xl p-6 shadow-sm border border-[#EAF3FF]">
                <h2 class="text-lg font-bold text-[#1E3A5F] mb-5 flex items-center gap-2">
                    <svg class="w-5 h-5 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                    Đổi mật khẩu
                </h2>
                <div id="pw-success" class="hidden bg-green-50 text-green-700 text-sm rounded-xl px-4 py-3 mb-4"></div>
                <div id="pw-error"   class="hidden bg-red-50 text-red-600 text-sm rounded-xl px-4 py-3 mb-4"></div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-[#3A4A5A] mb-1.5 uppercase tracking-wide">Mật khẩu hiện tại</label>
                        <input id="pw-current" type="password" class="profile-input" placeholder="••••••••">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[#3A4A5A] mb-1.5 uppercase tracking-wide">Mật khẩu mới</label>
                        <input id="pw-new" type="password" class="profile-input" placeholder="Tối thiểu 6 ký tự">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[#3A4A5A] mb-1.5 uppercase tracking-wide">Xác nhận mật khẩu mới</label>
                        <input id="pw-confirm" type="password" class="profile-input" placeholder="Nhập lại mật khẩu mới">
                    </div>
                    <button onclick="savePassword()" id="btn-save-pw" class="btn-sky w-full py-3 rounded-xl text-sm font-semibold">Đổi mật khẩu</button>
                </div>

                {{-- Tùy chọn thông báo --}}
                <div class="mt-6 pt-6 border-t border-[#EAF3FF]">
                    <h3 class="text-sm font-bold text-[#1E3A5F] mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 01-3 3H9a3 3 0 01-3-3v-1m6 0h6"/></svg>
                        Tùy chọn thông báo
                    </h3>
                    <div class="space-y-3">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" id="notif-booking" class="w-4 h-4 rounded" checked>
                            <span class="text-sm text-[#3A4A5A]">Thông báo đặt phòng</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" id="notif-promo" class="w-4 h-4 rounded" checked>
                            <span class="text-sm text-[#3A4A5A]">Khuyến mãi & ưu đãi</span>
                        </label>
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" id="notif-system" class="w-4 h-4 rounded">
                            <span class="text-sm text-[#3A4A5A]">Thông báo hệ thống</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>{{-- /panels --}}

{{-- ITINERARY DETAIL MODAL --}}
<div id="itineraryModal" class="fixed inset-0 bg-black/50 z-[200] hidden items-center justify-center p-4" onclick="closeItineraryModal(event)">
    <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[90vh] flex flex-col shadow-2xl" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between px-6 py-4 border-b border-[#EAF3FF] shrink-0">
            <div>
                <h3 id="itin-modal-title" class="font-bold text-[#1E3A5F] text-lg"></h3>
                <p id="itin-modal-meta" class="text-xs text-[#C9D3DD] mt-0.5"></p>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="printItinerary()" title="In / Xuất PDF" class="w-8 h-8 rounded-lg flex items-center justify-center text-[#C9D3DD] hover:text-[#1E3A5F] hover:bg-[#EAF3FF] transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                </button>
                <button onclick="shareItinerary()" title="Chia sẻ" class="w-8 h-8 rounded-lg flex items-center justify-center text-[#C9D3DD] hover:text-[#1E3A5F] hover:bg-[#EAF3FF] transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                </button>
                <button onclick="document.getElementById('itineraryModal').classList.replace('flex','hidden')"
                    class="w-8 h-8 rounded-lg flex items-center justify-center text-[#C9D3DD] hover:text-[#1E3A5F] hover:bg-[#EAF3FF] transition text-xl leading-none">&times;</button>
            </div>
        </div>
        <div id="itin-modal-body" class="overflow-y-auto flex-1 px-6 py-4 space-y-5"></div>
        <div class="px-6 py-4 border-t border-[#EAF3FF] shrink-0 flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-2">
                <label class="text-xs font-semibold text-[#3A4A5A]">Ngày bắt đầu:</label>
                <input id="itin-modal-start-date" type="date"
                    class="border border-[#C9D3DD] rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-[#87CEFA]"
                    onchange="updateItineraryDate()">
            </div>
            <div class="flex items-center gap-2">
                <button onclick="deleteCurrentItinerary()" class="text-xs text-red-400 hover:text-red-600 border border-red-200 hover:border-red-400 px-3 py-1.5 rounded-xl transition">Xóa lịch trình</button>
                <button onclick="document.getElementById('itineraryModal').classList.replace('flex','hidden')" class="btn-sky text-xs px-4 py-1.5 rounded-xl font-semibold">Đóng</button>
            </div>
        </div>
    </div>
</div>

{{-- REVIEW MODAL --}}
<div id="reviewModal" class="fixed inset-0 bg-black/50 z-[200] hidden items-center justify-center p-4" onclick="closeReviewModal(event)">
    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl p-6" onclick="event.stopPropagation()">
        <h3 class="font-bold text-[#1E3A5F] text-lg mb-1">Viết đánh giá</h3>
        <p id="review-hotel-name" class="text-sm text-[#C9D3DD] mb-4"></p>
        <input type="hidden" id="review-booking-id">
        <input type="hidden" id="review-hotel-id">

        <div id="review-error" class="hidden bg-red-50 text-red-600 text-sm rounded-xl px-4 py-3 mb-4"></div>

        {{-- Star rating --}}
        <div class="mb-4">
            <label class="block text-xs font-semibold text-[#3A4A5A] mb-2 uppercase tracking-wide">Đánh giá sao</label>
            <div class="star-rating" id="star-rating-container">
                <input type="radio" name="review-stars" id="star5" value="5"><label for="star5" title="5 sao">★</label>
                <input type="radio" name="review-stars" id="star4" value="4"><label for="star4" title="4 sao">★</label>
                <input type="radio" name="review-stars" id="star3" value="3"><label for="star3" title="3 sao">★</label>
                <input type="radio" name="review-stars" id="star2" value="2"><label for="star2" title="2 sao">★</label>
                <input type="radio" name="review-stars" id="star1" value="1"><label for="star1" title="1 sao">★</label>
            </div>
        </div>

        <div class="mb-4">
            <label class="block text-xs font-semibold text-[#3A4A5A] mb-2 uppercase tracking-wide">Nhận xét</label>
            <textarea id="review-comment" class="review-textarea" placeholder="Chia sẻ trải nghiệm của bạn tại khách sạn này..."></textarea>
        </div>

        <div class="flex gap-3">
            <button onclick="closeReviewModal()" class="flex-1 px-4 py-2.5 border border-[#C9D3DD] rounded-xl text-sm text-[#3A4A5A] hover:bg-[#EAF3FF] transition">Hủy</button>
            <button onclick="submitReview()" id="btn-submit-review" class="flex-1 btn-sky px-4 py-2.5 rounded-xl text-sm font-semibold">Gửi đánh giá</button>
        </div>
    </div>
</div>

{{-- CUSTOM CONFIRM MODAL --}}
<div id="confirmModal" class="fixed inset-0 bg-black/50 z-[300] hidden items-center justify-center p-4" onclick="closeConfirmModal()">
    <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl p-6" onclick="event.stopPropagation()">
        <div class="text-center mb-4">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-3" style="background: linear-gradient(135deg, #FEE2E2, #FECACA);">
                <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.232 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
            </div>
            <h3 id="confirmTitle" class="font-bold text-[#1E3A5F] text-lg mb-1">Xác nhận xóa</h3>
            <p id="confirmMessage" class="text-sm text-[#C9D3DD]">Bạn có chắc chắn muốn thực hiện hành động này?</p>
        </div>
        <div class="flex gap-3">
            <button onclick="closeConfirmModal()" class="flex-1 px-4 py-2.5 border border-[#C9D3DD] rounded-xl text-sm text-[#3A4A5A] hover:bg-[#EAF3FF] transition">
                Hủy
            </button>
            <button id="confirmOkBtn" class="flex-1 px-4 py-2.5 bg-red-500 hover:bg-red-600 text-white rounded-xl text-sm font-semibold transition">
                Xóa
            </button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
const dashboardToken = localStorage.getItem('token');
const user = JSON.parse(localStorage.getItem('user') || '{}');
if (!dashboardToken) { window.location.href = '/login'; }
if (user.name) {
    document.getElementById('welcome-name').textContent = user.name;
    document.getElementById('user-avatar').textContent  = user.name.charAt(0).toUpperCase();
}

// =====================
// DEBOUNCE UTILITY
// =====================
function debounce(func, wait = 300) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// =====================
// STATS
// =====================
async function loadStats() {
    try {
        const [b, w, r, i, n] = await Promise.all([
            api('/bookings/my').then(r => r.json()),
            api('/wishlist').then(r => r.json()),
            api('/reviews/my').then(r => r.json()),
            api('/itineraries').then(r => r.json()),
            api('/notifications?per_page=50').then(r => r.json()),
        ]);

        const totalBookings    = b?.total ?? b?.data?.length ?? 0;
        const totalWishlist    = Array.isArray(w) ? w.length : (w?.data?.length ?? 0);
        const totalReviews     = Array.isArray(r) ? r.length : (r?.data?.length ?? 0);
        const totalItineraries = Array.isArray(i) ? i.length : (i?.data?.length ?? 0);

        document.getElementById('stat-bookings').textContent    = totalBookings;
        document.getElementById('stat-wishlist').textContent    = totalWishlist;
        document.getElementById('stat-reviews').textContent     = totalReviews;
        document.getElementById('stat-itineraries').textContent = totalItineraries;

        // Helper set badge
        function setBadge(id, count) {
            const el = document.getElementById(id);
            if (!el) return;
            if (count > 0) { el.textContent = count > 99 ? '99+' : count; el.classList.remove('hidden'); }
            else el.classList.add('hidden');
        }

        // Bookings: đếm số pending
        const bookingList = Array.isArray(b) ? b : (b?.data || []);
        const pendingCount = bookingList.filter(x => x.status === 'pending').length;
        setBadge('tab-bookings-badge', pendingCount);

        // Wishlist: tổng số
        setBadge('tab-wishlist-badge', totalWishlist);

        // Reviews: tổng số
        setBadge('tab-reviews-badge', totalReviews);

        // Itineraries: tổng số
        setBadge('tab-itineraries-badge', totalItineraries);

        // Notifications: số chưa đọc
        const notifs = Array.isArray(n) ? n : (n?.data || []);
        const unread = notifs.filter(x => !x.read_at).length;
        setBadge('tab-notif-badge', unread);

    } catch(e) { console.error('loadStats error:', e); }
}
loadStats();

// =====================
// TABS
// =====================
const ALL_TABS = ['bookings','wishlist','reviews','itineraries','notifications','profile'];
function showTab(tab) {
    ALL_TABS.forEach(t => {
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
    if (tab === 'bookings')      loadBookings();
    if (tab === 'wishlist')      loadWishlist();
    if (tab === 'reviews')       loadReviews();
    if (tab === 'itineraries')   loadItineraries();
    if (tab === 'notifications') loadNotificationsPanel();
    if (tab === 'profile')       loadProfile();
}

// Handle #hash deep-link
const hashTab = window.location.hash.replace('#','');
if (ALL_TABS.includes(hashTab)) showTab(hashTab);

function emptyState(icon, msg, link, linkText, sub = '') {
    return `<div class="text-center py-16 text-[#C9D3DD]">
        <p class="text-5xl mb-3">${icon}</p>
        <p class="font-medium text-[#3A4A5A]">${msg}</p>
        ${sub  ? `<p class="text-sm mt-1 text-[#C9D3DD]">${sub}</p>` : ''}
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
    if (!items.length) { list.innerHTML = emptyState('🏨','Chưa có đặt phòng nào','/','Tìm khách sạn ngay →'); return; }

    const sc = {
        pending:     { c: 'bg-amber-50 text-amber-600 border border-amber-200',      l: 'Chờ xác nhận' },
        confirmed:   { c: 'bg-green-50 text-green-700 border border-green-200',      l: 'Đã xác nhận' },
        cancelled:   { c: 'bg-red-50 text-red-600 border border-red-200',            l: 'Đã huỷ' },
        completed:   { c: 'bg-[#EAF3FF] text-[#1E3A5F] border border-[#87CEFA]/30', l: 'Hoàn thành' },
        checked_in:  { c: 'bg-blue-50 text-blue-700 border border-blue-200',         l: 'Đang ở' },
        checked_out: { c: 'bg-gray-50 text-gray-600 border border-gray-200',         l: 'Đã trả phòng' },
    };

    list.innerHTML = `<div class="space-y-3">` + items.map(b => {
        const s = sc[b.status] || sc.pending;
        const canCancel = ['pending','confirmed'].includes(b.status);
        const canReview = ['completed','checked_out'].includes(b.status);
        const pm = b.payment_method ?? b.payment?.payment_method ?? null;
        const methodLabel = (pm==='banking'||pm==='vnpay') ? '<span class="text-xs text-[#87CEFA]">💳 QR</span>'
                          : pm==='cash' ? '<span class="text-xs text-amber-500">🏨 Tại quầy</span>' : '';
        return `
        <div class="booking-card bg-white rounded-2xl p-5 shadow-sm border border-[#EAF3FF] flex items-start gap-4">
            <div class="w-16 h-16 rounded-xl overflow-hidden shrink-0 bg-[#EAF3FF]">
                <img src="https://picsum.photos/seed/${b.hotel_id}/100/100" class="w-full h-full object-cover">
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1 flex-wrap">
                    <span class="text-xs font-mono text-[#C9D3DD]">#${b.id}</span>
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium ${s.c}">${s.l}</span>
                    ${methodLabel}
                </div>
                <h3 class="font-semibold text-[#1E3A5F] truncate">${b.hotel?.name || 'Khách sạn'}</h3>
                <p class="text-xs text-[#C9D3DD] mt-1 flex items-center gap-3">
                    <span>${b.check_in?.split('T')[0]} → ${b.check_out?.split('T')[0]}</span>
                    <span>${b.num_guests} khách</span>
                </p>
                <div class="flex items-center gap-2 mt-2 flex-wrap">
                    ${(b.status==='pending' && pm==='cash')
                        ? `<span class="text-xs text-amber-500 italic">📋 Thanh toán khi check-in</span>`
                        : (b.status==='completed' || pm==='banking')
                            ? `<button onclick="downloadInvoice(${b.id})" class="btn-invoice">📄 Hóa đơn PDF</button>`
                            : ''}
                    ${canReview ? `<button onclick="openReviewModal(${b.id}, ${b.hotel_id}, '${(b.hotel?.name||'').replace(/'/g,"\'")}')"
                        class="text-xs px-3 py-1 rounded-lg bg-amber-50 text-amber-600 border border-amber-200 hover:bg-amber-100 transition flex items-center gap-1">
                        ⭐ Viết đánh giá
                    </button>` : ''}
                </div>
            </div>
            <div class="text-right shrink-0">
                <p class="font-bold text-[#87CEFA] text-sm">${new Intl.NumberFormat('vi-VN').format(b.total_price)}đ</p>
                ${canCancel ? `<button onclick="cancelBooking(${b.id})" class="text-xs text-red-400 hover:text-red-600 mt-1 block w-full text-right">Huỷ</button>` : ''}
            </div>
        </div>`;
    }).join('') + `</div>`;
}

async function cancelBooking(id) {
    showConfirmModal({
        title: 'Hủy đặt phòng',
        message: 'Bạn có chắc chắn muốn hủy booking này?',
        okText: 'Hủy booking',
        type: 'warning',
        onConfirm: async () => {
            const res  = await api(`/bookings/${id}/cancel`, { method: 'POST' });
            const data = await res.json();
            if (res.ok) {
                showCustomToast('✅ Đã hủy booking!', 'success');
            } else {
                showCustomToast('❌ ' + (data.message || 'Hủy thất bại!'), 'error');
            }
            loadBookings(); 
            loadStats();
        }
    });
}

function downloadInvoice(bookingId) {
    api(`/invoices/${bookingId}/pdf`)
        .then(res => { if(!res.ok){showToast('Chưa có hóa đơn');return null;} return res.blob(); })
        .then(blob => { if(!blob) return; const url=URL.createObjectURL(blob); window.open(url,'_blank'); setTimeout(()=>URL.revokeObjectURL(url),10000); })
        .catch(()=>showToast('Lỗi tải hóa đơn'));
}

// =====================
// WISHLIST
// =====================
async function loadWishlist() {
    const list = document.getElementById('wishlist-list');
    list.innerHTML = '<div class="col-span-3 text-center py-16 text-[#C9D3DD]"><div class="spinner"></div>Đang tải...</div>';
    const raw  = await (await api('/wishlist')).json();
    const data = Array.isArray(raw) ? raw : (raw?.data || []);
    if (!data.length) { list.innerHTML=`<div class="col-span-3">${emptyState('❤️','Chưa có KS yêu thích','/','Khám phá ngay →')}</div>`; return; }
    list.innerHTML = data.map(w => `
        <div class="bg-white rounded-2xl overflow-hidden shadow-sm border border-[#EAF3FF] booking-card">
            <div class="relative h-36 overflow-hidden bg-[#EAF3FF]">
                <img src="https://picsum.photos/seed/${w.hotel_id}/400/200" class="w-full h-full object-cover">
                <button onclick="removeWishlist(${w.hotel_id},this)" class="absolute top-2 right-2 bg-white/90 w-7 h-7 rounded-full flex items-center justify-center text-red-400 shadow hover:bg-red-50 transition">♥</button>
            </div>
            <div class="p-4">
                <h3 class="font-semibold text-[#1E3A5F] text-sm truncate">${w.hotel?.name||''}</h3>
                <p class="text-xs text-[#C9D3DD] mt-1">📍 ${w.hotel?.location?.name||''}</p>
                <a href="/hotels/${w.hotel_id}" class="btn-sky text-xs px-3 py-1.5 rounded-lg mt-3 inline-block">Xem phòng →</a>
            </div>
        </div>`).join('');
}
async function removeWishlist(id,btn) {
    await api(`/wishlist/${id}`,{method:'DELETE'});
    btn.closest('.bg-white').remove(); showToast('Đã xóa khỏi yêu thích'); loadStats();
}

// =====================
// REVIEWS
// =====================
async function loadReviews() {
    const list = document.getElementById('reviews-list');
    list.innerHTML = '<div class="text-center py-16 text-[#C9D3DD]"><div class="spinner"></div>Đang tải...</div>';
    const raw  = await (await api('/reviews/my')).json();
    const data = Array.isArray(raw) ? raw : (raw?.data || []);
    if (!data.length) {
        list.innerHTML = emptyState('⭐','Chưa có đánh giá nào',null,null,'Đặt phòng và check-out để có thể viết đánh giá!');
        return;
    }
    list.innerHTML = `<div class="space-y-3">` + data.map(r => `
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-[#EAF3FF] booking-card">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-3 flex-1">
                    <div class="w-12 h-12 rounded-xl overflow-hidden shrink-0 bg-[#EAF3FF]">
                        <img src="https://picsum.photos/seed/${r.hotel_id||r.id}/80/80" class="w-full h-full object-cover">
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-[#1E3A5F]">${r.hotel?.name||'Khách sạn'}</h3>
                        <div class="flex items-center gap-0.5 mt-1">
                            ${Array(r.rating).fill('<span class="text-amber-400 text-sm">★</span>').join('')}
                            ${Array(5-r.rating).fill('<span class="text-[#C9D3DD] text-sm">★</span>').join('')}
                            <span class="text-xs text-[#C9D3DD] ml-1">${r.rating}/5</span>
                        </div>
                        ${r.comment ? `<p class="text-xs text-[#3A4A5A] mt-1.5 leading-relaxed">"${r.comment}"</p>` : ''}
                        <p class="text-xs text-[#C9D3DD] mt-1">${r.created_at?.split('T')[0]||''}</p>
                    </div>
                </div>
                <button onclick="deleteReview(${r.id},this)" class="text-xs text-[#C9D3DD] hover:text-red-500 border border-[#EAF3FF] hover:border-red-200 px-3 py-1.5 rounded-xl shrink-0 transition">Xóa</button>
            </div>
        </div>`).join('') + `</div>`;
}
async function deleteReview(id, btn) {
    showConfirmModal({
        title: 'Xóa đánh giá',
        message: 'Bạn có chắc chắn muốn xóa đánh giá này?',
        okText: 'Xóa',
        type: 'danger',
        onConfirm: async () => {
            await api(`/reviews/${id}`, { method: 'DELETE' });
            btn.closest('.bg-white').remove(); 
            showCustomToast('✅ Đã xóa đánh giá!', 'success');
            loadStats();
        }
    });
}

// =====================
// REVIEW MODAL
// =====================
function openReviewModal(bookingId, hotelId, hotelName) {
    document.getElementById('review-booking-id').value = bookingId;
    document.getElementById('review-hotel-id').value   = hotelId;
    document.getElementById('review-hotel-name').textContent = hotelName;
    document.getElementById('review-comment').value = '';
    document.getElementById('review-error').classList.add('hidden');
    // Reset stars
    document.querySelectorAll('input[name="review-stars"]').forEach(r => r.checked = false);
    const modal = document.getElementById('reviewModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}
function closeReviewModal(e) {
    if (e && e.target !== document.getElementById('reviewModal')) return;
    document.getElementById('reviewModal').classList.replace('flex','hidden');
}
async function submitReview() {
    const rating  = document.querySelector('input[name="review-stars"]:checked')?.value;
    const comment = document.getElementById('review-comment').value.trim();
    const hotelId = document.getElementById('review-hotel-id').value;
    const err     = document.getElementById('review-error');
    const btn     = document.getElementById('btn-submit-review');
    err.classList.add('hidden');

    if (!rating) { err.textContent = 'Vui lòng chọn số sao!'; err.classList.remove('hidden'); return; }
    if (!comment) { err.textContent = 'Vui lòng nhập nhận xét!'; err.classList.remove('hidden'); return; }

    btn.textContent = 'Đang gửi...'; btn.disabled = true;
    const res  = await api('/reviews', { method:'POST', body:JSON.stringify({ hotel_id:hotelId, rating:parseInt(rating), comment }) });
    const data = await res.json();
    btn.textContent = 'Gửi đánh giá'; btn.disabled = false;

    if (res.ok) {
        document.getElementById('reviewModal').classList.replace('flex','hidden');
        showToast('✅ Đã gửi đánh giá thành công! ');
        loadReviews(); loadStats();
    } else {
        err.textContent = data.message || 'Gửi đánh giá thất bại!';
        err.classList.remove('hidden');
    }
}

// =====================
// NOTIFICATIONS PANEL
// =====================
function stripEmoji(str) {
    return (str || '').replace(/[\u{1F300}-\u{1FFFF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}\u{FE00}-\u{FEFF}✅❌💳🎉🗺✍️⏰📍🔔]/gu, '').trim();
}

async function loadNotificationsPanel() {
    const list = document.getElementById('notifications-list');
    list.innerHTML = '<div class="text-center py-16 text-[#C9D3DD]"><div class="spinner"></div>Đang tải...</div>';
    const res  = await api('/notifications?per_page=50');
    const data = await res.json();
    const items = Array.isArray(data) ? data : (data?.data || []);

    if (!items.length) { list.innerHTML = emptyState('🔔','Chưa có thông báo nào'); return; }

    list.innerHTML = `<div class="space-y-2">` + items.map(n => `
        <div class="notif-panel-item ${n.read_at ? '' : 'unread'} bg-white rounded-2xl shadow-sm border border-[#EAF3FF] cursor-pointer flex items-start justify-between gap-3 p-4"
             onclick="handleNotifPanelClick(${n.id},'${n.data?.link||'/dashboard'}')">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-[#1E3A5F]">${stripEmoji(n.data?.title)||'Thông báo'}</p>
                <p class="text-xs text-[#C9D3DD] mt-0.5 leading-relaxed">${n.data?.message||''}</p>
                <p class="text-xs text-[#87CEFA] mt-1">${formatNotifTime(n.created_at)}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0 mt-0.5">
                ${!n.read_at ? '<span class="w-2 h-2 rounded-full bg-[#87CEFA]"></span>' : ''}
                <button onclick="event.stopPropagation(); deleteNotif(${n.id}, this)"
                    class="w-6 h-6 rounded-full flex items-center justify-center text-[#C9D3DD] hover:text-red-500 hover:bg-red-50 transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>`).join('') + `</div>`;

    const unread = items.filter(n=>!n.read_at).length;
    const tabBadge = document.getElementById('tab-notif-badge');
    if (unread > 0) { tabBadge.textContent = unread; tabBadge.classList.remove('hidden'); }
    else tabBadge.classList.add('hidden');
}

async function deleteNotif(id, btn) {
    await api(`/notifications/${id}`, { method: 'DELETE' });
    btn.closest('.notif-panel-item').remove();
    loadStats();
}

async function handleNotifPanelClick(id, link) {
    await api(`/notifications/${id}/read`, { method: 'POST' });
    if (link !== '#' && link !== '/dashboard') window.location.href = link;
    else loadNotificationsPanel();
}

async function markAllReadDashboard() {
    await api('/notifications/read-all', { method: 'POST' });
    showToast('Đã đánh dấu tất cả là đã đọc');
    loadNotificationsPanel(); loadStats();
}

function formatNotifTime(dateStr) {
    if (!dateStr) return '';
    const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
    if (diff < 60) return 'Vừa xong';
    if (diff < 3600) return Math.floor(diff/60) + ' phút trước';
    if (diff < 86400) return Math.floor(diff/3600) + ' giờ trước';
    return new Date(dateStr).toLocaleDateString('vi-VN');
}

// =====================
// HOTEL AUTOCOMPLETE + QUICK CITY
// =====================
let hotelData = [];
let allHotels = []; // Lưu toàn bộ khách sạn từ API

// ====================================================
// OPEN / CLOSE CITY HOTEL PANEL
// ====================================================
function openCityHotelPanel(city, seed) {
    // Cập nhật header panel
    document.getElementById('cityPanelTitle').textContent = city;
    document.getElementById('cityPanelImgSrc').src = `https://picsum.photos/seed/${seed}/180/144`;
    document.getElementById('cityPanelImgSrc').alt = city;

    // Hiện panel, reset list
    const panel = document.getElementById('cityHotelPanel');
    panel.classList.remove('hidden');
    document.getElementById('cityHotelList').innerHTML = `
        <div class="flex items-center justify-center gap-2 py-8 text-[#C9D3DD] text-sm">
            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            Đang tải khách sạn...
        </div>`;

    // Clear input
    const cityInput = document.getElementById('trip-city');
    if (cityInput) cityInput.value = '';

    // Scroll panel vào view
    panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    // Gọi API
    fetchHotelsForPanel(city);
}

function closeCityHotelPanel() {
    document.getElementById('cityHotelPanel').classList.add('hidden');
}

// Gọi API lấy khách sạn → render vào panel
async function fetchHotelsForPanel(city) {
    try {
        const token = localStorage.getItem('token');
        const res = await fetch(`/api/hotels?city=${encodeURIComponent(city)}&per_page=20`, {
            headers: {
                'Authorization': token ? `Bearer ${token}` : '',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        console.log('[fetchHotelsForPanel] status:', res.status, 'city:', city);

        if (!res.ok) {
            const err = await res.text();
            console.error('[fetchHotelsForPanel] error body:', err);
            throw new Error('HTTP ' + res.status);
        }

        const data = await res.json();
        console.log('[fetchHotelsForPanel] data:', data);

        const hotels = (data.data || data).map(h => ({
            id:       h.id,
            name:     h.name,
            location: h.location?.name || city,
            stars:    h.star_rating || 0,
            price:    h.rooms_min_price || 0,
            image:    `https://picsum.photos/seed/${h.id}/120/90`
        }));

        allHotels = hotels;
        renderPanelHotelList(hotels, city);
    } catch (e) {
        console.error('[fetchHotelsForPanel] catch:', e);
        document.getElementById('cityHotelList').innerHTML =
            `<div class="ac-empty text-red-400">❌ Lỗi: ${e.message}</div>`;
    }
}

// Render danh sách khách sạn vào panel
function renderPanelHotelList(hotels, city) {
    // Định nghĩa local để tránh scope issues
    function esc(text) {
        if (!text) return '';
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    const list = document.getElementById('cityHotelList');

    if (!hotels.length) {
        list.innerHTML = `
            <div class="ac-empty">
                Chưa có khách sạn tại ${esc(city)}
            </div>`;
        return;
    }

    list.innerHTML = hotels.map(h => {
        const price = h.price ? new Intl.NumberFormat('vi-VN').format(h.price) + 'đ' : '';
        const stars = h.stars ? '⭐'.repeat(Math.min(h.stars, 5)) : '';
        return `
        <div class="ac-item" onclick="selectHotelFromPanel('${esc(h.name).replace(/'/g, "\\'")}')">
            <div class="ac-img-wrap">
                <img src="${h.image}" alt="${esc(h.name)}" loading="lazy" onerror="this.style.display='none'">
            </div>
            <div class="ac-info">
                <div class="ac-name">${esc(h.name)}</div>
                <div class="ac-location">
                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    ${esc(h.location)} ${stars ? '· ' + stars : ''}
                </div>
            </div>
            ${price ? `<div class="ac-price">${price}<span style="font-size:10px;font-weight:400;color:#94A3B8">/đêm</span></div>` : ''}
        </div>`;
    }).join('');
}

// Khi click khách sạn trong panel → điền vào input
function selectHotelFromPanel(name) {
    const cityInput = document.getElementById('trip-city');
    if (cityInput) {
        cityInput.value = name;
        // Highlight input nhẹ
        cityInput.style.transition = 'box-shadow 0.3s';
        cityInput.style.boxShadow = '0 0 0 3px rgba(135,206,250,0.5)';
        setTimeout(() => { cityInput.style.boxShadow = ''; }, 1200);
    }
    closeCityHotelPanel();
    showCustomToast?.('✅ Đã chọn: ' + name, 'success');
}

// Hàm gọi API lấy khách sạn theo thành phố (dùng cho autocomplete input)
async function fetchHotelsByCity(city) {
    try {
        const res = await api(`/hotels?city=${encodeURIComponent(city)}&per_page=20`);
        const data = await res.json();
        const hotels = (data.data || data).map(h => ({
            id:       h.id,
            name:     h.name,
            location: h.location?.name || city,
            stars:    h.star_rating || 0,
            price:    h.rooms_min_price || 0,
            image:    `https://picsum.photos/seed/${h.id}/120/90`
        }));
        allHotels = hotels;
        renderHotelDropdown(city, hotels.slice(0, 7));
        document.getElementById('city-dropdown').classList.remove('hidden');
    } catch (e) {
        console.error('Lỗi lấy khách sạn:', e);
    }
}

// Hàm render dropdown khách sạn (autocomplete khi tự gõ)
function renderHotelDropdown(keyword, hotels) {
    const dd = document.getElementById('city-dropdown');

    if (!hotels.length) {
        dd.innerHTML = `
            <div class="ac-empty">
                <svg class="w-8 h-8 mx-auto mb-2 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                Không tìm thấy khách sạn tại đây
            </div>`;
        dd.classList.remove('hidden');
        return;
    }

    let html = `<div class="ac-header">🏨 Khách sạn tại ${escapeHtml(keyword)}</div>`;
    hotels.forEach(h => {
        const price = h.price ? new Intl.NumberFormat('vi-VN').format(h.price) + 'đ' : '';
        html += `
        <div class="ac-item" onclick="selectHotel('${escapeHtml(h.name).replace(/'/g, "\'")}')">
            <div class="ac-img-wrap">
                <img src="${h.image}" alt="${escapeHtml(h.name)}" loading="lazy"
                     onerror="this.style.display='none'">
            </div>
            <div class="ac-info">
                <div class="ac-name">${escapeHtml(h.name)}</div>
                <div class="ac-location">
                    <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    ${escapeHtml(h.location)} · ${'⭐'.repeat(Math.min(h.stars || 0, 5))}
                </div>
            </div>
            ${price ? `<div class="ac-price">${price}<span style="font-size:10px;font-weight:400;color:#94A3B8">/đêm</span></div>` : ''}
        </div>`;
    });

    dd.innerHTML = html;
    dd.classList.remove('hidden');
}

// Chọn khách sạn từ autocomplete input
function selectHotel(name) {
    document.getElementById('trip-city').value = name;
    hideCityDropdown();
}

// setCity alias (giữ tương thích nếu có nơi khác dùng)
function setCity(name) {
    openCityHotelPanel(name, name.toLowerCase().replace(/\s+/g, '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g,'d'));
}




// Khởi tạo
setTimeout(() => {
    const cityInput = document.getElementById('trip-city');
    if (!cityInput) return;
    
    cityInput.addEventListener('input', handleHotelInput);
    cityInput.addEventListener('focus', function() {
        if (!this.value.trim()) {
            hideCityDropdown();
        }
    });
    
    document.addEventListener('click', function(e) {
        const wrap = document.getElementById('cityAutocompleteWrap');
        if (wrap && !wrap.contains(e.target)) {
            hideCityDropdown();
        }
    });
}, 100);

document.addEventListener('click', function(e) {
    const wrap = document.getElementById('cityAutocompleteWrap');
    if (wrap && !wrap.contains(e.target)) hideCityDropdown();
});

// =====================
// GENERATE ITINERARY
// =====================
async function generateItinerary() {
    const city      = document.getElementById('trip-city').value.trim();
    const days      = document.getElementById('trip-days').value;
    const budget    = document.getElementById('trip-budget').value;
    const startDate = document.getElementById('trip-start-date').value;
    const btn       = document.getElementById('gen-btn');
    
    if (!city) { 
        showCustomToast('Vui lòng nhập thành phố!', 'warning');
        return; 
    }
    
    // Hiển thị loading
    btn.innerHTML = `<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg> Đang tạo...`;
    btn.disabled = true;
    
    try {
        const res  = await api('/itineraries/generate', { 
            method: 'POST', 
            body: JSON.stringify({ 
                city, 
                days: parseInt(days), 
                budget: parseInt(budget), 
                start_date: startDate || null 
            }) 
        });
        const data = await res.json();
        
        btn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg> Tạo`;
        btn.disabled = false;
        
        if (res.ok) {
            showCustomToast('✅ AI đã tạo lịch trình thành công!', 'success');
            loadItineraries(); 
            loadStats();
            if (data.itinerary) {
                setTimeout(() => openItineraryModal(data.itinerary), 500);
            }
        } else {
            showCustomToast('❌ ' + (data.message || 'Tạo lịch trình thất bại!'), 'error');
        }
    } catch (e) {
        btn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg> Tạo`;
        btn.disabled = false;
        showCustomToast('❌ Lỗi kết nối, vui lòng thử lại!', 'error');
    }
}

// =====================
// ITINERARIES LIST
// =====================
async function loadItineraries() {
    const list = document.getElementById('itineraries-list');
    list.innerHTML = '<div class="text-center py-16 text-[#C9D3DD]"><div class="spinner"></div>Đang tải...</div>';
    const raw  = await (await api('/itineraries')).json();
    const data = Array.isArray(raw) ? raw : (raw?.data || []);
    if (!data.length) { list.innerHTML = emptyState('🗺','Chưa có lịch trình',null,null,'Thử tạo bằng AI ở trên!'); return; }
    const sc = {
        draft:     { c:'bg-gray-50 text-gray-600 border border-gray-200',         l:'Bản nháp' },
        confirmed: { c:'bg-green-50 text-green-700 border border-green-200',      l:'Đã xác nhận' },
        completed: { c:'bg-[#EAF3FF] text-[#1E3A5F] border border-[#87CEFA]/30', l:'Hoàn thành' },
    };
    list.innerHTML = `<div class="grid grid-cols-1 md:grid-cols-2 gap-4">` + data.map(it => {
        const s = sc[it.status] || sc.draft;
        const budgetFmt = it.estimated_budget ? new Intl.NumberFormat('vi-VN').format(it.estimated_budget)+'đ' : 'Linh hoạt';
        const itJson = JSON.stringify(it).replace(/"/g,'&quot;');
        return `
        <div class="bg-white rounded-2xl shadow-sm border border-[#EAF3FF] hover:shadow-md hover:-translate-y-0.5 transition-all overflow-hidden">
            <div class="h-1.5 w-full" style="background:linear-gradient(90deg,#87CEFA,#5BB8F5);"></div>
            <div class="p-5">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div>
                        <h3 class="font-semibold text-[#1E3A5F] text-base">${it.title || 'Lịch trình'}</h3>
                        <div class="flex flex-wrap gap-2 text-xs text-[#C9D3DD] mt-1">
                            <span>${it.total_days} ngày</span>
                            <span>💰 ${budgetFmt}</span>
                            <span>🗂 ${it.items?.length||0} hoạt động</span>
                        </div>
                    </div>
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium ${s.c}">${s.l}</span>
                </div>
                <div class="flex items-center gap-2 pt-3 border-t border-[#EAF3FF]">
                    <button onclick="openItineraryModal(${itJson})"
                        class="flex-1 btn-sky text-xs py-2 rounded-xl font-semibold flex items-center justify-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        Xem chi tiết
                    </button>
                    <button onclick="shareItineraryById(${it.id})" title="Chia sẻ" class="w-8 h-8 rounded-xl flex items-center justify-center text-[#C9D3DD] hover:text-[#87CEFA] hover:bg-[#EAF3FF] transition border border-[#EAF3FF]">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                    </button>
                    <button onclick="deleteItineraryById(${it.id})" title="Xóa" class="w-8 h-8 rounded-xl flex items-center justify-center text-[#C9D3DD] hover:text-red-500 hover:bg-red-50 transition border border-[#EAF3FF]">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </div>
            </div>
        </div>`;
    }).join('') + `</div>`;
}

// =====================
// ITINERARY MODAL
// =====================
let currentItinerary = null;
const TYPE_ICON = { hotel:'🏨', restaurant:'🍽', attraction:'🎯', transport:'🚗', activity:'🎪', shopping:'🛍' };

function openItineraryModal(it) {
    currentItinerary = it;
    document.getElementById('itin-modal-title').textContent = it.title || 'Lịch trình';
    const meta = [it.total_days+' ngày', it.start_date ? '📅 '+it.start_date.split('T')[0] : '', it.estimated_budget ? '💰 '+new Intl.NumberFormat('vi-VN').format(it.estimated_budget)+'đ' : ''].filter(Boolean).join('  ·  ');
    document.getElementById('itin-modal-meta').textContent = meta;
    document.getElementById('itin-modal-start-date').value = it.start_date ? it.start_date.split('T')[0] : '';

    const items = it.items || [];
    const byDay = {};
    items.forEach(item => { const d=item.day_number||1; if(!byDay[d]) byDay[d]=[]; byDay[d].push(item); });
    const days = Object.keys(byDay).sort((a,b)=>a-b);
    const startDate = it.start_date ? new Date(it.start_date) : null;

    let html = '';
    if (!days.length) {
        html = `<div class="text-center py-10 text-[#C9D3DD]"><p class="text-4xl mb-3">🗺</p><p>Lịch trình chưa có hoạt động nào.</p></div>`;
    } else {
        html = days.map(day => {
            let dateLabel = '';
            if (startDate) {
                const d = new Date(startDate);
                d.setDate(d.getDate() + parseInt(day) - 1);
                dateLabel = d.toLocaleDateString('vi-VN',{weekday:'long',day:'2-digit',month:'2-digit'});
            }
            return `<div>
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-8 h-8 rounded-xl flex items-center justify-center text-white text-sm font-bold shrink-0" style="background:linear-gradient(135deg,#87CEFA,#5BB8F5);">${day}</div>
                    <div><p class="font-bold text-[#1E3A5F] text-sm">Ngày ${day}</p>${dateLabel?`<p class="text-xs text-[#C9D3DD]">${dateLabel}</p>`:''}</div>
                </div>
                <div class="ml-4 border-l-2 border-[#EAF3FF] pl-4 space-y-2.5">
                    ${byDay[day].map(item => `
                    <div class="bg-[#F8FBFF] rounded-xl p-3 border border-[#EAF3FF]">
                        <div class="flex items-start gap-2.5">
                            <span class="text-base shrink-0 mt-0.5">${TYPE_ICON[item.type]||'📌'}</span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p class="font-semibold text-[#1E3A5F] text-sm">${item.name||item.title||''}</p>
                                    ${item.time?`<span class="text-xs text-[#87CEFA] bg-[#EAF3FF] px-2 py-0.5 rounded-full">🕐 ${item.time}</span>`:''}
                                </div>
                                ${item.description?`<p class="text-xs text-[#C9D3DD] mt-1 leading-relaxed">${item.description}</p>`:''}
                                <div class="flex flex-wrap gap-3 mt-1">
                                    ${item.cost?`<span class="text-xs text-[#87CEFA] font-semibold">💰 ${new Intl.NumberFormat('vi-VN').format(item.cost)}đ</span>`:''}
                                    ${item.address?`<span class="text-xs text-[#C9D3DD]">📍 ${item.address}</span>`:''}
                                </div>
                            </div>
                        </div>
                    </div>`).join('')}
                </div>
            </div>`;
        }).join('');
    }
    document.getElementById('itin-modal-body').innerHTML = html;
    const modal = document.getElementById('itineraryModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeItineraryModal(e) {
    if (e.target !== document.getElementById('itineraryModal')) return;
    document.getElementById('itineraryModal').classList.replace('flex','hidden');
}

async function updateItineraryDate() {
    if (!currentItinerary) return;
    const date = document.getElementById('itin-modal-start-date').value;
    
    try {
        const res = await api(`/itineraries/${currentItinerary.id}`, {
            method: 'PUT', 
            body: JSON.stringify({ start_date: date })
        });
        
        if (res.ok) { 
            currentItinerary.start_date = date; 
            showCustomToast('✅ Đã cập nhật ngày bắt đầu!', 'success');
            openItineraryModal(currentItinerary); 
            loadItineraries(); 
        } else {
            const data = await res.json();
            showCustomToast('❌ ' + (data.message || 'Cập nhật thất bại!'), 'error');
        }
    } catch (e) {
        showCustomToast('❌ Lỗi kết nối, vui lòng thử lại!', 'error');
    }
}

async function deleteCurrentItinerary() {
    if (!currentItinerary) return;
    document.getElementById('itineraryModal').classList.replace('flex','hidden');
    await deleteItineraryById(currentItinerary.id);
}
async function deleteItineraryById(id) {
    showConfirmModal({
        title: 'Xóa lịch trình',
        message: 'Bạn có chắc chắn muốn xóa lịch trình này? Hành động này không thể hoàn tác!',
        okText: 'Xóa',
        type: 'danger',
        onConfirm: async () => {
            try {
                const res = await api(`/itineraries/${id}`, { method: 'DELETE' });
                if (res.ok) { 
                    showCustomToast('✅ Đã xóa lịch trình!', 'success');
                    loadItineraries(); 
                    loadStats(); 
                } else {
                    const data = await res.json();
                    showCustomToast('❌ ' + (data.message || 'Xóa thất bại!'), 'error');
                }
            } catch (e) {
                showCustomToast('❌ Lỗi kết nối!', 'error');
            }
        }
    });
}

function shareItinerary() { if (currentItinerary) shareItineraryById(currentItinerary.id); }
function shareItineraryById(id) {
    const url = `${window.location.origin}/itineraries/${id}`;
    navigator.clipboard.writeText(url)
        .then(() => showCustomToast('✅ Đã copy link chia sẻ!', 'success'))
        .catch(() => prompt('Copy link:', url));
}

function printItinerary() {
    if (!currentItinerary) return;
    const it = currentItinerary;
    const items = it.items||[];
    const byDay = {};
    items.forEach(i=>{const d=i.day_number||1;if(!byDay[d])byDay[d]=[];byDay[d].push(i);});
    const days = Object.keys(byDay).sort((a,b)=>a-b);
    const startDate = it.start_date ? new Date(it.start_date) : null;
    const win = window.open('','_blank');
    win.document.write(`<!DOCTYPE html><html><head><meta charset="UTF-8"><title>${it.title}</title><style>body{font-family:'Segoe UI',sans-serif;max-width:700px;margin:0 auto;padding:32px;color:#1E3A5F;}h1{font-size:22px;margin-bottom:4px;}.meta{color:#94A3B8;font-size:12px;margin-bottom:28px;padding-bottom:16px;border-bottom:2px solid #EAF3FF;}.day-wrap{margin-bottom:24px;}.day-head{display:flex;align-items:center;gap:10px;margin-bottom:10px;}.day-num{background:linear-gradient(135deg,#87CEFA,#5BB8F5);color:#fff;width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;}.day-title{font-weight:700;font-size:15px;}.day-date{color:#94A3B8;font-size:11px;}.item{border:1px solid #EAF3FF;border-radius:10px;padding:10px 14px;margin-bottom:7px;margin-left:40px;}.item-name{font-weight:600;font-size:13px;}.item-sub{color:#94A3B8;font-size:11px;margin-top:3px;}@media print{body{padding:16px;}}</style></head><body>
    <h1>${it.title}</h1>
    <div class="meta">${it.total_days} ngày${it.start_date?' · Từ '+it.start_date.split('T')[0]:''}${it.estimated_budget?' · '+new Intl.NumberFormat('vi-VN').format(it.estimated_budget)+'đ':''}</div>
    ${days.map(day=>{let dl='';if(startDate){const d=new Date(startDate);d.setDate(d.getDate()+parseInt(day)-1);dl=d.toLocaleDateString('vi-VN',{weekday:'long',day:'2-digit',month:'2-digit'});}return`<div class="day-wrap"><div class="day-head"><div class="day-num">${day}</div><div><div class="day-title">Ngày ${day}</div>${dl?`<div class="day-date">${dl}</div>`:''}</div></div>${byDay[day].map(item=>`<div class="item"><div class="item-name">${TYPE_ICON[item.type]||'📌'} ${item.name||item.title||''} ${item.time?'· 🕐 '+item.time:''}</div>${item.description?`<div class="item-sub">${item.description}</div>`:''}${item.cost?`<div class="item-sub">💰 ${new Intl.NumberFormat('vi-VN').format(item.cost)}đ</div>`:''}${item.address?`<div class="item-sub">📍 ${item.address}</div>`:''}</div>`).join('')}</div>`;}).join('')}
    </body></html>`);
    win.document.close(); win.focus(); setTimeout(()=>win.print(),400);
}

// =====================
// PROFILE
// =====================
async function loadProfile() {
    const res  = await api('/me');
    const data = await res.json();
    document.getElementById('profile-name').value   = data.name   || '';
    document.getElementById('profile-email').value  = data.email  || '';
    document.getElementById('profile-phone').value  = data.phone  || '';
    document.getElementById('profile-dob').value    = data.dob    || '';
    document.getElementById('profile-gender').value = data.gender || '';
}

async function saveProfile() {
    const name   = document.getElementById('profile-name').value.trim();
    const email  = document.getElementById('profile-email').value.trim();
    const phone  = document.getElementById('profile-phone').value.trim();
    const dob    = document.getElementById('profile-dob').value;
    const gender = document.getElementById('profile-gender').value;
    const btn    = document.getElementById('btn-save-profile');
    const succ   = document.getElementById('profile-success');
    const err    = document.getElementById('profile-error');
    succ.classList.add('hidden'); err.classList.add('hidden');
    if (!name||!email) { err.textContent='Vui lòng nhập họ tên và email!'; err.classList.remove('hidden'); return; }
    btn.textContent='Đang lưu...'; btn.disabled=true;
    const res  = await api('/me',{method:'PUT',body:JSON.stringify({name,email,phone,dob,gender})});
    const data = await res.json();
    btn.textContent='Lưu thay đổi'; btn.disabled=false;
    if (res.ok) {
        const u=JSON.parse(localStorage.getItem('user')||'{}'); u.name=data.user.name; u.email=data.user.email; localStorage.setItem('user',JSON.stringify(u));
        document.getElementById('welcome-name').textContent=data.user.name;
        document.getElementById('user-avatar').textContent=data.user.name.charAt(0).toUpperCase();
        succ.textContent='✅ '+data.message; succ.classList.remove('hidden'); showToast('✅ Cập nhật thành công!');
    } else { err.textContent=data.message||'Cập nhật thất bại!'; err.classList.remove('hidden'); }
}

async function savePassword() {
    const current=document.getElementById('pw-current').value;
    const newPw=document.getElementById('pw-new').value;
    const confirm=document.getElementById('pw-confirm').value;
    const btn=document.getElementById('btn-save-pw');
    const succ=document.getElementById('pw-success');
    const err=document.getElementById('pw-error');
    succ.classList.add('hidden'); err.classList.add('hidden');
    if (!current||!newPw||!confirm) { err.textContent='Vui lòng nhập đầy đủ!'; err.classList.remove('hidden'); return; }
    if (newPw.length<6) { err.textContent='Mật khẩu tối thiểu 6 ký tự!'; err.classList.remove('hidden'); return; }
    if (newPw!==confirm) { err.textContent='Mật khẩu xác nhận không khớp!'; err.classList.remove('hidden'); return; }
    btn.textContent='Đang đổi...'; btn.disabled=true;
    const res=await api('/me/password',{method:'POST',body:JSON.stringify({current_password:current,new_password:newPw,new_password_confirmation:confirm})});
    const data=await res.json();
    btn.textContent='Đổi mật khẩu'; btn.disabled=false;
    if (res.ok) {
        succ.textContent='✅ '+data.message; succ.classList.remove('hidden');
        document.getElementById('pw-current').value=''; document.getElementById('pw-new').value=''; document.getElementById('pw-confirm').value='';
        showToast('✅ Đổi mật khẩu thành công!');
    } else { err.textContent=data.message||'Đổi thất bại!'; err.classList.remove('hidden'); }
}

loadBookings();

// Custom Toast Function
function showCustomToast(message, type = 'info', duration = 4000) {
    const toast = document.createElement('div');
    toast.className = `custom-toast ${type}`;
    
    const icons = {
        success: '✓',
        error: '✕',
        warning: '!',
        info: 'i'
    };
    
    toast.innerHTML = `
        <div class="custom-toast-icon">${icons[type] || 'i'}</div>
        <div class="custom-toast-content">${message}</div>
        <div class="custom-toast-close" onclick="this.closest('.custom-toast').remove()">✕</div>
    `;
    
    document.body.appendChild(toast);
    
    // Tự động xóa sau duration
    setTimeout(() => {
        toast.style.animation = 'toastSlideOut 0.3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

// Override showToast cũ (nếu có)
const originalShowToast = window.showToast;
window.showToast = function(message, type = 'info') {
    showCustomToast(message, type);
};

// Xóa tất cả thông báo
async function deleteAllNotifications() {
    showConfirmModal({
        title: 'Xóa tất cả thông báo',
        message: 'Bạn có chắc chắn muốn xóa tất cả thông báo? Hành động này không thể hoàn tác!',
        okText: 'Xóa tất cả',
        type: 'warning',
        onConfirm: async () => {
            try {
                const res = await api('/notifications/delete-all', { method: 'DELETE' });
                if (res.ok) {
                    showCustomToast('✅ Đã xóa tất cả thông báo!', 'success');
                    loadNotificationsPanel();
                    loadStats();
                } else {
                    const data = await res.json();
                    showCustomToast('❌ ' + (data.message || 'Xóa thất bại!'), 'error');
                }
            } catch (e) {
                showCustomToast('❌ Lỗi kết nối!', 'error');
            }
        }
    });
}

// =====================
// CUSTOM CONFIRM MODAL
// =====================
let confirmCallback = null;

function showConfirmModal(options = {}) {
    const {
        title = 'Xác nhận xóa',
        message = 'Bạn có chắc chắn muốn xóa?',
        okText = 'Xóa',
        cancelText = 'Hủy',
        onConfirm = () => {},
        type = 'danger' // danger, warning, info
    } = options;
    
    const modal = document.getElementById('confirmModal');
    const titleEl = document.getElementById('confirmTitle');
    const messageEl = document.getElementById('confirmMessage');
    const okBtn = document.getElementById('confirmOkBtn');
    
    titleEl.textContent = title;
    messageEl.textContent = message;
    okBtn.textContent = okText;
    
    // Set màu theo type
    const iconDiv = modal.querySelector('.w-14');
    const iconSvg = modal.querySelector('svg');
    
    if (type === 'danger') {
        iconDiv.style.background = 'linear-gradient(135deg, #FEE2E2, #FECACA)';
        iconSvg.className = 'w-7 h-7 text-red-500';
        okBtn.className = 'flex-1 px-4 py-2.5 bg-red-500 hover:bg-red-600 text-white rounded-xl text-sm font-semibold transition';
    } else if (type === 'warning') {
        iconDiv.style.background = 'linear-gradient(135deg, #FEF3C7, #FDE68A)';
        iconSvg.className = 'w-7 h-7 text-amber-500';
        okBtn.className = 'flex-1 px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-sm font-semibold transition';
    }
    
    confirmCallback = onConfirm;
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeConfirmModal() {
    const modal = document.getElementById('confirmModal');
    modal.classList.replace('flex', 'hidden');
    confirmCallback = null;
}

document.getElementById('confirmOkBtn').addEventListener('click', function() {
    if (confirmCallback) {
        confirmCallback();
    }
    closeConfirmModal();
});

// ESC key to close
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeConfirmModal();
    }
});

// Tạo debounced version
const debouncedGenerate = debounce(async function() {
    const city      = document.getElementById('trip-city').value.trim();
    const days      = document.getElementById('trip-days').value;
    const budget    = document.getElementById('trip-budget').value;
    const startDate = document.getElementById('trip-start-date').value;
    const btn       = document.getElementById('gen-btn');
    
    if (!city) { 
        showCustomToast('Vui lòng nhập thành phố!', 'warning');
        return; 
    }
    
    btn.innerHTML = `<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg> Đang tạo...`;
    btn.disabled = true;
    
    try {
        const res  = await api('/itineraries/generate', { 
            method: 'POST', 
            body: JSON.stringify({ city, days: parseInt(days), budget: parseInt(budget), start_date: startDate || null }) 
        });
        const data = await res.json();
        
        btn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg> Tạo`;
        btn.disabled = false;
        
        if (res.ok) {
            showCustomToast('✅ AI đã tạo lịch trình thành công!', 'success');
            loadItineraries(); 
            loadStats();
            if (data.itinerary) {
                setTimeout(() => openItineraryModal(data.itinerary), 500);
            }
        } else {
            showCustomToast('❌ ' + (data.message || 'Tạo lịch trình thất bại!'), 'error');
        }
    } catch (e) {
        btn.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg> Tạo`;
        btn.disabled = false;
        showCustomToast('❌ Lỗi kết nối, vui lòng thử lại!', 'error');
    }
}, 500);

// Gán vào onclick
function generateItinerary() {
    debouncedGenerate();
}


// =====================
// CÁC HÀM BỊ THIẾU - PASTE BỔ SUNG VÀO
// =====================

function hideCityDropdown() {
    document.getElementById('city-dropdown').classList.add('hidden');
}

let hotelDebounceTimer;
function handleHotelInput(e) {
    clearTimeout(hotelDebounceTimer);
    hotelDebounceTimer = setTimeout(() => {
        const kw = e.target.value.trim();
        if (!kw) {
            hideCityDropdown();
            return;
        }

        // Nếu đã có allHotels (từ lần click city trước), lọc từ đó
        if (allHotels.length > 0) {
            const filtered = allHotels.filter(h =>
                h.name.toLowerCase().includes(kw.toLowerCase()) ||
                h.location.toLowerCase().includes(kw.toLowerCase())
            ).slice(0, 7);
            renderHotelDropdown(kw, filtered);
            document.getElementById('city-dropdown').classList.remove('hidden');
        } else {
            // Chưa có data → gọi API search
            fetchHotelsByCity(kw);
        }
    }, 300);
}

// =====================
// DISABLE PAST DATES CHO CHECK-IN / CHECK-OUT
// =====================
(function() {
    const today = new Date().toISOString().split('T')[0]; // "YYYY-MM-DD"

    const startInput = document.getElementById('trip-start-date');
    const endInput   = document.getElementById('trip-end-date');

    if (startInput) {
        startInput.min = today;
        startInput.addEventListener('change', function() {
            // Check-out phải >= check-in
            if (endInput) {
                endInput.min = this.value || today;
                // Nếu check-out đang chọn trước check-in → reset
                if (endInput.value && endInput.value < this.value) {
                    endInput.value = '';
                }
            }
        });
    }

    if (endInput) {
        endInput.min = today;
    }
})();

// Trong cancelBooking()
refreshNotifications();

// Trong deleteReview()
refreshNotifications();

// Trong submitReview()
refreshNotifications();

// Trong deleteItineraryById()
refreshNotifications();

// Trong generateItinerary()
refreshNotifications();

// Trong deleteAllNotifications()
refreshNotifications();

// Trong markAllReadDashboard()
refreshNotifications();

// Trong saveProfile()
refreshNotifications();
</script>
@endpush