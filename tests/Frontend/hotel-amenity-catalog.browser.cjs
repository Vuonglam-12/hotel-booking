const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const stage = JSON.parse(fs.readFileSync(process.argv[2], 'utf8').replace(/^\uFEFF/, ''));
assert.equal(stage.synthetic_only && stage.scoped_catalog, true);
const origin = process.argv[3], port = process.argv[4];
assert.match(origin, /^http:\/\/127\.0\.0\.1:\d+$/);
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
class DevTools {
    constructor(url) {
        this.socket = new WebSocket(url); this.id = 0; this.pending = new Map(); this.events = new Map();
        this.ready = new Promise((resolve, reject) => { this.socket.addEventListener('open', resolve); this.socket.addEventListener('error', reject); });
        this.socket.addEventListener('message', event => {
            const data = JSON.parse(event.data);
            if (data.id) { const item = this.pending.get(data.id); this.pending.delete(data.id); data.error ? item.reject(new Error(data.error.message)) : item.resolve(data.result); }
            else this.events.get(data.method)?.forEach(fn => fn(data.params));
        });
    }
    on(name, fn) { if (!this.events.has(name)) this.events.set(name, []); this.events.get(name).push(fn); }
    async send(method, params = {}) { await this.ready; const id = ++this.id; return new Promise((resolve, reject) => { this.pending.set(id, {resolve, reject}); this.socket.send(JSON.stringify({id, method, params})); }); }
    async evaluate(expression) { const data = await this.send('Runtime.evaluate', {expression, awaitPromise: true, returnByValue: true, userGesture: true}); if (data.exceptionDetails) throw new Error(JSON.stringify(data.exceptionDetails)); return data.result.value; }
}
let cdp;
(async () => {
    const targets = await (await fetch('http://127.0.0.1:' + port + '/json/list')).json();
    cdp = new DevTools(targets.find(item => item.type === 'page').webSocketDebuggerUrl);
    const errors = [], checks = [];
    let failCreate = false, failLoad = false, slowCreate = false, creations = 0;
    cdp.on('Runtime.exceptionThrown', event => errors.push(event.exceptionDetails.exception?.description || event.exceptionDetails.text));
    cdp.on('Fetch.requestPaused', async event => {
        try {
            const url = new URL(event.request.url);
            if (url.origin !== origin && !['data:', 'blob:'].includes(url.protocol)) return await cdp.send('Fetch.failRequest', {requestId: event.requestId, errorReason: 'BlockedByClient'});
            if (url.pathname === '/api/admin/dashboard') return await cdp.send('Fetch.failRequest', {requestId: event.requestId, errorReason: 'BlockedByClient'});
            if (url.pathname === '/api/admin/amenities') {
                const creating = event.request.method === 'POST';
                if (creating) creations++;
                if ((creating && failCreate) || (!creating && failLoad)) {
                    const message = creating ? 'Không thể lưu tiện nghi vào danh mục. Vui lòng thử lại.' : 'Không tải được danh mục tiện nghi. Vui lòng thử lại.';
                    return await cdp.send('Fetch.fulfillRequest', {requestId: event.requestId, responseCode: 503, responseHeaders: [{name: 'Content-Type', value: 'application/json'}], body: Buffer.from(JSON.stringify({message})).toString('base64')});
                }
                if (creating && slowCreate) await delay(500);
            }
            await cdp.send('Fetch.continueRequest', {requestId: event.requestId});
        } catch (error) { errors.push(error.message); }
    });
    await cdp.send('Page.enable'); await cdp.send('Runtime.enable'); await cdp.send('Fetch.enable', {patterns: [{urlPattern: '*'}]});
    await cdp.send('Emulation.setDeviceMetricsOverride', {width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false});
    await cdp.send('Page.addScriptToEvaluateOnNewDocument', {source: `if(location.origin===${JSON.stringify(origin)})localStorage.setItem('admin_token',${JSON.stringify(stage.admin_token)});`});
    const wait = async expression => { const end = Date.now() + 18000; while (Date.now() < end) { if (await cdp.evaluate(expression)) return; await delay(80); } throw new Error('Timed out: ' + expression); };
    const click = selector => cdp.evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
    const fill = (selector, value) => cdp.evaluate(`document.querySelector(${JSON.stringify(selector)}).value=${JSON.stringify(value)}`);
    const api = async endpoint => { const response = await fetch(origin + '/api/admin' + endpoint, {headers: {Authorization: 'Bearer ' + stage.admin_token, Accept: 'application/json'}}); assert.equal(response.status, 200); return response.json(); };
    const navigate = async id => { await cdp.send('Page.navigate', {url: origin + '/admin/hotels/' + id}); await wait("document.querySelector('[data-edit-group=amenities]') && !document.querySelector('#hotelDetailContent').hidden"); };
    const edit = async () => { await click('[data-edit-group=amenities]'); await wait("document.querySelector('#editAmenities [data-amenity-options] input') && !document.querySelector('#editAmenities fieldset').disabled"); };
    const option = name => `Array.from(document.querySelectorAll('#editAmenities [data-amenity-check]')).find(input=>input.value===${JSON.stringify(name)})`;
    const select = name => cdp.evaluate(option(name) + '.click()');
    const save = async () => { await click('[data-save-group=amenities]'); await wait("document.querySelector('[data-edit-group=amenities]')"); };
    const screenshot = async name => { const shot = await cdp.send('Page.captureScreenshot', {format: 'png', captureBeyondViewport: false}); fs.writeFileSync(path.join(stage.runtime, name + '.png'), Buffer.from(shot.data, 'base64')); };
    const name = 'Vườn trên mái';
    await navigate(1); await edit();
    await fill('#editAmenities [data-amenity-name]', '  Vườn   trên mái  ');
    await cdp.evaluate("document.querySelector('#editAmenities [data-amenity-add]').click(); document.querySelector('#editAmenities [data-amenity-add]').click()");
    await wait("document.querySelector('#editAmenities [data-amenity-status]').textContent.includes('Đã lưu tiện nghi')");
    assert.equal(creations, 1);
    assert.ok((await api('/amenities')).amenities.some(item => item.key === name));
    assert.equal((await api('/hotels/1')).hotel.amenities.length, 0);
    await fill('#editAmenities [data-amenity-search]', ' VƯỜN   TRÊN MÁI ');
    await cdp.evaluate("document.querySelector('#editAmenities [data-amenity-search]').dispatchEvent(new Event('input',{bubbles:true}))");
    assert.equal(await cdp.evaluate("document.querySelectorAll('#editAmenities [data-amenity-check]').length"), 1);
    checks.push('Create persists immediately to the catalog, appears in options and search; double click sends one request; hotel remains unassigned before Save');
    await cdp.send('Page.reload'); await wait("document.querySelector('[data-edit-group=amenities]')"); await edit();
    assert.equal(await cdp.evaluate(option(name) + '.checked'), false);
    await fill('#editAmenities [data-amenity-name]', ' VƯỜN  TRÊN MÁI '); await click('#editAmenities [data-amenity-add]');
    assert.equal(await cdp.evaluate("document.querySelector('#editAmenities [data-amenity-status]').dataset.error"), 'true');
    assert.equal(creations, 1);
    await select(name); await save();
    assert.deepEqual((await api('/hotels/1')).hotel.amenities.map(item => item.amenity), [name]);
    await navigate(2); await edit();
    assert.equal(await cdp.evaluate(option(name) + '.checked'), false);
    await select(name); await save();
    const assignedOther = (await api('/hotels/2')).hotel.amenities;
    await navigate(1); await edit();
    await cdp.evaluate(`Array.from(document.querySelectorAll('#editAmenities [data-amenity-remove]')).find(button=>button.dataset.amenityRemove===${JSON.stringify(name)}).click()`);
    assert.ok(await cdp.evaluate(option(name) + ' instanceof HTMLInputElement'));
    assert.equal(await cdp.evaluate(option(name) + '.checked'), false);
    await save();
    assert.deepEqual((await api('/hotels/2')).hotel.amenities, assignedOther);
    assert.ok((await api('/amenities')).amenities.some(item => item.key === name));
    checks.push('Reload and another hotel retain the shared option; Save updates assignments; deselection retains the catalog and other hotel assignments');
    await edit();
    for (const value of ['  ', 'x'.repeat(101)]) {
        await fill('#editAmenities [data-amenity-name]', value); await click('#editAmenities [data-amenity-add]');
        assert.equal(await cdp.evaluate("document.querySelector('#editAmenities [data-amenity-status]').dataset.error"), 'true');
    }
    failCreate = true;
    await fill('#editAmenities [data-amenity-name]', 'Sân đọc sách'); await click('#editAmenities [data-amenity-add]');
    await wait("document.querySelector('#editAmenities [data-amenity-status]').dataset.error==='true' && !document.querySelector('#editAmenities fieldset').disabled");
    assert.equal(await cdp.evaluate("document.querySelector('#editAmenities [data-amenity-name]').value"), 'Sân đọc sách');
    assert.equal((await api('/amenities')).amenities.some(item => item.key === 'Sân đọc sách'), false);
    failCreate = false; slowCreate = true;
    await cdp.evaluate("document.querySelector('#editAmenities [data-amenity-add]').click(); document.querySelector('[data-save-group=amenities]').click()");
    await wait("document.querySelector('[data-edit-group=amenities]')");
    assert.deepEqual((await api('/hotels/1')).hotel.amenities.map(item => item.amenity), ['Sân đọc sách']);
    slowCreate = false;
    checks.push('Empty/long/duplicate names show errors; failed creation preserves input and creates no option; Save waits for an in-flight catalog creation');
    failLoad = true; await click('[data-edit-group=amenities]');
    await wait("document.querySelector('#editAmenities [data-amenity-retry]') && !document.querySelector('#editAmenities [data-amenity-retry]').hidden");
    await click('[data-save-group=amenities]');
    assert.ok(await cdp.evaluate("document.querySelector('#editAmenities [data-amenity-status]').textContent.includes('tải lại')"));
    failLoad = false; await click('#editAmenities [data-amenity-retry]');
    await wait("document.querySelector('#editAmenities [data-amenity-retry]').hidden && !document.querySelector('#editAmenities fieldset').disabled");
    await screenshot('amenities-desktop');
    await cdp.send('Emulation.setDeviceMetricsOverride', {width: 390, height: 844, deviceScaleFactor: 1, mobile: true});
    await cdp.evaluate("document.querySelector('#sidebarClose').click(); document.querySelector('#editAmenities').scrollIntoView({block:'start'}); scrollBy(0,-70)");
    await delay(500);
    await screenshot('amenities-mobile');
    checks.push('Catalog load failure blocks saving and provides a working retry; desktop/mobile retain separate catalog and selected sections');
    await cdp.send('Page.navigate', {url: origin + '/admin/hotels'});
    await wait("document.querySelector('[onclick=\"openAddModal()\"]') && document.querySelector('#hotelTableBody .hotel-name-link')");
    await click('[onclick="openAddModal()"]');
    await wait("document.querySelector('#amenityGrid [data-amenity-check]') && !document.querySelector('#amenityGrid fieldset').disabled");
    assert.ok(await cdp.evaluate(`Array.from(document.querySelectorAll('#amenityGrid [data-amenity-check]')).some(input=>input.value===${JSON.stringify(name)})`));
    checks.push('The new hotel form uses the same persistent catalog');
    await cdp.evaluate('closeModal(); openAddModal()');
    await wait("document.querySelector('#amenityGrid [data-amenity-check]') && !document.querySelector('#amenityGrid fieldset').disabled");
    const previousCreations=creations;
    await fill('#amenityGrid [data-amenity-name]', 'Thư viện chung');
    await click('#amenityGrid [data-amenity-add]');
    await wait("document.querySelector('#amenityGrid [data-amenity-status]').textContent.includes('Đã lưu tiện nghi')");
    assert.equal(creations,previousCreations+1);
    checks.push('Repeated modal openings do not duplicate event handlers or creation requests');
    await navigate(1);await edit();slowCreate=true;
    await fill('#editAmenities [data-amenity-name]', 'Phòng trà chung');
    await cdp.evaluate("document.querySelector('#editAmenities [data-amenity-add]').click();document.querySelector('[data-save-group=amenities]').click();document.querySelector('[data-cancel-group=amenities]').click()");
    await wait("document.querySelector('[data-edit-group=amenities]')");await delay(900);
    assert.deepEqual((await api('/hotels/1')).hotel.amenities.map(item=>item.amenity),['Sân đọc sách']);
    assert.ok((await api('/amenities')).amenities.some(item=>item.key==='Phòng trà chung'));
    checks.push('Cancel during a pending creation keeps the newly created catalog option without assigning it to the canceled hotel form');
    assert.deepEqual(errors, []);
    fs.writeFileSync(path.join(stage.runtime, 'amenities-browser-results.json'), JSON.stringify({checks, errors}, null, 2));
    console.log(JSON.stringify({checks, errors}, null, 2));
    cdp.socket.close();
})().catch(error => { console.error(error); cdp?.socket.close(); process.exitCode = 1; });
