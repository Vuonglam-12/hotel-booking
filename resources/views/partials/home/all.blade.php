<section class="max-w-7xl mx-auto px-4 py-10 border-t border-[#EAF3FF]">
    <div class="flex items-center justify-between mb-8">
        <h2 class="font-display text-2xl font-bold text-[#1E3A5F]">Khám phá tất cả khách sạn</h2>
        @if(request()->hasAny(['search', 'city', 'star']))
            <a href="{{ route('home') }}" class="text-sm text-[#C9D3DD] underline flex items-center gap-1 transition">Xóa bộ lọc</a>
        @endif
    </div>

    <!-- Đã thêm id="allHotelsGrid" ở đây để JS nhận diện đúng -->
    <div id="allHotelsGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($hotels as $hotel)
            @include('partials.home.card', ['hotel' => $hotel])
        @empty
            <div class="col-span-3 text-center py-20 text-[#C9D3DD]">Không tìm thấy khách sạn phù hợp</div>
        @endforelse
    </div>

    {{-- KHÔI PHỤC FULL NÚT LOAD MORE & TRẠNG THÁI --}}
    @if($hotels->hasMorePages())
    <div class="mt-10 text-center" id="loadMoreContainer">
        <button id="loadMoreBtn" 
                data-next-page="{{ $hotels->currentPage() + 1 }}"
                data-last-page="{{ $hotels->lastPage() }}"
                data-total="{{ $hotels->total() }}"
                data-per-page="{{ $hotels->perPage() }}"
                class="load-more-btn bg-white border-2 border-[#0E5ED8] text-[#0E5ED8] hover:bg-[#0E5ED8] hover:text-white font-bold px-8 py-3.5 rounded-full transition-all">
            XEM THÊM KHÁCH SẠN
        </button>
        <div class="mt-3 text-sm text-[#94A3B8]" id="loadMoreStatus">
            Đã hiển thị <span id="shownCount">{{ $hotels->count() }}</span>/<span id="totalCount">{{ $hotels->total() }}</span> khách sạn
        </div>
    </div>
    @elseif($hotels->total() > 0)
    <div class="mt-10 text-center text-[#C9D3DD] text-sm">
        <div class="inline-flex items-center gap-2 bg-[#F8FAFC] px-6 py-3 rounded-full">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            Đã hiển thị tất cả <span id="totalCount">{{ $hotels->total() }}</span> khách sạn
        </div>
    </div>
    @endif
</section>