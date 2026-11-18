const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const stage=JSON.parse(fs.readFileSync(process.argv[2],'utf8').replace(/^\uFEFF/,''));
assert.equal(stage.synthetic_only&&stage.scoped_catalog,true);
const origin=process.argv[3],debugPort=process.argv[4],runtime=stage.runtime;
assert.ok(/^http:\/\/127\.0\.0\.1:\d+$/.test(origin));
class DevTools{
    constructor(url){this.socket=new WebSocket(url);this.next=0;this.pending=new Map();this.events=new Map();this.ready=new Promise((resolve,reject)=>{this.socket.addEventListener('open',resolve);this.socket.addEventListener('error',reject);});this.socket.addEventListener('message',event=>{const data=JSON.parse(event.data);if(data.id){const pending=this.pending.get(data.id);this.pending.delete(data.id);data.error?pending?.reject(new Error(data.error.message)):pending?.resolve(data.result);}else this.events.get(data.method)?.forEach(callback=>callback(data.params));});}
    on(name,callback){if(!this.events.has(name))this.events.set(name,[]);this.events.get(name).push(callback);}
    async send(method,params={}){await this.ready;const id=++this.next;const result=new Promise((resolve,reject)=>this.pending.set(id,{resolve,reject}));this.socket.send(JSON.stringify({id,method,params}));return result;}
    async evaluate(expression){const result=await this.send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true,userGesture:true});if(result.exceptionDetails)throw new Error(JSON.stringify(result.exceptionDetails));return result.result.value;}
}
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
let activeCdp;
(async()=>{
    const targets=await(await fetch('http://127.0.0.1:'+debugPort+'/json/list')).json();
    const cdp=new DevTools(targets.find(item=>item.type==='page').webSocketDebuggerUrl);activeCdp=cdp;
    const checks=[],errors=[];
    cdp.on('Runtime.exceptionThrown',event=>errors.push(event.exceptionDetails.exception?.description||event.exceptionDetails.text));
    cdp.on('Fetch.requestPaused',event=>{
        const url=new URL(event.request.url),allowed=url.origin===origin||['blob:','data:'].includes(url.protocol);
        cdp.send(allowed?'Fetch.continueRequest':'Fetch.failRequest',allowed?{requestId:event.requestId}:{requestId:event.requestId,errorReason:'BlockedByClient'}).catch(error=>errors.push(error.message));
    });
    await cdp.send('Page.enable');await cdp.send('Runtime.enable');await cdp.send('DOM.enable');await cdp.send('Fetch.enable',{patterns:[{urlPattern:'*'}]});
    await cdp.send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await cdp.send('Page.addScriptToEvaluateOnNewDocument',{source:`if(location.origin===${JSON.stringify(origin)})localStorage.setItem('admin_token',${JSON.stringify(stage.admin_token)});`});
    const wait=async expression=>{const end=Date.now()+18000;while(Date.now()<end){if(await cdp.evaluate(expression))return;await delay(80);}throw new Error('Timed out: '+expression);};
    const click=selector=>cdp.evaluate('document.querySelector('+JSON.stringify(selector)+').click()');
    const fill=(selector,value)=>cdp.evaluate(`document.querySelector(${JSON.stringify(selector)}).value=${JSON.stringify(value)}`);
    const api=async(endpoint,options={})=>{const r=await fetch(origin+'/api/admin'+endpoint,{...options,headers:{Authorization:'Bearer '+stage.admin_token,Accept:'application/json',...options.headers}});return {status:r.status,data:await r.json()};};
    const navigate=async hotel=>{await cdp.send('Page.navigate',{url:origin+'/admin/hotels/'+hotel});await wait("document.getElementById('hotelDetailHeader')&&!document.getElementById('hotelDetailHeader').hidden");};
    const rooms=async()=>{await click('#tabRoomTypes');await wait("!document.getElementById('addRoomType').hidden");};
    const edit=async()=>{await click('[data-edit-room="1"]');await wait("!document.getElementById('roomTypeEditor').hidden&&!document.getElementById('saveRoomType').disabled");};
    const screenshot=async name=>{const shot=await cdp.send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync(path.join(runtime,name+'.png'),Buffer.from(shot.data,'base64'));};
    const chooseFiles=async selector=>{const doc=await cdp.send('DOM.getDocument');const node=await cdp.send('DOM.querySelector',{nodeId:doc.root.nodeId,selector});await cdp.send('DOM.setFileInputFiles',{nodeId:node.nodeId,files:[path.join(runtime,'sample.jpg')]});};

    // Keep a missing demo image as the stored primary, append a valid upload without changing it.
    const upload=new FormData();upload.append('_method','PUT');upload.append('images[]',new Blob([fs.readFileSync(path.join(runtime,'sample.jpg'))],{type:'image/jpeg'}),'sample.jpg');
    assert.equal((await api('/hotels/1/room-types/1',{method:'POST',body:upload})).status,200);
    const initial=(await api('/hotels/1/room-types/1')).data.room_type;
    assert.ok(initial.cover_image_url.includes('/storage/'));
    await navigate(1);assert.equal(await cdp.evaluate("!!document.querySelector('input[type=time]')||document.body.textContent.includes('Chính sách lưu trú')"),false);
    await rooms();await wait("document.querySelector('.hd-room-thumb').complete&&document.querySelector('.hd-room-thumb').naturalWidth>0");
    assert.equal(await cdp.evaluate("document.querySelector('.hd-room-thumb').src"),initial.cover_image_url);checks.push('Missing legacy primary falls back to this type’s uploaded photo in the Admin table');
    await edit();assert.equal(await cdp.evaluate("document.querySelector('#hotel-room-images [data-image-cover] img').src"),initial.cover_image_url);
    assert.equal(await cdp.evaluate("document.querySelectorAll('#hotel-room-images .is-saved').length"),2);
    await chooseFiles('#hotel-room-images [data-image-cover-file]');
    await wait("document.querySelector('#hotel-room-images .is-new.is-primary')");
    const old=await cdp.evaluate("document.querySelector('#hotel-room-images .is-saved [data-image-action=delete]').dataset.imageKey");
    await click(`#hotel-room-images [data-image-action=delete][data-image-key="${old}"]`);
    await click('#saveRoomType');await wait("document.getElementById('roomTypeEditor').hidden&&!document.getElementById('roomTypesTableWrap').hidden");
    let saved=(await api('/hotels/1/room-types/1')).data.room_type;
    assert.notEqual(saved.cover_image_url,initial.cover_image_url);
    const gallery=(await api('/hotels/1/room-types/1/images')).data.images;assert.equal(gallery.length,2);assert.equal(gallery.filter(i=>Number(i.is_primary)===1).length,1);
    await navigate(1);await rooms();assert.equal(await cdp.evaluate("document.querySelector('.hd-room-thumb').src"),saved.cover_image_url);
    await edit();assert.equal(await cdp.evaluate("document.querySelector('#hotel-room-images [data-image-cover] img').src"),saved.cover_image_url);
    const retained=gallery.find(i=>!Number(i.is_primary));
    await click(`#hotel-room-images [data-image-primary][value="s-${retained.id}"]`);await click('#cancelRoomType');
    assert.equal((await api('/hotels/1/room-types/1')).data.room_type.cover_image_url,saved.cover_image_url);
    await edit();
    const cover=gallery.find(i=>Number(i.is_primary));
    await click(`#hotel-room-images [data-image-action=delete][data-image-key="s-${cover.id}"]`);
    assert.equal(await cdp.evaluate("document.querySelector('#hotel-room-images [data-image-cover] img').src"),retained.display_url);
    await click('#saveRoomType');await wait("document.getElementById('roomTypeEditor').hidden&&!document.getElementById('roomTypesTableWrap').hidden");
    saved=(await api('/hotels/1/room-types/1')).data.room_type;assert.equal(saved.cover_image_url,retained.display_url);
    await screenshot('management-desktop-room-types');
    checks.push('Upload, remove saved image, choose cover, save, reload and Cancel work in the shared image editor');

    await click('#tabOverview');await click('[data-edit-group=amenities]');
    await wait("!document.querySelector('#editAmenities fieldset').disabled");
    assert.ok(await cdp.evaluate("getComputedStyle(document.querySelector('#editAmenities [data-amenity-options]')).gridTemplateColumns.split(' ').length>=2"));
    await fill('#editAmenities [data-amenity-name]','  Vườn   trên mái  ');await click('#editAmenities [data-amenity-add]');
    await wait("document.querySelector('#editAmenities [data-amenity-status]').textContent.includes('Đã lưu tiện nghi')");
    assert.equal(await cdp.evaluate("document.querySelector('#editAmenities [data-amenity-selected]').textContent.includes('Vườn trên mái')"),true);
    await fill('#editAmenities [data-amenity-name]','vườn trên mái');await click('#editAmenities [data-amenity-add]');
    assert.equal(await cdp.evaluate("document.querySelector('#editAmenities [data-amenity-status]').dataset.error"),'true');
    await fill('#editAmenities [data-amenity-name]','   ');await click('#editAmenities [data-amenity-add]');
    assert.equal(await cdp.evaluate("document.querySelector('#editAmenities [data-amenity-status]').dataset.error"),'true');
    await fill('#editAmenities [data-amenity-search]','vườn');await cdp.evaluate("document.querySelector('#editAmenities [data-amenity-search]').dispatchEvent(new Event('input',{bubbles:true}))");
    assert.equal(await cdp.evaluate("document.querySelectorAll('#editAmenities [data-amenity-check]').length"),1);
    await cdp.send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
    assert.equal(await cdp.evaluate("getComputedStyle(document.querySelector('#editAmenities [data-amenity-options]')).gridTemplateColumns.split(' ').length"),1);
    assert.equal(await cdp.evaluate("document.querySelector('#editAmenities input[type=checkbox]').getBoundingClientRect().width"),14);
    await screenshot('management-mobile-amenities');
    await click('[data-save-group=amenities]');await wait("document.querySelector('[data-edit-group=amenities]')");
    const amenityRows=(await api('/hotels/1')).data.hotel.amenities;assert.deepEqual(amenityRows.map(a=>a.amenity),['wifi','Vườn trên mái']);
    await navigate(1);await click('[data-edit-group=amenities]');
    await wait("!document.querySelector('#editAmenities fieldset').disabled");
    await click('#editAmenities [data-amenity-selected] [data-amenity-remove="Vườn trên mái"]');await click('[data-cancel-group=amenities]');
    assert.equal((await api('/hotels/1')).data.hotel.amenities.length,2);
    await api('/hotels/2',{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify({amenities:['Vườn trên mái']})});
    await click('[data-edit-group=amenities]');await wait("!document.querySelector('#editAmenities fieldset').disabled");
    await click('#editAmenities [data-amenity-selected] [data-amenity-remove="Vườn trên mái"]');
    await click('[data-save-group=amenities]');await wait("document.querySelector('[data-edit-group=amenities]')");
    assert.deepEqual((await api('/hotels/1')).data.hotel.amenities.map(a=>a.amenity),['wifi']);
    assert.deepEqual((await api('/hotels/2')).data.hotel.amenities.map(a=>a.amenity),['Vườn trên mái']);
    await click('[data-edit-group=amenities]');await wait("!document.querySelector('#editAmenities fieldset').disabled");
    await click('#editAmenities [data-amenity-check][value="Vườn trên mái"]');
    await click('[data-save-group=amenities]');await wait("document.querySelector('[data-edit-group=amenities]')");
    checks.push('Custom amenities normalize names, reject blank/duplicate names, search, persist and cancel removal; mobile uses one column and 14px checkboxes');

    await cdp.send('Page.navigate',{url:origin+'/hotels/1'});await wait("document.readyState==='complete'&&document.querySelector('.hv-room-cover img')?.naturalWidth>0");
    assert.equal(await cdp.evaluate("document.querySelector('.hv-room-cover img').src"),saved.cover_image_url);
    assert.equal(await cdp.evaluate("document.querySelector('#amenity-modal').textContent.includes('Vườn trên mái')"),true);
    assert.equal(await cdp.evaluate("document.body.textContent.includes('Giờ nhận phòng')||document.body.textContent.includes('Giờ trả phòng')||document.body.textContent.includes('Chính sách lưu trú')"),false);
    await cdp.evaluate("document.getElementById('amenity-modal').classList.remove('hidden')");await screenshot('management-mobile-customer-amenities');
    await cdp.evaluate("document.getElementById('amenity-modal').classList.add('hidden')");
    await click('#hv-stay-checkin-trigger');await wait("!document.getElementById('hv-stay-date-dialog').hidden");
    await cdp.evaluate("document.querySelector('.hv-stay-day:not([disabled])').click()");
    await cdp.evaluate("document.querySelectorAll('.hv-stay-day:not([disabled])')[1].click()");
    await click('#hv-stay-done');await wait("document.getElementById('check-in').value&&document.getElementById('check-out').value");
    await click('[data-open-room="1"]');await wait("document.getElementById('room-detail-modal').open");await click('#room-modal-select');
    await wait("document.getElementById('booking-nights').textContent==='2 đêm'&&HolidayVietDetailRooms.stock.status==='ready'");
    assert.equal(await cdp.evaluate("document.getElementById('submit-booking-btn').disabled"),true);
    checks.push('Customer date picker still selects a two-night stay and loads stock without policy times');
    checks.push('Customer cover matches Admin, all selected custom amenities display, and policy-time blocks are absent');

    await cdp.send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await cdp.send('Page.navigate',{url:origin+'/admin/hotels'});await wait("typeof openRoomTypeModal==='function'&&document.querySelector('#hotelGrid button,.hotel-name')||typeof openRoomTypeModal==='function'");
    await cdp.evaluate('openRoomTypeModal(1,1)');await wait("document.getElementById('roomTypeModal').classList.contains('open')&&!document.getElementById('rtSaveBtn').disabled");
    assert.equal(await cdp.evaluate("document.querySelector('#modal-room-images [data-image-cover] img').src"),saved.cover_image_url);
    await click('#rtCloseBtn');
    assert.equal(await cdp.evaluate("!!document.querySelector('input[type=time]')"),false);
    checks.push('Hotel-list room modal uses the same saved gallery and cover; hotel form has no policy-time inputs');

    await navigate(1);await rooms();
    let dialogText='';cdp.on('Page.javascriptDialogOpening',event=>{dialogText=event.message;cdp.send('Page.handleJavaScriptDialog',{accept:true});});
    await click('[data-delete-room="1"]');await wait("!document.getElementById('roomTypesEmpty').hidden");
    assert.ok(dialogText.includes('Deluxe'));assert.equal((await api('/hotels/1/room-types/1')).status,404);
    assert.equal((await api('/hotels/2/room-types/1')).status,200);
    await cdp.send('Page.navigate',{url:origin+'/hotels/1'});await wait("document.readyState==='complete'&&document.querySelector('#hotel-room-types')");
    assert.equal(await cdp.evaluate("document.querySelectorAll('[data-room-type-id]').length"),0);
    checks.push('Delete confirmation names the type; successful deletion refreshes Admin and customer lists and preserves the other hotel');
    assert.deepEqual(errors,[]);
    fs.writeFileSync(path.join(runtime,'management-browser-proof.json'),JSON.stringify({passed:checks.length,checks,exceptions:errors,synthetic_only:true},null,2));
    console.log(JSON.stringify({passed:checks.length,checks,exceptions:errors}));
    await cdp.send('Browser.close');cdp.socket.close();
})().catch(async error=>{console.error(error.stack);try{await activeCdp?.send('Browser.close');}catch{}activeCdp?.socket.close();process.exitCode=1;});
