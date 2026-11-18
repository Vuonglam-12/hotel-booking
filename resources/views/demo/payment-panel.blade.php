<style>
.demo-pay{max-width:960px;margin:auto;color:#142B49}.demo-tag{display:inline-flex;padding:5px 10px;background:#FFFBEB;border:1px solid #FDE68A;color:#92400E;border-radius:7px;font-size:12px;font-weight:700}.demo-pay h1{font-size:28px;font-weight:700;margin:12px 0}.demo-pay p{line-height:1.7}.demo-layout{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:20px;margin:22px 0}.demo-card{padding:22px;border:1px solid #E2E8F0;border-radius:14px;background:white;min-width:0}.demo-card dl{display:grid;grid-template-columns:120px minmax(0,1fr);gap:12px;font-size:14px}.demo-card dt{color:#64748B}.demo-card dd{margin:0;overflow-wrap:anywhere}.demo-qr{display:block;width:200px;height:200px;margin:10px auto}.demo-money{font-size:26px;font-weight:750;color:#2563EB}.demo-controls{display:flex;gap:8px;flex-wrap:wrap;margin:18px 0}.demo-btn{min-height:40px;padding:10px 14px;border:1px solid #CBD5E1;border-radius:8px;background:#F1F5F9;color:#475569;font:inherit;font-size:13px;font-weight:600;cursor:pointer}.demo-btn--success{color:#15803D;background:#F0FDF4;border-color:#BBF7D0}.demo-btn--danger{color:#B91C1C;background:#FEF2F2;border-color:#FECACA}.demo-btn--warning{color:#92400E;background:#FFFBEB;border-color:#FDE68A}.demo-btn--primary{color:white;background:#2563EB;border-color:#2563EB}.demo-btn:focus-visible{outline:3px solid #2563EB;outline-offset:3px}.demo-btn:hover:not(:disabled){filter:brightness(.95)}.demo-btn:disabled{opacity:.5;cursor:not-allowed}.demo-feedback{padding:12px 14px;border:1px solid #BFDBFE;border-radius:9px;background:#EFF6FF;font-size:14px}.demo-feedback[data-error=true]{background:#FEF2F2;border-color:#FECACA;color:#B91C1C}.demo-events{overflow:auto}.demo-events table{border-collapse:collapse;width:100%;font-size:12px}.demo-events td,.demo-events th{padding:10px;text-align:left;border-bottom:1px solid #E2E8F0}.demo-pay [hidden]{display:none!important}@media(max-width:700px){.demo-layout{grid-template-columns:1fr}.demo-card{padding:16px}.demo-card dl{grid-template-columns:100px minmax(0,1fr)}.demo-pay h1{font-size:24px}}
</style>
<style>
.demo-feedback { border-left:4px solid #2563EB; font-weight:600; }
.demo-feedback::before { content:'i'; display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; margin-right:8px; border-radius:50%; background:#2563EB; color:white; font-size:13px; font-weight:700; }
.demo-feedback[data-tone="success"] { color:#166534; background:#F0FDF4; border-color:#86EFAC; border-left-color:#16A34A; }
.demo-feedback[data-tone="success"]::before { content:'✓'; background:#16A34A; }
.demo-feedback[data-tone="warning"] { color:#92400E; background:#FFFBEB; border-color:#FCD34D; border-left-color:#D97706; }
.demo-feedback[data-tone="warning"]::before { content:'!'; background:#D97706; }
.demo-feedback[data-tone="danger"] { color:#B91C1C; background:#FEF2F2; border-color:#FCA5A5; border-left-color:#DC2626; }
.demo-feedback[data-tone="danger"]::before { content:'!'; background:#DC2626; }
.demo-state { display:inline-flex; padding:4px 9px; border:1px solid #CBD5E1; border-radius:7px; background:#F1F5F9; color:#475569; font-size:12px; font-weight:600; }
.demo-state[data-tone="success"] { background:#DCFCE7; color:#166534; border-color:#86EFAC; }
.demo-state[data-tone="warning"] { background:#FFFBEB; color:#92400E; border-color:#FCD34D; }
.demo-state[data-tone="danger"] { background:#FEF2F2; color:#B91C1C; border-color:#FCA5A5; }
.demo-events td:first-child { overflow-wrap:anywhere; }
</style>
@php($localPayment = $localPayment ?? false)
<section class="demo-pay" data-demo-payment="{{ $bookingId }}">
    <h1>Thanh toán đặt phòng #{{ $bookingId }}</h1>
    <p>@if($adminMode)Gửi kết quả xử lý cho lượt thanh toán hiện tại. Hệ thống sẽ kiểm tra và cập nhật đơn, giao dịch và bản xác nhận.@else Kiểm tra thông tin đặt phòng và theo dõi kết quả xác nhận. Trạng thái được cập nhật tự động.@endif</p>
    <p id="demo-feedback" class="demo-feedback" role="status" aria-live="polite">Đang tải thông tin…</p>
    <div class="demo-layout" id="demo-details" hidden>
        <div class="demo-card"><dl id="demo-summary"></dl><details id="demo-nightly" class="demo-events" hidden><summary>Giá từng đêm đã lưu trong đơn</summary><table><thead><tr><th>Đêm lưu trú</th><th>Giá/phòng</th><th>Số phòng</th></tr></thead><tbody id="demo-nightly-rows"></tbody></table></details><div class="demo-controls">@if($adminMode)<button type="button" class="demo-btn" id="demo-refresh">Cập nhật</button>@else<button type="button" class="demo-btn demo-btn--primary" id="demo-invoice" hidden>Tải hóa đơn</button>@endif</div><p id="demo-delivery"></p></div>
        <div class="demo-card" style="text-align:center">
            @if($localPayment)
                <img class="demo-qr" src="{{ asset('image/payments/aurel-transfer-qr.png') }}" width="200" height="200" alt="QR chuyển khoản Vietcombank">
                <p style="font-size:12px;overflow-wrap:anywhere"><strong>Vietcombank</strong><br>NGUYEN TRAN QUOC ANH<br>1033816978</p>
            @else
                <svg class="demo-qr" viewBox="0 0 25 25" role="img" aria-label="Mã tham chiếu đặt phòng Angel"><rect width="25" height="25" fill="white"/><g id="demo-qr-cells" fill="#142B49"></g><rect x="4" y="10" width="17" height="5" fill="white"/><text x="12.5" y="13.5" text-anchor="middle" font-size="3" font-family="sans-serif" font-weight="bold" fill="#142B49">Angel</text></svg>
            @endif
            <p style="font-size:13px;color:#64748B">Mã thanh toán</p><p id="demo-transaction" style="overflow-wrap:anywhere;font-size:12px"></p><strong class="demo-money" id="demo-amount"></strong><p id="demo-deadline"></p>
        </div>
    </div>
    <div class="demo-controls" aria-label="Thao tác thanh toán">@if($adminMode || !$localPayment)<button type="button" class="demo-btn demo-btn--success" data-demo-action="success" disabled>Thông báo thành công</button><button type="button" class="demo-btn demo-btn--danger" data-demo-action="failed" disabled>Thông báo thất bại</button><button type="button" class="demo-btn" data-demo-action="cancelled" disabled>Hủy thanh toán</button><button type="button" class="demo-btn demo-btn--warning" data-demo-action="expired" disabled>Kết thúc thời hạn</button>@endif @unless($adminMode)<button type="button" class="demo-btn demo-btn--primary" id="demo-retry" hidden>Thử thanh toán lại</button>@endunless</div>
    <div class="demo-card demo-events"><h2 style="font-size:18px;font-weight:650">Lịch sử thanh toán</h2><table><thead><tr><th>Mã giao dịch</th><th>Kết quả</th><th>Số tiền</th></tr></thead><tbody id="demo-history"></tbody></table></div>
</section>
