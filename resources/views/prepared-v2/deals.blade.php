@extends('layout')
@section('title','Khách sạn gợi ý')
@section('content')
<div class="max-w-7xl mx-auto px-4 py-10 space-y-14">
    <div><h1 class="section-title">Khám phá khách sạn Angel</h1><p class="section-sub">Giá hiện có của khách sạn. Chưa có chương trình khuyến mãi được xác minh.</p></div>
    @foreach(['Giá từ thấp đến cao'=>$flashSale,'Khách sạn nổi bật'=>$featured,'Khách sạn cao cấp'=>$combos] as $heading=>$hotels)
    <section><h2 class="section-title mb-6">{{ $heading }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
        @forelse($hotels as $hotel)
            <a class="hotel-card deal-card bg-white rounded-2xl overflow-hidden shadow-sm border border-[#EAF3FF]" href="/hotels/{{ $hotel->id }}">
                <img src="{{ $hotel->cover_image_url }}" alt="{{ $hotel->name }}" class="w-full h-40 object-cover">
                <div class="p-5"><h3 class="font-semibold text-[#1E3A5F]">{{ $hotel->name }}</h3><p class="text-sm text-[#6B7280]">{{ $hotel->location?->name }}</p>
                    <div class="mt-4 pt-3 border-t border-[#EAF3FF]"><p class="text-xs text-[#6B7280]">Giá từ</p><p class="font-bold text-[#2563EB] text-lg">{{ number_format($hotel->rooms_min_price) }} ₫<span class="text-xs font-normal">/phòng/đêm</span></p></div>
                </div>
            </a>
        @empty <p class="text-sm text-[#6B7280]">Chưa có khách sạn phù hợp.</p> @endforelse
        </div>
    </section>
    @endforeach
    <section><h2 class="section-title mb-6">Theo điểm đến</h2><div class="flex gap-3 flex-wrap">
        @foreach($destinations as $dest)<a class="filter-tab" href="{{ route('home') }}?city={{ urlencode($dest->name) }}">{{ $dest->name }} · {{ $dest->hotels_count }} khách sạn</a>@endforeach
    </div></section>
</div>
@endsection
@push('scripts')
    @include('partials.deals.outstandingoffer-scrpit')
@endpush
