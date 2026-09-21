<script>
// ========================
// HERO CAROUSEL — slide ngang (phải → trái)
// ========================
(function () {
    const carousel = document.getElementById('heroCarousel');
    if (!carousel) return;                       // guard đặt TRƯỚC khi dùng carousel

    const slides  = carousel.querySelectorAll('.hero-slide');
    const dots    = carousel.querySelectorAll('.hero-dot');
    const btnPrev = document.getElementById('heroPrev');
    const btnNext = document.getElementById('heroNext');
    if (slides.length === 0) return;

    const INTERVAL = 4500;   // thời gian tự chuyển
    const DURATION = 850;    // phải khớp transition trong CSS

    let current   = 0;
    let timer     = null;
    let animating = false;

    // Đặt vị trí slide; animate=false => nhảy tức thì (không transition)
    function place(el, percent, animate) {
        el.style.transition = animate ? '' : 'none';
        el.style.transform  = `translateX(${percent}%)`;
        if (!animate) void el.offsetWidth;       // force reflow
    }

    // Khởi tạo: slide đầu ở giữa, các slide còn lại chờ bên phải
    slides.forEach((s, i) => place(s, i === 0 ? 0 : 100, false));

    /**
     * @param {number} idx  slide đích
     * @param {number} dir  1 = slide mới vào từ PHẢI (mặc định), -1 = vào từ TRÁI
     */
    function goTo(idx, dir = 1) {
        const next = (idx + slides.length) % slides.length;
        if (animating || next === current) return;

        animating = true;
        const oldIdx  = current;
        const elOld   = slides[oldIdx];
        const elNext  = slides[next];

        // slide mới đứng chờ ngoài khung
        elNext.classList.remove('active');
        place(elNext, dir > 0 ? 100 : -100, false);
        elNext.classList.add('active');

        // chạy: cũ đi ra, mới đi vào
        requestAnimationFrame(() => {
            place(elNext, 0, true);
            place(elOld, dir > 0 ? -100 : 100, true);
        });

        dots[oldIdx]?.classList.remove('active');
        dots[next]?.classList.add('active');
        current = next;

        setTimeout(() => {
            elOld.classList.remove('active');
            place(elOld, 100, false);            // trả về vị trí chờ
            animating = false;
        }, DURATION);
    }

    function next() { goTo(current + 1, 1); }
    function prev() { goTo(current - 1, -1); }

    function start() { stop(); timer = setInterval(next, INTERVAL); }
    function stop()  { clearInterval(timer); timer = null; }

    btnNext?.addEventListener('click', () => { stop(); next(); start(); });
    btnPrev?.addEventListener('click', () => { stop(); prev(); start(); });

    dots.forEach(dot => {
        dot.addEventListener('click', () => {
            const idx = parseInt(dot.dataset.index);
            if (isNaN(idx) || idx === current) return;
            stop();
            goTo(idx, idx > current ? 1 : -1);
            start();
        });
    });

    carousel.addEventListener('mouseenter', stop);
    carousel.addEventListener('mouseleave', start);

    // Dừng autoplay khi tab ẩn cho đỡ tốn CPU
    document.addEventListener('visibilitychange', () => {
        document.hidden ? stop() : start();
    });

    start();
})();

// ========================
// GUESTS PICKER — field "Who" (Adults / Children / Infants / Pets)
// ========================
(function () {
    const sfGuests = document.getElementById('sfGuests');
    if (!sfGuests) return;

    const trigger = document.getElementById('guestsTrigger');
    const display = document.getElementById('guestsDisplay');
    const panel   = document.getElementById('guestsPanel');
    const datesPanel = document.getElementById('datesPanel');
    const datesTrigger = document.getElementById('datesTrigger');
    const searchDropdown = document.getElementById('searchDropdown');

    const inputs = {
        adults:   document.getElementById('adultsInput'),
        children: document.getElementById('childrenInput'),
        infants:  document.getElementById('infantsInput'),
        pets:     document.getElementById('petsInput'),
    };
    const totalInput = document.getElementById('guestsTotalInput');

    const LIMITS = { adults: 10, children: 10, infants: 5, pets: 5 };

    const state = {
        adults:   parseInt(inputs.adults.value)   || 0,
        children: parseInt(inputs.children.value) || 0,
        infants:  parseInt(inputs.infants.value)  || 0,
        pets:     parseInt(inputs.pets.value)     || 0,
    };

    function syncButtons() {
        panel.querySelectorAll('.guests-row').forEach(row => {
            const key   = row.dataset.counter;
            const count = state[key];
            row.querySelector('[data-count]').textContent = count;
            row.querySelector('[data-action="minus"]').disabled = count <= 0;
            row.querySelector('[data-action="plus"]').disabled  = count >= LIMITS[key];
        });
    }

    function syncDisplay() {
        const parts  = [];
        const guests = state.adults + state.children;
        if (guests > 0)        parts.push(guests + (guests === 1 ? ' guest' : ' guests'));
        if (state.infants > 0) parts.push(state.infants + (state.infants === 1 ? ' infant' : ' infants'));
        if (state.pets > 0)    parts.push(state.pets + (state.pets === 1 ? ' pet' : ' pets'));

        if (parts.length) {
            display.textContent = parts.join(', ');
            display.classList.remove('sf-guests-placeholder');
            display.classList.add('sf-guests-value');
        } else {
            display.textContent = 'Add guests';
            display.classList.add('sf-guests-placeholder');
            display.classList.remove('sf-guests-value');
        }
    }

    function syncInputs() {
        inputs.adults.value   = state.adults;
        inputs.children.value = state.children;
        inputs.infants.value  = state.infants;
        inputs.pets.value     = state.pets;
        totalInput.value      = (state.adults + state.children) || '';
    }

    function render() {
        syncButtons();
        syncDisplay();
        syncInputs();
    }

    panel.addEventListener('click', e => {
        const btn = e.target.closest('.guests-btn');
        if (!btn) return;

        const row   = btn.closest('.guests-row');
        const key   = row.dataset.counter;
        const delta = btn.dataset.action === 'plus' ? 1 : -1;

        state[key] = Math.min(LIMITS[key], Math.max(0, state[key] + delta));
        render();
    });

    function openPanel() {
        datesPanel?.classList.remove('open');
        datesTrigger?.setAttribute('aria-expanded', 'false');
        searchDropdown?.classList.add('hidden');
        panel.classList.add('open');
        trigger.setAttribute('aria-expanded', 'true');
    }

    function closePanel() {
        panel.classList.remove('open');
        trigger.setAttribute('aria-expanded', 'false');
    }

    trigger.addEventListener('click', e => {
        e.stopPropagation();
        panel.classList.contains('open') ? closePanel() : openPanel();
    });

    // Click ra ngoài field "Who" (kể cả sang field khác trong cùng pill-bar) → đóng panel
    document.addEventListener('click', e => {
        if (!sfGuests.contains(e.target)) closePanel();
    });

    render(); // khởi tạo hiển thị theo query string hiện có (nếu user back lại từ kết quả search)
})();

// ========================
// SEARCH AUTOCOMPLETE
// ========================
(function () {
    const input    = document.getElementById('searchInput');
    const dropdown = document.getElementById('searchDropdown');
    if (!input || !dropdown) return;

    let debounceTimer = null;

    function esc(t) {
        if (!t) return '';
        const d = document.createElement('div');
        d.textContent = t;
        return d.innerHTML;
    }

    function highlight(name, kw) {
        const safe = esc(name);
        if (!kw) return safe;
        const re = new RegExp('(' + kw.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
        return safe.replace(re, '<mark>$1</mark>');
    }

    function hide() { dropdown.classList.add('hidden'); }

    async function search(kw) {
        try {
            const res  = await fetch(`/api/hotels?search=${encodeURIComponent(kw)}&per_page=6`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            render(data.data || [], kw);
        } catch (e) {
            hide();
        }
    }

    function render(hotels, kw) {
        if (!hotels.length) {
            dropdown.innerHTML = `<div class="ac-empty">Không tìm thấy khách sạn phù hợp</div>`;
            dropdown.classList.remove('hidden');
            return;
        }

        dropdown.innerHTML =
            `<div class="ac-header">Kết quả gợi ý</div>` +
            hotels.map(h => {
                const price = h.rooms_min_price
                    ? new Intl.NumberFormat('vi-VN').format(h.rooms_min_price) + 'đ'
                    : 'Liên hệ';
                return `
                <div class="ac-item" onclick="window.location.href='/hotels/${h.id}'">
                    <div class="ac-img-wrap">
                        <img src="https://picsum.photos/seed/${h.id}/120/90" alt="">
                    </div>
                    <div class="ac-info">
                        <div class="ac-name">${highlight(h.name, kw)}</div>
                        <div class="ac-location">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            </svg>
                            ${esc(h.location?.name || '')} · ${'⭐'.repeat(Math.min(h.star_rating || 0, 5))}
                        </div>
                    </div>
                    <div class="ac-price">${price}<span style="font-size:10px;font-weight:400;color:#94A3B8">/đêm</span></div>
                </div>`;
            }).join('');

        dropdown.classList.remove('hidden');
    }

    input.addEventListener('input', function () {
        const kw = this.value.trim();
        clearTimeout(debounceTimer);
        if (kw.length < 2) { hide(); return; }
        debounceTimer = setTimeout(() => search(kw), 1000);
    });

    input.addEventListener('focus', function () {
        if (this.value.trim().length >= 2 && dropdown.innerHTML) dropdown.classList.remove('hidden');
    });

    document.addEventListener('click', e => {
        if (!dropdown.contains(e.target) && e.target !== input) hide();
    });
})();

// ========================
// DATES PICKER — field "When" (2-month range calendar)
// ========================
(function () {
    const sfDates = document.getElementById('sfDates');
    if (!sfDates) return;

    const trigger = document.getElementById('datesTrigger');
    const display = document.getElementById('datesDisplay');
    const clearBtn = document.getElementById('datesClearBtn');
    const panel = document.getElementById('datesPanel');
    const monthsWrap = document.getElementById('calMonths');
    const btnPrev = document.getElementById('calPrev');
    const btnNext = document.getElementById('calNext');
    const flexRow = document.getElementById('datesFlexRow');
    const checkInInput = document.getElementById('checkInInput');
    const checkOutInput = document.getElementById('checkOutInput');
    const guestsPanel = document.getElementById('guestsPanel');
    const guestsTrigger = document.getElementById('guestsTrigger');
    const searchDropdown = document.getElementById('searchDropdown');
    const tabs = panel?.querySelectorAll('.dates-tab') || [];

    if (!trigger || !display || !clearBtn || !panel || !monthsWrap || !btnPrev || !btnNext || !flexRow || !checkInInput || !checkOutInput) return;

    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const weekdays = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    function parseISO(value) {
        if (!value) return null;
        const [year, month, day] = value.split('-').map(Number);
        const date = new Date(year, month - 1, day);
        return Number.isNaN(date.getTime()) ? null : date;
    }

    function toISO(date) {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    }

    function sameDay(first, second) {
        return first && second && first.getFullYear() === second.getFullYear()
            && first.getMonth() === second.getMonth() && first.getDate() === second.getDate();
    }

    function formatShort(date) {
        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    let checkIn = parseISO(checkInInput.value);
    let checkOut = parseISO(checkOutInput.value);
    let baseMonth = new Date((checkIn || today).getFullYear(), (checkIn || today).getMonth(), 1);

    function buildMonth(monthDate) {
        const year = monthDate.getFullYear();
        const month = monthDate.getMonth();
        const firstWeekday = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        let cells = '';

        for (let index = 0; index < firstWeekday; index += 1) {
            cells += '<div class="cal-cell"><button type="button" class="cal-day is-empty" tabindex="-1"></button></div>';
        }

        for (let day = 1; day <= daysInMonth; day += 1) {
            const date = new Date(year, month, day);
            const isPast = date < today;
            const isStart = sameDay(date, checkIn);
            const isEnd = sameDay(date, checkOut);
            const isInRange = checkIn && checkOut && date > checkIn && date < checkOut;
            let cellClass = isInRange ? 'in-range' : isStart && checkOut ? 'range-start' : isEnd ? 'range-end' : '';
            let dayClass = `cal-day${isPast ? ' is-disabled' : ''}${isStart || isEnd ? ' is-selected' : ''}`;

            cells += `<div class="cal-cell ${cellClass}"><button type="button" class="${dayClass}" data-date="${toISO(date)}"${isPast ? ' disabled' : ''}>${day}</button></div>`;
        }

        return `<div class="cal-month"><div class="cal-month-title">${monthNames[month]} ${year}</div><div class="cal-weekdays">${weekdays.map(day => `<span>${day}</span>`).join('')}</div><div class="cal-days">${cells}</div></div>`;
    }

    function syncDisplay() {
        display.textContent = checkIn && checkOut ? `${formatShort(checkIn)} – ${formatShort(checkOut)}` : checkIn ? formatShort(checkIn) : 'Add dates';
        display.classList.toggle('sf-guests-placeholder', !checkIn);
        display.classList.toggle('sf-guests-value', !!checkIn);
        clearBtn.classList.toggle('hidden', !checkIn);
    }

    function syncInputs() {
        checkInInput.value = checkIn ? toISO(checkIn) : '';
        checkOutInput.value = checkOut ? toISO(checkOut) : '';
    }

    function render() {
        const nextMonth = new Date(baseMonth.getFullYear(), baseMonth.getMonth() + 1, 1);
        monthsWrap.innerHTML = buildMonth(baseMonth) + buildMonth(nextMonth);
        btnPrev.disabled = baseMonth.getFullYear() === today.getFullYear() && baseMonth.getMonth() === today.getMonth();
        syncDisplay();
        syncInputs();
    }

    monthsWrap.addEventListener('click', event => {
        event.stopPropagation();
        const button = event.target.closest('.cal-day');
        if (!button || button.disabled || button.classList.contains('is-empty')) return;
        const date = parseISO(button.dataset.date);
        if (!date) return;
        if (!checkIn || checkOut) { checkIn = date; checkOut = null; }
        else if (date > checkIn) checkOut = date;
        else checkIn = date;
        panel.classList.add('open');
        trigger.setAttribute('aria-expanded', 'true');
        render();
    });

    btnPrev.addEventListener('click', () => {
        if (btnPrev.disabled) return;
        baseMonth = new Date(baseMonth.getFullYear(), baseMonth.getMonth() - 1, 1);
        render();
    });
    btnNext.addEventListener('click', () => {
        baseMonth = new Date(baseMonth.getFullYear(), baseMonth.getMonth() + 1, 1);
        render();
    });

    tabs.forEach(tab => tab.addEventListener('click', () => {
        tabs.forEach(item => item.classList.remove('active'));
        tab.classList.add('active');
    }));

    flexRow.addEventListener('click', event => {
        const chip = event.target.closest('.flex-chip');
        if (!chip) return;
        flexRow.querySelectorAll('.flex-chip').forEach(item => item.classList.remove('active'));
        chip.classList.add('active');
    });

    clearBtn.addEventListener('click', event => {
        event.stopPropagation();
        checkIn = null;
        checkOut = null;
        render();
    });

    trigger.addEventListener('click', event => {
        event.stopPropagation();
        const isOpen = panel.classList.toggle('open');
        trigger.setAttribute('aria-expanded', String(isOpen));
        if (isOpen) {
            guestsPanel?.classList.remove('open');
            guestsTrigger?.setAttribute('aria-expanded', 'false');
            searchDropdown?.classList.add('hidden');
            render();
        }
    });

    document.addEventListener('click', event => {
        if (!sfDates.contains(event.target)) {
            panel.classList.remove('open');
            trigger.setAttribute('aria-expanded', 'false');
        }
    });

    render();
})();
</script>