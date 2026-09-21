<div class="info-card bg-white rounded-2xl p-6 shadow-sm border border-[#C9D3DD]/30">
    <h2 class="font-display text-xl font-bold text-[#3A4A5A] section-title flex items-center gap-2">
        <svg class="w-6 h-6 text-[#87CEFA]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
        </svg>
        Loại phòng
    </h2>

    @if($hotel->rooms->isEmpty())
        <div class="text-center py-10 text-[#C9D3DD]">
            <div class="text-4xl mb-3">🛏️</div>
            <p class="text-sm font-medium text-[#3A4A5A]">Chưa có phòng nào</p>
            <p class="text-xs mt-1">Khách sạn này chưa cập nhật danh sách phòng.</p>
            <p class="text-xs mt-1">Vui lòng liên hệ trực tiếp để biết thêm thông tin.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($hotel->rooms->unique('room_type_id') as $room)
                @php
                    $totalCount     = $hotel->rooms->where('room_type_id', $room->room_type_id)->count();
                    $availableCount = $hotel->rooms->where('room_type_id', $room->room_type_id)->where('status', 'available')->count();
                    $lockedCount    = $hotel->rooms->where('room_type_id', $room->room_type_id)->where('status', 'locked')->count();
                @endphp
                <div class="room-item flex items-center justify-between p-4 rounded-xl border border-[#C9D3DD]/30 hover:border-[#87CEFA] transition"
                    data-room-type-id="{{ $room->room_type_id }}"
                    data-total="{{ $totalCount }}"
                    data-avail="{{ $availableCount }}"
                    onclick="viewRoom({{ $room->room_type_id }}, '{{ addslashes($room->roomType->name) }}', {{ $room->price }}, {{ $hotel->id }}, this)">
                    <div class="flex items-center gap-3">
                        <div class="w-16 h-16 rounded-xl overflow-hidden flex-shrink-0 bg-[#EAF3FF]">
                            <img src="https://picsum.photos/seed/room-{{ $hotel->id }}-{{ $room->room_type_id }}/120/120"
                                 alt="{{ $room->roomType->name }}" class="w-full h-full object-cover">
                        </div>
                        <div>
                            <p class="font-semibold text-[#3A4A5A]">{{ $room->roomType->name }}</p>
                            <div class="flex items-center gap-2 mt-1 flex-wrap">
                                <span class="text-xs text-[#C9D3DD]">
                                    Tổng: <strong>{{ $totalCount }}</strong> |
                                    Trống: <strong class="room-avail-badge-{{ $room->room_type_id }}">{{ $availableCount }}</strong>
                                </span>
                            </div>
                            <div class="flex items-center gap-3 mt-1 flex-wrap">
                                @if($availableCount > 0)
                                    <span class="room-status-badge-{{ $room->room_type_id }} text-xs bg-green-50 text-green-700 border border-green-200 rounded-full px-2 py-0.5">
                                        Còn {{ $availableCount }} phòng
                                    </span>
                                @elseif($lockedCount > 0)
                                    <span class="room-status-badge-{{ $room->room_type_id }} text-xs bg-amber-50 text-amber-700 border border-amber-200 rounded-full px-2 py-0.5">
                                        Đang giữ chỗ
                                    </span>
                                @else
                                    <span class="room-status-badge-{{ $room->room_type_id }} text-xs bg-red-50 text-red-600 border border-red-200 rounded-full px-2 py-0.5">
                                        Hết phòng
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <button onclick="event.stopPropagation(); viewRoom({{ $room->room_type_id }}, '{{ addslashes($room->roomType->name) }}', {{ $room->price }}, {{ $hotel->id }}, document.querySelector('[data-room-type-id=\'{{ $room->room_type_id }}\']'))"
                            class="btn-primary text-xs px-3 py-1.5 rounded-lg mt-1 inline-flex items-center gap-1 click-effect">
                            Xem phòng
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>