<div class="info-card bg-white rounded-2xl p-6 shadow-sm border border-[#C9D3DD]/30">
    <h2 class="font-display text-xl font-bold text-[#3A4A5A] section-title flex items-center gap-2">
        <svg class="w-6 h-6 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        Giới thiệu
    </h2>
    <p class="text-[#3A4A5A]/80 leading-relaxed">{{ $hotel->description ?? 'Khách sạn ' . $hotel->star_rating . ' sao tại ' . $hotel->location->name . ' với không gian sang trọng.' }}</p>
    <div class="grid grid-cols-2 gap-4 mt-5 pt-4 border-t border-[#C9D3DD]/30">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-[#EAF3FF] flex items-center justify-center">
                <svg class="w-5 h-5 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <p class="text-xs text-[#C9D3DD]">Check-in</p>
                <p class="font-semibold text-[#3A4A5A]">{{ substr($hotel->check_in_time, 0, 5) }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-[#EAF3FF] flex items-center justify-center">
                <svg class="w-5 h-5 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <p class="text-xs text-[#C9D3DD]">Check-out</p>
                <p class="font-semibold text-[#3A4A5A]">{{ substr($hotel->check_out_time, 0, 5) }}</p>
            </div>
        </div>
    </div>
</div>