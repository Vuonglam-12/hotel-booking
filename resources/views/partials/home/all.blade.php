@php
    $hasPaginator = $hotels instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $cityLabel = fn($name) => in_array(trim($name), ['Hồ Chí Minh', 'TP. Hồ Chí Minh', 'Thành phố Hồ Chí Minh', 'TP.HCM'], true) ? 'TP.HCM' : $name;
    $priorityCities = ['TP.HCM', 'Hà Nội', 'Đà Lạt', 'Đà Nẵng'];
    $initialSections = $hasPaginator
        ? $hotels->getCollection()->groupBy('location_id')->map(fn($rows) => ['location' => $rows->first()->location, 'hotels' => $rows])
        : $hotelSections;
    $initialSections = $initialSections->map(function ($section) {
        $section['hotels'] = $section['hotels']->where('status', 'active')->unique('id')->values();
        return $section;
    })->filter(fn($section) => $section['location'] && $section['hotels']->isNotEmpty())
        ->sortBy(function ($section) use ($cityLabel, $priorityCities) {
            $name = $cityLabel($section['location']->name);
            $priority = array_search($name, $priorityCities, true);
            return [$priority === false ? count($priorityCities) : $priority, $name];
        })->values();
    $initialCount = $initialSections->sum(fn($section) => $section['hotels']->count());
@endphp
<section class="hv-shell-container" aria-labelledby="home-hotels-heading">
    <h2 id="home-hotels-heading" class="sr-only">Khám phá khách sạn</h2>
    <p id="hotelLoadError" class="hv-home-error" role="alert" hidden>Chưa tải được danh sách khách sạn. <button type="button" id="retryHotelLoad">Thử lại</button></p>
    <div id="allHotelsGrid" class="hv-home-sections" aria-busy="false" data-home-url="{{ route('home') }}">
        @forelse($initialSections as $section)
            @php
                $sectionName = $cityLabel($section['location']->name);
                $sectionKey = 'id:' . $section['location']->id;
            @endphp
            <section class="hv-destination-section" data-location-key="{{ $sectionKey }}" data-location-name="{{ $section['location']->name }}" aria-labelledby="destination-heading-{{ $loop->index }}">
                <div class="hv-destination-heading">
                    <h2 id="destination-heading-{{ $loop->index }}">Những ngôi nhà ở {{ $sectionName }}</h2>
                    <div class="hv-destination-controls" data-row-controls hidden>
                        <button type="button" class="hv-row-arrow" data-row-direction="-1" aria-label="Xem các khách sạn trước ở {{ $sectionName }}" aria-controls="destination-row-{{ $loop->index }}" disabled>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m14 6-6 6 6 6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                        <button type="button" class="hv-row-arrow" data-row-direction="1" aria-label="Xem các khách sạn tiếp theo ở {{ $sectionName }}" aria-controls="destination-row-{{ $loop->index }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m10 6 6 6-6 6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                    </div>
                </div>
                <div class="hv-destination-row" id="destination-row-{{ $loop->index }}" tabindex="0" role="group" aria-label="Khách sạn ở {{ $sectionName }}">
                    @foreach($section['hotels'] as $hotel)
                        @include('partials.home.card', ['hotel' => $hotel])
                    @endforeach
                </div>
            </section>
        @empty
            <p class="hv-home-empty">Không tìm thấy khách sạn phù hợp.</p>
        @endforelse
    </div>
    <p class="sr-only" id="homeHotelStatus" role="status">Đã hiển thị {{ $initialCount }} khách sạn theo địa điểm.</p>
    @if($hasPaginator)
        <noscript>
            <nav class="hv-home-pagination" aria-label="Trang kết quả">
                @if($hotels->currentPage() > 1)<a href="{{ route('home', array_merge(request()->except('page'), ['page' => $hotels->currentPage() - 1])) }}">Trang trước</a>@endif
                @if($hotels->hasMorePages())<a href="{{ route('home', array_merge(request()->except('page'), ['page' => $hotels->currentPage() + 1])) }}">Trang tiếp theo</a>@endif
            </nav>
        </noscript>
    @endif
    <template id="hotelCardTemplate">@include('partials.home.card', ['hotel' => null])</template>
    <template id="hotelSectionTemplate">
        <section class="hv-destination-section">
            <div class="hv-destination-heading">
                <h2></h2>
                <div class="hv-destination-controls" data-row-controls hidden>
                    <button type="button" class="hv-row-arrow" data-row-direction="-1" disabled>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m14 6-6 6 6 6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <button type="button" class="hv-row-arrow" data-row-direction="1">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m10 6 6 6-6 6" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                </div>
            </div>
            <div class="hv-destination-row" tabindex="0" role="group"></div>
        </section>
    </template>
</section>
