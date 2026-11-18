@php
    $mapQuery = urlencode(($hotel->address ?? $hotel->name ?? 'khách sạn') . ', ' . ($hotel->location->name ?? 'Việt Nam'));
    $mapUrl = !empty($hotel->latitude) && !empty($hotel->longitude)
        ? "https://www.google.com/maps?q={$hotel->latitude},{$hotel->longitude}&z=14&output=embed"
        : "https://www.google.com/maps?q={$mapQuery}&output=embed";
@endphp

<section class="info-card bg-white rounded-[28px] border border-slate-200/80 shadow-[0_12px_30px_rgba(15,23,42,0.04)] p-6 md:p-7">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-5">
        <h2 class="text-2xl font-bold text-slate-900 flex items-center gap-3">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 21s-6-5.686-6-11a6 6 0 1112 0c0 5.314-6 11-6 11zm0-8a2 2 0 100-4 2 2 0 000 4z" /></svg>
            </span>
            Vị trí khách sạn
        </h2>
        <a href="https://www.google.com/maps/search/?api=1&query={{ $mapQuery }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-100">
            Chỉ đường
        </a>
    </div>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1.2fr)_260px] items-start">
        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-slate-100">
            <iframe
                src="{{ $mapUrl }}"
                width="100%"
                height="260"
                style="border:0;"
                allowfullscreen=""
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                title="Bản đồ khách sạn {{ $hotel->name }}">
            </iframe>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs uppercase tracking-[0.12em] text-slate-500 mb-2">Địa chỉ</p>
            <p class="text-base font-semibold text-slate-900">{{ $hotel->address }}</p>
            <p class="mt-3 text-sm text-slate-500">{{ $hotel->location?->name }}</p>
        </div>
    </div>
</section>
