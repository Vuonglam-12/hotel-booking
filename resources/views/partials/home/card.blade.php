<div class="hotel-card bg-white rounded-2xl overflow-hidden shadow-sm border border-[#C9D3DD]/30 click-effect cursor-pointer"
    onclick="window.location.href='/hotels/{{ $hotel->id }}'">

    <div class="relative h-52 bg-[#EAF3FF] overflow-hidden">
        <img src="https://picsum.photos/seed/{{ $hotel->id }}/800/600" alt="{{ $hotel->name }}"
            class="w-full h-full object-cover transition duration-500 hover:scale-110">
        <button onclick="event.stopPropagation(); toggleWishlist({{ $hotel->id }}, this)"
            class="absolute top-3 right-3 bg-white/95 backdrop-blur-sm w-8 h-8 rounded-full flex items-center justify-center text-[#C9D3DD] hover:text-red-500 transition wishlist-btn shadow-sm"
            data-hotel="{{ $hotel->id }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
            </svg>
        </button>
    </div>

    <div class="p-5">
        <div class="flex items-start justify-between gap-2">
            <div class="flex-1">
                <h3 class="font-semibold text-[#1E3A5F] text-base leading-snug">{{ $hotel->name }}</h3>
                <p class="text-[#C9D3DD] text-xs mt-1.5 flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path></svg>
                    {{ $hotel->location->name }}
                </p>
            </div>
            <div class="flex items-center gap-1 shrink-0 rating-badge">
                <svg class="w-4 h-4 rating-star" fill="#F59E0B" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                <span class="text-sm font-bold rating-score">{{ $hotel->avg_rating }}</span>
            </div>
        </div>
        <div class="mt-4 pt-3 border-t border-[#EAF3FF] flex items-center justify-between">
            <div>
                @if($hotel->rooms_min_price)
                    <p class="text-xs text-[#C9D3DD]">Giá từ</p>
                    <p class="font-bold text-[#87CEFA] text-lg">{{ number_format($hotel->rooms_min_price) }}<span class="text-xs font-normal text-[#C9D3DD]">/đêm</span></p>
                @else
                    <p class="text-xs text-[#C9D3DD]">Liên hệ</p>
                @endif
            </div>
            <span class="text-xs btn-primary px-4 py-2 rounded-lg font-semibold inline-flex items-center gap-1">Xem chi tiết <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></span>
        </div>
    </div>
</div>