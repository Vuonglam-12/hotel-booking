@if($hotel->amenities->isNotEmpty())
<div class="info-card bg-white rounded-2xl p-6 shadow-sm border border-[#C9D3DD]/30">
    <h2 class="font-display text-xl font-bold text-[#3A4A5A] section-title flex items-center gap-2">
        <svg class="w-6 h-6 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path></svg>
        Tiện ích nổi bật
    </h2>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
        @foreach($hotel->amenities as $amenity)
            @php
                $amenityIcons = [
                    'wifi' => ['svg' => '<path d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>', 'name' => 'WiFi'],
                    'pool' => ['svg' => '<path d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4"></path>', 'name' => 'Hồ bơi'],
                    // ... các icons khác copy từ file gốc ...
                ];
                $info = $amenityIcons[$amenity->amenity] ?? ['svg' => '<path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>', 'name' => str_replace('_', ' ', $amenity->amenity)];
            @endphp
            <div class="flex items-center gap-2 text-sm text-[#3A4A5A] p-2 rounded-lg amenity-icon">
                <svg class="w-5 h-5 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24">{!! $info['svg'] !!}</svg>
                <span class="capitalize">{{ $info['name'] }}</span>
            </div>
        @endforeach
    </div>
</div>
@endif