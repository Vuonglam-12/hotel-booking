<style>
    .section-title { font-size: 22px; font-weight: 800; color: #1E3A5F; display: flex; align-items: center; gap: 8px; }
    .section-sub { color: #C9D3DD; font-size: 13px; margin-top: 2px; }
    .hotel-card, .deal-card { transition: all 0.4s cubic-bezier(0.4,0,0.2,1); }
    .discount-badge { position: absolute; top: 12px; left: 12px; background: linear-gradient(135deg, #EF4444, #F59E0B); color: white; font-size: 12px; font-weight: 800; padding: 4px 10px; border-radius: 20px; letter-spacing: 0.3px; }
    .price-original { color: #9CA3AF; font-size: 12px; text-decoration: line-through; }
    .price-sale { color: #87CEFA; font-size: 18px; font-weight: 800; }
    .filter-tab { padding: 8px 18px; border-radius: 20px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s; border: 1.5px solid #E5E7EB; background: white; color: #6B7280; }
    .filter-tab.active, .filter-tab:hover { background: #87CEFA; color: #1E3A5F; border-color: #87CEFA; }

    @keyframes ratingPop { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.08); } }
    @keyframes starWiggle { 0%, 100% { transform: rotate(0deg); } 25% { transform: rotate(-12deg); } 75% { transform: rotate(12deg); } }
    .rating-badge { background: #FFF8E1; border: 1.5px solid #F59E0B; border-radius: 20px; padding: 3px 10px 3px 7px; animation: ratingPop 2.8s ease-in-out infinite; }
    .rating-star { animation: starWiggle 2.8s ease-in-out infinite; }
    .rating-score { color: #92400E; font-weight: 700; letter-spacing: 0.01em; }

    /* ========== STYLE 3D COVERFLOW (chỉ khai báo 1 lần) ========== */
    .perspective-\[1200px\] { perspective: 1200px; }
    .transform-style-3d { transform-style: preserve-3d; }

    /* Trạng thái mặc định (ẩn) */
    .flash-slide-3d {
        transform-origin: center center;
        transform: translate(-50%, -50%) scale(0.2);
        opacity: 0;
        z-index: 1;
        pointer-events: none;
        transition: all 0.5s ease-out;
    }

    /* Ảnh chính giữa */
    .flash-slide-3d.active {
        transform: translate(-50%, -50%) scale(1);
        opacity: 1;
        z-index: 20;
        filter: blur(0);
        pointer-events: auto;
    }
    .flash-slide-3d.active .hotel-card:hover { transform: translateY(-8px); }

    /* Ảnh bên Trái gần */
    .flash-slide-3d.prev-1 {
        transform: translate(-130%, -50%) scale(0.85) rotateY(15deg);
        opacity: 0.55;
        z-index: 15;
        filter: blur(2.5px);
        pointer-events: auto;
    }

    /* Ảnh bên Phải gần */
    .flash-slide-3d.prev-1 {
        transform: translate(-130%, -50%) scale(0.85) rotateY(15deg);
        opacity: 1;
        z-index: 15;
        filter: blur(1.5px);
        pointer-events: auto;
    }

        /* Ảnh bên Trái xa */
    .flash-slide-3d.prev-2 {
        transform: translate(-200%, -50%) scale(0.65) rotateY(25deg);
        opacity: 1;
        z-index: 10;
        filter: blur(2.5px);
        pointer-events: auto;
    }

    .flash-slide-3d.next-1 {
        transform: translate(30%, -50%) scale(0.85) rotateY(-15deg);
        opacity: 1;
        z-index: 15;
        filter: blur(1.5px);
        pointer-events: auto;
    }

    /* Ảnh bên Phải xa */
    .flash-slide-3d.next-2 {
        transform: translate(100%, -50%) scale(0.65) rotateY(-25deg);
        opacity: 0.25;
        z-index: 10;
        filter: blur(4px);
        pointer-events: auto;
    }

    /* Responsive Mobile */
    @media (max-width: 768px) {
        .flash-slide-3d.prev-1 { transform: translate(-105%, -50%) scale(0.8) rotateY(10deg); }
        .flash-slide-3d.next-1 { transform: translate(5%, -50%) scale(0.8) rotateY(-10deg); }
        .flash-slide-3d.prev-2 { opacity: 0; pointer-events: none; }
        .flash-slide-3d.next-2 { opacity: 0; pointer-events: none; }
    }

    .cursor-grab { cursor: grab; }
    .cursor-grabbing { cursor: grabbing; }
</style>

<script>
let currentFlashIndex = 0;
let visibleSlides = [];
let flashAutoPlayTimer;
let isDragging = false;
let startX = 0;
const dragThreshold = 60;

function initFlashCoverflow() {
    const allSlides = document.querySelectorAll('.flash-slide-3d');
    visibleSlides = Array.from(allSlides).filter(s => !s.classList.contains('is-hidden'));

    if (visibleSlides.length === 0) return;
    if (currentFlashIndex >= visibleSlides.length) currentFlashIndex = 0;

    // Xoá hết class vị trí cũ
    allSlides.forEach(s => s.classList.remove('active', 'prev-1', 'next-1', 'prev-2', 'next-2'));

    const len = visibleSlides.length;
    visibleSlides.forEach((slide, index) => {
        if (index === currentFlashIndex)                          slide.classList.add('active');
        else if (index === (currentFlashIndex - 1 + len) % len)  slide.classList.add('prev-1');
        else if (index === (currentFlashIndex + 1) % len)        slide.classList.add('next-1');
        else if (index === (currentFlashIndex - 2 + len) % len && len >= 5) slide.classList.add('prev-2');
        else if (index === (currentFlashIndex + 2) % len && len >= 5)       slide.classList.add('next-2');
    });
}

// Tiến sang phải (card mới vào từ bên phải)
function flashNext() {
    if (visibleSlides.length <= 1) return;
    currentFlashIndex = (currentFlashIndex + 1) % visibleSlides.length;
    initFlashCoverflow();
}

// Lùi sang trái
function flashPrev() {
    if (visibleSlides.length <= 1) return;
    currentFlashIndex = (currentFlashIndex - 1 + visibleSlides.length) % visibleSlides.length;
    initFlashCoverflow();
}

// ========== AUTO PLAY ==========
function startFlashAutoPlay() {
    stopFlashAutoPlay();
    // flashNext: card tràn vào từ phải → hiệu ứng chạy trái sang phải (tự nhiên)
    flashAutoPlayTimer = setInterval(flashNext, 1500);
}

function stopFlashAutoPlay() {
    clearInterval(flashAutoPlayTimer);
}

function resetFlashAutoPlay() {
    stopFlashAutoPlay();
    startFlashAutoPlay();
}

// ========== DRAG & SWIPE ==========
function setupNavigation() {
    const container = document.getElementById('flashSaleContainer');
    if (!container) return;

    // Mouse drag
    container.addEventListener('mousedown', (e) => {
        isDragging = true;
        startX = e.pageX;
        container.classList.add('cursor-grabbing');
    });
    window.addEventListener('mouseup', (e) => {
        if (!isDragging) return;
        isDragging = false;
        container.classList.remove('cursor-grabbing');
        const diffX = startX - e.pageX;
        if (Math.abs(diffX) > dragThreshold) {
            diffX > 0 ? flashNext() : flashPrev();
            resetFlashAutoPlay();
        }
    });

    // Touch swipe
    container.addEventListener('touchstart', (e) => {
        startX = e.touches[0].pageX;
    }, { passive: true });
    container.addEventListener('touchend', (e) => {
        const diffX = startX - e.changedTouches[0].pageX;
        if (Math.abs(diffX) > dragThreshold) {
            diffX > 0 ? flashNext() : flashPrev();
            resetFlashAutoPlay();
        }
    }, { passive: true });

    // *** FIX QUAN TRỌNG: Chỉ dừng autoplay khi hover vào container,
    //     KHÔNG để pointer-events:none trên track làm sự kiện lan sai ***
    container.addEventListener('mouseenter', stopFlashAutoPlay);
    container.addEventListener('mouseleave', startFlashAutoPlay);
}

// THAY bằng: (active → vào trang, prev/next → nhảy slide)
function handleFlashClick(element, id) {
    const slide = element.closest('.flash-slide-3d');
    if (slide.classList.contains('active')) {
        window.location.href = '/hotels/' + id;
    } else if (slide.classList.contains('prev-1') || slide.classList.contains('prev-2')) {
        flashPrev();
        resetFlashAutoPlay();
    } else if (slide.classList.contains('next-1') || slide.classList.contains('next-2')) {
        flashNext();
        resetFlashAutoPlay();
    }
}

function filterDeals(city, btn) {
    document.querySelectorAll('.filter-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.flash-slide-3d').forEach(slide => {
        const cardCity = slide.dataset.city || '';
        (city === 'all' || cardCity.includes(city))
            ? slide.classList.remove('is-hidden')
            : slide.classList.add('is-hidden');
    });
    currentFlashIndex = 0;
    initFlashCoverflow();
    resetFlashAutoPlay();
}

document.addEventListener('DOMContentLoaded', () => {
    initFlashCoverflow();
    startFlashAutoPlay();
    setupNavigation();
});
</script>