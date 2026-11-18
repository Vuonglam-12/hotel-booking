<script>
(() => {
    const checkIn = document.getElementById('check-in');
    const checkOut = document.getElementById('check-out');
    const guests = document.getElementById('guests');
    const quantity = document.getElementById('room-quantity');
    const error = document.getElementById('booking-error');
    const status = document.getElementById('booking-availability');
    const estimate = document.getElementById('price-estimate');
    const stock = HolidayVietDetailRooms.stock;
    const money = value => new Intl.NumberFormat('vi-VN').format(value) + ' ₫';
    let requestSerial = 0;
    let requestController;

    function validDates() {
        return checkIn.value && checkOut.value && checkIn.checkValidity()
            && checkOut.checkValidity() && checkOut.value > checkIn.value;
    }

    function updateDateLimits() {
        if (checkIn.value) {
            const next = new Date(checkIn.value + 'T00:00:00Z');
            next.setUTCDate(next.getUTCDate() + 1);
            checkOut.min = next.toISOString().slice(0, 10);
        } else checkOut.min = checkIn.min;
    }

    function calcPrice() {
        const type = HolidayVietDetailRooms.types.get(Number(document.getElementById('room-select').value));
        const count = Number(quantity.value);
        const guestCount = Number(guests.value);
        const problems = [];
        if (stock.status === 'error' && stock.error) problems.push(stock.error);
        error.hidden = true;
        estimate.hidden = true;
        baseTotal = 0;
        currentTotal = 0;
        if (!guests.checkValidity() || !Number.isSafeInteger(guestCount) || guestCount < 1) problems.push('Số khách phải là số nguyên dương.');
        if (!quantity.checkValidity() || !Number.isSafeInteger(count) || count < 1 || count > 10) problems.push('Chọn số lượng từ 1 đến 10 phòng.');
        if ((checkIn.value || checkOut.value) && !validDates()) problems.push('Chọn đủ ngày hợp lệ; ngày trả phòng phải sau ngày nhận phòng.');
        if (type && Number.isSafeInteger(count) && count > 0 && type.capacity && guestCount > type.capacity * count) {
            const alternatives = Array.from(HolidayVietDetailRooms.types.values()).filter(candidate => candidate.id !== type.id && candidate.capacity * count >= guestCount);
            problems.push('Số khách vượt sức chứa tổng của ' + count + ' phòng ' + type.name + '.'
                + (alternatives.length ? ' Bạn có thể xem ' + alternatives.map(candidate => candidate.name).join(', ') + '.' : ' Vui lòng chọn phương án phù hợp hơn.'));
        }
        if (type) {
            status.textContent = HolidayVietDetailRooms.availabilityLabel(type.id);
            if (stock.status === 'ready' && stock.counts.get(type.id) < count) problems.push('Không đủ ' + count + ' phòng trong toàn bộ khoảng lưu trú. Lựa chọn của bạn được giữ để điều chỉnh.');
            if (selectedRoomTypes[type.id]) selectedRoomTypes[type.id].quantity = count;
        } else status.textContent = 'Chọn loại phòng và ngày để kiểm tra phòng trống.';
        if (type && validDates() && quantity.checkValidity() && type.price > 0) {
            const nights = (Date.parse(checkOut.value + 'T00:00:00Z') - Date.parse(checkIn.value + 'T00:00:00Z')) / 86400000;
            baseTotal = type.price * count * nights;
            currentTotal = baseTotal;
            document.getElementById('booking-unit-price').textContent = money(type.price);
            document.getElementById('booking-room-quantity').textContent = count + ' phòng';
            document.getElementById('booking-nights').textContent = nights + ' đêm';
            document.getElementById('total-price').textContent = money(baseTotal);
            document.getElementById('price-subtotal').textContent = money(baseTotal);
            document.getElementById('price-label-nights').textContent = money(type.price) + ' × ' + count + ' phòng × ' + nights + ' đêm';
            estimate.hidden = false;
        }
        if (problems.length) { error.textContent = problems.join(' '); error.hidden = false; }
        // A test-only gate validates the owned endpoint; production remains disabled.
        const stagingCheckout = window.AUREL_V2_STAGING_CHECKOUT === true;
        document.getElementById('submit-booking-btn').disabled = !stagingCheckout || problems.length > 0 || !type || !validDates() || stock.status !== 'ready';
    }

    async function refreshAvailability() {
        updateDateLimits();
        requestController?.abort();
        const serial = ++requestSerial;
        stock.counts.clear();
        stock.error = '';
        if (!validDates()) {
            stock.status = 'idle';
            HolidayVietDetailRooms.sync();
            calcPrice();
            return;
        }
        stock.status = 'loading';
        HolidayVietDetailRooms.sync();
        calcPrice();
        requestController = new AbortController();
        const params = new URLSearchParams({ check_in: checkIn.value, check_out: checkOut.value });
        const typeId = document.getElementById('room-select').value;
        if (typeId && guests.checkValidity() && quantity.checkValidity()) {
            params.set('room_type_id', typeId);
            params.set('num_guests', guests.value);
            params.set('quantity', quantity.value);
        }
        try {
            // Do not filter each physical room by the whole party's size.
            const response = await _layoutApi('/hotels/' + HOTEL_ID + '/room-types?' + params, { signal: requestController.signal });
            if (!response.ok) throw new Error(getApiErrorMessage(await response.json()));
            const payload = await response.json(); const rows = payload.room_types;
            if (!Array.isArray(rows)) throw new Error('Invalid availability response');
            if (serial !== requestSerial) return;
            for (const id of HolidayVietDetailRooms.types.keys()) {
                const row = rows.find(room => Number(room.hotel_id) === HOTEL_ID && Number(room.id) === id);
                if (!row && typeId && id !== Number(typeId)) continue;
                const value = row ? Number(row.available_rooms) : 0;
                if (!Number.isSafeInteger(value) || value < 0) throw new Error('Invalid available room count');
                stock.counts.set(id, value);
            }
            stock.status = 'ready';
        } catch (exception) {
            if (exception.name === 'AbortError' || serial !== requestSerial) return;
            stock.status = 'error';
            stock.error = exception.message || 'Chưa kiểm tra được khả dụng. Vui lòng thử lại.';
            stock.counts.clear();
        }
        if (serial === requestSerial) { HolidayVietDetailRooms.sync(); calcPrice(); }
    }

    [checkIn, checkOut].forEach(field => field.addEventListener('change', refreshAvailability));
    let inputTimer;
    [guests, quantity].forEach(field => field.addEventListener('input', () => {
        calcPrice();
        clearTimeout(inputTimer);
        inputTimer = setTimeout(refreshAvailability, 250);
    }));
    window.calcPrice = calcPrice;
    window.refreshHotelAvailability = refreshAvailability;
    refreshAvailability();
})();


// Angel Hotel Detail date range picker. Keeps #check-in / #check-out as the authoritative values.
(() => {
    const overlay = document.getElementById('hv-stay-date-dialog');
    const checkIn = document.getElementById('check-in');
    const checkOut = document.getElementById('check-out');
    if (!overlay || !checkIn || !checkOut) return;
    document.body.appendChild(overlay);
    const $ = id => document.getElementById(id);
    const checkInTrigger = $('hv-stay-checkin-trigger');
    const checkOutTrigger = $('hv-stay-checkout-trigger');
    const months = $('hv-stay-months');
    const error = $('hv-stay-error');
    const now = new Date();
    const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}`;
    const firstMonth = new Date(now.getFullYear(), now.getMonth(), 1);
    const fmtISO = date => `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`;
    const formatLabel = value => value ? value.split('-').reverse().join('/') : 'Chọn ngày';
    const nextDay = value => { const [y,m,d] = value.split('-').map(Number); return fmtISO(new Date(y,m-1,d+1)); };
    let shown = new Date(firstMonth.getFullYear(),firstMonth.getMonth(),1);
    let draftIn = '';
    let draftOut = '';
    let editing = 'check_in';
    let returnFocus = null;
    function renderLabels() {
        $('hv-stay-checkin-label').textContent = formatLabel(checkIn.value);
        $('hv-stay-checkout-label').textContent = formatLabel(checkOut.value);
    }
    function draw() {
        $('hv-stay-prev').disabled = shown.getTime() <= firstMonth.getTime();
        $('hv-stay-draft-checkin').textContent = formatLabel(draftIn);
        $('hv-stay-draft-checkout').textContent = formatLabel(draftOut);
        $('hv-stay-select-checkin').classList.toggle('active',editing==='check_in');
        $('hv-stay-select-checkout').classList.toggle('active',editing==='check_out');
        $('hv-stay-dialog-hint').textContent = editing === 'check_in' ? 'Chọn ngày nhận phòng, sau đó chọn ngày trả phòng.' : 'Chọn ngày trả phòng sau ngày nhận phòng.';
        months.replaceChildren();
        const numberMonths = window.matchMedia('(max-width:640px)').matches ? 1 : 2;
        for(let offset=0;offset<numberMonths;offset++) {
            const first = new Date(shown.getFullYear(), shown.getMonth()+offset,1);
            const month = document.createElement('div'); month.className='hv-stay-month';
            const heading=document.createElement('h3'); heading.textContent=`Tháng ${first.getMonth()+1} Năm ${first.getFullYear()}`; month.appendChild(heading);
            const grid=document.createElement('div'); grid.className='hv-stay-days';
            ['CN','T2','T3','T4','T5','T6','T7'].forEach(label=>{const elem=document.createElement('span');elem.className='hv-stay-dow';elem.textContent=label;grid.appendChild(elem);});
            for(let n=0;n<first.getDay();n++){const blank=document.createElement('span');blank.className='hv-stay-blank';grid.appendChild(blank);}
            const count=new Date(first.getFullYear(),first.getMonth()+1,0).getDate();
            for(let day=1;day<=count;day++) {
                const date=fmtISO(new Date(first.getFullYear(),first.getMonth(),day));
                const button=document.createElement('button'); button.type='button'; button.className='hv-stay-day'; button.textContent=String(day);
                button.disabled=date<today || (editing==='check_out' && Boolean(draftIn) && date<=draftIn);
                button.setAttribute('aria-label',formatLabel(date));
                if(date===draftIn || date===draftOut) button.classList.add('selected');
                else if(draftIn && draftOut && date>draftIn && date<draftOut) button.classList.add('in-range');
                button.addEventListener('click',()=>{
                    if(editing==='check_in' || !draftIn) { draftIn=date; draftOut=''; editing='check_out'; }
                    else { draftOut=date; editing='check_in'; }
                    error.hidden=true; draw();
                });
                grid.appendChild(button);
            }
            month.appendChild(grid);months.appendChild(month);
        }
    }
    function open(boundary) {
        draftIn=checkIn.value>=today?checkIn.value:'';
        draftOut=checkOut.value>draftIn?checkOut.value:'';
        editing=boundary==='check_out' && draftIn?'check_out':'check_in';
        const base=draftIn||today; const [year,month]=base.split('-').map(Number);
        shown=new Date(year,month-1,1);
        error.hidden=true;
        overlay.hidden=false;
        returnFocus=boundary==='check_out'?checkOutTrigger:checkInTrigger;
        checkInTrigger.setAttribute('aria-expanded','true');
        checkOutTrigger.setAttribute('aria-expanded','true');
        draw(); $('hv-stay-close').focus();
    }
    function close() {
        overlay.hidden=true;
        checkInTrigger.setAttribute('aria-expanded','false');
        checkOutTrigger.setAttribute('aria-expanded','false');
        returnFocus?.focus();
    }
    function commit() {
        checkIn.value=draftIn; checkOut.value=draftOut;
        checkOut.min=draftIn?nextDay(draftIn):(checkIn.min||today);
        renderLabels();
        // The existing sidebar-script listens for change and refreshes availability/price.
        checkIn.dispatchEvent(new Event('change',{bubbles:true}));
        close();
    }
    checkInTrigger.addEventListener('click',()=>open('check_in'));
    checkOutTrigger.addEventListener('click',()=>open('check_out'));
    $('hv-stay-close').addEventListener('click',close);
    overlay.addEventListener('click',e=>{if(e.target===overlay)close();});
    document.addEventListener('keydown',e=>{if(overlay.hidden)return; if(e.key==='Escape'){e.preventDefault();close();} else if(e.key==='Tab') {const focusable=Array.from(overlay.querySelectorAll('button:not(:disabled)'));const i=focusable.indexOf(document.activeElement);if(e.shiftKey&&i===0){e.preventDefault();focusable.at(-1).focus();}else if(!e.shiftKey&&i===focusable.length-1){e.preventDefault();focusable[0].focus();}}});
    $('hv-stay-prev').addEventListener('click',()=>{shown=new Date(shown.getFullYear(),shown.getMonth()-1,1);draw();});
    $('hv-stay-next').addEventListener('click',()=>{shown=new Date(shown.getFullYear(),shown.getMonth()+1,1);draw();});
    $('hv-stay-select-checkin').addEventListener('click',()=>{editing='check_in';draw();});
    $('hv-stay-select-checkout').addEventListener('click',()=>{editing=draftIn?'check_out':'check_in';draw();});
    $('hv-stay-clear').addEventListener('click',()=>{draftIn='';draftOut='';editing='check_in';error.hidden=true;draw();});
    $('hv-stay-done').addEventListener('click',()=>{
        if ((draftIn&&!draftOut)||(!draftIn&&draftOut)) { error.textContent='Vui lòng chọn đủ ngày nhận và trả phòng, hoặc dùng Xóa ngày.';error.hidden=false;return; }
        if(draftIn && (draftIn<today || draftOut<=draftIn)){error.textContent='Ngày trả phòng phải sau ngày nhận phòng.';error.hidden=false;return;}
        commit();
    });
    checkIn.addEventListener('change',renderLabels);
    checkOut.addEventListener('change',renderLabels);
    renderLabels();
})();

async function toggleWishlistDetail(hotelId, button) {
    if (!localStorage.getItem('token')) { location.href = '/login'; return; }
    button.disabled = true;
    try {
        const response = await _layoutApi('/wishlist/' + hotelId + '/toggle', { method: 'POST' });
        const data = await response.json();
        if (!response.ok) throw new Error(getApiErrorMessage(data));
        button.textContent = data.liked ? 'Đã lưu yêu thích' : 'Lưu yêu thích';
        button.setAttribute('aria-pressed', String(Boolean(data.liked)));
        showToast(data.message || 'Đã cập nhật yêu thích');
    } catch (exception) { showToast(exception.message || 'Không cập nhật được yêu thích.'); }
    finally { button.disabled = false; }
    }


    /* Angel — Hotel Detail guest popover */
    (() => {
        const overlay = document.getElementById('hv-guest-overlay');
        const trigger = document.getElementById('hv-guest-trigger');
        const input = document.getElementById('guests');
        const summary = document.getElementById('hv-guest-summary');

        if (!overlay || !trigger || !input || !summary) return;

        document.body.appendChild(overlay);

        const parsedInitial = Number(input.value);
        const initial = Number.isSafeInteger(parsedInitial) && parsedInitial > 0
            ? parsedInitial
            : 2;
        const MAX_GUESTS = 20;
        const counts = {
            adults: initial,
            children: 0,
            infants: 0
        };
        let draft = { ...counts };
        const total = values =>
            values.adults + values.children + values.infants;

        function draw() {
            overlay.querySelectorAll('.hv-guest-row').forEach(row => {
                const kind = row.dataset.kind;
                const value = draft[kind];
                row.querySelector('[data-count]').textContent = value;
                row.querySelector('[data-step="-1"]').disabled =
                    value <= (kind === 'adults' ? 1 : 0);
                row.querySelector('[data-step="1"]').disabled =
                    total(draft) >= MAX_GUESTS;
            });
        }

        function positionPopover() {
            const rect = trigger.getBoundingClientRect();
            const viewportWidth = document.documentElement.clientWidth;
            const viewportHeight = window.innerHeight;
            const edge = 8;
            const width = Math.min(
                Math.max(rect.width, Math.min(280, viewportWidth - edge * 2)),
                360,
                viewportWidth - edge * 2
            );
            const left = Math.max(edge, Math.min(rect.left, viewportWidth - width - edge));

            overlay.style.width = `${width}px`;
            overlay.style.left = `${left}px`;
            overlay.style.top = `${rect.bottom + edge}px`;
            overlay.style.maxHeight = `${Math.max(0, viewportHeight - rect.bottom - edge * 2)}px`;
        }

        function open() {
            draft = { ...counts };
            draw();
            overlay.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            positionPopover();
            overlay.querySelector('button:not(:disabled)')?.focus();
        }

        function close(restoreFocus = false) {
            overlay.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            if (restoreFocus) trigger.focus();
        }

        function save() {
            counts.adults = draft.adults;
            counts.children = draft.children;
            counts.infants = draft.infants;

            const count = total(counts);

            input.value = String(count);
            summary.textContent = `${count} khách`;

            // Báo cho logic Booking hiện tại tính lại.
            input.dispatchEvent(new Event('input', {
                bubbles: true
            }));

            close(true);
        }

        trigger.addEventListener('click', () => {
            if (overlay.hidden) open();
            else close(true);
        });

        document.getElementById('hv-guest-done')
            .addEventListener('click', save);

        document.addEventListener('pointerdown', event => {
            if (!overlay.hidden && !overlay.contains(event.target) && !trigger.contains(event.target)) {
                close();
            }
        });

        overlay.querySelectorAll('[data-step]').forEach(button => {
            button.addEventListener('click', () => {
                const row = button.closest('.hv-guest-row');
                const kind = row.dataset.kind;
                const step = Number(button.dataset.step);

                const minimum = kind === 'adults' ? 1 : 0;
                const next = draft[kind] + step;

                if (next < minimum || next > MAX_GUESTS) return;
                if (step > 0 && total(draft) >= MAX_GUESTS) return;

                draft[kind] = next;
                draw();
            });
        });

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                if (!overlay.hidden) {
                    event.preventDefault();
                    close(true);
                }
            }
        });

        document.addEventListener('scroll', () => {
            if (!overlay.hidden) positionPopover();
        }, true);
        window.addEventListener('resize', () => {
            if (!overlay.hidden) positionPopover();
        });
        window.visualViewport?.addEventListener('resize', () => {
            if (!overlay.hidden) positionPopover();
        });
        window.visualViewport?.addEventListener('scroll', () => {
            if (!overlay.hidden) positionPopover();
        });

        summary.textContent = `${total(counts)} khách`;
    })();
</script>
