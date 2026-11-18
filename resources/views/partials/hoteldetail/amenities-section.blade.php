@php
    $amenityMap = [
        'wifi' => ['name' => 'WiFi', 'icon' => 'M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0'],
        'pool' => ['name' => 'Hồ bơi', 'icon' => 'M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4'],
        'gym' => ['name' => 'Phòng gym', 'icon' => 'M4 7h3v10H4zm13 0h3v10h-3zM7 9h10v6H7z'],
        'parking' => ['name' => 'Bãi đậu xe', 'icon' => 'M5 6h8a4 4 0 010 8H5zm0 0v12'],
        'restaurant' => ['name' => 'Nhà hàng', 'icon' => 'M3 2v7a3 3 0 006 0V2m3 0v9m0-9v7a3 3 0 006 0V2'],
        'spa' => ['name' => 'Spa', 'icon' => 'M12 3c2.5 2.5 2.5 5 0 7.5C9.5 13 9.5 15.5 12 18c2.5-2.5 2.5-5 0-7.5 2.5-2.5 2.5-5 0-7.5zM5 19h14'],
        'breakfast' => ['name' => 'Bữa sáng', 'icon' => 'M4 12h16m-1 0V9a2 2 0 00-2-2H7a2 2 0 00-2 2v3m12 0v3a2 2 0 01-2 2H9a2 2 0 01-2-2v-3'],
        'room_service' => ['name' => 'Room service', 'icon' => 'M4 10h16M7 10V7a5 5 0 0110 0v3m-9 0h12v7H7z'],
        'bar' => ['name' => 'Quầy bar', 'icon' => 'M5 3h14l-7 9-7-9zm7 9v9m-4 0h8'],
        'airport_shuttle' => ['name' => 'Đưa đón sân bay', 'icon' => 'M3 7h18v11H3zM6 18v3m12-3v3M6 11h3m6 0h3'],
    ];

    $featuredAmenities = $hotel->amenities->take(6);
    $allAmenities = $hotel->amenities;
@endphp

@if($allAmenities->isNotEmpty())
<section class="info-card bg-white rounded-[28px] border border-slate-200/80 shadow-[0_12px_30px_rgba(15,23,42,0.04)] p-6 md:p-7">
    <div class="mb-5 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" /></svg>
            </span>
            <h2 class="text-2xl font-bold text-slate-900">Tiện nghi</h2>
        </div>
        <button type="button" onclick="document.getElementById('amenity-modal').classList.remove('hidden')" class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">
            Xem tất cả tiện nghi
        </button>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($featuredAmenities as $amenity)
            @php
                $key = \App\Support\HotelAmenityCatalog::normalize($amenity->amenity);
                $name = \App\Support\HotelAmenityCatalog::labels()[$key] ?? $key;
                $icon = $amenityMap[$key]['icon'] ?? 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z';
            @endphp
            <div class="flex items-center gap-3 px-1 py-2">
                <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $icon }}" /></svg>
                <span class="text-sm font-semibold text-slate-700">{{ $name }}</span>
            </div>
        @endforeach
    </div>
</section>

<div id="amenity-modal" role="dialog" aria-modal="true" aria-label="Tất cả tiện nghi" class="fixed inset-0 z-50 hidden bg-slate-900/40 p-4 backdrop-blur-sm">
    <div class="mx-auto max-h-[90vh] max-w-4xl overflow-y-auto rounded-[28px] bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
            <h3 class="text-xl font-bold text-slate-900">Tất cả tiện nghi</h3>
            <button type="button" onclick="document.getElementById('amenity-modal').classList.add('hidden')" aria-label="Đóng danh sách tiện nghi" class="text-2xl text-slate-400 hover:text-slate-600">×</button>
        </div>
        <div class="grid gap-6 p-6 md:grid-cols-2">
            @php
                $groups = [
                    'Dịch vụ' => ['wifi','breakfast','room_service','spa'],
                    'Giải trí' => ['pool','gym'],
                    'Di chuyển' => ['parking','airport_shuttle'],
                    'Ăn uống' => ['restaurant','bar'],
                ];
                $knownKeys = array_merge(...array_values($groups));
                $otherKeys = $allAmenities->map(fn($amenity) => \App\Support\HotelAmenityCatalog::normalize($amenity->amenity))
                    ->diff($knownKeys)->unique()->values()->all();
                if ($otherKeys) $groups['Tiện nghi khác'] = $otherKeys;
            @endphp
            @foreach($groups as $groupName => $groupKeys)
                @php $items = $allAmenities->filter(fn($amenity) => in_array(\App\Support\HotelAmenityCatalog::normalize($amenity->amenity), $groupKeys, true)); @endphp
                @if($items->isNotEmpty())
                    <div class="p-2">
                        <h4 class="mb-3 text-base font-bold text-slate-900">{{ $groupName }}</h4>
                        <div class="space-y-2">
                            @foreach($items as $amenity)
                                @php
                                    $key = \App\Support\HotelAmenityCatalog::normalize($amenity->amenity);
                                    $name = \App\Support\HotelAmenityCatalog::labels()[$key] ?? $key;
                                    $icon = $amenityMap[$key]['icon'] ?? 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z';
                                @endphp
                                <div class="flex items-center gap-3 rounded-xl bg-white px-3 py-2">
                                    <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $icon }}" /></svg>
                                    <span class="text-sm font-medium text-slate-700">{{ $name }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</div>
@endif
