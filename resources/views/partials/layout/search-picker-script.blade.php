<script>
(() => {
    const form = document.getElementById('heroSearchBar');
    const checkIn = document.getElementById('checkInInput');
    const checkOut = document.getElementById('checkOutInput');
    const guests = document.getElementById('guestsInput');
    const error = document.getElementById('search-picker-error');
    const months = document.getElementById('search-calendar-months');
    const panels = { dates: document.getElementById('search-dates-panel'), guests: document.getElementById('search-guests-panel') };
    const toggles = { dates: document.getElementById('search-dates-toggle'), guests: document.getElementById('search-guests-toggle') };
    const mobile = window.matchMedia('(max-width: 639px)');
    const iso = date => date.toISOString().slice(0, 10);
    const parse = value => new Date(value + 'T00:00:00Z');
    const monthStart = date => new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), 1));
    const shiftMonth = (date, amount) => new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + amount, 1));
    const format = value => value ? new Intl.DateTimeFormat('vi-VN', { day: 'numeric', month: 'short', timeZone: 'UTC' }).format(parse(value)) : 'Chọn ngày';
    const today = checkIn.min;
    const firstMonth = monthStart(parse(today));
    let visibleMonth = monthStart(parse(checkIn.value && checkIn.value >= today ? checkIn.value : today));
    let boundary = checkIn.value && !checkOut.value ? 'check_out' : 'check_in';
    let activePanel = null;
    // The API accepts a total headcount; these groups do not change pricing or hotel policies.
    const initialGuests = Number(guests.value);
    const groupInputs={adults:document.getElementById('searchAdults'),children:document.getElementById('searchChildren'),infants:document.getElementById('searchInfants')};
    const counts = { adults: Number.isSafeInteger(initialGuests) && initialGuests > 0 && initialGuests<=20 ? initialGuests : 2, children: 0, infants: 0 };
    const totalGuests = () => Object.values(counts).reduce((total, count) => total + count, 0);
    function syncGroups(){
        const values=Object.fromEntries(Object.entries(groupInputs).map(([key,field])=>[key,Number(field.value)]));
        if(Object.values(values).every(Number.isSafeInteger)&&values.adults>=1&&values.children>=0&&values.infants>=0&&Object.values(values).reduce((a,b)=>a+b,0)===Number(guests.value)&&Number(guests.value)<=20)Object.assign(counts,values);
        else Object.assign(counts,{adults:Number(guests.value)||2,children:0,infants:0});
        Object.entries(counts).forEach(([key,value])=>{groupInputs[key].value=value;});
    }
    syncGroups();

    function closePanels(restoreFocus = false) {
        const previous = activePanel;
        Object.entries(panels).forEach(([name, panel]) => {
            panel.hidden = true;
            toggles[name].setAttribute('aria-expanded', 'false');
            toggles[name].closest('.hv-search-field').classList.remove('is-active');
        });
        activePanel = null;
        if (!form.querySelector('.hv-search-field.is-active')) form.classList.remove('has-active-field');
        if (restoreFocus && previous) toggles[previous].focus({ preventScroll: true });
        return Boolean(previous);
    }

    function openPanel(name) {
        closePanels();
        HolidayVietSearch.closeSuggestions();
        form.querySelector('.hv-search-destination').classList.remove('is-active');
        activePanel = name;
        panels[name].hidden = false;
        toggles[name].setAttribute('aria-expanded', 'true');
        toggles[name].closest('.hv-search-field').classList.add('is-active');
        form.classList.add('has-active-field');
        error.hidden = true;
        if (name === 'dates') {
            visibleMonth = monthStart(parse(checkIn.value && checkIn.value >= today ? checkIn.value : today));
            boundary = checkIn.value && !checkOut.value ? 'check_out' : 'check_in';
            renderCalendar();
        } else {
            const value = Number(guests.value);
            if (Number.isInteger(value) && value > 0 && value !== totalGuests()) {
                Object.assign(counts, { adults: value, children: 0, infants: 0 });
            }
            renderGuests();
        }
        const firstControl = name === 'dates'
            ? months.querySelector('[aria-pressed="true"]:not(:disabled)') || months.querySelector('[data-calendar-date]:not(:disabled)')
            : panels.guests.querySelector('[data-guest-step="1"]');
        firstControl?.focus({ preventScroll: true });
    }

    function syncDateLabels() {
        document.getElementById('search-checkin-value').textContent = format(checkIn.value);
        document.getElementById('search-checkout-value').textContent = format(checkOut.value);
        document.getElementById('search-calendar-hint').textContent = boundary === 'check_out'
            ? 'Chọn ngày trả phòng sau ngày nhận phòng.' : 'Chọn ngày nhận phòng, sau đó chọn ngày trả phòng.';
        form.querySelectorAll('[data-date-boundary]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.dateBoundary === boundary)));
    }

    function chooseDate(value) {
        if (boundary === 'check_out' && checkIn.value && value > checkIn.value) {
            checkOut.value = value;
            boundary = 'check_in';
        } else {
            checkIn.value = value;
            checkOut.value = '';
            boundary = 'check_out';
        }
        error.hidden = true;
        [checkIn, checkOut].forEach(field => field.dispatchEvent(new Event('input', { bubbles: true })));
        syncSelection();
        months.querySelector('[data-calendar-date="' + value + '"]')?.focus({ preventScroll: true });
    }

    function syncSelection() {
        months.querySelectorAll('[data-calendar-date]').forEach(button => {
            const value = button.dataset.calendarDate;
            button.setAttribute('aria-pressed', String(value === checkIn.value || value === checkOut.value));
            button.classList.toggle('is-start', value === checkIn.value);
            button.classList.toggle('is-end', value === checkOut.value);
            button.classList.toggle('is-in-range', Boolean(checkIn.value && checkOut.value && value > checkIn.value && value < checkOut.value));
        });
        syncDateLabels();
    }

    function renderCalendar() {
        months.replaceChildren();
        for (let offset = 0; offset < (mobile.matches ? 1 : 2); offset++) {
            const month = shiftMonth(visibleMonth, offset);
            const section = document.createElement('section');
            section.className = 'hv-calendar-month';
            const title = document.createElement('h3');
            title.textContent = new Intl.DateTimeFormat('vi-VN', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(month);
            section.append(title);
            const weekdays = document.createElement('div');
            weekdays.className = 'hv-calendar-weekdays';
            weekdays.setAttribute('aria-hidden', 'true');
            ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'].forEach(name => {
                const label = document.createElement('span');
                label.textContent = name;
                weekdays.append(label);
            });
            section.append(weekdays);
            const days = document.createElement('div');
            days.className = 'hv-calendar-days';
            for (let empty = 0; empty < month.getUTCDay(); empty++) days.append(document.createElement('span'));
            const length = new Date(Date.UTC(month.getUTCFullYear(), month.getUTCMonth() + 1, 0)).getUTCDate();
            for (let day = 1; day <= length; day++) {
                const date = new Date(Date.UTC(month.getUTCFullYear(), month.getUTCMonth(), day));
                const value = iso(date);
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'hv-calendar-day';
                button.dataset.calendarDate = value;
                button.textContent = day;
                button.disabled = value < today;
                button.setAttribute('aria-label', new Intl.DateTimeFormat('vi-VN', { dateStyle: 'full', timeZone: 'UTC' }).format(date));
                if (value === today) button.setAttribute('aria-current', 'date');
                button.addEventListener('click', () => chooseDate(value));
                days.append(button);
            }
            section.append(days);
            months.append(section);
        }
        document.getElementById('search-calendar-prev').disabled = visibleMonth <= firstMonth;
        syncSelection();
    }

    function renderGuests() {
        const focused = document.activeElement;
        form.querySelectorAll('[data-guest-count]').forEach(output => { output.textContent = counts[output.dataset.guestCount]; });
        form.querySelectorAll('[data-guest-step]').forEach(button => {
            const count = counts[button.dataset.guestKind];
            button.disabled = Number(button.dataset.guestStep) < 0 ? count <= (button.dataset.guestKind==='adults'?1:0) : totalGuests()>=20;
        });
        if (focused.matches('[data-guest-step]') && focused.disabled) {
            form.querySelector('[data-guest-kind="' + focused.dataset.guestKind + '"][data-guest-step="1"]').focus({ preventScroll: true });
        }
    }

    Object.entries(toggles).forEach(([name, toggle]) => toggle.addEventListener('click', () => {
        if (activePanel === name) closePanels(); else openPanel(name);
    }));
    form.querySelectorAll('[data-search-picker-close]').forEach(button => button.addEventListener('click', () => closePanels(true)));
    document.addEventListener('click', event => { if (!form.contains(event.target) && !event.composedPath().includes(form)) closePanels(); });
    // Wait until the browser finishes moving focus between picker controls.
    form.addEventListener('focusout', () => setTimeout(() => {
        if (!form.contains(document.activeElement)) closePanels();
    }, 0));
    document.getElementById('search-calendar-prev').addEventListener('click', () => { visibleMonth = shiftMonth(visibleMonth, -1); renderCalendar(); });
    document.getElementById('search-calendar-next').addEventListener('click', () => { visibleMonth = shiftMonth(visibleMonth, 1); renderCalendar(); });
    mobile.addEventListener('change', () => { if (activePanel === 'dates') renderCalendar(); });
    form.querySelectorAll('[data-date-boundary]').forEach(button => button.addEventListener('click', () => {
        boundary = button.dataset.dateBoundary === 'check_out' && checkIn.value ? 'check_out' : 'check_in';
        syncDateLabels();
    }));
    document.getElementById('search-dates-clear').addEventListener('click', () => {
        checkIn.value = '';
        checkOut.value = '';
        boundary = 'check_in';
        error.hidden = true;
        [checkIn, checkOut].forEach(field => field.dispatchEvent(new Event('input', { bubbles: true })));
        renderCalendar();
    });
    document.getElementById('search-dates-done').addEventListener('click', () => {
        if (checkIn.checkValidity() && checkOut.checkValidity()) closePanels(true);
    });
    form.querySelectorAll('[data-guest-step]').forEach(button => button.addEventListener('click', () => {
        const kind = button.dataset.guestKind;
        const step = Number(button.dataset.guestStep);
        if (counts[kind] + step < (kind==='adults'?1:0) || totalGuests() + step > 20) return;
        counts[kind] += step;
        guests.value = totalGuests();
        Object.entries(counts).forEach(([key,value])=>{groupInputs[key].value=value;});
        guests.dispatchEvent(new Event('input', { bubbles: true }));
        renderGuests();
        error.hidden = true;
    }));
    // Native constraints still apply; focus the visible picker instead of a clipped field.
    [checkIn, checkOut, guests].forEach(field => field.addEventListener('invalid', event => {
        event.preventDefault();
        const name = field === guests ? 'guests' : 'dates';
        if (activePanel !== name) openPanel(name);
        panels[name].querySelector('.hv-picker-heading').after(error);
        error.textContent = field === guests ? 'Chọn từ 1 đến 20 khách, có ít nhất 1 người lớn.'
            : 'Chọn đủ ngày nhận phòng và trả phòng, với ngày trả phòng sau ngày nhận phòng và không chọn ngày đã qua.';
        error.hidden = false;
        toggles[name].focus({ preventScroll: true });
    }));
    months.addEventListener('keydown', event => {
        const value = event.target.dataset.calendarDate;
        const delta = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[event.key];
        if (!value || !delta) return;
        event.preventDefault();
        const date = parse(value);
        date.setUTCDate(date.getUTCDate() + delta);
        if (iso(date) < today) return;
        let target = months.querySelector('[data-calendar-date="' + iso(date) + '"]');
        if (!target) {
            visibleMonth = monthStart(date);
            renderCalendar();
            target = months.querySelector('[data-calendar-date="' + iso(date) + '"]');
        }
        target?.focus({ preventScroll: true });
    });
    HolidayVietSearch.closePanels = closePanels;
    form.addEventListener('aurel:search-sync',()=>{syncGroups();renderGuests();syncDateLabels();if(activePanel==='dates')renderCalendar();});
    syncDateLabels();
})();
</script>
