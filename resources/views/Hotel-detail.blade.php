@extends('layout')

@section('title', $hotel->name)

@section('content')

{{-- Đảm bảo nạp Swiper CSS và JS ở đầu để các component khác có thể sử dụng nếu cần --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    {{-- 1. Styles (Đã sửa lại tên file cho khớp với globals-styles.blade.php) --}}
    @include('partials.hoteldetail.globals-styles')

    {{-- 2. Hero Section --}}
    @include('partials.hoteldetail.hero-section')

    {{-- 3. Gallery Section --}}
    @include('partials.hoteldetail.gallery-section')


    <div class="max-w-7xl mx-auto px-4 py-10">
        {{-- Breadcrumb có thể để ở đây nếu mày không tách riêng --}}
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- CỘT TRÁI: Thông tin khách sạn --}}
            <div class="lg:col-span-2 space-y-6">
                @include('partials.hoteldetail.info-section')
                @include('partials.hoteldetail.amenities-section')
                @include('partials.hoteldetail.rooms-section')
            </div>

            {{-- CỘT PHẢI: Sidebar đặt phòng --}}
            <div class="lg:col-span-1">
                @include('partials.hoteldetail.sidebar-section')
            </div>
        </div>
    </div>

    {{-- 4. Các thành phần ẩn --}}
    @include('partials.hoteldetail.modals-section')
@endsection

@push('scripts')
    {{-- THỨ TỰ SCRIPT: Biến toàn cục nạp trước, logic nạp sau --}}
    @include('partials.hoteldetail.globals-script')
    @include('partials.hoteldetail.gallery-script')
    @include('partials.hoteldetail.rooms-script')
    @include('partials.hoteldetail.sidebar-script')
    @include('partials.hoteldetail.modals-script')
@endpush