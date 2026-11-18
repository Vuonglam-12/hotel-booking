@php
    $reviewName = $review?->customer?->name ?: 'Khách hàng';
    if (str_starts_with((string)$review?->comment, '[Đánh giá mẫu] ')) {
        $reviewName = preg_replace('/\s*\(Mẫu\)$/u', '', $reviewName);
    }
    $reviewInitials = mb_strtoupper(collect(preg_split('/\s+/u',trim($reviewName)))->take(2)->map(fn($part)=>mb_substr($part,0,1))->implode(''));
    $reviewRating = $review?->rating;
    $reviewDate = $review?->created_at ? \Carbon\CarbonImmutable::parse($review->created_at)->setTimezone('Asia/Ho_Chi_Minh') : null;
    $reviewAvatar = $review?->customer?->avatar_url;
    $avatarScheme = $reviewAvatar ? parse_url($reviewAvatar,PHP_URL_SCHEME) : null;
    $reviewAvatar = $reviewAvatar && (!$avatarScheme || in_array(strtolower($avatarScheme),['http','https'],true)) ? $reviewAvatar : null;
@endphp
<article class="hv-review-card" data-review-id="{{ $review?->id }}">
    <div class="hv-review-author"><div class="hv-review-avatar" aria-hidden="true"><span data-review-initials>{{ $reviewInitials }}</span><img data-review-avatar @if($reviewAvatar) src="{{ $reviewAvatar }}" @else hidden @endif alt="" loading="lazy" width="42" height="42"></div><div><h3 data-review-name>{{ $reviewName }}</h3></div></div>
    <div class="hv-review-content"><div class="hv-review-meta"><span class="hv-review-score-badge"><span class="hv-review-gold" aria-hidden="true">★</span> <strong data-review-rating>{{ $reviewRating ?? '—' }}</strong><span>/5</span></span><time data-review-date @if($reviewDate) datetime="{{ $reviewDate->toIso8601String() }}" @else hidden @endif>{{ $reviewDate?->format('d/m/Y') }}</time></div><p class="hv-review-comment" data-review-comment>{{ $review?->comment ?: 'Khách hàng đã chấm điểm, chưa có nhận xét bằng lời.' }}</p></div>
</article>
