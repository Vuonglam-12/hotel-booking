@php
    $similarHotels = $similarHotels ?? collect();
@endphp

@if($similarHotels->isNotEmpty())
    <section class="max-w-7xl mx-auto px-4 mt-12">
        <div class="flex items-center justify-between gap-3 mb-5">
            <h2 class="text-2xl font-bold text-slate-900">Các khách sạn tương tự</h2>
            <a href="{{ route('home') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">Xem tất cả</a>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            @foreach($similarHotels as $similar)
                @php
                    $similarImage = $similar->cover_image_url;
                    $similarPrice = $similar->prepared_min_price ?? 0;
                @endphp
                <article class="group overflow-hidden rounded-[26px] border border-slate-200 bg-white shadow-[0_12px_28px_rgba(15,23,42,0.04)] transition hover:-translate-y-1 hover:shadow-[0_20px_40px_rgba(59,130,246,0.12)]">
                    <a href="{{ route('hotel.detail', $similar->id) }}" class="block">
                        <div class="relative h-52 overflow-hidden">
                            <img src="{{ $similarImage }}" data-accommodation-image alt="{{ $similar->name }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                            <div class="absolute inset-0 bg-gradient-to-t from-slate-900/35 to-transparent"></div>
                            <div class="absolute left-4 top-4 inline-flex items-center gap-1 rounded-full bg-white/90 px-2.5 py-1 text-xs font-semibold text-slate-800">
                                <span>★</span> {{ number_format($similar->avg_rating ?? 4.8, 1) }}
                            </div>
                        </div>
                        <div class="space-y-2 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="line-clamp-2 text-base font-bold text-slate-900">{{ $similar->name }}</h3>
                            </div>
                            <p class="text-sm text-slate-500">{{ $similar->location->name ?? 'Đà Lạt' }}</p>
                            <div class="flex items-center justify-between pt-1">
                                <div>
                                    <span class="text-sm text-slate-500">Từ</span>
                                    <span class="ml-1 text-lg font-bold text-slate-900">{{ number_format($similarPrice) }}đ</span>
                                </div>
                                <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">Xem phòng</span>
                            </div>
                        </div>
                    </a>
                </article>
            @endforeach
        </div>
    </section>
@endif
