const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const root=path.resolve(__dirname,'../..');
const source=fs.readFileSync(path.join(root,'resources/views/partials/hoteldetail/sidebar-script.blade.php'),'utf8')
    .split('// Angel Hotel Detail date range picker.')[0].replace('<script>','')
    .replace(/@json\([^\r\n]+\)/,'true');
const flush=()=>new Promise(resolve=>setImmediate(resolve));
const response=(data,status=200)=>({ok:status<400,status,json:async()=>data});
const deferred=()=>{let resolve;const promise=new Promise(done=>{resolve=done;});return {promise,resolve};};
function quote(quantity=1,unit='100000.25'){
    const unitValue=Number(unit);
    return {currency:'VND',quantity,nights:2,unit_total:(unitValue*2).toFixed(2),total_price:(unitValue*2*quantity).toFixed(2),average_nightly_price:unit,
        quote_hash:'quote-'+quantity+'-'+unit,nightly_rates:[{date:'2026-12-10',unit_price:unit,source:'day'},{date:'2026-12-11',unit_price:unit,source:'season'}]};
}
function harness(){
    const nodes=new Map(),quotes=[],calls=[],redirects=[],events=new Map();
    const node=id=>{
        if(!nodes.has(id))nodes.set(id,{value:'',hidden:false,disabled:false,textContent:'',dataset:{},children:[],handlers:new Map(),
            checkValidity(){return true;},reportValidity(){return true;},addEventListener(event,handler){this.handlers.set(event,handler);},
            replaceChildren(...children){this.children=children;},appendChild(child){this.children.push(child);},setAttribute(){},removeAttribute(){},focus(){},scrollIntoView(){}});
        return nodes.get(id);
    };
    node('check-in').value='2026-12-10';node('check-out').value='2026-12-12';node('room-select').value='1';node('room-quantity').value='1';node('guests').value='2';
    const context={document:{getElementById:node,createElement:()=>node(Symbol())},
        HolidayVietDetailRooms:{types:new Map([[1,{id:1,name:'Deluxe',capacity:4,price:1}]]),stock:{status:'ready',counts:new Map([[1,5]])},sync(){},paintAvailability(){}},
        HOTEL_ID:1,selectedRoomTypes:{1:{quantity:1}},baseTotal:0,currentTotal:0,AbortController,URLSearchParams,Intl,Date,Map,Number,Array,Promise,
        location:{search:'',pathname:'/hotels/1',assign:url=>redirects.push(url)},localStorage:{getItem:()=> 'test'},crypto:{randomUUID:()=> 'synthetic-request-id'},
        setTimeout(){},clearTimeout(){},addEventListener:(name,handler)=>events.set(name,handler),getApiErrorMessage:data=>Object.values(data.errors||{}).flat().join(' ')||data.message,
        _layoutApi:async(url,options)=>{calls.push({url,options});if(url.includes('/quote?')){const pending=deferred();quotes.push({url,...pending});return pending.promise;}return response([{hotel_id:1,room_type_id:1,available_rooms:5}]);},
        fetch:async(url,options)=>{calls.push({url,options});return response({payment_url:'/payments/bookings/42'});}};
    context.window=context;vm.createContext(context);vm.runInContext(source,context);
    return {context,node,quotes,calls,redirects,events};
}

test('Room selection waits for a server nightly quote and displays its total with saved quote identity',async()=>{
    const h=harness();await flush();
    assert.equal(h.node('submit-booking-btn').disabled,true);
    assert.equal(h.node('total-price').textContent,'—');
    h.quotes[0].resolve(response(quote()));await flush();
    assert.equal(h.node('submit-booking-btn').disabled,false);
    assert.equal(h.context.currentTotal,200000.5);
    assert.equal(h.node('booking-nightly-rates').children.length,2);
    const firstNight=h.node('booking-nightly-rates').children[0].innerHTML;
    assert.ok(firstNight.includes('10/12/2026'));
    assert.equal((firstNight.match(/<td>/g)||[]).length,3);
    assert.ok(firstNight.includes('Giá ngày cụ thể'));
    assert.ok(h.node('booking-nightly-rates').children[1].innerHTML.includes('Giá mùa'));
    assert.ok(!h.node('booking-nightly-rates').children.some(row=>row.innerHTML.includes('12/12/2026')));
    await h.node('submit-booking-btn').handlers.get('click')();
    const booking=h.calls.find(call=>call.url==='/api/bookings');
    const payload=JSON.parse(booking.options.body);
    assert.equal(payload.quote_hash,quote().quote_hash);
    assert.equal(payload.quantity,1);
    assert.equal('total_price' in payload,false);
    assert.deepEqual(h.redirects,['/payments/bookings/42']);
});

test('A response for the old room quantity cannot replace the current quote',async()=>{
    const h=harness();await flush();
    h.node('room-quantity').value='2';
    const refresh=h.context.refreshHotelAvailability();await flush();
    h.quotes[1].resolve(response(quote(2)));await refresh;await flush();
    assert.equal(h.context.currentTotal,400001);
    h.quotes[0].resolve(response(quote(1,'990000.00')));await flush();
    assert.equal(h.context.currentTotal,400001);
    assert.equal(h.context.HolidayVietDetailPricing.getQuote(1).quantity,2);
});

test('Returning to customer tab refreshes a quote even when dates and quantity are unchanged',async()=>{
    const h=harness();await flush();h.quotes[0].resolve(response(quote()));await flush();
    h.events.get('focus')();await flush();
    assert.equal(h.quotes.length,2);
    assert.equal(h.node('submit-booking-btn').disabled,true);
    assert.equal(h.node('price-subtotal').textContent,'—');
    assert.equal(h.node('total-price').textContent,'—');
    h.quotes[1].resolve(response(quote(1,'150000.00')));await flush();
    assert.equal(h.context.currentTotal,300000);
    assert.equal(h.node('submit-booking-btn').disabled,true);
    assert.equal(h.node('booking-price-confirmation').hidden,false);
    h.node('booking-price-accept').checked=true;
    h.node('booking-price-accept').handlers.get('change')();
    assert.equal(h.node('submit-booking-btn').disabled,false);
});

test('Surcharge detail shows base6500000 plus100000 and multiplies by room count',async()=>{
    const h=harness();await flush();
    const q=quote(1,'6600000.00');
    q.nightly_rates.forEach(n=>{n.base_price='6500000.00';n.surcharge='100000.00';n.amount_mode='surcharge';});
    h.quotes[0].resolve(response(q));await flush();
    const row=h.node('booking-nightly-rates').children[0].innerHTML;
    assert.ok(row.includes('Giá cơ bản 6.500.000 ₫'));
    assert.ok(row.includes('Phụ thu +100.000 ₫'));
    assert.ok(row.includes('6.600.000 ₫'));
    assert.equal(h.context.currentTotal,13200000);
});

test('An Admin price change refreshes only the matching hotel in another tab',async()=>{
    const h=harness();await flush();h.quotes[0].resolve(response(quote()));await flush();
    h.events.get('storage')({key:'angel.room-pricing.changed',newValue:JSON.stringify({hotel_id:99})});await flush();
    assert.equal(h.quotes.length,1);
    h.events.get('storage')({key:'angel.room-pricing.changed',newValue:JSON.stringify({hotel_id:1})});await flush();
    assert.equal(h.quotes.length,2);
    h.quotes[1].resolve(response(quote(1,'175000.00')));await flush();
    assert.equal(h.context.currentTotal,350000);
});

test('Quote errors keep booking disabled and never fall back to base price; retry recovers',async()=>{
    const h=harness();await flush();
    h.quotes[0].resolve(response({message:'Bảng giá đang được cập nhật.'},503));await flush();
    assert.equal(h.node('submit-booking-btn').disabled,true);
    assert.equal(h.context.currentTotal,0);
    assert.ok(h.node('booking-error').textContent.includes('Bảng giá đang được cập nhật.'));
    assert.equal(h.node('booking-price-retry').hidden,false);
    const retry=h.node('booking-price-retry').handlers.get('click')();await flush();
    h.quotes[1].resolve(response(quote()));await retry;await flush();
    assert.equal(h.node('submit-booking-btn').disabled,false);
    assert.equal(h.node('booking-price-retry').hidden,true);
});

test('A rejected stale quote refreshes the price and requires the customer to submit again',async()=>{
    const h=harness();await flush();h.quotes[0].resolve(response(quote()));await flush();
    let creates=0;
    h.context.fetch=async()=>{creates++;return response({message:'Giá đã thay đổi. Vui lòng kiểm tra lại.'},409);};
    const submit=h.node('submit-booking-btn').handlers.get('click')();await flush();
    h.quotes[1].resolve(response(quote(1,'150000.00')));await submit;await flush();
    assert.equal(creates,1);assert.equal(h.context.currentTotal,300000);
    assert.equal(h.node('booking-submit-error').hidden,false);
    assert.ok(h.node('booking-submit-error').textContent.includes('Giá đã thay đổi'));
    assert.deepEqual(h.redirects,[]);
});

const adminSource=fs.readFileSync(path.join(root,'resources/views/admin/partials/room-rate-editor-script.blade.php'),'utf8').replace(/<\/?script>/g,'');
function adminHarness(api){
    const nodes=new Map(),calls=[];
    const get=id=>{
        if(!nodes.has(id))nodes.set(id,{value:'',hidden:false,disabled:false,open:false,textContent:'',innerHTML:'',dataset:{},handlers:new Map(),
            querySelectorAll:()=>[],addEventListener(event,handler){this.handlers.set(event,handler);},replaceChildren(){this.innerHTML='';},reset(){},reportValidity:()=>true,focus(){}});
        return nodes.get(id);
    };
    get('rtRateKind').value='day';
    const host={saving:false,isSaving(){return this.saving;},setSaving(value){this.saving=value;},update(){}};
    const context={document:{getElementById:get},AbortController,Intl,Number,Array,Object,confirm:()=>true,
        AdminRoomRateHost:host,adminApi:async(url,options={})=>{calls.push({url,options});return api(url,options);}};
    context.window=context;vm.createContext(context);vm.runInContext(adminSource,context);
    return {get,calls,host,editor:context.AdminRoomRates};
}

test('Admin can save a specific-day price and same-priority overlap errors retain the form',async()=>{
    let fail=false;
    const h=adminHarness(async(url,options)=>options.method?response(fail?{errors:{start_date:['Khoảng giá đã chồng chéo.']}}:{message:'ok'},fail?422:201):response({rates:[]}));
    h.get('rtRateEditor').open=true;h.editor.select(1,2,'scoped');await flush();
    h.get('rtRateStart').value='2026-12-10';h.get('rtRatePrice').value='125000.25';
    h.get('rtRateForm').handlers.get('submit')({preventDefault(){}});await flush();
    const save=h.calls.find(call=>call.options.method==='POST');
    assert.equal(save.url,'/hotels/1/room-types/2/rates');
    assert.equal(JSON.parse(save.options.body).end_date,'2026-12-10');
    assert.equal(JSON.parse(save.options.body).price,'125000.25');
    fail=true;h.get('rtRateStart').value='2026-12-11';h.get('rtRatePrice').value='200000.00';
    h.get('rtRateForm').handlers.get('submit')({preventDefault(){}});await flush();
    assert.equal(h.get('rtRateFeedback').dataset.error,'true');
    assert.ok(h.get('rtRateFeedback').textContent.includes('chồng chéo'));
    assert.equal(h.get('rtRatePrice').value,'200000.00');
    assert.equal(h.host.saving,false);
});

test('Switching admin room type ignores a late response for the previous type',async()=>{
    const pending=[];
    const h=adminHarness(async()=>{const wait=deferred();pending.push(wait);return wait.promise;});
    h.get('rtRateEditor').open=true;h.editor.select(1,2,'scoped');await flush();h.editor.select(1,3,'scoped');await flush();
    pending[1].resolve(response({rates:[{id:30,kind:'day',start_date:'2026-12-11',end_date:'2026-12-11',price:'333000.00',label:'Current room'}]}));await flush();
    pending[0].resolve(response({rates:[{id:20,kind:'day',start_date:'2026-12-10',end_date:'2026-12-10',price:'222000.00',label:'Old room'}]}));await flush();
    assert.ok(h.get('rtRateRows').innerHTML.includes('Current room'));
    assert.ok(!h.get('rtRateRows').innerHTML.includes('Old room'));
});

const paymentSource=fs.readFileSync(path.join(root,'resources/views/demo/payment-script.blade.php'),'utf8').replace(/<\/?script>/g,'')
    .replace('@json($bookingId)','42').replace('@json($adminMode)','true').replace('@json($localPayment ?? false)','true');
function paymentHarness(initial){
    const nodes=new Map(),actions=['success','failed','cancelled','expired'].map(action=>({dataset:{demoAction:action},handlers:new Map(),disabled:false,addEventListener(event,handler){this.handlers.set(event,handler);}}));
    const get=id=>{
        if(id==='demo-qr-cells')return null;
        if(!nodes.has(id))nodes.set(id,{textContent:'',innerHTML:'',dataset:{},hidden:false,handlers:new Map(),addEventListener(event,handler){this.handlers.set(event,handler);},setAttribute(){}});
        return nodes.get(id);
    };
    let state=initial;
    const context={document:{getElementById:get,querySelectorAll:()=>actions},Intl,Date,Math,Number,String,Object,Array,JSON,
        localStorage:{getItem:()=> 'test'},setInterval(){},setTimeout(){},location:{assign(){}},
        fetch:async(url,options)=>{
            if(options?.method==='POST'){state={...state,payment:{...state.payment,payment_status:'success',display_status:'reconciliation'},transactions:[{outcome:'late_reconciliation',merchant_ref:'receipt',amount:state.booking.total_price}]};return response({outcome:'late_reconciliation'});}
            return response(state);
        }};
    context.window=context;context.addEventListener=()=>{};vm.createContext(context);vm.runInContext(paymentSource,context);
    return {get,actions};
}
test('Admin can record late funds for a released order; UI keeps cancellation and shows reconciliation',async()=>{
    const initial={booking:{status:'cancelled',check_in:'2026-12-10',check_out:'2026-12-12',num_guests:2,total_price:'200000.50',
        nightly_rates:quote().nightly_rates,summary:{hotel:'Hotel',lines:[{name:'Saved room',quantity:1,nights:2,unit_price:'999999.00'}]}},
        payment:{id:7,payment_status:'expired',amount:'200000.50',transaction_id:'DEMO_LOCAL_42'},transactions:[],expires_at_utc:'2026-01-01T00:00:00Z'};
    const h=paymentHarness(initial);await flush();
    assert.equal(h.actions[0].disabled,false);
    assert.equal(h.actions[1].disabled,true);
    assert.ok(h.get('demo-nightly-rows').innerHTML.includes('2026-12-10'));
    assert.ok(!h.get('demo-summary').innerHTML.includes('999.999'));
    await h.actions[0].handlers.get('click')();await flush();
    assert.equal(h.actions[0].disabled,true);
    assert.ok(h.get('demo-summary').innerHTML.includes('Đã hủy'));
    assert.ok(h.get('demo-summary').innerHTML.includes('data-tone="warning">Đã nhận tiền · Cần đối soát'));
    assert.ok(h.get('demo-feedback').textContent.includes('đối soát'));
    assert.ok(!h.get('demo-feedback').textContent.includes('đã được xác nhận'));
});
