
@include('partials.hoteldetail.price-breakdown-styles')
<style>
    /* Hotel Detail date range picker: scoped to avoid changing the Header search picker. */
    .hv-booking-panel .hv-stay-date-fields { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; }
    .hv-booking-panel .hv-stay-date-fields > div { min-width:0; }
    .hv-booking-panel .hv-stay-field-label { display:block; font-size:13px; font-weight:500; margin-bottom:8px; color:#142B49; }
    .hv-booking-panel .hv-stay-date-trigger { width:100%; min-height:50px; display:flex; justify-content:space-between; align-items:center; gap:8px; padding:10px 12px; border:1px solid #E5E7EB; border-radius:12px; background:#FFFFFF; color:#142B49; font:inherit; font-size:13px; cursor:pointer; text-align:left; transition:border-color .18s,box-shadow .18s; }
    .hv-booking-panel .hv-stay-date-trigger svg { flex:0 0 auto; color:#607086; }
    .hv-booking-panel .hv-stay-date-trigger:hover, .hv-booking-panel .hv-stay-date-trigger[aria-expanded="true"] { border-color:#2563EB; box-shadow:0 0 0 3px rgba(37,99,235,.09); }
    .hv-booking-panel .hv-stay-date-trigger:focus-visible { outline:3px solid #93C5FD; outline-offset:2px; }
    .hv-booking-panel .hv-stay-native { position:absolute; width:1px; height:1px; opacity:0; pointer-events:none; overflow:hidden; clip-path:inset(50%); }
    .hv-booking-panel .hv-field-feedback{margin:7px 0 0;font-size:12px;line-height:1.6;color:#9a3412;overflow-wrap:anywhere}
    .hv-booking-panel [aria-invalid="true"]:not(.hv-stay-native){border-color:#d97706;box-shadow:0 0 0 1px #fef3c7}
    .hv-booking-panel .hv-booking-checklist{margin:10px 0;padding:12px 14px;border:1px solid #fde68a;border-radius:10px;background:#fffbeb;color:#854d0e;font-size:12px;line-height:1.6;overflow-wrap:anywhere}
    .hv-booking-panel .hv-booking-checklist::before{content:none}
    .hv-booking-checklist p{margin:0 0 5px;font-weight:600}.hv-booking-checklist ul{margin:0;padding-left:18px}
    .hv-booking-checklist li+li{margin-top:5px}.hv-booking-checklist a{color:inherit;text-decoration:underline;text-underline-offset:3px}
    .hv-booking-checklist a:focus-visible{outline:2px solid #2563eb;outline-offset:3px}
    .hv-booking-panel .hv-booking-next-step{margin:9px 0 0;font-size:12px;line-height:1.65;color:#607086;overflow-wrap:anywhere}
    .hv-booking-panel .hv-booking-status{overflow-wrap:anywhere}
    .hv-booking-panel [hidden]{display:none!important}
    .hv-stay-overlay[hidden] { display:none !important; }
    .hv-stay-overlay { position:fixed; inset:0; z-index:25000; display:flex; align-items:center; justify-content:center; padding:20px; background:rgba(15,23,42,.45); }
    .hv-stay-dialog { width:min(850px,100%); max-height:min(90vh,850px); overflow-y:auto; background:#FFF; border-radius:24px; box-shadow:0 24px 70px rgba(15,23,42,.22); padding:26px 30px 24px; color:#142B49; font-family:'Be Vietnam Pro',system-ui,sans-serif; box-sizing:border-box; }
    .hv-stay-dialog button { font:inherit; cursor:pointer; }
    .hv-stay-heading { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
    .hv-stay-heading h2 { font-size:20px; font-weight:650; margin:0; }
    .hv-stay-heading p { font-size:13px; color:#64748B; margin:8px 0 0; }
    .hv-stay-close { width:36px; height:36px; border:0; border-radius:50%; background:#F8F8F8; color:#142B49; font-size:25px !important; line-height:1; }
    .hv-stay-nav { display:flex; justify-content:space-between; margin:14px 0 -42px; position:relative; z-index:1; pointer-events:none; }
    .hv-stay-arrow { border:0; background:#FFF; color:#142B49; font-size:28px !important; width:36px; height:36px; border-radius:50%; pointer-events:auto; }
    .hv-stay-arrow:hover:not(:disabled), .hv-stay-close:hover { background:#EFF5FF; }
    .hv-stay-arrow:disabled { opacity:.3; cursor:not-allowed; }
    .hv-stay-months { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); column-gap:30px; margin:16px 0 20px; }
    .hv-stay-month h3 { margin:0 0 22px; text-align:center; font-size:16px; font-weight:600; }
    .hv-stay-days { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); text-align:center; gap:4px 0; }
    .hv-stay-dow { font-size:12px; color:#64748B; padding:9px 0; }
    .hv-stay-blank { height:40px; }
    .hv-stay-day { position:relative; border:0; height:40px; width:100%; background:transparent; border-radius:8px; color:#142B49; font-size:13px !important; }
    .hv-stay-day:disabled { color:#BDC5D1; cursor:not-allowed; }
    .hv-stay-day:hover:not(:disabled) { background:#EFF5FF; }
    .hv-stay-day.in-range { background:#EAF1FF; border-radius:0; }
    .hv-stay-day.selected { background:#2563EB !important; color:#FFF !important; border-radius:9px; font-weight:700; }
    .hv-stay-day:focus-visible, .hv-stay-dialog button:focus-visible { outline:2px solid #2563EB; outline-offset:2px; }
    .hv-stay-summary { display:grid; grid-template-columns:1fr 1fr; border:1px solid #E5E7EB; border-radius:14px; overflow:hidden; }
    .hv-stay-summary button { text-align:left; padding:12px 16px; border:0; background:#FFF; color:#142B49; }
    .hv-stay-summary button+button { border-left:1px solid #E5E7EB; }
    .hv-stay-summary button.active { background:#EFF5FF; }
    .hv-stay-summary span { display:block; font-size:11px; color:#64748B; }
    .hv-stay-summary strong { display:block; font-size:14px; font-weight:600; margin-top:3px; }
    .hv-stay-actions { display:flex; align-items:center; justify-content:space-between; margin-top:16px; gap:12px; }
    .hv-stay-clear { background:transparent; border:0; color:#64748B; padding:10px 12px; }
    .hv-stay-done { background:#2563EB; color:#FFF; border:0; padding:11px 24px; border-radius:12px; font-weight:600; }
    .hv-stay-error { margin:12px 0 0; color:#C2410C; font-size:12px; }
    @media (max-width:640px) { .hv-stay-overlay { padding:8px; align-items:flex-end; } .hv-stay-dialog { padding:20px 16px; border-radius:20px 20px 10px 10px; max-height:90dvh; } .hv-stay-months { grid-template-columns:1fr; gap:24px; } .hv-stay-nav { margin-bottom:-42px; } .hv-stay-month h3 { font-size:15px; } }
    @media (prefers-reduced-motion:reduce) { .hv-booking-panel .hv-stay-date-trigger { transition:none; } }


    /* Angel — Guest Picker */

    .hv-guest-trigger {
        width: 100%;
        min-height: 52px;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border: 1px solid #E5E7EB;
        border-radius: 12px;
        background: #FFFFFF;
        color: #142B49;
        font: inherit;
        cursor: pointer;
        text-align: left;
    }

    .hv-guest-trigger:hover,
    .hv-guest-trigger[aria-expanded="true"] {
        border-color: #2563EB;
    }

    .hv-guest-overlay[hidden] { display: none !important; }
    .hv-guest-overlay {
        position: fixed;
        z-index: 26000;
        width: min(360px, calc(100vw - 16px));
        box-sizing: border-box;
    }
    .hv-guest-dialog {
        width: 100%;
        max-height: inherit;
        overflow-y: auto;
        box-sizing: border-box;
        padding: 12px;
        border: 1px solid #E5E7EB;
        border-radius: 16px;
        background: #FFFFFF;
        color: #142B49;
        box-shadow: 0 12px 32px rgba(15, 23, 42, 0.14);
        font-family: 'Be Vietnam Pro', system-ui, sans-serif;
    }

    .hv-guest-header {
        display: flex;
        align-items: center;
        margin-bottom: 4px;
    }

    .hv-guest-header h2 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
    }

    .hv-guest-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 8px 0;
        border-bottom: 1px solid #E5E7EB;
    }

    .hv-guest-row strong {
        font-size: 14px;
        font-weight: 600;
    }

    .hv-guest-controls {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .hv-guest-controls button {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border: 1px solid #E2E8F0;
        border-radius: 50%;
        background: #F8FAFC;
        color: #142B49;
        font-size: 20px;
        cursor: pointer;
    }

    .hv-guest-controls button:hover:not(:disabled) {
        border-color: #2563EB;
        background: #EFF6FF;
    }

    .hv-guest-controls button:disabled {
        opacity: 0.35;
        cursor: not-allowed;
    }

    .hv-guest-controls output {
        min-width: 20px;
        text-align: center;
        font-size: 14px;
    }

    .hv-guest-note {
        margin: 8px 0;
        font-size: 12px;
        line-height: 1.5;
        color: #64748B;
    }

    .hv-guest-footer {
        display: flex;
        justify-content: flex-end;
    }

    #hv-guest-done {
        border: none;
        border-radius: 10px;
        padding: 9px 16px;
        background: #2563EB;
        color: #FFFFFF;
        font: inherit;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
    }

    .hv-guest-dialog button:focus-visible,
    .hv-guest-trigger:focus-visible {
        outline: 2px solid #2563EB;
        outline-offset: 3px;
    }

</style>
<section class="hv-booking-panel" id="hotel-booking-panel" aria-labelledby="booking-panel-heading">
    <h2 id="booking-panel-heading">Lựa chọn lưu trú</h2>
    <p class="hv-booking-intro">Chọn ngày và loại phòng phù hợp với chuyến đi.</p>
    <div class="hv-booking-fields">
        <div class="hv-booking-dates hv-stay-date-fields">
            <div>
                <span class="hv-stay-field-label">Nhận phòng</span>
                <button type="button" id="hv-stay-checkin-trigger" class="hv-stay-date-trigger" aria-label="Chọn ngày nhận phòng" aria-haspopup="dialog" aria-controls="hv-stay-date-dialog" aria-expanded="false" aria-describedby="booking-checkin-feedback">
                    <span id="hv-stay-checkin-label">Chọn ngày</span>
                    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/></svg>
                </button>
                <p id="booking-checkin-feedback" class="hv-field-feedback" hidden></p>
            </div>
            <div>
                <span class="hv-stay-field-label">Trả phòng</span>
                <button type="button" id="hv-stay-checkout-trigger" class="hv-stay-date-trigger" aria-label="Chọn ngày trả phòng" aria-haspopup="dialog" aria-controls="hv-stay-date-dialog" aria-expanded="false" aria-describedby="booking-checkout-feedback">
                    <span id="hv-stay-checkout-label">Chọn ngày</span>
                    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/></svg>
                </button>
                <p id="booking-checkout-feedback" class="hv-field-feedback" hidden></p>
            </div>
            {{-- Native values are retained for booking availability, prices and existing JS listeners. --}}
            <input type="date" id="check-in" class="hv-stay-native" min="{{ \Carbon\CarbonImmutable::today('Asia/Ho_Chi_Minh')->toDateString() }}" value="{{ request('check_in', '') }}" tabindex="-1" aria-label="Giá trị ngày nhận phòng">
            <input type="date" id="check-out" class="hv-stay-native" min="{{ \Carbon\CarbonImmutable::today('Asia/Ho_Chi_Minh')->toDateString() }}" value="{{ request('check_out', '') }}" tabindex="-1" aria-label="Giá trị ngày trả phòng">
        </div>

            <div class="hv-booking-guest-field">
                <label for="hv-guest-trigger">Tổng số khách</label>

                <button
                    type="button"
                    id="hv-guest-trigger"
                    class="hv-guest-trigger"
                    aria-haspopup="dialog"
                    aria-controls="hv-guest-dialog"
                    aria-expanded="false"
                    aria-describedby="booking-guests-feedback"
                >
                    <span id="hv-guest-summary">{{ request('guests', 2) }} khách</span>
                    <span aria-hidden="true">⌄</span>
                </button>

                <!-- Giữ lại giá trị cho logic Booking hiện tại -->
                <input
                    type="number"
                    id="guests"
                    class="hv-stay-native"
                    min="1"
                    step="1"
                    required
                    value="{{ request('guests', 2) }}"
                    tabindex="-1"
                    aria-label="Tổng số khách"
                >

                <p id="booking-guests-feedback" class="hv-field-feedback" hidden></p>

                <p class="hv-booking-help">
                    Chính sách trẻ em cần được khách sạn xác nhận.
                </p>
            </div>

        <div><span class="hv-booking-label">Phòng</span><div id="room-type-display" tabindex="-1" aria-describedby="booking-room-feedback"><span id="room-type-display-text">Chưa chọn phòng</span></div><a id="booking-choose-room" class="hv-booking-change" href="#hotel-room-types">Chọn loại phòng</a><a id="change-room-selection" class="hv-booking-change" href="#hotel-room-types" hidden>Đổi loại phòng</a><p id="booking-room-feedback" class="hv-field-feedback" hidden></p></div>
        <div><label for="room-quantity">Số lượng phòng</label><input type="number" id="room-quantity" class="hv-input" min="1" max="10" step="1" required value="{{ max(1,min(10,(int)request('quantity',1))) }}" aria-describedby="booking-quantity-feedback"><p id="booking-quantity-feedback" class="hv-field-feedback" hidden></p></div>
    </div>
    <input type="hidden" id="room-select"><input type="hidden" id="room-select-price"><input type="hidden" id="room-select-name"><input type="hidden" id="selected-room-id">
    <p class="hv-booking-status" id="booking-availability" role="status" aria-live="polite" aria-atomic="true">Bạn chưa chọn ngày nhận phòng và ngày trả phòng.</p>
    <div class="hv-booking-checklist" id="booking-error" aria-label="Thông tin cần kiểm tra trước khi đặt phòng" hidden></div>
    <button type="button" id="booking-price-retry" class="hv-button" hidden>Tải lại giá và phòng trống</button>
    <div class="hv-booking-estimate hv-price-card" id="price-estimate" hidden>
        <details class="hv-price-details" open>
            <summary><span>Chi tiết giá</span><span class="hv-price-chevron" aria-hidden="true">⌄</span></summary>
            <div class="hv-price-body">
                <p class="hv-price-context"><strong>{{ $hotel->name }}</strong><span id="booking-priced-room"></span></p>
                <div class="hv-price-stay"><span id="booking-room-quantity">—</span><span aria-hidden="true">·</span><span id="booking-nights">—</span><span id="booking-unit-price" hidden>—</span></div>
                <div class="hv-nightly-scroll"><table class="hv-nightly-table"><thead><tr><th>Đêm lưu trú</th><th>Đơn giá × phòng</th><th>Thành tiền</th></tr></thead><tbody id="booking-nightly-rates"></tbody></table></div>
                <dl class="hv-price-totals"><div><dt>Tiền phòng</dt><dd id="price-subtotal">—</dd></div><div class="hv-price-grand-total"><dt>Tổng thanh toán</dt><dd id="total-price">—</dd></div></dl>
                <p id="price-label-nights" class="hv-price-note"></p>
                <p class="hv-price-note"><span id="booking-surcharge-note" hidden>Phụ thu ngày/mùa đã nằm trong đơn giá từng đêm, không cộng lại. </span>Chưa áp dụng giảm giá. Yêu cầu thêm cần được khách sạn xác nhận.</p>
                <label id="booking-price-confirmation" class="hv-price-note" hidden><input id="booking-price-accept" type="checkbox"> Giá đã thay đổi. Tôi xác nhận giá từng đêm và tổng thanh toán mới.</label>
            </div>
        </details>
    </div>
    @if(\App\Support\LocalBookingDemo::enabled())
    <div class="hv-booking-note"><label for="booking-special-request">Yêu cầu thêm <span>(không bắt buộc)</span></label><textarea id="booking-special-request" class="hv-input" maxlength="500" rows="3" placeholder="Ví dụ: ưu tiên phòng yên tĩnh" aria-describedby="booking-note-feedback"></textarea><p id="booking-note-feedback" class="hv-field-feedback" hidden></p></div>
    @endif
    <p id="booking-submit-error" class="hv-booking-error" role="alert" tabindex="-1" hidden></p>
    <p id="booking-submit-action" class="hv-booking-help" hidden></p>
    <button type="button" id="submit-booking-btn" class="hv-button hv-button--primary" disabled aria-describedby="booking-next-step booking-error booking-submit-error">Tiếp tục đặt phòng</button>
    <p id="booking-next-step" class="hv-booking-next-step">Chọn ngày nhận phòng và ngày trả phòng để tiếp tục.</p>
    @unless(\App\Support\LocalBookingDemo::enabled())<p class="hv-booking-help">Đặt phòng trực tuyến đang được cập nhật. Vui lòng liên hệ khách sạn để xác nhận sức chứa và chính sách trẻ em.</p>@endunless
    <button type="button" class="hv-button hv-detail-wishlist" id="hotel-detail-wishlist" aria-pressed="false" onclick="toggleWishlistDetail({{ $hotel->id }}, this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 0 0 0 6.364L12 20.364l7.682-7.682a4.5 4.5 0 0 0-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 0 0-6.364 0Z"/></svg><span data-wishlist-label>Lưu yêu thích</span></button>
</section>


{{-- Scoped modal, moved into document.body by JS so sticky/overflow parents cannot clip it. --}}
<div id="hv-stay-date-dialog" class="hv-stay-overlay" hidden>
    <section class="hv-stay-dialog" role="dialog" aria-modal="true" aria-labelledby="hv-stay-dialog-title" aria-describedby="hv-stay-dialog-hint">
        <div class="hv-stay-heading">
            <div><h2 id="hv-stay-dialog-title">Chọn ngày lưu trú</h2><p id="hv-stay-dialog-hint">Chọn ngày nhận phòng, sau đó chọn ngày trả phòng.</p></div>
            <button type="button" class="hv-stay-close" id="hv-stay-close" aria-label="Đóng lịch">×</button>
        </div>
        <div class="hv-stay-nav">
            <button type="button" class="hv-stay-arrow" id="hv-stay-prev" aria-label="Tháng trước">‹</button>
            <button type="button" class="hv-stay-arrow" id="hv-stay-next" aria-label="Tháng sau">›</button>
        </div>
        <div class="hv-stay-months" id="hv-stay-months"></div>
        <div class="hv-stay-summary">
            <button type="button" id="hv-stay-select-checkin"><span>Nhận phòng</span><strong id="hv-stay-draft-checkin">Chọn ngày</strong></button>
            <button type="button" id="hv-stay-select-checkout"><span>Trả phòng</span><strong id="hv-stay-draft-checkout">Chọn ngày</strong></button>
        </div>
        <p class="hv-stay-error" id="hv-stay-error" role="alert" hidden></p>
        <div class="hv-stay-actions">
            <button type="button" class="hv-stay-clear" id="hv-stay-clear">Xóa ngày</button>
            <button type="button" class="hv-stay-done" id="hv-stay-done">Xong</button>
        </div>
    </section>
</div>


<div id="hv-guest-overlay" class="hv-guest-overlay" hidden>
    <section
        id="hv-guest-dialog"
        class="hv-guest-dialog"
        role="dialog"
        aria-labelledby="hv-guest-title"
    >
        <div class="hv-guest-header">
            <h2 id="hv-guest-title">Khách lưu trú</h2>
        </div>

        <div class="hv-guest-row" data-kind="adults">
            <div>
                <strong>Người lớn</strong>
            </div>
            <div class="hv-guest-controls">
                <button type="button" data-step="-1" aria-label="Giảm người lớn">−</button>
                <output data-count>2</output>
                <button type="button" data-step="1" aria-label="Tăng người lớn">+</button>
            </div>
        </div>

        <div class="hv-guest-row" data-kind="children">
            <div>
                <strong>Trẻ em</strong>
            </div>
            <div class="hv-guest-controls">
                <button type="button" data-step="-1" aria-label="Giảm trẻ em">−</button>
                <output data-count>0</output>
                <button type="button" data-step="1" aria-label="Tăng trẻ em">+</button>
            </div>
        </div>

        <div class="hv-guest-row" data-kind="infants">
            <div>
                <strong>Em bé</strong>
            </div>
            <div class="hv-guest-controls">
                <button type="button" data-step="-1" aria-label="Giảm em bé">−</button>
                <output data-count>0</output>
                <button type="button" data-step="1" aria-label="Tăng em bé">+</button>
            </div>
        </div>

        <p class="hv-guest-note">
            Tổng số khách gồm người lớn, trẻ em và em bé.
            Chính sách độ tuổi và sức chứa tùy từng khách sạn.
        </p>

        <div class="hv-guest-footer">
            <button type="button" id="hv-guest-done">Xong</button>
        </div>
    </section>
</div>
