{{-- resources/views/partials/home/hotels/featured.blade.php --}}
<section class="max-w-7xl mx-auto px-4 py-16">
    <div class="mb-10 flex flex-col items-center">
        <!-- Badge mới: Tone xanh Blue/Glass đồng bộ UI -->
        <div class="inline-flex items-center gap-2 px-5 py-2 rounded-full bg-blue-50 border border-blue-100 shadow-sm mb-4">
            <svg class="w-5 h-5 text-blue-600 animate-pulse" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
            </svg>
            <span class="text-blue-800 font-bold tracking-wide uppercase text-sm">Khách sạn nổi bật</span>
        </div>
        <h2 class="text-3xl md:text-4xl font-bold text-slate-900 font-display">Trải nghiệm đẳng cấp nhất</h2>
    </div>

    <!-- Swiper Container -->
    <div class="swiper featuredSwiper pb-12">
        <div class="swiper-wrapper">
            @foreach($hotels->take(10) as $hotel)
                <div class="swiper-slide transition-all duration-500">
                    @include('partials.home.card', ['hotel' => $hotel])
                </div>
            @endforeach
        </div>
    </div>
</section>

<style>
    .featuredSwiper .swiper-slide {
        opacity: 2;
        transform: scale(0.85);
        filter: blur(2.5px);
        transition: all 0.5s ease-out;
    }
    .featuredSwiper .swiper-slide-active {
        opacity: 2;
        transform: scale(1.05);
        filter: blur(0);
        z-index: 10;
    }
    /* Card kế bên (prev/next gần) */
    .featuredSwiper .swiper-slide-prev,
    .featuredSwiper .swiper-slide-next {
        opacity: 0.75;
        filter: blur(1.5px);
    }
    .swiper-pagination-bullet-active {
        background: #87CEFA !important;
        width: 24px !important;
        border-radius: 5px !important;
    }
</style>