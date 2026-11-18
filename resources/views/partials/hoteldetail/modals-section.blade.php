{{-- ================================================
     MODAL: Xác nhận đặt phòng & Thanh toán
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