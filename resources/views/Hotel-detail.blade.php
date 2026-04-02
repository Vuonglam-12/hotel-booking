@extends('layout')

@section('title', $hotel->name)

@section('content')

<style>
    /* Soft Sky Color Scheme */
    :root {
        --soft-sky: #87CEFA;
        --soft-bg: #EAF3FF;
        --white: #FFFFFF;
        --soft-gray: #C9D3DD;
        --dark-soft: #3A4A5A;
    }
    
    /* Font chữ đồng bộ */
    .font-display {
        font-family: 'Playfair Display', serif;
    }
    
    /* Hiệu ứng card nổi */
    .info-card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .info-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 25px -5px rgba(58, 74, 90, 0.1);
    }
    
    /* Nút chính */
    .btn-primary {
        background: var(--soft-sky);
        color: var(--dark-soft);
        transition: all 0.3s ease;
        font-weight: 600;
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px -5px rgba(135, 206, 250, 0.4);
        background: #7BC4F5;
    }
    
    .btn-primary:active {
        transform: translateY(0);
    }
    
    /* Nút gold */
    .btn-gold {
        background: linear-gradient(135deg, #F59E0B, #FBBF24);
        color: white;
        transition: all 0.3s ease;
        font-weight: 600;
    }
    
    .btn-gold:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px -5px rgba(245, 158, 11, 0.4);
    }
    
    .btn-gold:active {
        transform: translateY(0);
    }
    
    /* Hiệu ứng click */
    .click-effect:active {
        transform: scale(0.98);
        transition: transform 0.1s ease;
    }
    
    /* Room item hover */
    .room-item {
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .room-item:hover {
        border-color: var(--soft-sky);
        background: var(--soft-bg);
        transform: translateX(4px);
    }
    
    /* Sidebar sticky */
    .sidebar-sticky {
        position: sticky;
        top: 20px;
        transition: all 0.3s ease;
    }
    
    .sidebar-sticky:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 30px -10px rgba(58, 74, 90, 0.15);
    }
    
    /* Rating badge */
    .rating-badge {
        background: rgba(58, 74, 90, 0.9);
        backdrop-filter: blur(10px);
    }
    
    /* Amenities icon */
    .amenity-icon {
        transition: all 0.2s ease;
        cursor: pointer;
    }
    
    .amenity-icon:hover {
        transform: scale(1.05);
        background: var(--soft-bg);
    }
    
    /* Line height fix */
    p, h1, h2, h3, .text-content {
        line-height: 1.5;
    }
    
    .section-title {
        line-height: 1.3;
        margin-bottom: 1rem;
    }
</style>

{{-- HOTEL HERO --}}
<section class="relative h-96 overflow-hidden" style="background: linear-gradient(135deg, #87CEFA, #EAF3FF);">
    @php
        $heroImg = "https://picsum.photos/seed/" . $hotel->id . "/1200/600";
    @endphp
    <img src="{{ $heroImg }}" alt="{{ $hotel->name }}" class="w-full h-full object-cover opacity-40">
    <div class="absolute inset-0 flex items-end">
        <div class="max-w-7xl mx-auto px-4 pb-8 w-full">
            <div class="flex items-end justify-between flex-wrap gap-4">
                <div>
                    <p class="text-amber-400 text-sm font-semibold mb-2 flex items-center gap-1">
                        @for($i = 1; $i <= $hotel->star_rating; $i++)
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                            </svg>
                        @endfor
                    </p>
                    <h1 class="font-display text-4xl md:text-5xl font-bold text-white mb-2 drop-shadow-lg">{{ $hotel->name }}</h1>
                    <p class="text-white/90 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        {{ $hotel->address }}
                    </p>
                </div>
                <div class="text-right">
                    <div class="rating-badge rounded-2xl px-6 py-4 text-white">
                        <p class="text-3xl font-bold" style="color: var(--soft-sky);">{{ $hotel->avg_rating }}</p>
                        <p class="text-sm text-white/80">/5 Đánh giá</p>
                        <p class="text-xs text-white/60 mt-1">({{ rand(50, 500) }} đánh giá)</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- MAIN CONTENT --}}
<div class="max-w-7xl mx-auto px-4 py-10">

    {{-- Breadcrumb --}}
    <div class="mb-6 flex items-center gap-2 text-sm">
        <a href="{{ route('home') }}" class="text-[#87CEFA] hover:text-[#3A4A5A] transition flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
            </svg>
            Trang chủ
        </a>
        <svg class="w-4 h-4 text-[#C9D3DD]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
        </svg>
        <a href="{{ route('home') }}?city={{ $hotel->location->name }}" class="text-[#87CEFA] hover:text-[#3A4A5A] transition">{{ $hotel->location->name }}</a>
        <svg class="w-4 h-4 text-[#C9D3DD]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
        </svg>
        <span class="text-[#3A4A5A] font-medium">{{ $hotel->name }}</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        {{-- LEFT: Info --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Description --}}
            <div class="info-card bg-white rounded-2xl p-6 shadow-sm border border-[#C9D3DD]/30">
                <h2 class="font-display text-xl font-bold text-[#3A4A5A] section-title flex items-center gap-2">
                    <svg class="w-6 h-6 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Giới thiệu
                </h2>
                <p class="text-[#3A4A5A]/80 leading-relaxed">{{ $hotel->description ?? 'Khách sạn ' . $hotel->star_rating . ' sao tại ' . $hotel->location->name . ' với không gian sang trọng, dịch vụ chuyên nghiệp và view đẹp.' }}</p>

                <div class="grid grid-cols-2 gap-4 mt-5 pt-4 border-t border-[#C9D3DD]/30">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-[#EAF3FF] flex items-center justify-center">
                            <svg class="w-5 h-5 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-[#C9D3DD]">Check-in</p>
                            <p class="font-semibold text-[#3A4A5A]">{{ substr($hotel->check_in_time, 0, 5) }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-[#EAF3FF] flex items-center justify-center">
                            <svg class="w-5 h-5 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-[#C9D3DD]">Check-out</p>
                            <p class="font-semibold text-[#3A4A5A]">{{ substr($hotel->check_out_time, 0, 5) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Amenities --}}
            @if($hotel->amenities->isNotEmpty())
            <div class="info-card bg-white rounded-2xl p-6 shadow-sm border border-[#C9D3DD]/30">
                <h2 class="font-display text-xl font-bold text-[#3A4A5A] section-title flex items-center gap-2">
                    <svg class="w-6 h-6 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                    </svg>
                    Tiện ích nổi bật
                </h2>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    @foreach($hotel->amenities as $amenity)
                    @php
                        $amenityIcons = [
                            'wifi' => ['svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>', 'name' => 'WiFi miễn phí'],
                            'pool' => ['svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>', 'name' => 'Hồ bơi'],
                            'gym' => ['svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>', 'name' => 'Phòng gym'],
                            'spa' => ['svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>', 'name' => 'Spa'],
                            'parking' => ['svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>', 'name' => 'Bãi đỗ xe'],
                            'restaurant' => ['svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>', 'name' => 'Nhà hàng'],
                            'bar' => ['svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>', 'name' => 'Bar'],
                            'beach_access' => ['svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>', 'name' => 'Bãi biển riêng'],
                            'airport_shuttle' => ['svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>', 'name' => 'Xe đưa đón'],
                            'breakfast' => ['svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>', 'name' => 'Bữa sáng'],
                            'room_service' => ['svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>', 'name' => 'Phục vụ phòng'],
                        ];
                        $info = $amenityIcons[$amenity->amenity] ?? ['svg' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>', 'name' => str_replace('_', ' ', $amenity->amenity)];
                    @endphp
                    <div class="flex items-center gap-2 text-sm text-[#3A4A5A] p-2 rounded-lg amenity-icon">
                        <svg class="w-5 h-5 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            {!! $info['svg'] !!}
                        </svg>
                        <span class="capitalize">{{ $info['name'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Rooms --}}
            <div class="info-card bg-white rounded-2xl p-6 shadow-sm border border-[#C9D3DD]/30">
                <h2 class="font-display text-xl font-bold text-[#3A4A5A] section-title flex items-center gap-2">
                    <svg class="w-6 h-6 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    Loại phòng
                </h2>
                <div class="space-y-3">
                    @foreach($hotel->rooms->unique('room_type_id') as $room)
                    <div class="room-item flex items-center justify-between p-4 rounded-xl border border-[#C9D3DD]/30 hover:border-[#87CEFA] transition">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-xl bg-[#EAF3FF] flex items-center justify-center">
                                <svg class="w-6 h-6 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                            <div>
                                <p class="font-semibold text-[#3A4A5A]">{{ $room->roomType->name }}</p>
                                <div class="flex items-center gap-3 mt-1">
                                    <span class="text-xs text-[#C9D3DD] flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                        </svg>
                                        {{ $room->capacity }} khách
                                    </span>
                                    <span class="text-xs text-[#C9D3DD] flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                                        </svg>
                                        {{ $room->roomType->bed_type }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-[#87CEFA] text-lg">{{ number_format($room->price) }}<span class="text-xs font-normal text-[#C9D3DD]">/đêm</span></p>
                            <button
                                onclick="bookRoom({{ $hotel->id }}, {{ $room->room_type_id }}, {{ $room->price }})"
                                class="btn-gold text-xs px-3 py-1.5 rounded-lg mt-1 inline-flex items-center gap-1 click-effect"
                            >
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                Đặt phòng
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

        </div>

        {{-- RIGHT: Booking sidebar --}}
        <div class="lg:col-span-1">
            <div class="sidebar-sticky bg-white rounded-2xl p-6 shadow-sm border border-[#C9D3DD]/30">
                <h3 class="font-display text-lg font-bold text-[#3A4A5A] mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    Đặt phòng nhanh
                </h3>

                <div id="booking-error" class="hidden bg-red-50 text-red-700 text-xs rounded-xl px-3 py-2 mb-3"></div>
                <div id="booking-success" class="hidden bg-green-50 text-green-700 text-xs rounded-xl px-3 py-2 mb-3"></div>

                <div class="space-y-4">
                    <div>
                        <label class="text-xs font-medium text-[#3A4A5A] block mb-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            Nhận phòng
                        </label>
                        <input type="date" id="check-in" class="w-full border border-[#C9D3DD] rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#87CEFA]">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-[#3A4A5A] block mb-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            Trả phòng
                        </label>
                        <input type="date" id="check-out" class="w-full border border-[#C9D3DD] rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#87CEFA]">
                    </div>
                    <div>
                        <label class="text-xs font-medium text-[#3A4A5A] block mb-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                            Số khách
                        </label>
                        <select id="guests" class="w-full border border-[#C9D3DD] rounded-xl px-3 py-2 text-sm focus:outline-none bg-white">
                            <option value="1">1 khách</option>
                            <option value="2" selected>2 khách</option>
                            <option value="3">3 khách</option>
                            <option value="4">4 khách</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-[#3A4A5A] block mb-1 flex items-center gap-1">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                            Loại phòng
                        </label>
                        <select id="room-select" class="w-full border border-[#C9D3DD] rounded-xl px-3 py-2 text-sm focus:outline-none bg-white">
                            @foreach($hotel->rooms->unique('room_type_id') as $room)
                            <option value="{{ $room->room_type_id }}" data-price="{{ $room->price }}">
                                {{ $room->roomType->name }} — {{ number_format($room->price) }}đ/đêm
                            </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Price estimate --}}
                    <div id="price-estimate" class="bg-[#EAF3FF] rounded-xl p-3 hidden">
                        <div class="flex justify-between text-sm">
                            <span class="text-[#3A4A5A]">Tổng tiền</span>
                            <span class="font-bold text-[#87CEFA]" id="total-price">0đ</span>
                        </div>
                    </div>

                    <button onclick="submitBooking({{ $hotel->id }})" class="btn-gold w-full py-3 rounded-xl text-sm font-semibold click-effect flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        Đặt phòng ngay
                    </button>
                </div>

                <button
                    onclick="toggleWishlistDetail({{ $hotel->id }}, this)"
                    class="w-full mt-3 py-2 rounded-xl text-sm border border-[#C9D3DD] text-[#3A4A5A] hover:border-red-300 hover:text-red-500 transition flex items-center justify-center gap-2"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                    </svg>
                    Lưu yêu thích
                </button>
            </div>
        </div>

    </div>
</div>

@endsection

{{-- MODAL XÁC NHẬN THANH TOÁN --}}
<div id="checkoutModal" class="fixed inset-0 bg-[#3A4A5A]/60 z-50 hidden items-center justify-center p-4 pt-16">
    <div class="bg-white rounded-2xl max-w-lg w-full max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">

        {{-- Header --}}
        <div class="sticky top-0 bg-white border-b border-[#C9D3DD]/30 p-4 flex justify-between items-center">
            <h3 class="font-display text-xl font-bold text-[#3A4A5A]">Xác nhận đặt phòng</h3>
            <button onclick="closeCheckout()" class="text-[#C9D3DD] hover:text-[#3A4A5A] text-2xl">&times;</button>
        </div>

        <div class="p-6 space-y-5">

            {{-- Thông tin booking --}}
            <div class="bg-[#EAF3FF] rounded-xl p-4 space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-[#C9D3DD]">Khách sạn</span>
                    <span class="font-semibold text-[#3A4A5A]" id="co-hotel"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#C9D3DD]">Loại phòng</span>
                    <span class="font-semibold text-[#3A4A5A]" id="co-room"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#C9D3DD]">Nhận phòng</span>
                    <span class="font-semibold text-[#3A4A5A]" id="co-checkin"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#C9D3DD]">Trả phòng</span>
                    <span class="font-semibold text-[#3A4A5A]" id="co-checkout"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#C9D3DD]">Số đêm</span>
                    <span class="font-semibold text-[#3A4A5A]" id="co-nights"></span>
                </div>
                <div class="border-t border-[#C9D3DD]/30 pt-2 flex justify-between">
                    <span class="font-bold text-[#3A4A5A]">Tổng tiền</span>
                    <span class="font-bold text-[#87CEFA] text-lg" id="co-total"></span>
                </div>
            </div>

            {{-- Chọn phương thức thanh toán --}}
            <div>
                <p class="text-sm font-semibold text-[#3A4A5A] mb-3">Chọn phương thức thanh toán</p>
                <div class="space-y-2">

                    {{-- VNPay --}}
                    <label class="flex items-center gap-3 p-3 border border-[#C9D3DD]/50 rounded-xl cursor-pointer hover:border-[#87CEFA] hover:bg-[#EAF3FF] transition has-[:checked]:border-[#87CEFA] has-[:checked]:bg-[#EAF3FF]">
                        <input type="radio" name="payment_method" value="vnpay" checked class="accent-[#87CEFA]">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">💳</span>
                            <div>
                                <p class="text-sm font-semibold text-[#3A4A5A]">VNPay / Thẻ ngân hàng</p>
                                <p class="text-xs text-[#C9D3DD]">ATM, Visa, Mastercard, QR VNPay</p>
                            </div>
                        </div>
                    </label>

                    {{-- Bank Transfer --}}
                    <label class="flex items-center gap-3 p-3 border border-[#C9D3DD]/50 rounded-xl cursor-pointer hover:border-[#87CEFA] hover:bg-[#EAF3FF] transition has-[:checked]:border-[#87CEFA] has-[:checked]:bg-[#EAF3FF]">
                        <input type="radio" name="payment_method" value="banking" class="accent-[#87CEFA]">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">🏦</span>
                            <div>
                                <p class="text-sm font-semibold text-[#3A4A5A]">Chuyển khoản ngân hàng</p>
                                <p class="text-xs text-[#C9D3DD]">Quét QR — số tiền tự động điền</p>
                            </div>
                        </div>
                    </label>

                    {{-- Cash --}}
                    <label class="flex items-center gap-3 p-3 border border-[#C9D3DD]/50 rounded-xl cursor-pointer hover:border-[#87CEFA] hover:bg-[#EAF3FF] transition has-[:checked]:border-[#87CEFA] has-[:checked]:bg-[#EAF3FF]">
                        <input type="radio" name="payment_method" value="cash" class="accent-[#87CEFA]">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">💵</span>
                            <div>
                                <p class="text-sm font-semibold text-[#3A4A5A]">Tiền mặt tại quầy</p>
                                <p class="text-xs text-[#C9D3DD]">Thanh toán khi check-in</p>
                            </div>
                        </div>
                    </label>

                </div>
            </div>

            {{-- QR VietQR — hiện khi chọn banking --}}
            <div id="qr-section" class="hidden bg-[#EAF3FF] rounded-xl p-4 text-center">
                <p class="text-sm font-semibold text-[#3A4A5A] mb-3">Quét QR để chuyển khoản</p>
                <img id="qr-image" src="" alt="QR Code" class="w-48 h-48 mx-auto rounded-xl border border-[#C9D3DD]/30">
                <div class="mt-3 space-y-1 text-xs text-[#3A4A5A]">
                    <p>Ngân hàng: <strong>Vietcombank</strong></p>
                    <p>STK: <strong>1033816978</strong></p>
                    <p>Chủ TK: <strong> NGUYỄN TRẦN QUỐC ANH</strong></p>
                    <p id="qr-amount" class="text-[#87CEFA] font-bold text-base mt-1"></p>
                    <p id="qr-content" class="text-[#C9D3DD]"></p>
                </div>
                <p class="text-xs text-amber-500 mt-2">* Ghi đúng nội dung để hệ thống tự xác nhận</p>
            </div>

            {{-- Cash info --}}
            <div id="cash-section" class="hidden bg-amber-50 rounded-xl p-4 text-sm text-amber-700 border border-amber-200">
                <p class="font-semibold mb-1">📋 Hướng dẫn thanh toán tiền mặt</p>
                <p>Booking của bạn sẽ được giữ chỗ. Vui lòng thanh toán tại quầy lễ tân khi check-in.</p>
                <p class="mt-1 text-xs text-amber-500">* Booking sẽ tự hủy nếu không check-in đúng giờ</p>
            </div>

            {{-- Error --}}
            <div id="co-error" class="hidden bg-red-50 text-red-700 text-xs rounded-xl px-3 py-2"></div>

            {{-- Nút xác nhận --}}
            <button onclick="confirmPayment()" id="co-confirm-btn"
                class="btn-gold w-full py-3 rounded-xl text-sm font-semibold click-effect flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Xác nhận thanh toán
            </button>

        </div>
    </div>
</div>

@push('scripts')
<script>
    let currentBookingId = null;
    let currentTotal     = 0;

    document.getElementById('check-in').addEventListener('change', calcPrice);
    document.getElementById('check-out').addEventListener('change', calcPrice);
    document.getElementById('room-select').addEventListener('change', calcPrice);

    const today = new Date().toISOString().split('T')[0];
    document.getElementById('check-in').min = today;
    document.getElementById('check-out').min = today;

    // Lắng nghe chọn phương thức thanh toán
    document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
        radio.addEventListener('change', function() {
            document.getElementById('qr-section').classList.add('hidden');
            document.getElementById('cash-section').classList.add('hidden');
            document.getElementById('co-confirm-btn').textContent = 'Xác nhận thanh toán';

            if (this.value === 'banking') {
                document.getElementById('qr-section').classList.remove('hidden');
                generateQR(currentTotal, currentBookingId);
                document.getElementById('co-confirm-btn').innerHTML = '✅ Đã chuyển khoản xong';

                //Tự scroll xuống phần QR khi chọn banking
                setTimeout(() => {
                    document.getElementById('qr-section').scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
            }
            if (this.value === 'cash') {
                document.getElementById('cash-section').classList.remove('hidden');
                document.getElementById('co-confirm-btn').innerHTML = '✅ Xác nhận — Thanh toán tại quầy';

                //tự scroll xuống phần cash info khi chọn cash
                setTimeout(() => {
                    document.getElementById('cash-section').scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
            }
            if (this.value === 'vnpay') {
                document.getElementById('co-confirm-btn').innerHTML = `
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg> Thanh toán qua VNPay`;
            }
        });
    });

    function generateQR(amount, bookingId) {
        // VietQR API — miễn phí, không cần key
        // Format: https://img.vietqr.io/image/{BANK}-{STK}-{TEMPLATE}.png?amount={AMOUNT}&addInfo={CONTENT}
        const bank    = 'VCB';        // Vietcombank — đổi theo ngân hàng của mày
        const stk     = '1033816978'; // Số tài khoản của mày
        const content = `BOOKING${bookingId}`;
        const qrUrl   = `https://img.vietqr.io/image/${bank}-${stk}-compact2.png?amount=${amount}&addInfo=${content}&accountName=HOTEL%20BOOKING`;

        document.getElementById('qr-image').src = qrUrl;
        document.getElementById('qr-amount').textContent  = new Intl.NumberFormat('vi-VN').format(amount) + 'đ';
        document.getElementById('qr-content').textContent = `Nội dung: ${content}`;
    }

    function calcPrice() {
        const checkIn    = document.getElementById('check-in').value;
        const checkOut   = document.getElementById('check-out').value;
        const roomSelect = document.getElementById('room-select');
        const price      = roomSelect.options[roomSelect.selectedIndex]?.dataset.price;

        if (checkIn && checkOut && price) {
            const nights = Math.ceil((new Date(checkOut) - new Date(checkIn)) / 86400000);
            if (nights > 0) {
                const total = nights * parseInt(price);
                currentTotal = total;
                document.getElementById('total-price').textContent = new Intl.NumberFormat('vi-VN').format(total) + 'đ';
                document.getElementById('price-estimate').classList.remove('hidden');
            }
        }
    }

    // [SỬA] submitBooking — tạo booking xong mở modal xác nhận thay vì redirect thẳng
    async function submitBooking(hotelId) {
        const token = localStorage.getItem('token');
        if (!token) { window.location.href = '/login'; return; }

        const checkIn  = document.getElementById('check-in').value;
        const checkOut = document.getElementById('check-out').value;
        const guests   = document.getElementById('guests').value;
        const roomId   = document.getElementById('room-select').value;
        const errDiv   = document.getElementById('booking-error');
        const succDiv  = document.getElementById('booking-success');
        const roomSelect = document.getElementById('room-select');
        const roomName   = roomSelect.options[roomSelect.selectedIndex]?.text;

        errDiv.classList.add('hidden');
        succDiv.classList.add('hidden');

        if (!checkIn || !checkOut) {
            errDiv.textContent = 'Vui lòng chọn ngày nhận và trả phòng!';
            errDiv.classList.remove('hidden');
            return;
        }
        if (new Date(checkOut) <= new Date(checkIn)) {
            errDiv.textContent = 'Ngày trả phòng phải sau ngày nhận phòng!';
            errDiv.classList.remove('hidden');
            return;
        }

        const res = await api('/bookings', {
            method: 'POST',
            body: JSON.stringify({
                hotel_id:     hotelId,
                room_type_id: parseInt(roomId),
                quantity:     1,
                check_in:     checkIn,
                check_out:    checkOut,
                num_guests:   parseInt(guests)
            })
        });

        const data = await res.json();

        if (res.ok) {
            currentBookingId = data.booking.id;

            const nights = Math.ceil((new Date(checkOut) - new Date(checkIn)) / 86400000);
            const price  = roomSelect.options[roomSelect.selectedIndex]?.dataset.price;
            currentTotal = nights * parseInt(price);

            // Điền thông tin vào modal
            document.getElementById('co-hotel').textContent   = document.querySelector('h1').textContent.trim();
            document.getElementById('co-room').textContent    = roomName?.split('—')[0]?.trim();
            document.getElementById('co-checkin').textContent = checkIn;
            document.getElementById('co-checkout').textContent= checkOut;
            document.getElementById('co-nights').textContent  = nights + ' đêm';
            document.getElementById('co-total').textContent   = new Intl.NumberFormat('vi-VN').format(currentTotal) + 'đ';

            // Reset về VNPay
            document.querySelector('input[value="vnpay"]').checked = true;
            document.getElementById('qr-section').classList.add('hidden');
            document.getElementById('cash-section').classList.add('hidden');

            // Mở modal xác nhận
            document.getElementById('checkoutModal').classList.remove('hidden');
            document.getElementById('checkoutModal').classList.add('flex');

        } else {
            errDiv.textContent = data.message || 'Đặt phòng thất bại!';
            errDiv.classList.remove('hidden');
        }
    }

    function closeCheckout() {
        document.getElementById('checkoutModal').classList.add('hidden');
        document.getElementById('checkoutModal').classList.remove('flex');
    }

    // Xác nhận thanh toán theo phương thức đã chọn
// Thay toàn bộ hàm confirmPayment() trong blade bằng đoạn này:

    async function confirmPayment() {
        const method = document.querySelector('input[name="payment_method"]:checked').value;
        const errDiv = document.getElementById('co-error');
        errDiv.classList.add('hidden');

        if (method === 'vnpay') {
            const payRes  = await api('/payments/create', {
                method: 'POST',
                body: JSON.stringify({ booking_id: currentBookingId })
            });
            const payData = await payRes.json();
            if (payData.payment_url) {
                window.location.href = payData.payment_url;
            } else {
                errDiv.textContent = payData.message || 'Tạo URL thanh toán thất bại!';
                errDiv.classList.remove('hidden');
            }

        } else if (method === 'banking') {
            // ✅ Gọi API tạo payment record + invoice + gửi email PDF
            const res  = await api('/payments/manual', {
                method: 'POST',
                body: JSON.stringify({
                    booking_id:     currentBookingId,
                    payment_method: 'banking'
                })
            });
            const data = await res.json();

            closeCheckout();

            if (res.ok) {
                document.getElementById('booking-success').textContent =
                    '✅ Đặt phòng thành công! Vui lòng chuyển khoản và chờ xác nhận từ khách sạn. Hóa đơn đã gửi vào email. Mã booking: #' + currentBookingId;
            } else {
                document.getElementById('booking-success').textContent =
                    '✅ Đặt phòng thành công! Mã booking: #' + currentBookingId + ' (Lỗi tạo hóa đơn: ' + data.message + ')';
            }
            document.getElementById('booking-success').classList.remove('hidden');

        } else if (method === 'cash') {
            // ✅ Tương tự cho cash
            const res  = await api('/payments/manual', {
                method: 'POST',
                body: JSON.stringify({
                    booking_id:     currentBookingId,
                    payment_method: 'cash'
                })
            });
            const data = await res.json();

            closeCheckout();

            if (res.ok) {
                document.getElementById('booking-success').textContent =
                    '✅ Đặt phòng thành công! Thanh toán tại quầy khi check-in. Hóa đơn đã gửi vào email. Mã booking: #' + currentBookingId;
            } else {
                document.getElementById('booking-success').textContent =
                    '✅ Đặt phòng thành công! Mã booking: #' + currentBookingId;
            }
            document.getElementById('booking-success').classList.remove('hidden');
        }
    }

    function bookRoom(hotelId, roomTypeId, price) {
        document.getElementById('room-select').value = roomTypeId;
        calcPrice();
        document.getElementById('check-in').scrollIntoView({ behavior: 'smooth', block: 'center' });
        document.getElementById('check-in').focus();
    }

    async function toggleWishlistDetail(hotelId, btn) {
        const token = localStorage.getItem('token');
        if (!token) { window.location.href = '/login'; return; }
        const res  = await api(`/wishlist/${hotelId}/toggle`, { method: 'POST' });
        const data = await res.json();
        btn.innerHTML = data.liked ?
            '<svg class="w-4 h-4" fill="currentColor" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg> Đã lưu yêu thích' :
            '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg> Lưu yêu thích';
        btn.style.color = data.liked ? '#ef4444' : '';
        showToast(data.message);
    }

    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeCheckout(); });
</script>
@endpush