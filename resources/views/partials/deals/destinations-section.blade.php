<section>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="section-title">📍 Ưu đãi theo điểm đến</h2>
            <p class="section-sub">Khám phá các ưu đãi hấp dẫn tại những điểm đến yêu thích</p>
        </div>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        @foreach($destinations as $dest)
        <a href="{{ route('home') }}?city={{ urlencode($dest->name) }}" class="dest-card block">
            <img src="https://picsum.photos/seed/dest{{ $dest->id }}/300/200" alt="{{ $dest->name }}">
            <div class="dest-overlay"></div>
            <div class="dest-info">
                <div style="background:linear-gradient(135deg,#EF4444,#F59E0B);color:white;font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;display:inline-block;margin-bottom:4px;">
                    Giảm đến {{ $dest->discount }}%
                </div>
                <p class="text-white text-sm font-bold">{{ $dest->name }}</p>
                <p class="text-white/70 text-xs">{{ $dest->hotels_count }} khách sạn</p>
            </div>
        </a>
        @endforeach
    </div>
</section>