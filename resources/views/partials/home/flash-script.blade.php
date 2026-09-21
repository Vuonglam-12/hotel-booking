<script>
// ========================
// FLASH DEAL CAROUSEL & COUNTDOWN
// ========================
(function () {
    const el        = document.getElementById('flashCarousel');
    if(!el) return;
    const slides    = el.querySelectorAll('.flash-slide');
    const dots      = el.querySelectorAll('.flash-dot');
    const btnPrev   = document.getElementById('flashPrev');
    const btnNext   = document.getElementById('flashNext');
    let current     = 0;
    let timer       = null;
    const INTERVAL  = 4000;

    function goTo(idx) {
        slides[current].classList.remove('active');
        dots[current].classList.remove('active');
        current = (idx + slides.length) % slides.length;
        slides[current].classList.add('active');
        dots[current].classList.add('active');
    }

    function start() { timer = setInterval(() => goTo(current + 1), INTERVAL); }
    function stop()  { clearInterval(timer); }

    btnPrev.addEventListener('click', () => { stop(); goTo(current - 1); start(); });
    btnNext.addEventListener('click', () => { stop(); goTo(current + 1); start(); });
    dots.forEach(d  => d.addEventListener('click', () => { stop(); goTo(parseInt(d.dataset.index)); start(); }));
    el.addEventListener('mouseenter', stop);
    el.addEventListener('mouseleave', start);
    start();
})();

// COUNTDOWN
function updateCountdown() {
    const now = new Date();
    const endOfDay = new Date(now);
    endOfDay.setHours(23, 59, 59, 0);

    let diff = endOfDay - now;
    if (diff <= 0) {
        const tomorrow = new Date(now);
        tomorrow.setDate(now.getDate() + 1);
        tomorrow.setHours(23, 59, 59, 0);
        diff = tomorrow - now;
        if (diff <= 0) return;
    }

    const h = Math.floor(diff / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    const s = Math.floor((diff % 60000) / 1000);

    const elH = document.getElementById('cd-hours');
    const elM = document.getElementById('cd-mins');
    const elS = document.getElementById('cd-secs');

    if(elH) elH.textContent = String(h).padStart(2, '0');
    if(elM) elM.textContent = String(m).padStart(2, '0');
    if(elS) elS.textContent = String(s).padStart(2, '0');
}
updateCountdown();
setInterval(updateCountdown, 1000);
</script>