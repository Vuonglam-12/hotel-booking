<style>
    .deals-banner { position: relative; width: 100%; height: 560px; overflow: hidden; }
    .deals-slide { position: absolute; inset: 0; opacity: 0; transition: opacity 1.1s cubic-bezier(0.4, 0, 0.2, 1); }
    .deals-slide.active { opacity: 1; }
    .deals-slide img { width: 100%; height: 100%; object-fit: cover; object-position: center center; transform: scale(1.03); transition: transform 6s ease; display: block; }
    .deals-slide.active img { transform: scale(1); }
    .deals-banner-overlay { position: absolute; inset: 0; background: linear-gradient(105deg, rgba(10, 18, 38, 0.82) 0%, rgba(10, 18, 38, 0.55) 42%, rgba(10, 18, 38, 0.08) 100%); z-index: 1; }
    .deals-banner-content { position: relative; z-index: 10; height: 100%; display: flex; align-items: center; padding: 0 6%; max-width: 1280px; margin: 0 auto; width: 100%; }
    .deals-fs-badge { display: inline-flex; align-items: center; gap: 6px; background: linear-gradient(135deg, #F59E0B, #EF4444); color: white; font-size: 12px; font-weight: 800; padding: 5px 14px; border-radius: 20px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 14px; box-shadow: 0 4px 16px rgba(239,68,68,0.45); animation: pulse-badge 2s ease-in-out infinite; }
    @keyframes pulse-badge { 0%, 100% { box-shadow: 0 4px 16px rgba(239,68,68,0.45); } 50% { box-shadow: 0 4px 28px rgba(239,68,68,0.7); } }
    .deals-cd-wrap { display: flex; align-items: center; gap: 10px; margin-top: 22px; }
    .deals-cd-box { background: rgba(255,255,255,0.12); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.28); border-radius: 10px; padding: 8px 14px; text-align: center; min-width: 58px; }
    .deals-cd-num  { color: #FCD34D; font-size: 22px; font-weight: 900; font-family: monospace; line-height: 1; }
    .deals-cd-unit { color: rgba(255,255,255,0.55); font-size: 9px; font-weight: 700; text-transform: uppercase; margin-top: 3px; }
    .deals-cd-sep  { color: #FCD34D; font-size: 22px; font-weight: 900; line-height: 1; }
    .deals-arrow { position: absolute; top: 50%; transform: translateY(-50%); z-index: 20; background: rgba(0,0,0,0.22); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.3); color: #fff; width: 46px; height: 46px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s, transform 0.2s; }
    .deals-arrow:hover { background: rgba(0,0,0,0.42); transform: translateY(-50%) scale(1.08); }
    .deals-arrow-prev { left: 20px; }
    .deals-arrow-next { right: 20px; }
    .deals-dots { position: absolute; bottom: 18px; left: 50%; transform: translateX(-50%); z-index: 20; display: flex; gap: 8px; align-items: center; }
    .deals-dot { width: 8px; height: 8px; border-radius: 50%; background: rgba(255,255,255,0.45); border: 1px solid rgba(255,255,255,0.6); cursor: pointer; transition: all 0.3s ease; }
    .deals-dot.active { background: #fff; width: 24px; border-radius: 4px; }
    @media (max-width: 768px) { .deals-banner { height: 400px; } }
    @media (max-width: 640px) { .deals-banner { height: 320px; } .deals-arrow { width: 36px; height: 36px; } .deals-arrow-prev { left: 10px; } .deals-arrow-next { right: 10px; } .deals-cd-wrap { display: none; } }
</style>

<script>
function updateCountdown() {
    const now = new Date();
    const endOfDay = new Date(now);
    endOfDay.setHours(23, 59, 59, 0);

    let diff = endOfDay - now;
    if (diff < 0) diff = 0;

    const h = Math.floor(diff / 3600000);
    const m = Math.floor((diff % 3600000) / 60000);
    const s = Math.floor((diff % 60000) / 1000);
    const pad = n => String(n).padStart(2, '0');

    ['b-h', 'b-m', 'b-s'].forEach((id, i) => {
        const el = document.getElementById(id);
        if (el) el.textContent = pad([h, m, s][i]);
    });

    const dcH = document.getElementById('dc-h');
    const dcM = document.getElementById('dc-m');
    const dcS = document.getElementById('dc-s');
    if (dcH) dcH.textContent = pad(h);
    if (dcM) dcM.textContent = pad(m);
    if (dcS) dcS.textContent = pad(s);
}
updateCountdown();
setInterval(updateCountdown, 1000);

(function () {
    const el = document.getElementById('dealsCarousel');
    if (!el) return;
    const slides = el.querySelectorAll('.deals-slide');
    const dots = el.querySelectorAll('.deals-dot');
    const prev = document.getElementById('dealsPrev');
    const next = document.getElementById('dealsNext');
    let cur = 0; let timer = null; const MS = 4500;

    function goTo(idx) {
        slides[cur].classList.remove('active');
        dots[cur].classList.remove('active');
        cur = (idx + slides.length) % slides.length;
        slides[cur].classList.add('active');
        dots[cur].classList.add('active');
    }

    function start() { timer = setInterval(() => goTo(cur + 1), MS); }
    function stop()  { clearInterval(timer); }

    if(prev) prev.addEventListener('click', () => { stop(); goTo(cur - 1); start(); });
    if(next) next.addEventListener('click', () => { stop(); goTo(cur + 1); start(); });
    dots.forEach(d => d.addEventListener('click', () => { stop(); goTo(parseInt(d.dataset.index)); start(); }));
    el.addEventListener('mouseenter', stop);
    el.addEventListener('mouseleave', start);
    start();
})();
</script>