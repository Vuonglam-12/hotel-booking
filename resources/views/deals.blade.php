@extends('layout')

@section('title', 'Ưu đãi & Khuyến mãi')

@section('content')

    {{-- 1. Hero Banner --}}
    @include('partials.deals.hero-section')

    {{-- Main Content Wrapper --}}
    <div class="max-w-7xl mx-auto px-4 py-10 space-y-14">
        
        {{-- 2. Flash Sale --}}
        @include('partials.deals.flashsale-section')

        {{-- 3. Outstanding Offer (Ưu đãi nổi bật) --}}
        @include('partials.deals.outstandingoffer-section')

        {{-- 4. Vouchers --}}
        @include('partials.deals.vouchers-section')

        {{-- 5. Destinations --}}
        @include('partials.deals.destinations-section')

        {{-- 6. Combo --}}
        @include('partials.deals.combo-section')

    </div>

    {{-- Modal dùng chung (Đã chuyển sang thư mục hoteldetail như thống nhất) --}}
    @include('partials.hoteldetail.modals-section')

@endsection

@push('scripts')
    {{-- Gọi tất cả các file script (chứa CSS & JS) của từng section --}}
    @include('partials.deals.hero-script')
    @include('partials.deals.fashsale-script')
    @include('partials.deals.outstandingoffer-scrpit')
    @include('partials.deals.vouchers-script')
    @include('partials.deals.destinations-script')
    @include('partials.deals.combo-script')

    @include('partials.hoteldetail.modals-script')

    {{-- Script dùng chung toàn trang (Modal) --}}
    <script>
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

        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
    </script>
@endpush