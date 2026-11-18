<script>
(() => {
    const form = document.getElementById('heroSearchBar');
    const input = document.getElementById('searchInput');
    const cityInput = document.getElementById('searchCity');
    const locationInput = document.getElementById('searchLocationId');
    const hotelInput = document.getElementById('searchHotelId');
    const dropdown = document.getElementById('searchDropdown');
    const checkIn = document.getElementById('checkInInput');
    const checkOut = document.getElementById('checkOutInput');
    const guests = document.getElementById('guestsInput');
    const knownCities = new Map();
    const normalize = value => String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[đĐ]/g, 'd').toLowerCase().trim();
    let catalogPromise;
    let catalog = [];
    let suggestions = [];
    let selectedIndex = -1;
    let suggestionsRequested = false;
    let suggestionController, suggestionTimer, suggestionSerial = 0, suppressFocus = false;
    let syncedQuery = location.search;

    async function getCatalog() {
        if (!catalogPromise) catalogPromise = (async () => {
            const records = new Map();
            let page = 1;
            let lastPage = 1;
            do {
                const response = await _layoutApi('/hotels?per_page=100&page=' + page);
                if (!response.ok) throw new Error('Không tải được địa điểm.');
                const data = await response.json();
                if (!Array.isArray(data.data)) throw new Error('Dữ liệu khách sạn không hợp lệ.');
                data.data.filter(hotel => hotel.status === 'active').forEach(hotel => records.set(hotel.id, hotel));
                lastPage = Number(data.last_page) || 1;
                page++;
            } while (page <= lastPage);
            catalog = Array.from(records.values());
            catalog.forEach(hotel => { if (hotel.location?.name) knownCities.set(normalize(hotel.location.name),hotel.location); });
            return catalog;
        })().catch(error => { catalogPromise = null; throw error; });
        return catalogPromise;
    }

    function closeSuggestions() {
        const wasOpen = !dropdown.classList.contains('hidden');
        suggestionsRequested = false;
        clearTimeout(suggestionTimer);
        suggestionController?.abort();
        suggestionSerial++;
        dropdown.classList.add('hidden');
        form.classList.remove('has-suggestions');
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        selectedIndex = -1;
        return wasOpen;
    }

    function updateSummary() {
        document.querySelector('[data-search-summary-place]').textContent = input.value.trim() || 'Địa điểm bất kỳ';
        const dates = checkIn.value && checkOut.value ? checkIn.value.slice(5).split('-').reverse().join('/')
            + ' – ' + checkOut.value.slice(5).split('-').reverse().join('/') : 'Thêm ngày';
        document.querySelector('[data-search-summary-dates]').textContent = dates;
        document.querySelector('[data-search-summary-guests]').textContent = (guests.value || '2') + ' khách';
        document.getElementById('search-dates-value').textContent = dates;
        document.getElementById('search-guests-value').textContent = (guests.value || '2') + ' khách';
    }

    function updateDates() {
        checkOut.setCustomValidity('');
        checkIn.required = Boolean(checkOut.value);
        checkOut.required = Boolean(checkIn.value);
        if (checkIn.value) {
            const nextDay = new Date(checkIn.value + 'T00:00:00Z');
            nextDay.setUTCDate(nextDay.getUTCDate() + 1);
            checkOut.min = nextDay.toISOString().slice(0, 10);
            if (checkOut.value && checkOut.value <= checkIn.value) checkOut.setCustomValidity('Ngày trả phòng phải sau ngày nhận phòng.');
            else if(checkOut.value && (Date.parse(checkOut.value+'T00:00:00Z')-nextDay.getTime())/86400000>=365) checkOut.setCustomValidity('Kỳ lưu trú tối đa 365 đêm.');
        } else checkOut.min = checkIn.min;
        updateSummary();
    }

    function selectSuggestion(suggestion) {
        input.value = suggestion.value;
        const city = suggestion.location;
        cityInput.value = city?.name || '';
        cityInput.disabled = !city;
        locationInput.value = city?.id || '';
        locationInput.disabled = !city;
        hotelInput.value = suggestion.hotel?.id || '';
        hotelInput.disabled = !suggestion.hotel;
        input.name = city ? '' : 'search';
        closeSuggestions();
        updateSummary();
        if(document.activeElement!==input){suppressFocus=true;input.focus({preventScroll:true});}
    }

    function positionSuggestions() {
        if(dropdown.classList.contains('hidden'))return;
        const height=window.visualViewport?.height||window.innerHeight;
        dropdown.style.maxHeight=Math.max(64,Math.min(440,height-dropdown.getBoundingClientRect().top-16))+'px';
    }

    function suggestionMessage(message, retry=false) {
        dropdown.replaceChildren();
        const text=document.createElement('p');text.className='hv-suggestion-message';text.setAttribute('role','status');text.textContent=message;dropdown.append(text);
        if(retry){const button=document.createElement('button');button.type='button';button.className='hv-suggestion-retry';button.textContent='Thử lại';button.addEventListener('click',requestSuggestions);dropdown.append(button);}
    }

    function renderSuggestions(data) {
        if (!suggestionsRequested) return;
        dropdown.replaceChildren();
        suggestions = [
            ...data.locations.map(city => ({value:city.name,subtitle:city.hotels_count+' khách sạn'+(city.country?' · '+city.country:''),location:city,group:input.value.trim()?'Địa phương':'Điểm đến gợi ý'})),
            ...data.hotels.map(hotel=>({value:hotel.name,subtitle:hotel.location?.name||'',hotel,group:'Khách sạn'}))
        ];
        data.locations.forEach(city=>knownCities.set(normalize(city.name),city));
        selectedIndex = -1;
        let currentGroup;
        suggestions.forEach((suggestion, index) => {
            if(currentGroup!==suggestion.group){const heading=document.createElement('p');heading.className='hv-suggestion-heading';heading.textContent=suggestion.group;heading.setAttribute('aria-hidden','true');dropdown.append(heading);currentGroup=suggestion.group;}
            const option = document.createElement('button');
            option.type = 'button';
            option.id = 'search-suggestion-' + index;
            option.className = 'hv-search-suggestion';
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', 'false');
            option.tabIndex=-1;
            if (suggestion.hotel) {
                const image = document.createElement('img');
                image.src = HolidayVietImages.cover(suggestion.hotel);
                image.alt = '';
                image.setAttribute('data-accommodation-image', '');
                option.append(image);
            } else {
                const icon=document.createElement('span');icon.className='hv-suggestion-place-icon';
                icon.innerHTML='<svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg>';
                option.append(icon);
            }
            const text = document.createElement('span');
            text.textContent = suggestion.value;
            const subtitle = document.createElement('small');
            subtitle.textContent = suggestion.subtitle;
            text.append(subtitle);
            option.append(text);
            option.addEventListener('click', () => selectSuggestion(suggestion));
            dropdown.append(option);
        });
        if(!suggestions.length)suggestionMessage('Không tìm thấy địa phương hoặc khách sạn phù hợp.');
        positionSuggestions();
    }

    async function requestSuggestions() {
        clearTimeout(suggestionTimer);
        suggestionController?.abort();
        suggestionController=new AbortController();
        const serial=++suggestionSerial;
        suggestionsRequested=true;suggestions=[];selectedIndex=-1;
        dropdown.classList.remove('hidden');form.classList.add('has-suggestions');input.setAttribute('aria-expanded','true');input.removeAttribute('aria-activedescendant');
        suggestionMessage('Đang tải gợi ý…');positionSuggestions();
        try {
            const response=await _layoutApi('/hotels/suggestions?q='+encodeURIComponent(input.value.trim()),{signal:suggestionController.signal});
            if(!response.ok)throw new Error('Suggestions unavailable');
            const data=await response.json();
            if(!Array.isArray(data.locations)||!Array.isArray(data.hotels))throw new Error('Invalid suggestions');
            if(serial===suggestionSerial&&suggestionsRequested)renderSuggestions(data);
        } catch(error){if(error.name!=='AbortError'&&serial===suggestionSerial&&suggestionsRequested){suggestionMessage('Chưa thể tải gợi ý. Vui lòng thử lại.',true);positionSuggestions();}}
    }

    input.addEventListener('input', () => {
        cityInput.disabled=locationInput.disabled=hotelInput.disabled=true;
        cityInput.value=locationInput.value=hotelInput.value='';input.name='search';
        suggestionController?.abort();suggestionSerial++;
        suggestions=[];selectedIndex=-1;suggestionsRequested=true;dropdown.classList.remove('hidden');form.classList.add('has-suggestions');input.setAttribute('aria-expanded','true');input.removeAttribute('aria-activedescendant');suggestionMessage('Đang tìm gợi ý…');positionSuggestions();
        clearTimeout(suggestionTimer);
        suggestionTimer=setTimeout(requestSuggestions,200);
        updateSummary();
    });
    input.addEventListener('focus', () => {
        window.HolidayVietSearch.closePanels?.();
        form.classList.add('has-active-field');
        input.closest('.hv-search-field').classList.add('is-active');
        if(suppressFocus){suppressFocus=false;return;}
        requestSuggestions();
    });
    input.addEventListener('blur', () => {
        input.closest('.hv-search-field').classList.remove('is-active');
        if (!form.querySelector('.hv-search-field.is-active')) form.classList.remove('has-active-field');
    });
    input.addEventListener('keydown', event => {
        if (dropdown.classList.contains('hidden')) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            if(!suggestions.length)return;
            event.preventDefault();
            selectedIndex = selectedIndex < 0 ? (event.key === 'ArrowDown' ? 0 : suggestions.length - 1)
                : (selectedIndex + (event.key === 'ArrowDown' ? 1 : -1) + suggestions.length) % suggestions.length;
            Array.from(dropdown.querySelectorAll('[role="option"]')).forEach((option, index) => option.setAttribute('aria-selected', String(index === selectedIndex)));
            input.setAttribute('aria-activedescendant', 'search-suggestion-' + selectedIndex);
            document.getElementById('search-suggestion-'+selectedIndex)?.scrollIntoView({block:'nearest'});
        } else if (event.key === 'Enter' && selectedIndex >= 0) {
            event.preventDefault();
            selectSuggestion(suggestions[selectedIndex]);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            closeSuggestions();
        }
    });
    [checkIn, checkOut].forEach(field => field.addEventListener('input', updateDates));
    guests.addEventListener('input', updateSummary);
    const isSuggestionInteraction = target => input === target || dropdown.contains(target);
    document.addEventListener('click', event => { if (!isSuggestionInteraction(event.target) && !event.composedPath().includes(dropdown)) closeSuggestions(); });
    document.addEventListener('focusin', event => { if (!isSuggestionInteraction(event.target)) closeSuggestions(); });
    dropdown.addEventListener('mousedown',event=>{if(event.target.closest('[role="option"]'))event.preventDefault();});
    window.addEventListener('resize',positionSuggestions);
    window.visualViewport?.addEventListener('resize',positionSuggestions);

    form.addEventListener('submit', event => {
        updateDates();
        if (!form.reportValidity()) { event.preventDefault(); return; }
        const term = input.value.trim();
        const city = hotelInput.disabled ? knownCities.get(normalize(term)) : null;
        input.name = city ? '' : 'search';
        cityInput.disabled = !city;
        cityInput.value = city?.name || '';
        if(city){locationInput.value=city.id;locationInput.disabled=false;}
        closeSuggestions();
        window.HolidayVietSearch.closePanels?.();
    });

    window.HolidayVietSearch = {
        catalog: getCatalog,
        closeSuggestions,
        sync: () => {
            if(syncedQuery===location.search){updateSummary();return;}
            syncedQuery=location.search;
            const params = new URLSearchParams(location.search);
            input.value = params.get('city') || params.get('search') || '';
            cityInput.value=params.get('city')||'';cityInput.disabled=!cityInput.value;
            locationInput.value=params.get('location_id')||'';locationInput.disabled=!locationInput.value;
            hotelInput.value=params.get('hotel_id')||'';hotelInput.disabled=!hotelInput.value;
            checkIn.value=params.get('check_in')||'';checkOut.value=params.get('check_out')||'';
            guests.value=params.get('guests')||'2';
            document.getElementById('searchAdults').value=params.get('adults')||guests.value;
            document.getElementById('searchChildren').value=params.get('children')||'0';
            document.getElementById('searchInfants').value=params.get('infants')||'0';
            updateDates();form.dispatchEvent(new Event('aurel:search-sync'));
        }
    };
    updateDates();
    getCatalog().catch(() => {});
})();
</script>
@include('partials.layout.search-picker-script')
