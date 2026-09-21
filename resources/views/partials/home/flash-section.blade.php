<div class="max-w-7xl mx-auto px-4 flash-wrapper mt-12">
    <div class="flash-banner" id="flashCarousel">
        <div class="flash-slide active"><div class="flash-bg" style="background-image: url('{{ asset("image/bannerdeal(1).png") }}');"></div></div>
        <div class="flash-slide"><div class="flash-bg" style="background-image: url('{{ asset("image/bannerdeal(2).png") }}');"></div></div>
        <div class="flash-slide"><div class="flash-bg" style="background-image: url('{{ asset("image/bannerdeal(3).png") }}');"></div></div>
        
        <div class="flash-overlay"></div>
        <div class="flash-content">
            <div class="w-full md:w-2/3 lg:w-1/2">
                <div class="flash-badge">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.381z" clip-rule="evenodd"></path></svg>
                    Flash Sale Cuối Tuần
                </div>
                <h2 class="text-white text-3xl md:text-5xl font-black mb-3 leading-tight" style="font-family: var(--font-sans, 'Nunito Sans', sans-serif);">
                    MÙA HÈ RỰC RỠ<br>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#FCD34D] to-[#F59E0B]">GIẢM ĐẾN 50%</span>
                </h2>
                <p class="text-gray-300 text-sm md:text-base mb-6 max-w-md">Ưu đãi khủng cho hàng trăm khách sạn cao cấp toàn quốc. Nhanh tay săn deal giá hời trước khi kết thúc!</p>
                <a href="/deals" class="inline-flex items-center gap-2 bg-gradient-to-r from-[#F59E0B] to-[#EF4444] text-white font-bold px-8 py-3.5 rounded-full transition-all hover:scale-105 hover:shadow-lg">
                    XEM ƯU ĐÃI NGAY <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                </a>
            </div>
        </div>

        <button class="flash-arrow flash-arrow-prev" id="flashPrev" aria-label="Previous"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg></button>
        <button class="flash-arrow flash-arrow-next" id="flashNext" aria-label="Next"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>

        <div class="flash-dots" id="flashDots">
            <span class="flash-dot active" data-index="0"></span>
            <span class="flash-dot" data-index="1"></span>
            <span class="flash-dot" data-index="2"></span>
        </div>

        <div class="countdown-container hidden md:flex">
            <div class="text-right mr-2"><span class="block text-white text-xs font-bold uppercase tracking-wider">Kết thúc sau</span></div>
            <div class="countdown-box"><div class="text-[#FCD34D] text-2xl font-black leading-none" id="cd-hours">00</div><div class="text-white/60 text-[10px] font-bold uppercase mt-1">Giờ</div></div>
            <div class="countdown-box"><div class="text-[#FCD34D] text-2xl font-black leading-none" id="cd-mins">00</div><div class="text-white/60 text-[10px] font-bold uppercase mt-1">Phút</div></div>
            <div class="countdown-box"><div class="text-[#FCD34D] text-2xl font-black leading-none" id="cd-secs">00</div><div class="text-white/60 text-[10px] font-bold uppercase mt-1">Giây</div></div>
        </div>
    </div>
</div>