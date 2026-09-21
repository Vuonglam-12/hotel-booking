{{-- ================================================
     MODAL 1: Chọn phòng cụ thể theo tầng
     ================================================ --}}
<div id="roomModal" class="fixed inset-0 bg-[#3A4A5A]/60 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">

        {{-- Header sticky --}}
        <div class="sticky top-0 bg-white border-b p-4 flex justify-between items-start">
            <div>
                <h3 class="font-bold text-[#3A4A5A]" id="modal-room-type-name">Chọn phòng</h3>
                <p class="text-xs text-[#C9D3DD] mt-0.5" id="modal-room-checkin-info"></p>
            </div>
            <button onclick="closeRoomModal()" class="text-2xl text-[#C9D3DD] hover:text-[#3A4A5A]">&times;</button>
        </div>

        {{-- Stats --}}
        <div class="flex gap-4 px-6 pt-4 text-xs text-center">
            <div class="flex-1 bg-green-50 rounded-xl py-2">
                <p class="font-bold text-green-700 text-base" id="modal-stat-avail">0</p>
                <p class="text-green-600">Còn trống</p>
            </div>
            <div class="flex-1 bg-amber-50 rounded-xl py-2">
                <p class="font-bold text-amber-600 text-base" id="modal-stat-locked">0</p>
                <p class="text-amber-500">Đang giữ</p>
            </div>
            <div class="flex-1 bg-[#EAF3FF] rounded-xl py-2">
                <p class="font-bold text-[#3A4A5A] text-base" id="modal-stat-booked">0</p>
                <p class="text-[#3A4A5A]/60">Đã đặt</p>
            </div>
        </div>

        {{-- Chú thích màu --}}
        <div class="flex gap-4 px-6 pt-3 pb-1 text-xs text-[#3A4A5A]/60">
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-green-200 inline-block"></span> Trống</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-amber-200 inline-block"></span> Đang giữ</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-[#C9D3DD]/40 inline-block"></span> Đã đặt</span>
            <span class="flex items-center gap-1"><span class="w-3 h-3 rounded border-2 border-[#87CEFA] bg-[#EAF3FF] inline-block"></span> Đang chọn</span>
        </div>

        {{-- Danh sách phòng theo tầng --}}
        <div class="p-6">
            <div id="modal-floors-container" class="space-y-4"></div>

            {{-- Panel phòng đã chọn --}}
            <div id="modal-selected-panel" class="hidden mt-4 p-4 bg-[#EAF3FF] rounded-xl border border-[#87CEFA]/30">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="font-semibold text-[#3A4A5A]" id="modal-sel-name">—</p>
                        <p class="text-xs text-[#3A4A5A]/60 mt-0.5" id="modal-sel-meta"></p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-[#87CEFA]" id="modal-sel-price">—</p>
                        <p class="text-xs text-[#3A4A5A]/60" id="modal-sel-total"></p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Footer sticky --}}
        <div class="sticky bottom-0 bg-white border-t p-4 flex justify-end gap-3">
            <button onclick="closeRoomModal()" class="px-4 py-2 border border-[#C9D3DD] rounded-xl text-sm text-[#3A4A5A]">Huỷ</button>
            <button id="modal-confirm-btn"
                class="btn-gold px-6 py-2 rounded-xl text-sm opacity-40 cursor-not-allowed"
                disabled
                onclick="confirmRoomSelection()">
                Xác nhận chọn phòng
            </button>
        </div>
    </div>
</div>


{{-- ================================================
     MODAL 2: Xác nhận đặt phòng & Thanh toán
     ================================================ --}}
<div id="checkoutModal" class="fixed inset-0 bg-[#3A4A5A]/60 z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full relative overflow-hidden">

        {{-- Overlay loading / success --}}
        <div id="payment-overlay" style="display:none;"
            class="absolute inset-0 bg-white/97 flex flex-col items-center justify-center rounded-2xl z-10">
            <div id="overlay-loading" class="flex flex-col items-center gap-3">
                <svg class="w-10 h-10 animate-spin text-[#87CEFA]" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                </svg>
                <p class="text-sm text-[#3A4A5A]">Đang xử lý...</p>
            </div>
            <div id="overlay-success" style="display:none;" class="flex flex-col items-center gap-3 animate-success">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-[#3A4A5A] text-center px-4" id="overlay-success-msg">Đặt phòng thành công!</p>
            </div>
        </div>

        {{-- Nội dung modal --}}
        <div class="p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="font-bold text-xl text-[#3A4A5A]">Xác nhận đặt phòng</h3>
                <button onclick="closeCheckout()" class="text-2xl text-[#C9D3DD] hover:text-[#3A4A5A]">&times;</button>
            </div>

            {{-- Error --}}
            <div id="co-error" class="hidden bg-red-50 text-red-600 text-xs rounded-xl px-3 py-2 mb-3"></div>

            {{-- Chi tiết booking --}}
            <div id="co-details" class="bg-[#EAF3FF] p-4 rounded-xl text-sm mb-4 space-y-1.5">
                <div class="flex justify-between">
                    <span class="text-[#3A4A5A]/60">Khách sạn</span>
                    <span class="font-semibold text-[#3A4A5A]" id="co-hotel"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#3A4A5A]/60">Loại phòng</span>
                    <span class="font-semibold text-[#3A4A5A]" id="co-room"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#3A4A5A]/60">Nhận phòng</span>
                    <span class="font-semibold text-[#3A4A5A]" id="co-checkin"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#3A4A5A]/60">Trả phòng</span>
                    <span class="font-semibold text-[#3A4A5A]" id="co-checkout"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#3A4A5A]/60">Số đêm</span>
                    <span class="font-semibold text-[#3A4A5A]" id="co-nights"></span>
                </div>
                <div class="flex justify-between pt-1.5 border-t border-[#C9D3DD]/40">
                    <span class="font-bold text-[#3A4A5A]">Tổng tiền</span>
                    <span class="font-bold text-[#87CEFA]" id="co-total"></span>
                </div>
            </div>

            {{-- Chọn phương thức thanh toán --}}
            <div class="space-y-2 mb-4">
                <label class="flex items-center gap-3 border border-[#C9D3DD] p-3 rounded-xl cursor-pointer hover:border-[#87CEFA] transition">
                    <input type="radio" name="payment_method" value="cash" checked class="accent-[#87CEFA]">
                    <span class="text-sm text-[#3A4A5A]">🏨 Thanh toán tại quầy</span>
                </label>
                <label class="flex items-center gap-3 border border-[#C9D3DD] p-3 rounded-xl cursor-pointer hover:border-[#87CEFA] transition">
                    <input type="radio" name="payment_method" value="banking" class="accent-[#87CEFA]">
                    <span class="text-sm text-[#3A4A5A]">🏦 Chuyển khoản QR</span>
                </label>
            </div>

            {{-- Section cash --}}
            <div id="cash-section" class="text-center text-sm text-[#3A4A5A]/60 py-2 mb-2">
                Bạn sẽ thanh toán trực tiếp tại quầy lễ tân khi nhận phòng.
            </div>

            {{-- Section QR --}}
            <div id="qr-section" class="hidden text-center pb-2 mb-2">
                <img id="qr-image" src="" alt="QR Code" class="w-48 h-48 mx-auto rounded-xl border border-[#C9D3DD]">
                <p class="text-sm font-semibold text-[#3A4A5A] mt-2" id="qr-amount"></p>
                <p class="text-xs text-[#3A4A5A]/60 mt-1" id="qr-content"></p>
                <div class="mt-2 flex items-center justify-center gap-2 text-xs text-amber-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>

            {{-- Section QR expired --}}
            <div id="qr-expired-section" class="hidden text-center py-3 mb-2 bg-red-50 rounded-xl">
                <p class="text-sm text-red-600 font-semibold"> QR đã hết hạn</p>
                <p class="text-xs text-red-400 mt-1">Vui lòng đóng và thực hiện lại</p>
            </div>

            {{-- Nút xác nhận --}}
            <button onclick="confirmPayment()" id="co-confirm-btn"
                class="btn-gold w-full py-3 rounded-xl text-sm font-semibold click-effect">
                <span>🏨</span> Xác nhận — Thanh toán tại quầy
            </button>
        </div>
    </div>
</div>