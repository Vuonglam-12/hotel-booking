<section class="hv-detail-container hv-detail-heading" aria-labelledby="hotel-title">
    <h1 id="hotel-title">{{ $hotel->name }}</h1>
    <div class="hv-detail-meta">
        @if($hotel->star_rating)<span><span style="color:#EAB308" aria-hidden="true">★</span> Khách sạn {{ $hotel->star_rating }} sao</span>@endif
        @if($hotel->address)<span>{{ $hotel->address }}</span>
        @elseif($hotel->location?->name)<span>{{ $hotel->location->name }}</span>@endif
        @if(($hotel->review_count ?? 0) > 0)
            <span>{{ number_format($reviewSummary['avg_overall'] ?? $hotel->reviews->avg('rating'), 1) }}/5 · {{ $hotel->review_count }} đánh giá</span>
        @else<span>Chưa có đánh giá</span>@endif
    </div>
</section>
