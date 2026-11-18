<script>
(() => {
    const types = new Map(DETAIL_ROOM_TYPES.map(type => [Number(type.id), type]));
    const stock = { status: 'idle', counts: new Map() };
    const bedLabels = { Double: 'Giường đôi', King: 'Giường king', Twin: 'Giường twin' };
    let viewedType = null;
    let gallery;
    const money = value => new Intl.NumberFormat('vi-VN', {maximumFractionDigits:2}).format(Number(value)) + ' ₫';

    function paintPrice(type, priceNode, totalNode, modal = false) {
        const pricing = window.HolidayVietDetailPricing;
        const quote = pricing?.getQuote(type.id);
        const dated = pricing?.hasDates();
        if (modal) {
            priceNode.textContent = quote ? money(quote.average_nightly_price) : dated ? 'Đang kiểm tra giá…' : type.price > 0 ? money(type.price) : 'Chưa có thông tin giá';
            document.getElementById('room-modal-price-label').textContent = quote ? '/ phòng/đêm (bình quân)' : dated ? '' : '/ đêm';
        } else {
            priceNode.replaceChildren();
            const value = document.createElement('strong');
            const label = document.createElement('span');
            value.textContent = quote ? money(quote.average_nightly_price) : dated ? 'Đang kiểm tra giá…' : type.price > 0 ? money(type.price) : 'Chưa có thông tin giá';
            label.textContent = quote ? ' / phòng/đêm (bình quân)' : dated ? '' : ' / đêm';
            priceNode.append(value, label);
        }
        const failure = pricing?.errors.get(type.id);
        if (dated && failure) priceNode.textContent = 'Chưa lấy được giá. Vui lòng thử lại.';
        totalNode.hidden = !quote;
        totalNode.textContent = quote ? money(quote.total_price) + ' / ' + quote.quantity + ' phòng · ' + quote.nights + ' đêm' : '';
    }

    function availabilityLabel(id) {
        if (stock.status === 'loading') return 'Đang kiểm tra phòng trống…';
        if (stock.status === 'error') return 'Chưa xác định được phòng trống. Vui lòng thử lại.';
        if (stock.status !== 'ready') return 'Chọn ngày để kiểm tra phòng trống';
        const count = stock.counts.get(id);
        if (count === undefined) return 'Chọn loại phòng để kiểm tra phòng trống';
        return count > 0 ? 'Còn ' + count + ' phòng' : 'Hết phòng trong khoảng lưu trú này';
    }

    function sync() {
        const selected = Number(document.getElementById('room-select').value);
        document.querySelectorAll('article[data-room-type-id]').forEach(card => {
            const id = Number(card.dataset.roomTypeId);
            card.dataset.roomSelected = String(id === selected);
            card.querySelector('[data-room-selection-label]').hidden = id !== selected;
            paintAvailability(card.querySelector('[data-room-availability]'),id);
            paintPrice(types.get(id), card.querySelector('[data-room-price]'), card.querySelector('[data-room-stay-total]'));
        });
        if (viewedType) {
            paintAvailability(document.getElementById('room-modal-availability'),viewedType.id);
            paintPrice(viewedType, document.getElementById('room-modal-price'), document.getElementById('room-modal-stay-total'), true);
            document.getElementById('room-modal-select').disabled = !(viewedType.price > 0 || window.HolidayVietDetailPricing?.getQuote(viewedType.id))
                || (stock.status === 'ready' && stock.counts.has(viewedType.id) && stock.counts.get(viewedType.id) === 0);
        }
    }

    function paintAvailability(element,id) {
        element.textContent = availabilityLabel(id);
        const count=stock.counts.get(id);
        element.dataset.availability=stock.status==='error'?'danger':stock.status==='ready'&&count!==undefined?(count>0?'success':'danger'):stock.status==='loading'?'loading':'idle';
    }

    function open(id, opener = document.activeElement) {
        viewedType = types.get(Number(id));
        if (!viewedType) return;
        document.getElementById('room-modal-title').textContent = viewedType.name;
        document.getElementById('room-modal-description').textContent = viewedType.description || 'Chưa có mô tả cho loại phòng này.';
        document.getElementById('room-modal-amenities').textContent = viewedType.amenities?.length ? viewedType.amenities.join(' · ') : 'Chưa có thông tin tiện nghi riêng theo loại phòng.';
        const facts = document.getElementById('room-modal-facts');
        facts.replaceChildren();
        [['Sức chứa', viewedType.capacity ? 'Tối đa ' + viewedType.capacity + ' khách/phòng' : null],
            ['Loại giường', bedLabels[viewedType.bed_type] || viewedType.bed_type],
            ['Diện tích', viewedType.area ? viewedType.area + ' m²' : null]].forEach(([label, value]) => {
            if (!value) return;
            const row = document.createElement('div');
            const term = document.createElement('dt');
            const description = document.createElement('dd');
            term.textContent = label;
            description.textContent = value;
            row.append(term, description);
            facts.append(row);
        });
        document.getElementById('room-modal-price').textContent = viewedType.price > 0
            ? new Intl.NumberFormat('vi-VN').format(viewedType.price) + ' ₫' : 'Chưa có thông tin giá';
        gallery = HolidayVietDetailDialogs.photos('room', viewedType.images, viewedType.name);
        gallery.show(0);
        sync();
        HolidayVietDetailDialogs.open('room-detail-modal', opener);
    }

    document.querySelector('.hv-room-list').addEventListener('click', event => {
        const card = event.target.closest('article[data-room-type-id]');
        if (!card) return;
        open(card.dataset.roomTypeId, event.target.closest('[data-open-room]') || card.querySelector('[data-open-room]'));
    });
    function select(type) {
        if (!type || !types.has(Number(type.id))) return;
        const quantity = Number(document.getElementById('room-quantity').value);
        // Exactly one type is selected. Quantity only changes through the customer's input.
        selectedRoomTypes = { [type.id]: { ...type, quantity } };
        document.getElementById('room-select').value = type.id;
        document.getElementById('room-select-price').value = type.price;
        document.getElementById('room-select-name').value = type.name;
        document.getElementById('room-type-display-text').textContent = type.name;
        document.getElementById('selected-room-id').value = '';
        sync();
        window.calcPrice?.();
        window.refreshHotelAvailability?.();
    }
    document.getElementById('room-modal-select').addEventListener('click', () => {
        if (!viewedType || document.getElementById('room-modal-select').disabled) return;
        select(viewedType);
        HolidayVietDetailDialogs.close('room-detail-modal');
        if (window.matchMedia('(max-width: 1023px)').matches) {
            document.getElementById('hotel-booking-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
    window.HolidayVietDetailRooms = { types, stock, sync, availabilityLabel, paintAvailability, open, select };
    sync();
})();
</script>
