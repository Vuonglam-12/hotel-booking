@php
    $galleryImages = array_slice($hotel->gallery_image_urls, 0, 5);
    $galleryCount = count($galleryImages);
@endphp
<section class="hv-detail-container hv-detail-gallery-section" aria-label="Ảnh khách sạn {{ $hotel->name }}">
    <div class="hv-detail-gallery-wrap">
        <div class="hv-detail-gallery hv-detail-gallery--{{ max(1, $galleryCount) }}">
            @forelse($galleryImages as $index => $image)
                <button type="button" class="hv-detail-gallery-tile" data-hotel-photo="{{ $index }}" aria-label="Xem ảnh {{ $index + 1 }} của {{ $hotel->name }}">
                    <img src="{{ $image }}" data-accommodation-image alt="{{ $hotel->name }} – ảnh {{ $index + 1 }}" @if($index > 0) loading="lazy" @endif>
                </button>
            @empty
                <div class="hv-detail-gallery-tile"><img src="{{ \App\Support\AccommodationImage::placeholder() }}" data-accommodation-image alt="Chưa có ảnh khách sạn"></div>
            @endforelse
        </div>
        @if($galleryCount)<button type="button" class="hv-detail-gallery-all" data-hotel-photo="0">Xem tất cả ảnh</button>
        @else<span class="hv-detail-gallery-empty">Chưa có ảnh khách sạn</span>@endif
    </div>
</section>
