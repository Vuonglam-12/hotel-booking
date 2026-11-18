@if(\App\Support\RoomTypeV2CheckoutGate::staging())
<dialog id="v2-checkout" aria-labelledby="v2-checkout-title" style="width:min(520px,calc(100% - 32px));border:1px solid #E5E7EB;border-radius:20px;padding:24px;color:#111827;background:white">
    <h2 id="v2-checkout-title" style="font-size:20px;font-weight:650">Xác nhận đặt phòng</h2>
    <p style="font-size:12px;color:#6B7280">Môi trường nghiệm thu, dữ liệu tổng hợp. Không chuyển tiền thật.</p>
    <div id="v2-checkout-summary" style="margin:16px 0"></div>
    <label style="display:block;margin-bottom:12px">Phương thức thanh toán
        <select id="v2-payment-method" style="display:block;width:100%;padding:12px;border:1px solid #E5E7EB;border-radius:12px">
            @if(config('booking-demo.enabled'))
            <option value="banking">QR DEMO — không chuyển tiền thật</option>
            @else
            <option value="banking">QR Banking — chờ Admin xác minh</option>
            <option value="vnpay">VNPay — cần merchant sandbox được xác minh</option>
            @endif
        </select>
    </label>
    <p id="v2-checkout-error" role="alert" style="color:#B91C1C"></p>
    <button type="button" id="v2-checkout-confirm" class="btn-primary" style="padding:12px;border-radius:12px">Tạo đặt phòng</button>
    <button type="button" id="v2-checkout-close" style="padding:12px">Quay lại</button>
</dialog>
@endif
