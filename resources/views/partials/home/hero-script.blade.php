<script>
// ========================
// HERO CAROUSEL
// ========================
(function () {
    const carousel  = document.getElementById('heroCarousel');
    const slides    = carousel.querySelectorAll('.hero-slide');
    const dots      = carousel.querySelectorAll('.hero-dot');
    const btnPrev   = document.getElementById('heroPrev');
    const btnNext   = document.getElementById('heroNext');
    let current     = 0;
    let timer       = null;
    const INTERVAL  = 4500;

    if(!carousel) return; // Bảo vệ lỗi nếu thiếu DOM

    function goTo(idx) {
        slides[current].classList.remove('active');
        dots[current].classList.remove('active');
        current = (idx + slides.length) % slides.length;
        slides[current].classList.add('active');
        dots[current].classList.add('active');
    }

    function start() { timer = setInterval(() => goTo(current + 1), INTERVAL); }
    function stop() { clearInterval(timer); }

    btnPrev.addEventListener('click', () => { stop(); goTo(current - 1); start(); });
    btnNext.addEventListener('click', () => { stop(); goTo(current + 1); start(); });

    dots.forEach(dot => {
        dot.addEventListener('click', () => { stop(); goTo(parseInt(dot.dataset.index)); start(); });
    });

    carousel.addEventListener('mouseenter', stop);
    carousel.addEventListener('mouseleave', start);
    start();
})();

// ========================
// SEARCH AUTOCOMPLETE
// ========================
// (Paste toàn bộ khối (function () { ... buildHotelDataFromDOM ... })() của Search từ file cũ vào đây)
</script>