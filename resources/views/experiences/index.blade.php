@extends('Layout')
@section('title', 'Trải nghiệm — Mỗi điểm đến, một góc nhìn mới')
@section('description', 'Khám phá địa điểm tham quan, ẩm thực, vui chơi và hoạt động địa phương trên Angel.')
@section('content')
@include('experiences.styles')
<div class="exp-page" id="experience-page">
    <div class="exp-wrap">
        <section class="exp-hero" aria-labelledby="experience-title">
            <div>
                <h1 id="experience-title">Một điểm đến.<br><span>Nhiều cách khám phá.</span></h1>
                <p class="exp-intro">Tìm nơi muốn ghé, món muốn thử và những hoạt động mang bạn đến gần hơn với nhịp sống địa phương.</p>
            </div>
            <div class="exp-hero-note">
                <p>Chọn một cách khám phá của riêng bạn</p>
                <ul>
                    @foreach($categories as $key => $label)
                    <li><span class="exp-symbol" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5 5-2Z"/></svg></span>{{ $label }}</li>
                    @endforeach
                </ul>
            </div>
        </section>
        <div class="exp-categories" aria-label="Danh mục trải nghiệm">
            <button type="button" class="exp-category" data-experience-category="" aria-pressed="true">Tất cả trải nghiệm</button>
            @foreach($categories as $key => $label)
                <button type="button" class="exp-category" data-experience-category="{{ $key }}" aria-pressed="false">{{ $label }}</button>
            @endforeach
        </div>
        <form class="exp-filters" id="experience-filters" method="GET" action="{{ route('experiences.index') }}" aria-label="Lọc trải nghiệm">
            <div class="exp-field"><label for="experience-location">Bạn muốn đi đâu?</label><select id="experience-location" name="location" disabled><option value="">Tất cả địa phương</option></select></div>
            <div class="exp-field"><label for="experience-query">Tìm một trải nghiệm</label><input id="experience-query" name="q" maxlength="120" placeholder="Tên địa điểm hoặc hoạt động" type="search"></div>
            <div class="exp-field"><label for="experience-sort">Sắp xếp</label><select id="experience-sort" name="sort"><option value="latest">Mới cập nhật</option><option value="name">Theo tên A–Z</option></select></div>
            <button type="submit" class="exp-button">Tìm trải nghiệm <span aria-hidden="true">↗</span></button>
        </form>
        <div class="exp-results-heading"><h2>Điểm hẹn cho hành trình mới</h2><p id="experience-count" role="status" aria-live="polite"></p></div>
        <div id="experience-results" aria-busy="true">
            <div id="experience-loading" role="status"><span class="sr-only">Đang tải trải nghiệm…</span><div class="exp-grid">@for($i=0;$i<6;$i++)<div class="exp-skeleton" aria-hidden="true"></div>@endfor</div></div>
            <div id="experience-grid" class="exp-grid" hidden></div>
            <section id="experience-empty" class="exp-state" hidden><h2>Chưa có trải nghiệm phù hợp</h2><p>Thử một địa phương hoặc danh mục khác, hoặc quay lại sau để khám phá thêm.</p><button type="button" class="exp-button exp-button--quiet" id="experience-reset">Xem tất cả trải nghiệm</button></section>
            <section id="experience-error" class="exp-state" role="alert" hidden><h2>Chưa thể tải trải nghiệm</h2><p>Vui lòng kiểm tra kết nối và thử lại sau.</p><button type="button" class="exp-button" id="experience-retry">Thử lại</button></section>
        </div>
        <nav id="experience-pagination" class="exp-pagination" aria-label="Phân trang trải nghiệm" hidden><button type="button" class="exp-button exp-button--quiet" id="experience-prev">← Trước</button><span id="experience-page-number"></span><button type="button" class="exp-button exp-button--quiet" id="experience-next">Tiếp →</button></nav>
        <noscript><div class="exp-state"><p>Bật JavaScript để lọc và xem danh sách trải nghiệm.</p></div></noscript>
    </div>
</div>
@endsection
@push('scripts')
@include('experiences.index-script')
@endpush
