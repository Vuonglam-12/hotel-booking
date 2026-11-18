@extends('Layout')
@section('title', $guide['title'].' — Angel')
@section('description', $guide['excerpt'] ?? 'Cẩm nang du lịch Angel')
@section('content')
@include('guides.styles')
<div class="gd-page"><div class="gd-wrap">
    <nav class="gd-breadcrumb" aria-label="Đường dẫn"><a href="{{ route('guides.index') }}">Cẩm nang du lịch</a><span aria-hidden="true"> / </span>{{ $guide['category']['name'] }}</nav>
    <article>
        <header class="gd-article-head"><p class="gd-eyebrow">{{ $guide['category']['name'] }}</p><h1>{{ $guide['title'] }}</h1><div class="gd-meta"><time datetime="{{ $guide['published_at'] }}">{{ $guide['published_label'] }}</time><span>·</span><span>{{ $guide['author'] ?: 'Chưa có thông tin tác giả' }}</span>@if($guide['location'])<span>· {{ $guide['location']['name'] }}</span>@endif</div>@if($guide['excerpt'])<p class="gd-deck">{{ $guide['excerpt'] }}</p>@endif</header>
        @if($guide['cover_url'])<img class="gd-cover" src="{{ $guide['cover_url'] }}" alt="{{ $guide['title'] }}" fetchpriority="high">@else<div class="gd-media gd-cover-placeholder">Ảnh bài viết đang được cập nhật</div>@endif
        <div class="gd-article-body">{!! $guide['body_html'] !!}</div>
    </article>
    @if($guide['experiences'])
    <section class="gd-related"><div class="gd-section-heading"><h2>Khám phá {{ $guide['location']['name'] }}</h2></div><div class="gd-grid">@foreach($guide['experiences'] as $experience)<article class="gd-card"><a href="{{ $experience['url'] }}" class="gd-media" aria-label="{{ $experience['name'] }}">@if($experience['cover'])<img src="{{ $experience['cover']['url'] }}" alt="{{ $experience['cover']['alt'] }}" loading="lazy">@else<span>Ảnh địa điểm đang được cập nhật</span>@endif</a><span class="gd-category">{{ $experience['category']['label'] }}</span><h3><a href="{{ $experience['url'] }}">{{ $experience['name'] }}</a></h3><p>{{ $experience['summary'] }}</p></article>@endforeach</div></section>
    @endif
    @if($guide['hotels'])
    <section class="gd-related"><div class="gd-section-heading"><h2>Lưu trú tại {{ $guide['location']['name'] }}</h2></div><div class="gd-grid">@foreach($guide['hotels'] as $hotel)<article class="gd-hotel"><a href="{{ $hotel['url'] }}"><div class="gd-media"><img src="{{ $hotel['cover_url'] }}" alt="{{ $hotel['name'] }}" loading="lazy"></div><h3>{{ $hotel['name'] }}</h3></a>@if($hotel['address'])<p class="gd-count">{{ $hotel['address'] }}</p>@endif</article>@endforeach</div></section>
    @endif
    @if($guide['related'])
    <section class="gd-related"><div class="gd-section-heading"><h2>Đọc tiếp cho chuyến đi</h2></div><div class="gd-grid">@foreach($guide['related'] as $item)@include('guides.card', ['item' => $item])@endforeach</div></section>
    @endif
</div></div>
@endsection
