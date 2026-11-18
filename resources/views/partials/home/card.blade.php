@php
    $cardHotel = $hotel ?? null;
    $cardUrl = $cardHotel ? route('hotel.detail', array_merge(['id' => $cardHotel->id], request()->only(['check_in', 'check_out', 'guests']))) : route('home');
    if($cardHotel?->search_availability)$cardUrl=route('hotel.detail',array_merge(['id'=>$cardHotel->id],request()->only(['check_in','check_out','guests','adults','children','infants']),['room_type_id'=>$cardHotel->search_availability['room_type_id'],'quantity'=>$cardHotel->search_availability['required_rooms']]));
    $cardPrice = $cardHotel?->rooms_min_price;
    // A stored avg_rating alone does not establish a score from actual reviews.
    $reviewCount = (int) ($cardHotel?->review_count ?? 0);
    $reviewAverage = data_get($cardHotel?->rating_breakdown, 'avg_overall');
@endphp
<article class="hotel-card hv-hotel-card" data-hotel-id="{{ $cardHotel?->id }}">
    <div class="hv-hotel-photo">
        <a href="{{ $cardUrl }}" data-hotel-link aria-label="{{ $cardHotel?->name }}">
            <img src="{{ $cardHotel?->cover_image_url ?? \App\Support\AccommodationImage::placeholder() }}" data-accommodation-image data-card-field="image"
                alt="{{ $cardHotel?->name }}" width="640" height="480" loading="lazy">
        </a>
        <button type="button" class="hv-hotel-wishlist wishlist-btn" data-hotel="{{ $cardHotel?->id }}" aria-label="Lưu khách sạn yêu thích" aria-pressed="false">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 0 0 0 6.364L12 20.364l7.682-7.682a4.5 4.5 0 0 0-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 0 0-6.364 0Z"/></svg>
        </button>
    </div>
    <h3><a href="{{ $cardUrl }}" data-hotel-link data-card-field="name">{{ $cardHotel?->name }}</a></h3>
    <p class="hv-hotel-location" data-card-field="location">{{ $cardHotel?->location?->name }}</p>
    <p class="hv-hotel-rating" data-card-field="rating" @if(!$reviewCount || !$reviewAverage) hidden @endif>
        @if($reviewCount && $reviewAverage) {{ number_format($reviewAverage, 1) }} · {{ $reviewCount }} đánh giá @endif
    </p>
    <p class="hv-hotel-availability" data-card-field="availability" @if(!data_get($cardHotel?->search_availability,'dates_checked')) hidden @endif>
        @if(data_get($cardHotel?->search_availability,'dates_checked')) {{ $cardHotel->search_availability['required_rooms'] }} phòng cho {{ $cardHotel->search_availability['guests'] }} khách · Còn phòng theo ngày đã chọn @endif
    </p>
    <p class="hv-hotel-price" data-card-field="price">
        @if($cardPrice) Từ <strong>{{ number_format($cardPrice, 0, ',', '.') }} ₫</strong> <span>/ đêm</span>
        @else Liên hệ để biết giá @endif
    </p>
</article>
