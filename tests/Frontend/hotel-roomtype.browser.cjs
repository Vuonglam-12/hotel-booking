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
    const cdp=new DevTools(targets.find(item=>item.type==='page').webSocketDebuggerUrl);activeCdp=cdp;const checks=[],errors=[],writes=[];
    cdp.on('Runtime.exceptionThrown',event=>errors.push(event.exceptionDetails.exception?.description||event.exceptionDetails.text));
    cdp.on('Network.requestWillBeSent',event=>{if(event.request.url.startsWith(origin+'/api/')&&!['GET','HEAD'].includes(event.request.method))writes.push({path:new URL(event.request.url).pathname,method:event.request.method});});
    cdp.on('Fetch.requestPaused',event=>{const url=new URL(event.request.url);cdp.send(url.origin===origin||['blob:','data:'].includes(url.protocol)?'Fetch.continueRequest':'Fetch.failRequest',url.origin===origin||['blob:','data:'].includes(url.protocol)?{requestId:event.requestId}:{requestId:event.requestId,errorReason:'BlockedByClient'}).catch(error=>errors.push(error.message));});
    await cdp.send('Page.enable');await cdp.send('Runtime.enable');await cdp.send('Network.enable');await cdp.send('Fetch.enable',{patterns:[{urlPattern:'*'}]});
    await cdp.send('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false});
    await cdp.send('Page.addScriptToEvaluateOnNewDocument',{source:'if(location.origin==='+JSON.stringify(origin)+'){localStorage.setItem("admin_token",'+JSON.stringify(stage.admin_token)+');}'});
    const wait=async expression=>{const end=Date.now()+15000;while(Date.now()<end){if(await cdp.evaluate(expression))return;await delay(70);}throw new Error('Timed out: '+expression);};
    const click=selector=>cdp.evaluate('document.querySelector('+JSON.stringify(selector)+').click()');
    const fill=values=>cdp.evaluate('Object.entries('+JSON.stringify(values)+').forEach(([id,value])=>document.getElementById(id).value=value)');
    const api=async endpoint=>{const response=await fetch(origin+'/api/admin'+endpoint,{headers:{Authorization:'Bearer '+stage.admin_token,Accept:'application/json'}});return {status:response.status,data:await response.json()};};
    const navigate=async hotel=>{await cdp.send('Page.navigate',{url:origin+'/admin/hotels/'+hotel});await wait("document.getElementById('hotelDetailHeader')&&!document.getElementById('hotelDetailHeader').hidden");await click('#tabRoomTypes');await wait("!document.getElementById('addRoomType').hidden");};
    await navigate(1);await click('[data-edit-room="1"]');await wait("!document.getElementById('roomTypeEditor').hidden&&!document.getElementById('saveRoomType').disabled");
    await fill({roomName:'Canceled draft'});await click('#cancelRoomType');assert.equal(writes.length,0);await click('[data-edit-room="1"]');await wait("!document.getElementById('roomTypeEditor').hidden&&!document.getElementById('saveRoomType').disabled");
    assert.equal(await cdp.evaluate("document.getElementById('roomName').value"),'Deluxe');checks.push('Cancel preserves saved metadata and makes no write');
    await fill({roomCapacity:'0'});await click('#saveRoomType');await wait("document.getElementById('roomCapacity').getAttribute('aria-invalid')==='true'");checks.push('Backend validation appears next to the field');
    await fill({roomName:'Deluxe A',roomCapacity:'3',roomPrice:'900000.13',roomAmenities:'Balcony\nDesk'});
    const galleryBefore=(await api('/hotels/1/room-types/1/images')).data.images;
    await click('#saveRoomType');
    await wait("document.getElementById('roomTypeEditor').hidden&&document.getElementById('roomTypesBody').textContent.includes('Deluxe A')");
    assert.deepEqual((await api('/hotels/1/room-types/1/images')).data.images,galleryBefore);checks.push('Edit saves metadata without changing the existing photo gallery');
    const other=await api('/hotels/2/room-types/1');assert.equal(other.data.room_type.name,'Deluxe');assert.equal(other.data.room_type.base_price,'800000.25');assert.equal((await api('/hotels/2/room-types/1/images')).data.images.length,0);assert.equal((await api('/hotels/3/room-types/1')).status,404);checks.push('Other hotel and invalid relationships stay isolated');
    await navigate(3);await click('#addRoomType');await wait("!document.getElementById('saveRoomType').disabled");await fill({roomName:'Created owned type',roomPrice:'700000.25',roomCapacity:'2',roomTotal:'2',roomFloor:'3'});await click('#saveRoomType');await wait("document.getElementById('roomTypeEditor').hidden&&document.getElementById('roomTypesBody').textContent.includes('Created owned type')");
    await click('[data-edit-room]');await wait("!document.getElementById('roomTypeEditor').hidden&&!document.getElementById('saveRoomType').disabled");assert.equal(await cdp.evaluate("document.getElementById('roomName').value"),'Created owned type');checks.push('Create remains usable and the new type is immediately editable');
    const screenshot=await cdp.send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync(path.join(runtime,'created-type-editor.png'),Buffer.from(screenshot.data,'base64'));
    await cdp.send('Page.navigate',{url:origin+'/hotels/1'});await wait("document.readyState==='complete'&&window.HolidayVietDetailRooms&&document.querySelector('.hv-room-card h3')?.textContent.includes('Deluxe A')");await click('[data-open-room="1"]');await wait("document.getElementById('room-modal-amenities').textContent.includes('Balcony')");assert.ok(await cdp.evaluate("document.getElementById('submit-booking-btn').disabled"));checks.push('Customer detail reads owned metadata; checkout gate remains closed');
    fs.writeFileSync(path.join(runtime,'browser-proof.json'),JSON.stringify({passed:checks.length,checks,writes,exceptions:errors,synthetic_only:true},null,2));assert.deepEqual(errors,[]);
    console.log(JSON.stringify({passed:checks.length,checks,exceptions:errors}));await cdp.send('Browser.close');cdp.socket.close();
})().catch(async error=>{console.error(error.message);try{await activeCdp?.send('Browser.close');}catch{}activeCdp?.socket.close();process.exitCode=1;});
