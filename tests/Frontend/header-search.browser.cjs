// Usage: node tests/Frontend/header-search.browser.cjs [optional Chrome executable]
// Uses a standalone Blade container, synthetic HTTP responses and a temporary browser profile. No application database is loaded.
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const http = require('node:http');
const { spawn, execFileSync } = require('node:child_process');
const assert = require('node:assert/strict');
const postcss = require('postcss');
const tailwind = require('tailwindcss');
const root = path.resolve(__dirname, '../..');
const runtime = fs.mkdtempSync(path.join(os.tmpdir(), 'aurel-header-browser-'));
const chrome = process.argv[2] || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

async function browserChecks() {
    const checks = [], errors = [];
    addEventListener('error', event => errors.push(event.message));
    addEventListener('unhandledrejection', event => errors.push(String(event.reason)));
    const settle = () => new Promise(resolve => setTimeout(resolve, 220));
    const check = (condition, message) => { if (!condition) throw new Error(message); checks.push(message); };
    const $ = selector => document.querySelector(selector);
    const visible = element => Boolean(element && element.getClientRects().length && getComputedStyle(element).visibility !== 'hidden');
    const rect = selector => $(selector).getBoundingClientRect();
    const escape = () => document.activeElement.dispatchEvent(new KeyboardEvent('keydown', {key: 'Escape', bubbles: true}));
    const type = value => { $('#searchInput').focus({preventScroll: true}); $('#searchInput').value = value; $('#searchInput').dispatchEvent(new Event('input', {bubbles: true})); };
    const noOverlap = () => {
        check(rect('#header-test-banner').top + 1 >= rect('.hv-header').bottom, 'Header stays above the banner, including open disclosures');
        check(document.documentElement.scrollWidth <= innerWidth, 'No horizontal overflow');
        check(['.hv-header-row', '#searchInput', '.hv-search-dates', '.hv-search-guests', '#mobile-menu-toggle'].every(selector => {
            const element = $(selector), bounds = element.getBoundingClientRect();
            return !visible(element) || (bounds.left >= -1 && bounds.right <= innerWidth + 1);
        }), 'Header controls stay inside the viewport');
    };
    let report;
    try {
        await settle();
        const desktop = innerWidth >= 1024;
        const menu = desktop ? '#header-experiences' : '#mobile-menu';
        const toggle = desktop ? '[aria-controls="header-destinations"]' : '[aria-controls="mobile-destinations"]';
        const panel = desktop ? '#header-destinations' : '#mobile-destinations';
        const links = Array.from(document.querySelectorAll(desktop ? menu + ' a' : '#mobile-menu > a, #mobile-menu > .hv-mobile-experience-row > a')).slice(0, 4);
        check(JSON.stringify(links.map(a => a.textContent.trim())) === JSON.stringify(['Khách sạn', 'Trải nghiệm', 'Cẩm nang', 'Lịch trình AI']), 'Four menu labels and ordering');
        const future = location.search.includes('future=1');
        check(new URL(links[0].href).pathname === '/' && new URL(links[1].href).pathname === (future ? '/trai-nghiem' : '/')
            && new URL(links[2].href).pathname === (future ? '/cam-nang' : '/blog') && new URL(links[3].href).pathname === '/itineraries', 'Working fallback links and future route names');
        check(document.querySelectorAll('nav.hv-header').length === 1, 'One shared header');
        check(Math.abs(rect('#header-search-panel').top - rect('.hv-header-row').bottom) < 1, 'No reserved menu gap above search');
        check(desktop ? visible($('#header-experiences')) && !visible($('#mobile-menu-toggle')) : !visible($('#header-experiences')) && visible($('#mobile-menu-toggle')), 'Desktop/mobile navigation breakpoint');
        noOverlap();
        if (!desktop) { $('#mobile-menu-toggle').click(); await settle(); }
        $(toggle).click(); await settle();
        check(visible($(panel)) && $(toggle).getAttribute('aria-expanded') === 'true', 'Destination disclosure opens');
        check(rect(panel).bottom <= rect('#header-search-panel').top + 1, 'Destination disclosure does not cover search');
        const link = $(panel + ' a');
        const hit = document.elementFromPoint(link.getBoundingClientRect().left + 12, link.getBoundingClientRect().top + 12);
        check(hit === link || link.contains(hit), 'Destination link is not covered by another stacking context');
        const linkParams = new URL(link.href).searchParams;
        if (location.search.includes('check_in=')) check(linkParams.get('check_in') === '2030-11-10' && linkParams.get('check_out') === '2030-11-13' && linkParams.get('guests') === '4', 'Destination links retain dates and guest context');
        noOverlap();
        escape(); await settle();
        check(!visible($(panel)) && document.activeElement === $(toggle), 'Escape closes destinations and restores focus');
        $(toggle).click(); await settle();
        let filtered = 0;
        document.addEventListener('click', event => { if (event.target.closest('.city-filter-link')) filtered++; if (event.target.closest('.hv-header a')) event.preventDefault(); });
        $(panel + ' a').click(); await settle();
        check(!visible($(panel)) && !$('#mobile-menu').classList.contains('open') && filtered === 0, 'Selection closes navigation without hotel-filter click handlers');
        if (!desktop) $('#mobile-menu-toggle').click();
        $(toggle).click();
        $('#outside-header').click(); await settle();
        check(!visible($(panel)) && !$('#mobile-menu').classList.contains('open'), 'Outside click closes navigation');
        // A city with no hotel still comes from JSON after all destination links are removed.
        const destinationLinks = Array.from(document.querySelectorAll('.hv-navigation-panel a')).map(link => [link, link.parentElement]);
        destinationLinks.forEach(([link]) => link.remove());
        type('Hue'); await settle();
        check(visible($('#searchDropdown')) && $('#searchDropdown').textContent.includes('Huế'), 'Autocomplete uses independent city data, including cities without hotels');
        destinationLinks.forEach(([link, parent]) => parent.append(link));
        check(rect('#searchDropdown').top >= rect('.hv-search-guests').bottom - 1, 'Suggestions are anchored after all search fields');
        noOverlap();
        $('#searchDropdown button').click(); await settle();
        check($('#searchInput').value === 'Huế' && !visible($('#searchDropdown')), 'Selecting a suggestion closes the list');
        type('Da'); await settle(); escape(); await settle();
        check(!visible($('#searchDropdown')) && document.activeElement === $('#searchInput'), 'Escape closes suggestions without reopening');
        type('Da'); await settle(); $('#search-dates-toggle').click(); await settle();
        check(!visible($('#searchDropdown')) && visible($('#search-dates-panel')), 'Date interaction closes suggestions');
        escape(); await settle();
        check(!visible($('#search-dates-panel')) && document.activeElement === $('#search-dates-toggle'), 'Escape closes calendar and restores focus');
        $('#search-dates-toggle').click(); await settle();
        const dates = Array.from(document.querySelectorAll('[data-calendar-date]:not(:disabled)')).slice(1, 3);
        dates[0].click(); await settle();
        document.querySelector('[data-calendar-date="' + dates[1].dataset.calendarDate + '"]').click();
        $('#search-dates-done').click(); await settle();
        check($('#checkOutInput').value > $('#checkInInput').value && !visible($('#search-dates-panel')), 'Calendar selection retains a valid stay range');
        $('#search-guests-toggle').click(); await settle();
        const before = Number($('#guestsInput').value);
        $('[data-guest-kind="children"][data-guest-step="1"]').click();
        check(Number($('#guestsInput').value) === before + 1, 'Guest picker preserves total guest count');
        escape(); await settle();
        // Existing account and mobile toggles must close every search popover.
        window.renderAuthenticatedHeader({name: 'Header Fixture'});
        type('Da'); await settle(); $('#user-menu-toggle').click(); await settle();
        check(!visible($('#searchDropdown')) && $('#user-dropdown-menu').classList.contains('open'), 'Account interaction closes autocomplete');
        $('#search-guests-toggle').click(); await settle();
        check(!$('#user-dropdown-menu').classList.contains('open') && visible($('#search-guests-panel')), 'Search interaction closes account menu');
        $('#notif-bell').click(); await settle();
        check(!visible($('#search-guests-panel')) && $('#notif-dropdown').classList.contains('open'), 'Notification interaction closes guest picker');
        escape(); await settle();
        $('#search-guests-toggle').click(); await settle();
        dispatchEvent(new Event('resize')); await settle();
        check(!visible($('#search-guests-panel')) && !visible($('#searchDropdown')), 'Resize closes search popovers');
        const set = (selector, value) => { $(selector).value = value; $(selector).dispatchEvent(new Event('input', {bubbles: true})); };
        let submitted;
        $('#heroSearchBar').addEventListener('submit', event => { event.preventDefault(); submitted = Object.fromEntries(new FormData(event.target)); });
        type('ha noi'); set('#checkInInput', '2030-11-10'); set('#checkOutInput', '2030-11-13'); set('#guestsInput', '4');
        $('#heroSearchBar').requestSubmit(); await settle();
        check(submitted?.city === 'Hà Nội' && !submitted.search && submitted.check_in === '2030-11-10' && submitted.check_out === '2030-11-13' && submitted.guests === '4', 'City search submits city, dates and guests unchanged');
        type('Aurel Sample'); $('#heroSearchBar').requestSubmit(); await settle();
        check(submitted?.search === 'Aurel Sample' && !submitted.city, 'Hotel-name search retains the search parameter');
        type('Hue'); $('#searchInput').dispatchEvent(new KeyboardEvent('keydown', {key: 'ArrowUp', bubbles: true}));
        check($('#searchDropdown [aria-selected="true"]') !== null, 'Keyboard suggestion navigation');
        $('#outside-header').focus(); window.scrollTo(0, 450); await settle();
        check($('.hv-header').classList.contains('is-compact') && !visible($('#header-search-panel')) && rect('.hv-header').height <= 65, 'Scrolled header compacts without a hidden search gap');
        $('#compact-search-toggle').click(); await settle();
        check(visible($('#header-search-panel')) && document.activeElement === $('#searchInput'), 'Compact search expands and focuses destination');
        $('#outside-header').focus(); window.scrollTo(0, 0); await settle();
        if (!desktop) { $('#mobile-menu-toggle').click(); await settle(); check($('#mobile-menu').classList.contains('open'), 'Mobile menu opens'); escape(); await settle(); check(!$('#mobile-menu').classList.contains('open'), 'Escape closes mobile menu'); }
        noOverlap();
        check(errors.length === 0, 'No browser script errors');
        report = {ok: true, viewport: [innerWidth, innerHeight], checks, errors};
    } catch (error) { report = {ok: false, viewport: [innerWidth, innerHeight], checks, errors, failure: error.stack}; }
    report.geometry = Object.fromEntries(['.hv-header', '.hv-header-row', '#searchInput', '#mobile-menu-toggle'].map(selector => [selector, $(selector).getBoundingClientRect().toJSON()]));
    const output = document.createElement('pre'); output.id = 'header-check-result';
    output.dataset.result = btoa(unescape(encodeURIComponent(JSON.stringify(report))));
    output.textContent = JSON.stringify(report); document.body.append(output);
}

(async () => {
    const sources = ['resources/views/Layout.blade.php', 'resources/views/partials/layout/header.blade.php', 'resources/views/partials/layout/search.blade.php', 'resources/views/partials/layout/search-script.blade.php', 'resources/views/partials/layout/search-picker-script.blade.php'];
    const css = await postcss([tailwind({content: sources.map(file => ({raw: fs.readFileSync(path.join(root, file), 'utf8'), extension: 'html'})), theme: {extend: {fontFamily: {sans: ['Nunito Sans', 'system-ui', 'sans-serif']}}}})])
        .process(fs.readFileSync(path.join(root, 'resources/css/app.css'), 'utf8'), {from: undefined});
    const shellCss = ['design-system.css', 'hotel-explore.css', 'site-shell.css'].map(file => fs.readFileSync(path.join(root, 'resources/css', file), 'utf8').replace(/^@import .*;\r?\n/gm, '')).join('\n');
    const pages = new Map(), requests = [];
    const server = http.createServer((req, res) => {
        requests.push(req.url);
        const url = new URL(req.url, 'http://127.0.0.1');
        if (pages.has(url.pathname + url.search)) { res.setHeader('Content-Type', 'text/html; charset=utf-8'); return res.end(pages.get(url.pathname + url.search)); }
        if (url.pathname === '/header-test.css') { res.setHeader('Content-Type', 'text/css'); return res.end(css.css + '\n' + shellCss); }
        if (url.pathname === '/api/hotels') {
            res.setHeader('Content-Type', 'application/json');
            return res.end(JSON.stringify({data: [{id: 1, name: 'Aurel Sample', status: 'active', location: {name: 'Hà Nội'}}], last_page: 1}));
        }
        if (url.pathname.startsWith('/api/notifications')) { res.setHeader('Content-Type', 'application/json'); return res.end(JSON.stringify({data: [], unread_count: 0})); }
        const assets = {'/image/logothuonghieu.png': 'image/logothuonghieu.png', '/image/placeholders/hotel.svg': 'image/placeholders/hotel.svg'};
        if (assets[url.pathname] && fs.existsSync(path.join(root, 'public', assets[url.pathname]))) return res.end(fs.readFileSync(path.join(root, 'public', assets[url.pathname])));
        res.writeHead(404); res.end('Fixture route unavailable');
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const origin = 'http://127.0.0.1:' + server.address().port;
    const cases = [[1440, 1000, '/'], [1024, 800, '/blog'], [1023, 800, '/deals'], [768, 900, '/hotels/1'], [640, 800, '/blog'], [390, 844, '/?city=H%C3%A0%20N%E1%BB%99i&check_in=2030-11-10&check_out=2030-11-13&guests=4'], [320, 568, '/blog'], [1440, 1000, '/?future=1']]
        .filter(([width]) => !process.env.AUREL_HEADER_TEST_WIDTHS || process.env.AUREL_HEADER_TEST_WIDTHS.split(',').includes(String(width)));
    const reports = [];
    // Chrome's pipe transport allows exact device viewports without dependencies or a debug network port.
    const browser = spawn(chrome, ['--headless=new', '--no-first-run', '--no-default-browser-check', '--disable-background-networking', '--disable-component-update', '--disable-sync', '--disable-extensions', '--disable-gpu', '--user-data-dir=' + path.join(runtime, 'chrome-profile'), '--remote-debugging-pipe'], {stdio: ['ignore', 'ignore', 'pipe', 'pipe', 'pipe']});
    const pending = new Map(); let nextId = 0, buffer = '', browserErrors = '';
    browser.stdio[4].setEncoding('utf8');
    browser.stderr.on('data', chunk => browserErrors += chunk);
    browser.stdio[4].on('data', chunk => {
        buffer += chunk.toString();
        let end;
        while ((end = buffer.indexOf('\0')) !== -1) {
            const message = JSON.parse(buffer.slice(0, end)); buffer = buffer.slice(end + 1);
            const item = pending.get(message.id);
            if (item) { pending.delete(message.id); clearTimeout(item.timer); message.error ? item.reject(new Error(JSON.stringify(message.error))) : item.resolve(message.result); }
        }
    });
    const send = (method, params = {}, sessionId) => new Promise((resolve, reject) => {
        const id = ++nextId;
        const timer = setTimeout(() => { pending.delete(id); reject(new Error('Chrome command timed out: ' + method + '\n' + browserErrors.slice(-1000))); }, 20000);
        pending.set(id, {resolve, reject, timer});
        browser.stdio[3].write(JSON.stringify({id, method, params, ...(sessionId ? {sessionId} : {})}) + '\0');
    });
    try {
        const target = await send('Target.createTarget', {url: 'about:blank'});
        const {sessionId} = await send('Target.attachToTarget', {targetId: target.targetId, flatten: true});
        const page = (method, params) => send(method, params, sessionId);
        await page('Page.enable'); await page('Runtime.enable');
        for (const [width, height, pagePath] of cases) {
            const args = [path.join(root, 'tests/Support/header_preview.php'), runtime, origin, pagePath];
            if (pagePath.includes('future=1')) args.push('--future-routes');
            let html = execFileSync('php', args, {cwd: root, encoding: 'utf8', maxBuffer: 4 * 1024 * 1024});
            html = html.replace('</body>', '<script>(' + browserChecks.toString() + ')();</script></body>');
            pages.set(pagePath, html);
            const prefix = path.join(runtime, 'viewport-' + width + (pagePath.includes('future=1') ? '-future' : ''));
            await page('Emulation.setDeviceMetricsOverride', {width, height, screenWidth: width, screenHeight: height, deviceScaleFactor: 1, mobile: false});
            await page('Page.navigate', {url: origin + pagePath});
            let encoded;
            const deadline = Date.now() + 30000;
            while (Date.now() < deadline) {
                const result = await page('Runtime.evaluate', {expression: 'document.getElementById("header-check-result")?.dataset.result', returnByValue: true});
                encoded = result.result.value;
                if (encoded) break;
                await new Promise(resolve => setTimeout(resolve, 100));
            }
            const dom = await page('Runtime.evaluate', {expression: 'document.documentElement.outerHTML', returnByValue: true});
            fs.writeFileSync(prefix + '.html', dom.result.value);
            assert.ok(encoded, 'Browser result missing; inspect ' + prefix + '.html');
            const report = JSON.parse(Buffer.from(encoded, 'base64').toString('utf8'));
            const screenshot = await page('Page.captureScreenshot', {format: 'png', clip: {x: 0, y: 0, width, height, scale: 1}, captureBeyondViewport: true});
            fs.writeFileSync(prefix + '.png', Buffer.from(screenshot.data, 'base64'));
            reports.push({...report, page: pagePath, screenshot: prefix + '.png'});
            console.log(JSON.stringify({viewport: report.viewport, page: pagePath, ok: report.ok, checks: report.checks.length, failure: report.failure}));
        }
        fs.writeFileSync(path.join(runtime, 'report.json'), JSON.stringify({synthetic_only: true, database_loaded: false, reports, requests}, null, 2));
        assert.ok(reports.every(report => report.ok), 'Some header checks failed.');
    } finally { browser.kill(); server.close(); console.log('Header test artifacts: ' + runtime); }
})().catch(error => { console.error(error.stack); process.exitCode = 1; });
