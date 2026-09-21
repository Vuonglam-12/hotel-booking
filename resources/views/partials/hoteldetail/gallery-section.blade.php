<section class="gallery-carousel-wrapper py-8 overflow-hidden">
    <div class="max-w-7xl mx-auto px-4">
        <div class="swiper hotelGallerySwiper">
            <div class="swiper-wrapper">
                @for ($i = 1; $i <= 10; $i++)
                    <div class="swiper-slide">
                        <div class="gallery-slide-inner overflow-hidden rounded-2xl shadow-md bg-white">
                            <img src="https://picsum.photos/seed/hotel-{{ $hotel->id }}-{{ $i }}/600/400"
                                 alt="Hotel image {{ $i }}"
                                 class="w-full h-64 object-cover">
                        </div>
                    </div>
                @endfor
            </div>
        </div>
    </div>
</section>

<style>
    .hotelGallerySwiper {
        padding: 20px 0 10px !important;
        overflow: visible !important;
    }

    /* Tất cả slide mặc định: thu nhỏ + mờ */
    .hotelGallerySwiper .swiper-slide {
        transform: scale(0.85);
        opacity: 0.4;
        filter: blur(1.5px);
        transition: transform 0.4s ease, opacity 0.4s ease, filter 0.4s ease;
        cursor: pointer;
    }

    /* Slide active: nổi lên, rõ nét */
    .hotelGallerySwiper .swiper-slide-active {
        transform: scale(1);
        opacity: 1;
        filter: blur(0);
        z-index: 2;
    }

    /* Slide prev/next: hơi mờ nhẹ hơn các slide xa */
    .hotelGallerySwiper .swiper-slide-prev,
    .hotelGallerySwiper .swiper-slide-next {
        transform: scale(0.92);
        opacity: 0.65;
        filter: blur(0.5px);
    }
    
</style>