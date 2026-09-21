
<div class="sidebar-sticky bg-white rounded-2xl p-6 shadow-sm border border-[#C9D3DD]/30">
    <h3 class="font-display text-lg font-bold text-[#3A4A5A] mb-4 flex items-center gap-2">
        <svg class="w-5 h-5 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
        Đặt phòng nhanh
    </h3>

    <div id="booking-error" class="hidden bg-red-50 text-red-700 text-xs rounded-xl px-3 py-2 mb-3"></div>
    <div id="booking-success" class="hidden bg-green-50 text-green-700 text-xs rounded-xl px-3 py-2 mb-3"></div>

    <div class="space-y-3">
        <div class="grid grid-cols-2 gap-2">
            <div>
                <label class="text-xs font-medium text-[#3A4A5A] block mb-1">Nhận phòng</label>
                <input type="date" id="check-in" class="w-full border border-[#C9D3DD] rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#87CEFA]">
            </div>
            <div>
                <label class="text-xs font-medium text-[#3A4A5A] block mb-1">Trả phòng</label>
                <input type="date" id="check-out" class="w-full border border-[#C9D3DD] rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#87CEFA]">
            </div>
        </div>

        <div>
            <label class="text-xs font-medium text-[#3A4A5A] block mb-1">Loại phòng</label>
            <div id="room-type-display" class="w-full border border-[#C9D3DD] rounded-xl px-3 py-2 text-sm bg-[#EAF3FF] text-[#3A4A5A] min-h-[38px] flex items-center">
                <span id="room-type-display-text" class="text-[#C9D3DD] italic">Chọn phòng bên trái</span>
            </div>
            <input type="hidden" id="room-select" value="">
            <input type="hidden" id="room-select-price" value="">
            <input type="hidden" id="room-select-name" value="">
            <input type="hidden" id="selected-room-id" value="">
        </div>

        <div>
            <label class="text-xs font-medium text-[#3A4A5A] block mb-1">Số khách</label>
            <input type="number" id="guests" class="w-full border border-[#C9D3DD] rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#87CEFA]" value="1" min="1" max="5">
        </div>

        <div>
            <label class="text-xs font-medium text-[#3A4A5A] block mb-1">Mã giảm giá</label>
            <div class="flex gap-2">
                <input type="text" id="voucher-input" placeholder="Nhập mã..." class="flex-1 border border-[#C9D3DD] rounded-xl px-3 py-2 text-sm uppercase">
                <button onclick="applyVoucher()" class="px-3 py-2 rounded-xl border border-[#87CEFA] text-[#87CEFA] text-xs font-semibold">Áp dụng</button>
            </div>
            <div id="voucher-status" class="hidden mt-2 text-xs rounded-lg px-3 py-2"></div>
        </div>

        <div id="price-estimate" class="bg-[#EAF3FF] rounded-xl p-3 hidden space-y-1.5 text-sm">
            <div class="flex justify-between text-[#3A4A5A]/70">
                <span id="price-label-nights">Giá phòng</span>
                <span id="price-subtotal">—</span>
            </div>
            <div id="voucher-discount-row" class="hidden flex justify-between text-green-600">
                <span id="voucher-discount-label">Giảm giá</span>
                <span id="voucher-discount-amount"></span>
            </div>
            <div class="flex justify-between font-bold text-[#3A4A5A] pt-1 border-t border-[#C9D3DD]/40">
                <span>Tổng tiền</span>
                <span class="text-[#87CEFA]" id="total-price">0đ</span>
            </div>
        </div>

        <button id="submit-booking-btn" onclick="openBookingPreview({{ $hotel->id }})" class="btn-gold w-full py-3 rounded-xl text-sm font-semibold click-effect">
            Đặt phòng ngay
        </button>
    </div>

    <button onclick="toggleWishlistDetail({{ $hotel->id }}, this)" class="w-full mt-3 py-2 rounded-xl text-sm border border-[#C9D3DD] text-[#3A4A5A] flex items-center justify-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
        Lưu yêu thích
    </button>
</div>