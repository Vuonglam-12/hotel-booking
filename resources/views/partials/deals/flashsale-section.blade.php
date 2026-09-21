<section>
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
            <h2 class="section-title">⚡ Flash Sale Cuối Tuần</h2>
            <p class="section-sub">Giá giảm mạnh – Số lượng có hạn, đặt ngay kẻo lỡ!</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <button class="filter-tab active" onclick="filterDeals('all', this)">Tất cả</button>
            @foreach($locations->take(4) as $loc)
            <button class="filter-tab" onclick="filterDeals('{{ $loc->name }}', this)">{{ $loc->name }}</button>
            @endforeach
        </div>
    </div>

    {{-- Khung chứa Coverflow 3D - Hỗ trợ kéo/vuốt --}}
    <div class="relative w-full h-[460px] md:h-[500px] overflow-hidden perspective-[1200px] cursor-grab active:cursor-grabbing" id="flashSaleContainer">
        
        <div class="absolute inset-0 transform-style-3d pointer-events-none" id="flashCoverflowTrack">
            @foreach($flashSale as $index => $hotel)
            <div class="flash-slide-3d absolute top-1/2 left-1/2 w-[280px] md:w-[320px] transition-all duration-500 ease-out select-none" 
                 data-city="{{ $hotel->location->name }}">
                
                <div class="hotel-card deal-card bg-white rounded-2xl overflow-hidden shadow-2xl border border-[#EAF3FF] w-full pointer-events-auto"
                    onclick="handleFlashClick(this, {{ $hotel->id }})">
                    <div class="relative h-44 overflow-hidden bg-[#EAF3FF]">
                        <img src="https://picsum.photos/seed/{{ $hotel->id }}d/400/300" alt="{{ $hotel->name }}"
                            class="w-full h-full object-cover pointer-events-none">
                        <div class="discount-badge">-{{ $hotel->discount }}%</div>
                    </div>
                    <div class="p-5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1">
                                <h3 class="font-semibold text-[#1E3A5F] text-base leading-snug mb-1 truncate">{{ $hotel->name }}</h3>
                                <p class="text-[#C9D3DD] text-xs mt-1.5 flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    {{ $hotel->location->name }}
                                </p>                          
                            </div>
                            <div class="flex items-center gap-1 shrink-0 rating-badge">
                                <svg class="w-4 h-4 rating-star" fill="#F59E0B" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                </svg>
                                <span class="text-sm font-bold rating-score">{{ $hotel->avg_rating }}</span>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-[#EAF3FF] flex items-center justify-between">
                            <div>
                                <p class="text-xs text-[#C9D3DD]">Giá từ</p>
                                <p class="font-bold text-[#87CEFA] text-lg">{{ number_format($hotel->rooms_min_price) }}<span class="text-xs font-normal text-[#C9D3DD]">đ/đêm</span></p>
                            </div>
                            <button class="btn-primary px-4 py-2 rounded-xl text-sm font-semibold pointer-events-none">
                                Xem chi tiết
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>