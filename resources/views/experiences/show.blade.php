@extends('Layout')
@section('title', $experience['name'].' — Trải nghiệm Angel')
@section('description', $experience['summary'])
@section('content')
@include('experiences.styles')
<article class="exp-page">
    <div class="exp-wrap">
        <nav class="exp-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('experiences.index') }}">Trải nghiệm</a><span aria-hidden="true">/</span><a href="{{ route('experiences.index', ['location' => $experience['location']['code']]) }}">{{ $experience['location']['name'] }}</a></nav>
        <header class="exp-detail-heading"><span class="exp-badge">{{ $experience['category']['label'] }}</span><h1>{{ $experience['name'] }}</h1><p class="exp-intro">{{ $experience['summary'] }}</p></header>
        @if(count($experience['gallery']))
        <div class="exp-gallery {{ [1 => 'exp-gallery--single', 2 => 'exp-gallery--two', 3 => 'exp-gallery--three'][count($experience['gallery'])] ?? '' }}" aria-label="Ảnh trải nghiệm">
            @foreach(array_slice($experience['gallery'], 0, 5) as $index => $image)
                <button type="button" data-experience-photo="{{ $index }}" aria-label="Xem ảnh {{ $index+1 }}: {{ $image['alt'] }}"><img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}" @if($index > 0) loading="lazy" @endif></button>
            @endforeach
        </div>
        <p class="exp-gallery-note">Chọn ảnh để xem toàn bộ gallery và nguồn ảnh.</p>
        @else
        <div class="exp-gallery exp-gallery--empty"><div class="exp-no-image"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="M12 21s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/></svg><span>Ảnh địa điểm đang được cập nhật</span></div></div>
        @endif
        <div class="exp-details">
            <section><h2 class="exp-section-title">Một chút về nơi này</h2><div class="exp-description">{{ $experience['description'] ?: $experience['summary'] }}</div></section>
            <aside class="exp-facts"><h2>Thông tin địa điểm</h2><dl>
                <div><dt>Địa phương</dt><dd>{{ $experience['location']['name'] }}</dd></div>
                @if($experience['address'])<div><dt>Địa chỉ</dt><dd>{{ $experience['address'] }}</dd></div>@endif
                @if($experience['duration_minutes'])<div><dt>Thời lượng tham khảo</dt><dd>{{ $experience['duration_minutes'] }} phút</dd></div>@endif
                @if($experience['open_time'] || $experience['close_time'])<div><dt>Giờ hoạt động</dt><dd>@if($experience['open_time'])Mở cửa {{ $experience['open_time'] }}@endif @if($experience['close_time'])· Đóng cửa {{ $experience['close_time'] }}@endif</dd></div>@endif
            </dl>@if($experience['source_url'])<a class="exp-source" href="{{ $experience['source_url'] }}" rel="noopener noreferrer" target="_blank">Nguồn thông tin ↗</a>@endif</aside>
        </div>
        <section aria-labelledby="experience-hotels-title"><h2 class="exp-section-title" id="experience-hotels-title">Ở lại và khám phá {{ $experience['location']['name'] }}</h2>
            @if(count($experience['hotels']))
            <div class="exp-hotels">@foreach($experience['hotels'] as $hotel)<a class="exp-hotel" href="{{ $hotel['url'] }}"><img src="{{ $hotel['cover_url'] }}" data-accommodation-image alt="{{ $hotel['name'] }}" loading="lazy"><div><h3>{{ $hotel['name'] }}</h3>@if($hotel['address'])<p>{{ $hotel['address'] }}</p>@endif</div></a>@endforeach</div>
            @else<p class="exp-intro" style="margin-top:16px">Chưa có khách sạn để gợi ý tại địa phương này.</p>@endif
        </section>
    </div>
</article>
@if(count($experience['gallery']))
<dialog class="exp-lightbox" id="experience-lightbox" aria-label="Gallery trải nghiệm"><div class="exp-lightbox-bar"><span>Ảnh địa điểm</span><button type="button" class="exp-button exp-button--quiet" data-lightbox-close aria-label="Đóng ảnh">Đóng ×</button></div><img id="experience-lightbox-image" alt=""><div class="exp-lightbox-bar"><button type="button" class="exp-button exp-button--quiet" data-lightbox-prev aria-label="Ảnh trước">←</button><span id="experience-lightbox-count" aria-live="polite"></span><button type="button" class="exp-button exp-button--quiet" data-lightbox-next aria-label="Ảnh tiếp">→</button></div><p id="experience-lightbox-source"></p></dialog>
@endif
@endsection
@push('scripts')
@if(count($experience['gallery']))
<script>
(() => {
    const gallery = @json($experience['gallery']);
    const dialog = document.getElementById('experience-lightbox');
    let index = 0, opener;
    function show(next) { index = (next + gallery.length) % gallery.length; const image = document.getElementById('experience-lightbox-image'); image.src = gallery[index].url; image.alt = gallery[index].alt; document.getElementById('experience-lightbox-source').textContent = gallery[index].source; document.getElementById('experience-lightbox-count').textContent = (index+1) + ' / ' + gallery.length; }
    document.querySelectorAll('[data-experience-photo]').forEach(button => button.addEventListener('click', () => { opener = button; show(Number(button.dataset.experiencePhoto)); dialog.showModal(); }));
    dialog.querySelector('[data-lightbox-close]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    dialog.addEventListener('close', () => opener?.focus({preventScroll:true}));
    dialog.querySelector('[data-lightbox-prev]').addEventListener('click', () => show(index-1));
    dialog.querySelector('[data-lightbox-next]').addEventListener('click', () => show(index+1));
    dialog.addEventListener('keydown', event => { if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') { event.preventDefault(); show(index + (event.key === 'ArrowRight' ? 1 : -1)); } });
})();
</script>
@endif
@endpush
