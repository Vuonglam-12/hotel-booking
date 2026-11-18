<article class="gd-card">
    <a href="{{ $item['url'] }}" class="gd-media" aria-label="{{ $item['title'] }}">@if($item['cover_url'])<img src="{{ $item['cover_url'] }}" alt="{{ $item['title'] }}" loading="lazy">@else<span>Ảnh bài viết đang được cập nhật</span>@endif</a>
    <div class="gd-meta"><span class="gd-category">{{ $item['category']['name'] }}</span><time datetime="{{ $item['published_at'] }}">{{ $item['published_label'] }}</time></div>
    <h3><a href="{{ $item['url'] }}">{{ $item['title'] }}</a></h3>
    @if($item['excerpt'])<p>{{ $item['excerpt'] }}</p>@endif
    <a class="gd-read" href="{{ $item['url'] }}">Đọc bài viết ↗</a>
</article>
