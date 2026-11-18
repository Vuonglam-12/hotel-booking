const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '../..');
const source = fs.readFileSync(path.join(root, 'resources/views/prepared-v2/sidebar-script.blade.php'), 'utf8');
const script = source.replace(/^<script>\s*/, '').split('// AUREL Hotel Detail date range picker.')[0];

function harness(payload, quantity = '2') {
    const nodes = new Map();
    function node(id) {
        if (!nodes.has(id)) nodes.set(id, { value: '', hidden: false, disabled: false, min: '', textContent: '',
            checkValidity: () => true, addEventListener: () => {} });
        return nodes.get(id);
    }
    node('check-in').value = '2026-10-15'; node('check-out').value = '2026-10-17';
    node('guests').value = '4'; node('room-quantity').value = quantity; node('room-select').value = '713';
    const stock = { counts: new Map(), status: 'idle', error: '' };
    const type = { id: 713, name: 'Standard', capacity: 2, price: '500000.00' };
    const calls = [];
    const context = vm.createContext({ document: { getElementById: node }, window: {},
        HolidayVietDetailRooms: { stock, types: new Map([[713,type]]), sync: () => {}, availabilityLabel: () => stock.status },
        HOTEL_ID: 1, selectedRoomTypes: {713:{quantity:1}}, baseTotal:0, currentTotal:0,
        URLSearchParams, AbortController, Intl, Date, setTimeout, clearTimeout,
        getApiErrorMessage: data => data.message,
        _layoutApi: async url => { calls.push(url); return { ok:true, json:async()=>payload }; }
    });
    vm.runInContext(script, context);
    return {context,stock,calls,node};
}
test('Prepared sidebar JavaScript parses without changing the date picker', () => {
    new vm.Script(source.replace(/^<script>\s*/, '').replace(/\s*<\/script>\s*$/, ''));
});
test('Availability consumes owned Type IDs, dates and quantity; Checkout stays disabled', async () => {
    const h=harness({hotel_id:1,room_types:[{id:713,hotel_id:1,available_rooms:3}]});
    await h.context.window.refreshHotelAvailability();
    assert.equal(h.stock.status,'ready'); assert.equal(h.stock.counts.get(713),3);
    assert.match(h.calls.at(-1),/^\/hotels\/1\/room-types\?/);
    assert.match(h.calls.at(-1),/quantity=2/); assert.doesNotMatch(h.calls.at(-1),/(?:^|[?&])room_id=/);
    assert.equal(h.context.baseTotal,2000000); assert.equal(h.node('submit-booking-btn').disabled,true);
});
test('Malformed inventory fails closed and clears previous available counts', async () => {
    const h=harness([{id:101,room_type_id:713}]); await h.context.window.refreshHotelAvailability();
    assert.equal(h.stock.status,'error'); assert.equal(h.stock.counts.size,0); assert.equal(h.node('submit-booking-btn').disabled,true);
});
test('Quantity ten is retained without introducing physical room numbers', async () => {
    const h=harness({hotel_id:1,room_types:[{id:713,hotel_id:1,available_rooms:10}]},'10');
    await h.context.window.refreshHotelAvailability(); assert.match(h.calls.at(-1),/quantity=10/);
    assert.equal(h.context.selectedRoomTypes[713].quantity,10); assert.equal(h.context.baseTotal,10000000);
});
test('Prepared Admin submits exact decimal text and Type inventory without legacy floor/count fields', async () => {
    const admin=fs.readFileSync(path.join(root,'resources/views/prepared-v2/admin-hotels.blade.php'),'utf8');
    const start=admin.indexOf('async function saveRoomType()'); const end=admin.indexOf('function validateForm()',start);
    const nodes={rtHotelId:{value:'1'},rtName:{value:'Prepared type'},rtPrice:{value:'777777.13'},rtCapacity:{value:'2'},
        rtBedType:{value:'twin'},rtCount:{value:'10'},rtDesc:{value:'Synthetic'},rtSaveBtn:{disabled:false,textContent:''}};
    let payload;
    const context=vm.createContext({document:{getElementById:id=>nodes[id]},showToast:()=>{},closeRoomTypeModal:()=>{},loadHotels:()=>{},currentPage:1,
        adminApi:async(url,options)=>{payload=JSON.parse(options.body);return{ok:true,json:async()=>({})};}});
    vm.runInContext(admin.slice(start,end),context); await context.saveRoomType();
    assert.equal(payload.base_price,'777777.13'); assert.equal(payload.total_rooms,10); assert.equal(payload.status,'active');
    assert.equal('floor_start' in payload,false); assert.equal('room_count' in payload,false); assert.equal(nodes.rtSaveBtn.disabled,false);
});
if (process.argv[2]) {
    const runtime=path.resolve(process.argv[2]);
    const allowed=path.join(root,'storage/app/private/roomtype-v2-tests')+path.sep;
    assert.ok(runtime.startsWith(allowed),'Only a private test runtime may supply rendered HTML');
    for (const page of ['prepared-hotel-detail.html','prepared-admin-hotels.html']) {
        test('All rendered inline scripts parse: '+page,()=>{
            const html=fs.readFileSync(path.join(runtime,page),'utf8'); let count=0;
            for (const match of html.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script>/gi)) {
                if (/\bsrc=|application\/ld\+json|application\/json/.test(match[1]) || !match[2].trim()) continue;
                new vm.Script(match[2],{filename:page}); count++;
            }
            assert.ok(count>0);
        });
    }
}
