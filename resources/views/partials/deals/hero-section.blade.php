<div class="deals-banner" id="dealsCarousel">
    <div class="deals-slide active">
        <img src="{{ asset('image/deal1.png') }}" alt="Deal Banner 1">
    </div>
    <div class="deals-slide">
        <img src="{{ asset('image/deal2.png') }}" alt="Deal Banner 2">
    </div>
    <div class="deals-slide">
        <img src="{{ asset('image/deal3.jpg') }}" alt="Deal Banner 3">
    </div>

    <div class="deals-banner-overlay"></div>

    <div class="deals-banner-content">
        <div class="max-w-lg">
            <div class="deals-fs-badge">
                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.381z" clip-rule="evenodd"/>
                </svg>
                Flash Sale Cuối Tuần
            </div>

            <h1 class="text-white font-black leading-tight drop-shadow-2xl"
                style="font-family:var(--font-sans, 'Nunito Sans', sans-serif); font-size: clamp(2rem, 4vw, 3.5rem);">
                MÙA HÈ RỰC RỠ<br>
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#FCD34D] to-[#F59E0B]">
                    GIẢM ĐẾN 50%
                </span>
            </h1>

            <p class="text-white/80 mt-3 text-base md:text-lg drop-shadow-md max-w-sm">
                Ưu đãi khủng cho hàng trăm khách sạn &amp; resort cao cấp toàn quốc
            </p>

            <a href="#flash-grid"
               class="inline-flex items-center gap-2 mt-5 bg-gradient-to-r from-[#F59E0B] to-[#EF4444]
                      text-white font-bold px-7 py-3 rounded-full shadow-lg
                      hover:scale-105 hover:shadow-xl transition-all text-sm">
                XEM ƯU ĐÃI NGAY
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                </svg>
            </a>

            <div class="deals-cd-wrap">
                <span class="text-white/70 text-xs font-semibold uppercase tracking-wider">Kết thúc sau:</span>
                <div class="deals-cd-box">
                    <div class="deals-cd-num" id="dc-h">00</div>
                    <div class="deals-cd-unit">Giờ</div>
                </div>
                <span class="deals-cd-sep">:</span>
                <div class="deals-cd-box">
                    <div class="deals-cd-num" id="dc-m">00</div>
                    <div class="deals-cd-unit">Phút</div>
                </div>
                <span class="deals-cd-sep">:</span>
                <div class="deals-cd-box">
                    <div class="deals-cd-num" id="dc-s">00</div>
                    <div class="deals-cd-unit">Giây</div>
                </div>
            </div>
        </div>
    </div>

    <button class="deals-arrow deals-arrow-prev" id="dealsPrev" aria-label="Previous">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    </button>
    <button class="deals-arrow deals-arrow-next" id="dealsNext" aria-label="Next">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
    </button>

    <div class="deals-dots" id="dealsDots">
        <span class="deals-dot active" data-index="0"></span>
        <span class="deals-dot" data-index="1"></span>
        <span class="deals-dot" data-index="2"></span>
    </div>
</div>