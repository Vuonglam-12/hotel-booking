@php
    $introText = strip_tags($hotel->description ?? '');
    $shortIntro = Str::limit(strip_tags($introText), 220);
@endphp

<section class="info-card bg-white rounded-[28px] border border-slate-200/80 shadow-[0_12px_30px_rgba(15,23,42,0.04)] p-6 md:p-7">
    <div class="mb-4 flex items-center gap-3">
        <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        </span>
        <h2 class="text-2xl font-bold text-slate-900">Giới thiệu</h2>
    </div>

    <div class="space-y-4 text-slate-600">
        <p id="hotel-intro" class="leading-8 text-base">{{ $shortIntro ?: 'Chưa có mô tả khách sạn.' }}</p>
        @if(mb_strlen($introText) > 220)
        <button type="button" class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3 py-1.5 text-sm font-semibold text-blue-700 transition hover:bg-blue-100" onclick="document.getElementById('hotel-intro').textContent = @js($introText); this.remove()">
            Xem thêm
        </button>
        @endif
    </div>
</section>
