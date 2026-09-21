<script>

    document.addEventListener('DOMContentLoaded', function() {
        const swiper = new Swiper(".hotelGallerySwiper", {
            // ----- CẬP NHẬT CHỖ NÀY -----
            centeredSlides: true,  // Căn slide active vào giữa màn hình
            slidesPerView: "auto", // Quan trọng: Cho phép slide to nhỏ tự do theo CSS mày viết
            spaceBetween: 20,      // Khoảng cách giữa các ảnh (tùy chỉnh)
            // --------------------------
            loop: true,             
            autoplay: {
                delay: 3500,
                disableOnInteraction: false,
            },
            // Thêm các tính năng khác nếu cần (navigation, pagination)
            pagination: {
                el: ".swiper-pagination",
                clickable: true,
            },
                        navigation: {
                nextEl: ".swiper-button-next",
                prevEl: ".swiper-button-prev",
            },
            // Responsive config
            breakpoints: {
                576: { slidesPerView: 2 },
                768: { slidesPerView: 3 },
                1200: { slidesPerView: 4 } // Hiển thị 4 ảnh trên màn hình lớn cho gọn
            }
        });
    });
</script>