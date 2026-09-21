<section>
    <div class="mb-6">
        <h2 class="section-title">📦 Combo tiết kiệm</h2>
        <p class="section-sub">Tiết kiệm hơn khi đặt gói dịch vụ</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @php
            $comboLabels = [
                ['icon'=>'🌅', 'tag'=>'Tiết kiệm 20%', 'title'=>'Nghỉ dưỡng + Ăn sáng', 'desc'=>'Bữa sáng hàng ngày cho 2 người'],
                ['icon'=>'🗺️', 'tag'=>'Tiết kiệm 35%', 'title'=>'Khám phá điểm đến', 'desc'=>'Bao gồm vé tham quan nổi tiếng'],
                ['icon'=>'🚗', 'tag'=>'Tiết kiệm 15%', 'title'=>'Thoải mái di chuyển', 'desc'=>'Đón sân bay 2 chiều'],
            ];
        @endphp
        @foreach($combos as $i => $hotel)
        @php $label = $comboLabels[$i] ?? $comboLabels[0]; @endphp
        <div class="combo-card" onclick="window.location.href='/hotels/{{ $hotel->id }}'">
            <div class="relative h-48 overflow-hidden">
                <img src="https://picsum.photos/seed/combo{{ $hotel->id }}/500/300" alt="{{ $hotel->name }}"
                    class="w-full h-full object-cover transition duration-500 hover:scale-105">
                <div style="position:absolute;top:12px;left:12px;background:linear-gradient(135deg,#1E3A5F,#0F3460);color:#FCD34D;font-size:11px;font-weight:700;padding:4px 10px;border-radius:12px;">
                    {{ $label['icon'] }} {{ $label['tag'] }}
                </div>
            </div>
            <div class="p-5">
                <h3 class="font-bold text-[#1E3A5F] text-base mb-1">{{ $label['title'] }}</h3>
                <p class="text-[#C9D3DD] text-xs mb-1">{{ $label['desc'] }}</p>
                <p class="text-[#87CEFA] text-xs font-semibold mb-3">🏨 {{ $hotel->name }} — {{ $hotel->location->name }}</p>
                <div class="flex items-center justify-between border-t border-[#EAF3FF] pt-3">
                    <div>
                        <p class="text-[#C9D3DD] text-xs">Từ</p>
                        <p class="text-[#1E3A5F] text-lg font-black">{{ number_format($hotel->rooms_min_price ?? 0) }}<span class="text-xs font-normal text-[#C9D3DD]">đ/đêm</span></p>
                    </div>
                    <button class="bg-[#87CEFA] text-[#1E3A5F] text-xs font-bold px-4 py-2 rounded-xl hover:bg-[#7BC4F5] transition">
                        Chọn combo
                    </button>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</section>