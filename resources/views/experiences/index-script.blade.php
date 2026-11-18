<script>
(() => {
    const form = document.getElementById('experience-filters');
    const locationSelect = document.getElementById('experience-location');
    const query = document.getElementById('experience-query');
    const sort = document.getElementById('experience-sort');
    const grid = document.getElementById('experience-grid');
    const results = document.getElementById('experience-results');
    const pagination = document.getElementById('experience-pagination');
    const categoryButtons = [...document.querySelectorAll('[data-experience-category]')];
    let category = '', page = 1, lastPage = 1, requestId = 0, pending;
    let locationsPromise;
    const element = (tag, className, text) => { const node = document.createElement(tag); node.className = className; if (text !== undefined) node.textContent = text; return node; };
    const safeUrl = value => { if (typeof value !== 'string' || !value.trim()) return ''; try { const url = new URL(value, location.origin); return url.origin === location.origin && ['http:', 'https:'].includes(url.protocol) ? url.href : ''; } catch { return ''; } };
    const noImage = () => element('div', 'exp-no-image', 'Chưa có ảnh');

    function syncFromUrl() {
        const params = new URLSearchParams(location.search);
        query.value = params.get('q') || '';
        sort.value = params.get('sort') === 'name' ? 'name' : 'latest';
        category = categoryButtons.some(button => button.dataset.experienceCategory === params.get('category')) ? params.get('category') : '';
        page = Math.max(1, Number(params.get('page')) || 1);
        return params.get('location') || '';
    }

    function currentParams() {
        const params = new URLSearchParams();
        if (locationSelect.value) params.set('location', locationSelect.value);
        if (query.value.trim()) params.set('q', query.value.trim());
        if (category) params.set('category', category);
        if (sort.value !== 'latest') params.set('sort', sort.value);
        if (page > 1) params.set('page', page);
        return params;
    }

    async function getLocations() {
        if (!locationsPromise) locationsPromise = fetch('/api/experiences/filters', {headers: {Accept: 'application/json'}})
            .then(async response => { if (!response.ok) throw new Error('filters'); return response.json(); })
            .then(data => {
                locationSelect.replaceChildren(new Option('Tất cả địa phương', ''));
                data.locations.forEach(location => locationSelect.add(new Option(location.name, location.code)));
                locationSelect.disabled = false;
            }).catch(error => { locationsPromise = null; throw error; });
        return locationsPromise;
    }

    function card(item) {
        const article = element('article', 'exp-card');
        const link = element('a', 'exp-card-link'); link.href = safeUrl(item.url);
        const cover = element('div', 'exp-cover');
        const imageUrl = safeUrl(item.cover?.url);
        if (imageUrl) {
            const image = element('img', ''); image.src = imageUrl; image.alt = item.cover.alt || item.name; image.loading = 'lazy';
            image.addEventListener('error', () => cover.replaceChildren(noImage()), {once: true}); cover.append(image);
        } else cover.append(noImage());
        const body = element('div', 'exp-card-body');
        body.append(element('span', 'exp-badge', item.category.label), element('h3', '', item.name), element('p', 'exp-card-place', item.location.name));
        if (item.summary) body.append(element('p', 'exp-card-summary', item.summary));
        const action = element('div', 'exp-card-action'); action.append(element('span', '', 'Khám phá địa điểm'), element('span', '', '↗'));
        body.append(action); link.append(cover, body); article.append(link); return article;
    }

    async function load({history = false, selectedLocation = locationSelect.value} = {}) {
        const serial = ++requestId;
        pending?.abort(); pending = new AbortController();
        const signal = pending.signal;
        results.setAttribute('aria-busy', 'true'); pagination.hidden = true;
        ['grid', 'empty', 'error'].forEach(name => document.getElementById('experience-' + name).hidden = true);
        document.getElementById('experience-loading').hidden = false;
        document.getElementById('experience-count').textContent = '';
        categoryButtons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.experienceCategory === category)));
        try {
            await getLocations();
            if (serial !== requestId) return;
            locationSelect.value = selectedLocation;
            const params = currentParams();
            if (selectedLocation && !locationSelect.value) params.set('location', selectedLocation);
            if (history) window.history.pushState(null, '', form.action + (params.size ? '?' + params : ''));
            const response = await fetch('/api/experiences?' + params, {signal, headers: {Accept: 'application/json'}});
            if (!response.ok) throw new Error('experiences');
            const data = await response.json();
            if (serial !== requestId) return;
            page = data.meta.current_page; lastPage = data.meta.last_page;
            grid.replaceChildren(...data.data.map(card));
            grid.hidden = !data.data.length;
            document.getElementById('experience-empty').hidden = data.data.length > 0;
            document.getElementById('experience-count').textContent = data.meta.total ? data.meta.total + ' trải nghiệm để khám phá' : '';
            pagination.hidden = lastPage <= 1 || !data.data.length;
            document.getElementById('experience-page-number').textContent = 'Trang ' + page + ' / ' + lastPage;
            document.getElementById('experience-prev').disabled = page <= 1;
            document.getElementById('experience-next').disabled = page >= lastPage;
        } catch (error) {
            if (error.name !== 'AbortError' && serial === requestId) document.getElementById('experience-error').hidden = false;
        } finally {
            if (serial === requestId) { document.getElementById('experience-loading').hidden = true; results.setAttribute('aria-busy', 'false'); }
        }
    }
    form.addEventListener('submit', event => { event.preventDefault(); page = 1; load({history: true}); });
    [locationSelect, sort].forEach(field => field.addEventListener('change', () => { page = 1; load({history: true}); }));
    categoryButtons.forEach(button => button.addEventListener('click', () => { category = button.dataset.experienceCategory; page = 1; load({history: true}); }));
    document.getElementById('experience-prev').addEventListener('click', () => { if (page > 1) { page--; load({history: true}); } });
    document.getElementById('experience-next').addEventListener('click', () => { if (page < lastPage) { page++; load({history: true}); } });
    document.getElementById('experience-retry').addEventListener('click', () => load({selectedLocation: new URLSearchParams(location.search).get('location') || locationSelect.value}));
    document.getElementById('experience-reset').addEventListener('click', () => { category = ''; query.value = ''; locationSelect.value = ''; sort.value = 'latest'; page = 1; load({history: true}); });
    window.addEventListener('popstate', () => load({selectedLocation: syncFromUrl()}));
    load({selectedLocation: syncFromUrl()});
})();
</script>
