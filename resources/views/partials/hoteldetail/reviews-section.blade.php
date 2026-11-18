@php
    $reviewList = $reviews ?? collect();
    $approved = $hotel->relationLoaded('reviews') ? $hotel->reviews->where('status', 'approved') : collect();
    $summary = $reviewSummary ?? ['total'=>$approved->count(), 'avg_overall'=>$approved->avg('rating'),
        'rating_counts'=>collect(range(5,1))->mapWithKeys(fn($score)=>[$score=>$approved->where('rating',$score)->count()])->all()];
    $reviewCount = (int)$summary['total'];
    $avgScore = $summary['avg_overall'];
    $verdict = $avgScore === null ? 'Chưa có điểm đánh giá' : ($avgScore >= 4.5 ? 'Xuất sắc' : ($avgScore >= 4 ? 'Rất tốt' : ($avgScore >= 3 ? 'Tốt' : ($avgScore >= 2 ? 'Trung bình' : 'Cần cải thiện'))));
    $paginated = $reviewList instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $reviewPage = $paginated ? $reviewList->currentPage() : 1;
    $reviewLastPage = $paginated ? $reviewList->lastPage() : 1;
    $metrics = ['cleanliness'=>'Vệ sinh','service'=>'Dịch vụ','location'=>'Vị trí'];
@endphp
<section id="hotel-reviews" class="hv-reviews" aria-labelledby="hotel-reviews-title" data-hotel-id="{{ $hotel->id }}" data-page="{{ $reviewPage }}" data-last-page="{{ $reviewLastPage }}" data-total="{{ $reviewCount }}">
    <div class="hv-reviews-heading">
        <div><p class="hv-reviews-eyebrow">GÓC NHÌN TỪ KHÁCH HÀNG</p><h2 id="hotel-reviews-title">Đánh giá khách hàng</h2><p class="hv-reviews-intro">Những chia sẻ giúp bạn chọn nơi lưu trú phù hợp.</p></div>
    </div>
    <div class="hv-review-summary">
        <div class="hv-review-overall">
            <div class="hv-review-score"><strong data-review-overall>{{ $avgScore === null ? '—' : number_format($avgScore,1) }}</strong><span>/ 5</span></div>
            <div><h3 data-review-verdict>{{ $verdict }}</h3><span class="hv-review-stars" role="img" aria-label="{{ $avgScore === null ? 'Chưa có điểm' : number_format($avgScore,1).' trên 5 sao' }}" style="--review-fill:{{ ($avgScore ?? 0)*20 }}%" data-review-summary-stars><span aria-hidden="true">★★★★★</span></span><p class="hv-review-basis" data-review-basis>Từ {{ $reviewCount }} đánh giá đã duyệt</p></div>
        </div>
        <div class="hv-review-distribution" aria-label="Phân bố số sao">
            @foreach(range(5,1) as $score)
                @php($scoreCount = (int)($summary['rating_counts'][$score] ?? 0))
                <button type="button" class="hv-review-histogram-row" data-review-score="{{ $score }}" aria-pressed="false" aria-label="Lọc {{ $score }} sao, {{ $scoreCount }} đánh giá">
                    <span>{{ $score }} <span class="hv-review-gold" aria-hidden="true">★</span></span><span class="hv-review-bar" aria-hidden="true"><span data-review-bar="{{ $score }}" style="width:{{ $reviewCount ? $scoreCount/$reviewCount*100 : 0 }}%"></span></span><span data-review-count="{{ $score }}">{{ $scoreCount }}</span>
                </button>
            @endforeach
        </div>
        <div class="hv-review-metrics" id="hotel-review-metrics" @if(!collect(array_keys($metrics))->contains(fn($key)=>($summary[$key.'_count']??0)>0)) hidden @endif>
            @foreach($metrics as $key=>$label)
                <div class="hv-review-metric" data-review-metric="{{ $key }}" @if(($summary[$key.'_count']??0)<1) hidden @endif>
                    <span>{{ $label }}</span><strong data-metric-value>{{ isset($summary['avg_'.$key]) ? number_format($summary['avg_'.$key],1) : '—' }}<small>/5</small></strong><small data-metric-count>{{ $summary[$key.'_count']??0 }} lượt chấm</small>
                </div>
            @endforeach
        </div>
    </div>
    <div class="hv-review-toolbar" id="hotel-review-toolbar" @if(!$reviewCount) hidden @endif>
        <div class="hv-review-filters"><label for="hotel-review-rating">Số sao<select id="hotel-review-rating"><option value="">Tất cả số sao</option>@foreach(range(5,1) as $score)<option value="{{ $score }}">{{ $score }} sao</option>@endforeach</select></label><label for="hotel-review-sort">Sắp xếp<select id="hotel-review-sort"><option value="newest">Mới nhất</option><option value="highest">Điểm cao nhất</option><option value="lowest">Điểm thấp nhất</option></select></label></div>
        <p class="hv-review-result-count" id="hotel-review-status" role="status" aria-live="polite">{{ $reviewCount }} nhận xét</p>
    </div>
    <div class="hv-review-load-error" id="hotel-review-error" role="alert" hidden><span>Chưa tải được đánh giá. Vui lòng thử lại.</span><button type="button" id="hotel-review-retry">Thử lại</button></div>
    <div class="hv-review-list" id="hotel-review-list">
        @foreach($reviewList as $review) @include('partials.hoteldetail.review-card', ['review'=>$review]) @endforeach
    </div>
    <div class="hv-review-empty" id="hotel-review-empty" @if($reviewCount) hidden @endif>
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5Z"/></svg>
        <h3 data-review-empty-title>Chưa có đánh giá được duyệt</h3><p data-review-empty-text>Nhận xét của bạn sẽ giúp những khách hàng tiếp theo hiểu thêm về khách sạn.</p>
    </div>
    <nav class="hv-review-pagination" id="hotel-review-pagination" aria-label="Trang đánh giá" @if($reviewLastPage<=1) hidden @endif>
        <button type="button" id="hotel-review-prev" @disabled($reviewPage<=1)>← Trước</button><span id="hotel-review-page" aria-live="polite">Trang {{ $reviewPage }} / {{ $reviewLastPage }}</span><button type="button" id="hotel-review-next" @disabled($reviewPage>=$reviewLastPage)>Sau →</button>
    </nav>
    <template id="hotel-review-card-template">@include('partials.hoteldetail.review-card', ['review'=>null])</template>
</section>
