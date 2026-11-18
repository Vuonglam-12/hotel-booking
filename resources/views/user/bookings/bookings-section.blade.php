@php
    // Read-only capability check. Never enable a checkout or register a payment route here.
    $accountPaymentPage = (\App\Support\LocalBookingDemo::enabled() || (config('roomtype-v2.final_integration') && config('roomtype-v2.production_manifest') === null))
        && collect(app('router')->getRoutes()->getRoutes())->contains(fn($route) => $route->uri() === 'payments/bookings/{booking}');
@endphp
<div class="page-header"><div><p class="eyebrow">NHỮNG CHUYẾN ĐI CỦA BẠN</p><h1>Lịch sử đặt phòng</h1><p class="page-description">Theo dõi đặt phòng, thanh toán và hóa đơn tại cùng một nơi.</p></div><a class="button" href="/">Đặt phòng mới @include('user.partials.icon', ['name'=>'plus'])</a></div>
@unless($accountPaymentPage)
<div class="booking-capability-note" role="status">@include('user.partials.icon', ['name'=>'info'])<span>Thanh toán trực tuyến hiện chưa khả dụng. Bạn vẫn có thể xem, hủy đặt phòng đủ điều kiện và tải hóa đơn đã phát hành.</span></div>
@endunless
<div class="booking-toolbar">
    <form class="booking-search" id="bookingSearchForm" role="search">
        <label class="sr-only" for="bookingSearch">Tìm theo mã đặt phòng hoặc tên khách sạn</label>
        <div class="booking-search-field">
            @include('user.partials.icon', ['name'=>'search'])
            <input id="bookingSearch" class="form-input" type="search" maxlength="100" autocomplete="off" placeholder="Mã đặt phòng hoặc tên khách sạn">
        </div>
        <button class="button" type="submit">Tìm kiếm</button>
    </form>
    <div id="tabsContainer" class="tabs-container" aria-label="Lọc theo trạng thái đặt phòng"></div>
</div>
<div class="booking-layout" id="bookingHistoryApp" data-payment-page="{{ $accountPaymentPage ? 'true' : 'false' }}">
    <div><div class="booking-list" id="bookingList" aria-live="polite"></div><nav id="paginationContainer" aria-label="Phân trang đặt phòng"></nav></div>
    <section class="detail-panel" id="detailPanel" aria-labelledby="bookingDetailHeading"><div class="detail-header"><h2 id="bookingDetailHeading" tabindex="-1">Chi tiết đặt phòng</h2><button type="button" class="icon-button" id="btnCloseDetail" aria-label="Đóng chi tiết đặt phòng">@include('user.partials.icon', ['name'=>'x'])</button></div><div class="detail-content" id="detailContent"></div></section>
</div>
<div id="reviewModal" style="display:none"></div>
