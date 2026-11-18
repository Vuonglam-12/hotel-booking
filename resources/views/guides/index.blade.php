@extends('Layout')
@section('title', 'Cẩm nang du lịch — Angel')
@section('description', 'Bài viết, trải nghiệm và kinh nghiệm du lịch giúp bạn chuẩn bị cho hành trình tiếp theo cùng Angel.')
@section('content')
@include('guides.styles')
<div class="gd-page" id="guide-page"><div class="gd-wrap">
    <section class="gd-intro"><div><p class="gd-eyebrow">Angel · Cẩm nang du lịch</p><h1>Đi để khám phá.<br><span>Đọc để sẵn sàng.</span></h1></div><p>Những góc nhìn, kinh nghiệm và cảm hứng để mỗi hành trình bắt đầu từ một lựa chọn có ý nghĩa.</p></section>
    @if(is_scalar(request('legacy')) && in_array((string) request('legacy'), array_map('strval', array_column(\App\Support\LegacyBlogCatalog::INDEX, 'id')), true))
    <p class="gd-notice" role="status">Bài viết bạn tìm đang được rà soát nội dung. Khám phá những bài đã xuất bản bên dưới.</p>
    @endif
    <div class="gd-filters"><nav class="gd-categories" id="guide-categories" aria-label="Danh mục cẩm nang"><button class="gd-pill" data-category="" aria-pressed="true" type="button">Tất cả bài viết</button></nav><form class="gd-search" id="guide-search" action="{{ route('guides.index') }}" method="get"><input type="search" name="q" id="guide-query" maxlength="120" placeholder="Tìm cảm hứng cho chuyến đi…" aria-label="Tìm bài viết"><button class="gd-button" type="submit">Tìm kiếm ↗</button></form></div>
    <div id="guide-results" aria-busy="true">
        <div id="guide-loading" role="status"><span class="sr-only">Đang tải cẩm nang…</span><div class="gd-grid">@for($i=0;$i<3;$i++)<div class="gd-skeleton" aria-hidden="true"></div>@endfor</div></div>
        <div id="guide-featured" hidden></div>
        <div id="guide-heading" class="gd-section-heading" hidden><h2>Góc nhìn cho hành trình</h2><p id="guide-count" class="gd-count" aria-live="polite"></p></div>
        <div id="guide-grid" class="gd-grid" hidden></div>
        <section id="guide-empty" class="gd-state" hidden><h2>Chưa có bài viết phù hợp</h2><p>Thử từ khóa hoặc danh mục khác. Những câu chuyện mới sẽ được bổ sung khi sẵn sàng.</p><button type="button" class="gd-button gd-button--quiet" id="guide-reset">Xem tất cả bài viết</button></section>
        <section id="guide-unavailable" class="gd-state" role="status" hidden><h2>Cẩm nang đang được chuẩn bị</h2><p>Những bài viết đang được chuẩn bị để ra mắt. Mời bạn quay lại sau.</p></section>
        <section id="guide-error" class="gd-state" role="alert" hidden><h2>Chưa thể tải cẩm nang</h2><p>Vui lòng kiểm tra kết nối và thử lại sau.</p><button class="gd-button" type="button" id="guide-retry">Thử lại</button></section>
    </div>
    <nav id="guide-pagination" class="gd-pagination" hidden aria-label="Phân trang cẩm nang"><button type="button" class="gd-button gd-button--quiet" id="guide-prev">← Trước</button><span id="guide-page-label"></span><button type="button" class="gd-button gd-button--quiet" id="guide-next">Tiếp →</button></nav>
    <noscript><p class="gd-state">Bật JavaScript để tìm kiếm và xem danh sách Cẩm nang.</p></noscript>
</div></div>
@endsection
@push('scripts')
@include('guides.index-script')
@endpush
