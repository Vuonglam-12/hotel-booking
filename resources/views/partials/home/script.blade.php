<script>
(() => {
    const grid = document.getElementById('allHotelsGrid');
    const template = document.getElementById('hotelCardTemplate');
    const sectionTemplate = document.getElementById('hotelSectionTemplate');
    const errorBox = document.getElementById('hotelLoadError');
    const searchButton = document.querySelector('#heroSearchBar [type="submit"]');
    const state = { params: new URLSearchParams(location.search) };
    const likedHotels = new Map();
    const pendingLikes = new Set();
    const touchedLikes = new Set();
    let requestSerial = 0;
    let requestController;
    let retryRequest;
    const money = value => new Intl.NumberFormat('vi-VN').format(value) + ' ₫';
    const contextKeys = ['check_in', 'check_out', 'guests', 'adults', 'children', 'infants'];
    const apiFilterKeys = ['city', 'search', 'hotel_id', 'star', 'location_id', 'max_price', 'amenity', 'sort', 'dir', ...contextKeys];
    const cityLabel = name => ['Hồ Chí Minh', 'TP. Hồ Chí Minh', 'Thành phố Hồ Chí Minh', 'TP.HCM'].includes(name.trim()) ? 'TP.HCM' : name;
    const priorityCities = ['TP.HCM', 'Hà Nội', 'Đà Lạt', 'Đà Nẵng'];
    const compareCities = (a, b) => {
        const rank = name => { const index = priorityCities.indexOf(cityLabel(name)); return index < 0 ? priorityCities.length : index; };
        return rank(a) - rank(b) || a.localeCompare(b, 'vi');
    };
    const observedRows = new WeakSet();
    const rowObserver = new ResizeObserver(entries => entries.forEach(entry => updateRow(entry.target)));

    function updateRow(row) {
        const section = row.closest('.hv-destination-section');
        const overflow = row.scrollWidth - row.clientWidth;
        section.querySelector('[data-row-controls]').hidden = overflow <= 1;
        section.querySelector('[data-row-direction="-1"]').disabled = row.scrollLeft <= 1;
        section.querySelector('[data-row-direction="1"]').disabled = row.scrollLeft >= overflow - 1;
    }

    function observeRows() {
        grid.querySelectorAll('.hv-destination-row').forEach(row => {
            if (!observedRows.has(row)) {
                observedRows.add(row);
                rowObserver.observe(row);
                row.addEventListener('scroll', () => updateRow(row), { passive: true });
            }
            updateRow(row);
        });
    }

    function hotelUrl(hotelId, availability) {
        const params = new URLSearchParams();
        contextKeys.forEach(key => { if (state.params.get(key)) params.set(key, state.params.get(key)); });
        if(availability){params.set('room_type_id',availability.room_type_id);params.set('quantity',availability.required_rooms);}
        const url = new URL('hotels/' + hotelId, grid.dataset.homeUrl);
        url.search = params.toString();
        return url.href;
    }

    function createCard(hotel) {
        if (hotel.status !== 'active' || !Number.isInteger(Number(hotel.id)) || Number(hotel.id) <= 0) return null;
        const card = template.content.firstElementChild.cloneNode(true);
        card.dataset.hotelId = hotel.id;
        const image = card.querySelector('[data-card-field="image"]');
        image.src = HolidayVietImages.cover(hotel);
        image.alt = hotel.name || '';
        card.querySelectorAll('[data-hotel-link]').forEach(link => {
            link.href = hotelUrl(hotel.id,hotel.search_availability);
            link.setAttribute('aria-label', hotel.name || 'Xem khách sạn');
        });
        card.querySelector('[data-card-field="name"]').textContent = hotel.name || '';
        card.querySelector('[data-card-field="location"]').textContent = hotel.location?.name || '';
        const price = Number(hotel.rooms_min_price);
        const priceElement = card.querySelector('[data-card-field="price"]');
        priceElement.replaceChildren();
        if (Number.isFinite(price) && price > 0) {
            const amount = document.createElement('strong');
            amount.textContent = money(price);
            const unit = document.createElement('span');
            unit.textContent = ' / đêm';
            priceElement.append('Từ ', amount, unit);
        } else priceElement.textContent = 'Liên hệ để biết giá';

        const ratingElement = card.querySelector('[data-card-field="rating"]');
        const reviewCount = Number(hotel.review_count);
        const reviewAverage = Number(hotel.rating_breakdown?.avg_overall);
        const hasVerifiedReviews = reviewCount > 0 && reviewAverage > 0 && Number.isFinite(reviewAverage);
        ratingElement.hidden = !hasVerifiedReviews;
        ratingElement.textContent = hasVerifiedReviews ? reviewAverage.toFixed(1) + ' · ' + reviewCount + ' đánh giá' : '';
        const availability=card.querySelector('[data-card-field="availability"]');
        const match=hotel.search_availability;
        availability.hidden=!match?.dates_checked;
        availability.textContent=match?.dates_checked?match.required_rooms+' phòng cho '+match.guests+' khách · Còn phòng theo ngày đã chọn':'';
        const wishlist = card.querySelector('.wishlist-btn');
        wishlist.dataset.hotel = hotel.id;
        wishlist.setAttribute('aria-pressed', String(likedHotels.get(Number(hotel.id)) || false));
        wishlist.setAttribute('aria-label', 'Lưu ' + hotel.name + ' vào yêu thích');
        return card;
    }

    function renderGrid(hotels) {
        const existingIds = new Set();
        const groups = new Map();
        hotels.forEach(hotel => {
            const name = hotel.location?.name?.trim();
            if (!name || existingIds.has(String(hotel.id))) return;
            const card = createCard(hotel);
            if (!card) return;
            existingIds.add(String(hotel.id));
            const key = hotel.location.id ? 'id:' + hotel.location.id : 'name:' + name;
            if (!groups.has(key)) groups.set(key, { key, name, cards: [] });
            groups.get(key).cards.push(card);
        });
        grid.querySelectorAll('.hv-destination-row').forEach(row => rowObserver.unobserve(row));
        const sections = Array.from(groups.values()).sort((a, b) => compareCities(a.name, b.name)).map((group, index) => {
            const section = sectionTemplate.content.firstElementChild.cloneNode(true);
            const heading = section.querySelector('h2');
            const row = section.querySelector('.hv-destination-row');
            const name = cityLabel(group.name);
            section.dataset.locationKey = group.key;
            section.dataset.locationName = group.name;
            heading.id = 'destination-heading-' + index;
            heading.textContent = 'Những ngôi nhà ở ' + name;
            section.setAttribute('aria-labelledby', heading.id);
            row.id = 'destination-row-' + index;
            row.setAttribute('aria-label', 'Khách sạn ở ' + name);
            row.append(...group.cards);
            section.querySelectorAll('[data-row-direction]').forEach(button => {
                button.setAttribute('aria-controls', row.id);
                button.setAttribute('aria-label', (button.dataset.rowDirection === '-1' ? 'Xem các khách sạn trước ở ' : 'Xem các khách sạn tiếp theo ở ') + name);
            });
            return section;
        });
        grid.replaceChildren(...sections);
        if (!grid.querySelector('.hotel-card')) {
            const message = document.createElement('p');
            message.className = 'hv-home-empty';
            message.textContent = 'Không tìm thấy khách sạn phù hợp.';
            grid.append(message);
        }
        observeRows();
        document.getElementById('homeHotelStatus').textContent = 'Đã hiển thị ' + existingIds.size + ' khách sạn tại ' + sections.length + ' địa điểm.';
    }
    // Preserve the shared Home entry point used by existing filter integrations.
    window.renderHotelGrid = hotels => renderGrid(Array.isArray(hotels) ? hotels : []);

    function updateFilterState() {
        const city = state.params.get('city') || '';
        document.querySelectorAll('.city-filter-link').forEach(link => {
            if ((link.dataset.city || '') === city) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
        });
        const heading=document.getElementById('home-hotels-heading');
        if(heading)heading.textContent=city?'Khách sạn tại '+cityLabel(city):'Khám phá khách sạn';
        const clear=document.getElementById('clearHotelFilters');
        if(clear)clear.hidden=!apiFilterKeys.some(key=>state.params.get(key));
        HolidayVietSearch.sync();
    }

    async function loadHotels({ params = state.params, historyMode = 'none', retry = false } = {}) {
        requestController?.abort();
        requestController = new AbortController();
        const serial = ++requestSerial;
        const requestedParams = new URLSearchParams(params);
        requestedParams.delete('page');
        retryRequest = () => loadHotels({ params: requestedParams, historyMode, retry: true });
        errorBox.hidden = true;
        grid.setAttribute('aria-busy', 'true');
        searchButton.disabled=true;searchButton.setAttribute('aria-busy','true');
        document.getElementById('homeHotelStatus').textContent='Đang tìm khách sạn phù hợp…';
        const query = new URLSearchParams();
        apiFilterKeys.forEach(key => { if (requestedParams.get(key)) query.set(key, requestedParams.get(key)); });
        try {
            let hotels;
            if (!query.size && !retry) {
                // Reuse the complete active catalog already requested by the header search.
                hotels = await HolidayVietSearch.catalog();
            } else {
                hotels = [];
                query.set('per_page', '100');
                let page = 1;
                let lastPage = 1;
                do {
                    query.set('page', page);
                    const response = await _layoutApi('/hotels?' + query, { signal: requestController.signal });
                    if (!response.ok) throw new Error('Không tải được khách sạn.');
                    const data = await response.json();
                    if (!Array.isArray(data.data) || !Number.isInteger(Number(data.last_page)) || Number(data.last_page) < 1) throw new Error('Dữ liệu khách sạn không hợp lệ.');
                    hotels.push(...data.data);
                    lastPage = Number(data.last_page);
                    page++;
                } while (page <= lastPage);
            }
            if (serial !== requestSerial) return;
            state.params = requestedParams;
            if (historyMode === 'push') {
                const url = new URL(grid.dataset.homeUrl);
                url.search = requestedParams.toString();
                history.pushState(null, '', url);
            }
            renderGrid(hotels);
            updateFilterState();
        } catch (error) {
            if (error.name !== 'AbortError' && serial === requestSerial) errorBox.hidden = false;
        } finally {
            if (serial === requestSerial) {
                grid.setAttribute('aria-busy', 'false');
                searchButton.disabled=false;searchButton.removeAttribute('aria-busy');
            }
        }
    }

    function setWishlistState(hotelId, liked) {
        likedHotels.set(hotelId, liked);
        document.querySelectorAll('.wishlist-btn[data-hotel="' + hotelId + '"]').forEach(button => {
            button.setAttribute('aria-pressed', String(liked));
            button.disabled = pendingLikes.has(hotelId);
        });
    }

    window.toggleWishlist = async (hotelId, button) => {
        if (!localStorage.getItem('token')) { window.requestCustomerLogin('Vui lòng đăng nhập để lưu khách sạn yêu thích.'); return; }
        if (pendingLikes.has(hotelId)) return;
        touchedLikes.add(hotelId);
        pendingLikes.add(hotelId);
        button.disabled = true;
        try {
            const response = await _layoutApi('/wishlist/' + hotelId + '/toggle', { method: 'POST' });
            if(response.status===401){localStorage.removeItem('token');localStorage.removeItem('user');window.requestCustomerLogin('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.');return;}
            if (!response.ok) throw new Error(response.status === 401 ? 'Vui lòng đăng nhập lại.' : 'Không thể cập nhật yêu thích.');
            const data = await response.json();
            if (typeof data.liked !== 'boolean') throw new Error('Không thể cập nhật yêu thích.');
            likedHotels.set(hotelId, data.liked);
            showToast(data.liked ? 'Đã lưu khách sạn yêu thích.' : 'Đã bỏ yêu thích.');
        } catch (error) { showToast(error.message); }
        finally { pendingLikes.delete(hotelId); setWishlistState(hotelId, likedHotels.get(hotelId) || false); }
    };

    document.addEventListener('click', event => {
        const rowArrow = event.target.closest('[data-row-direction]');
        if (rowArrow && grid.contains(rowArrow)) {
            const row = rowArrow.closest('.hv-destination-section').querySelector('.hv-destination-row');
            row.scrollBy({ left: Number(rowArrow.dataset.rowDirection) * (row.clientWidth + parseFloat(getComputedStyle(row).columnGap)), behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
            return;
        }
        const wishlist = event.target.closest('.wishlist-btn');
        if (wishlist && grid.contains(wishlist)) {
            event.preventDefault();
            event.stopPropagation();
            window.toggleWishlist(Number(wishlist.dataset.hotel), wishlist);
            return;
        }
        const cityLink = event.target.closest('.city-filter-link');
        const resetLink = event.target.closest('.city-reset-link');
        if (!cityLink && !resetLink) return;
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        const params = new URLSearchParams(state.params);
        params.delete('page');
        if (resetLink) apiFilterKeys.forEach(key => params.delete(key));
        else if (cityLink.dataset.city) {['search','hotel_id','location_id'].forEach(key=>params.delete(key));params.set('city', cityLink.dataset.city);}
        else params.delete('city');
        loadHotels({ params, historyMode: 'push' });
    });
    document.getElementById('retryHotelLoad').addEventListener('click', () => retryRequest?.());
    document.getElementById('heroSearchBar').addEventListener('submit',event=>{
        if(event.defaultPrevented)return;
        event.preventDefault();
        const params=new URLSearchParams(state.params);
        ['city','search','hotel_id','location_id','page',...contextKeys].forEach(key=>params.delete(key));
        new FormData(event.currentTarget).forEach((value,key)=>{if(String(value).trim()!=='')params.set(key,String(value).trim());});
        loadHotels({params,historyMode:'push'});
    });
    window.addEventListener('popstate', () => loadHotels({ params: new URLSearchParams(location.search) }));

    function loadSavedWishlist() {
        if(!localStorage.getItem('token'))return;
        _layoutApi('/wishlist').then(response => response.ok ? response.json() : []).then(items => {
            if (!Array.isArray(items)) return;
            items.forEach(item => {
                const hotelId = Number(item.hotel_id);
                if (Number.isInteger(hotelId) && hotelId > 0 && !touchedLikes.has(hotelId)) setWishlistState(hotelId, true);
            });
        }).catch(() => {});
    }
    loadSavedWishlist();
    window.addEventListener('message',event=>{
        if(event.origin===location.origin&&event.data?.type==='aurel-authenticated'){
            likedHotels.clear();touchedLikes.clear();document.querySelectorAll('.wishlist-btn').forEach(button=>button.setAttribute('aria-pressed','false'));loadSavedWishlist();
        }
    });

    observeRows();
    loadHotels();
})();
</script>
