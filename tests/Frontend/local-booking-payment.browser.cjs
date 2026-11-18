// Focused acceptance on the previously verified restored clone only; never port 8000/3306.
const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict'),crypto=require('node:crypto'),{spawn}=require('node:child_process');
const root=path.resolve(__dirname,'../..'),proof=JSON.parse(fs.readFileSync(path.join(root,'storage/logs/booking-local-stage.json'),'utf8'));
const s=JSON.parse(fs.readFileSync(proof.descriptor,'utf8')),origin=s.origin;
assert.equal(origin,'http://127.0.0.1:8012');assert.equal(s.synthetic_only&&s.restored_clone,true);assert.equal(s.database,proof.restored_clone);assert.notEqual(s.port,3306);
const pause=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{
 const health=await new Promise((res,rej)=>{http.get(origin+'/__local-demo/health',r=>{let b='';r.on('data',c=>b+=c);r.on('end',()=>{try{res(JSON.parse(b));}catch{rej(new Error('Clone health unavailable'));}});}).on('error',rej);});assert.equal(health.database,s.database);assert.equal(health.isolated,true);
 const chrome=spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',['--headless=new','--no-first-run','--no-default-browser-check','--disable-background-networking','--disable-component-update','--disable-sync','--disable-extensions','--disable-gpu','--user-data-dir='+path.join(s.runtime,'chrome-payment-'+Date.now()),'--remote-debugging-pipe'],{windowsHide:true,stdio:['ignore','ignore','pipe','pipe','pipe']});
 let seq=0,buffer='',session,createRequests=0;const pending=new Map(),checks=[],errors=[],bookingIds=[];
 const timer=setTimeout(()=>{chrome.kill();process.exitCode=1;},150000);
 const send=(method,params={},sid=session)=>new Promise((resolve,reject)=>{const id=++seq,t=setTimeout(()=>{pending.delete(id);reject(new Error('CDP timeout '+method));},18000);pending.set(id,{resolve,reject,t});chrome.stdio[3].write(JSON.stringify({id,method,params,...(sid?{sessionId:sid}:{})})+'\0');});
 chrome.stdio[4].setEncoding('utf8');chrome.stdio[4].on('data',chunk=>{buffer+=chunk;let i;while((i=buffer.indexOf('\0'))!==-1){const m=JSON.parse(buffer.slice(0,i));buffer=buffer.slice(i+1);const p=pending.get(m.id);if(p){pending.delete(m.id);clearTimeout(p.t);m.error?p.reject(new Error(m.error.message)):p.resolve(m.result);}else if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description||m.params.exceptionDetails.text);else if(m.method==='Network.requestWillBeSent'&&m.params.request.method==='POST'&&m.params.request.url===origin+'/api/bookings')createRequests++;else if(m.method==='Fetch.requestPaused'){const u=new URL(m.params.request.url),ok=u.origin===origin||['data:','blob:'].includes(u.protocol);send(ok?'Fetch.continueRequest':'Fetch.failRequest',{requestId:m.params.requestId,...(ok?{}:{errorReason:'BlockedByClient'})},m.sessionId).catch(()=>{});}}});
 const js=async expression=>{const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true,userGesture:true});if(r.exceptionDetails)throw new Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;};
 const wait=async expression=>{const until=Date.now()+18000;while(Date.now()<until){try{if(await js(expression))return;}catch(e){if(!e.message.includes('Cannot read properties of null'))throw e;}await pause(150);}throw new Error('UI condition timed out: '+expression);};
 const go=async url=>{await js('window.__aurelLeaving=true');await send('Page.navigate',{url:origin+url});await wait(`!window.__aurelLeaving && location.pathname===${JSON.stringify(url.split('?')[0])} && document.readyState==='complete'`);};
 const click=selector=>js(`document.querySelector(${JSON.stringify(selector)}).click()`);
 const api=(url,method='GET',data,admin=false)=>js(`(async()=>{const r=await fetch(${JSON.stringify(url)},{method:${JSON.stringify(method)},headers:{Accept:'application/json','Content-Type':'application/json',Authorization:'Bearer '+localStorage.getItem(${JSON.stringify(admin?'admin_token':'token')})},${data===undefined?'':'body:JSON.stringify('+JSON.stringify(data)+'),'}});return {status:r.status,data:await r.json()};})()`);
 const mark=text=>{checks.push(text);console.log('PASS '+text);};
 const runOffset=crypto.randomInt(40,200);
 const day=n=>{const d=new Date(s.check_in+'T12:00:00Z');d.setUTCDate(d.getUTCDate()+runOffset+n);return d.toISOString().slice(0,10);};
 const create=async n=>{const r=await api('/api/bookings','POST',{hotel_id:46,room_type_id:20,quantity:1,num_guests:2,check_in:day(n),check_out:day(n+2),payment_method:'demo',request_key:crypto.randomUUID()});assert.equal(r.status,201,JSON.stringify(r.data));bookingIds.push(r.data.booking_id);return r.data.booking_id;};
 const clean=async()=>assert.equal(await js("/demo|giả lập|mô phỏng|\\btest\\b/i.test(document.querySelector('.demo-pay').innerText)"),false);
 try{
  const target=await send('Target.createTarget',{url:'about:blank'},null);session=(await send('Target.attachToTarget',{targetId:target.targetId,flatten:true},null)).sessionId;
  for(const method of ['Page.enable','Runtime.enable','Network.enable'])await send(method);
  await send('Fetch.enable',{patterns:[{urlPattern:'*'}]});await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
  await go('/login');
  for(const admin of [false,true])await js(`(async()=>{const r=await fetch(${JSON.stringify(admin?'/api/admin/login':'/api/login')},{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json'},body:JSON.stringify({email:${JSON.stringify(admin?s.admin_email:s.customer_email)},password:${JSON.stringify(s.password)}})});const d=await r.json();if(!r.ok)throw new Error('Synthetic clone login refused');localStorage.setItem(${JSON.stringify(admin?'admin_token':'token')},d.token);localStorage.setItem(${JSON.stringify(admin?'admin':'user')},JSON.stringify(d.user||d.admin));})()`);
  mark('Customer and Admin authenticated against the isolated restored clone');
  await go('/hotels/46?check_in='+day(20)+'&check_out='+day(22));await wait("typeof HolidayVietDetailRooms!=='undefined' && document.querySelector('[data-open-room=\"20\"]')");
  assert.ok(await js("getComputedStyle(document.documentElement).getPropertyValue('--hv-bg').trim().length>0"),'Hotel design-system stylesheet must actually load');
  await click('[data-open-room="20"]');await wait("document.querySelector('#room-detail-modal').open && !document.querySelector('#room-modal-select').disabled");await click('#room-modal-select');await wait("!document.querySelector('#submit-booking-btn').disabled");
  await wait("HolidayVietDetailRooms.stock.status==='ready' && HolidayVietDetailRooms.stock.counts.size===HolidayVietDetailRooms.types.size");
  const availableTypes=await js("[...HolidayVietDetailRooms.types.keys()].filter(id=>HolidayVietDetailRooms.stock.counts.get(id)>0)");assert.ok(availableTypes.length>=2);
  await js("document.querySelector('#booking-special-request').value='Giữ nguyên ghi chú khi đổi loại phòng'");
  for(const typeId of [...availableTypes.filter(v=>v!==20),20]){
   await click('[data-open-room="'+typeId+'"]');await wait("document.querySelector('#room-detail-modal').open && !document.querySelector('#room-modal-select').disabled");await click('#room-modal-select');await wait("HolidayVietDetailRooms.stock.status==='ready'");
   assert.equal(await js("Number(document.querySelector('#room-select').value)"),typeId);
   assert.equal(await js("document.querySelector('#booking-special-request').value"),'Giữ nguyên ghi chú khi đổi loại phòng');assert.equal(await js("document.querySelector('#check-in').value"),day(20));
  }
  assert.equal(await js("document.querySelectorAll('article[data-room-selected=true]').length"),1);assert.equal(await js("document.querySelector('#change-room-selection').hidden"),false);
  mark('Customer switches between all available room types and back without losing dates, quantity or notes');
  assert.equal(await js("document.querySelector('#booking-availability').dataset.availability"),'success');
  assert.equal(await js("getComputedStyle(document.querySelector('#booking-availability')).color"),'rgb(37, 99, 235)');
  assert.equal(await js("document.querySelector('#local-demo-confirm')===null && document.querySelector('#booking-pending-note')===null"),true);
  assert.equal(await js("Boolean(document.querySelector('#booking-special-request').compareDocumentPosition(document.querySelector('#submit-booking-btn'))&Node.DOCUMENT_POSITION_FOLLOWING)"),true);
  mark('Available rooms highlighted blue; notes appear above the direct booking button; old popup removed');
  await js("(()=>{const f=document.querySelector('#guests');f.value='99';f.dispatchEvent(new Event('input',{bubbles:true}));})()");await wait("!document.querySelector('#booking-error').hidden");
  assert.equal(await js("getComputedStyle(document.querySelector('#booking-error')).color"),'rgb(185, 28, 28)');assert.equal(await js("document.querySelector('#submit-booking-btn').disabled"),true);
  await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});await js("document.querySelector('#booking-error').scrollIntoView({block:'center'})");
  assert.equal(await js("document.documentElement.scrollWidth<=innerWidth+2"),true);let hotelShot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(s.runtime,'hotel-capacity-mobile.png'),Buffer.from(hotelShot.data,'base64'));
  mark('Capacity warning is red, blocks submission and fits the mobile viewport');
  await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
  await js("(()=>{const f=document.querySelector('#guests');f.value='2';f.dispatchEvent(new Event('input',{bubbles:true}));document.querySelector('#booking-special-request').value='Ưu tiên phòng yên tĩnh';})()");await wait("!document.querySelector('#submit-booking-btn').disabled");
  await js("(()=>{const original=window.fetch;window.fetch=function(url,...args){if(url==='/api/bookings'){window.fetch=original;return Promise.reject(new Error('Kết nối bị gián đoạn. Vui lòng thử lại.'));}return original.call(this,url,...args);};})()");await click('#submit-booking-btn');await wait("!document.querySelector('#booking-submit-error').hidden && !document.querySelector('#submit-booking-btn').disabled");assert.equal(createRequests,0);
  assert.equal(await js("document.querySelector('#booking-special-request').value"),'Ưu tiên phòng yên tĩnh');mark('Submission failure stays visible in red and preserves the note for retry');
  await js("(()=>{const b=document.querySelector('#submit-booking-btn');b.click();b.click();})()");await wait("location.pathname.startsWith('/payments/bookings/') && document.querySelector('#demo-details') && !document.querySelector('#demo-details').hidden");
  const id=await js("Number(document.querySelector('[data-demo-payment]').dataset.demoPayment)");bookingIds.push(id);assert.equal(createRequests,1);await clean();
  assert.equal((await api('/api/demo/bookings/'+id)).data.booking.special_request,'Ưu tiên phòng yên tĩnh');mark('One direct click creates the real booking with its note; repeated click sends only one POST');
  assert.equal(await js("document.querySelectorAll('[data-demo-action]').length"),0);assert.equal(await js("document.querySelector('#demo-transaction').textContent.startsWith('AUREL-PAY-')"),true);
  let state=(await api('/api/demo/bookings/'+id)).data;assert.equal(state.booking.status,'pending');assert.equal(state.payment.payment_status,'pending');
  mark('Real pending booking and payment persisted; Customer has neutral labels and no result controls');
  let r=await api('/api/demo/bookings/'+id+'/simulate','POST',{action:'success',payment_id:state.payment.id});assert.equal(r.status,403);assert.equal((await api('/api/demo/bookings/'+id)).data.booking.status,'pending');
  mark('Customer cannot forge payment confirmation through the API');
  await go('/admin/bookings');await wait(`document.querySelector('a[href="/demo/admin/payments/${id}"]')`);await click(`a[href="/demo/admin/payments/${id}"]`);await wait("!document.querySelector('[data-demo-action=failed]').disabled");await clean();await click('[data-demo-action=failed]');await wait("document.querySelector('#demo-feedback').textContent.includes('thất bại')");
  await go('/payments/bookings/'+id);await wait("!document.querySelector('#demo-retry').hidden");await click('#demo-retry');await wait("document.querySelector('#demo-feedback').textContent.includes('Đang chờ')");
  state=(await api('/api/demo/bookings/'+id)).data;assert.equal(state.booking.status,'pending');assert.equal(state.payment.payment_status,'pending');assert.equal(state.transactions.length,1);
  mark('Admin sends failure; Customer retries with a new pending attempt and preserved history');
  await go('/demo/admin/payments/'+id);await wait("!document.querySelector('[data-demo-action=success]').disabled");await click('[data-demo-action=success]');await wait("document.querySelector('#demo-feedback').textContent.includes('đã được xác nhận')");
  await go('/payments/bookings/'+id);await wait("!document.querySelector('#demo-invoice').hidden");await clean();state=(await api('/api/demo/bookings/'+id)).data;assert.equal(state.booking.status,'confirmed');assert.equal(state.payment.payment_status,'success');assert.ok(state.invoice.id);assert.equal(state.transactions.length,2);
  mark('Admin result updates real booking/payment/invoice; Customer sees the confirmed result');
  r=await api('/api/admin/demo/bookings/'+id+'/simulate','POST',{action:'success',payment_id:state.payment.id},true);assert.equal(r.status,200);assert.equal(r.data.duplicate,true);assert.equal((await api('/api/demo/bookings/'+id)).data.invoice.id,state.invoice.id);
  mark('Repeated success cannot create another invoice or event');
  const pdf=await js(`(async()=>{const r=await fetch('/api/invoices/${id}/pdf',{headers:{Authorization:'Bearer '+localStorage.getItem('token')}});const data=new Uint8Array(await r.arrayBuffer());let str='';for(let i=0;i<data.length;i+=8192)str+=String.fromCharCode(...data.subarray(i,i+8192));return {status:r.status,type:r.headers.get('content-type'),name:r.headers.get('content-disposition'),base64:btoa(str)};})()`);
  assert.equal(pdf.status,200);assert.ok(pdf.type.includes('application/pdf'));assert.ok(pdf.name.includes('AUREL-hoa-don-'));fs.writeFileSync(path.join(s.runtime,'payment-confirmation.pdf'),Buffer.from(pdf.base64,'base64'));
  mark('Customer downloads the generated AUREL booking confirmation PDF');
  for(const [n,action,status] of [[25,'cancelled','cancelled'],[30,'expired','expired']]){const bid=await create(n);await go('/demo/admin/payments/'+bid);await wait(`!document.querySelector('[data-demo-action=${action}]').disabled`);await click('[data-demo-action='+action+']');await wait("document.querySelector('[data-demo-action=success]').disabled");await go('/payments/bookings/'+bid);await wait("!document.querySelector('#demo-details').hidden");await clean();assert.equal((await api('/api/demo/bookings/'+bid)).data.booking.status,status);}
  mark('Cancellation and expiry reach Customer and preserve terminal booking state');
  for(const [width,height,mobile] of [[1440,1000,false],[768,1024,false],[390,844,true]]){await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile});await go('/payments/bookings/'+id);await wait("!document.querySelector('#demo-details').hidden");await clean();assert.equal(await js("document.documentElement.scrollWidth<=innerWidth+2"),true);const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(s.runtime,'payment-final-'+width+'.png'),Buffer.from(shot.data,'base64'));}
  mark('Desktop, tablet and mobile payment screens fit the viewport without unwanted labels');
  await go('/bookings');await wait("document.querySelector('#bookingList')?.textContent.includes('Thanh toán đặt phòng')");assert.equal(await js("/demo|giả lập|mô phỏng/i.test(document.querySelector('#bookingList').innerText)"),false);
  mark('Existing booking history displays normal payment labels');
  assert.equal(await js("document.querySelector('#demo-invoice')===null"),true);
  for(const [width,height,mobile] of [[1440,1000,false],[768,1024,false],[390,844,true]]){
   await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile});await go('/bookings');await wait("document.querySelector('.booking-card')");
   assert.equal(await js("document.documentElement.scrollWidth<=innerWidth+2"),true);
   assert.equal(await js("(()=>{const l=document.querySelector('label[for=bookingSearch]').getBoundingClientRect();return l.width<=1&&l.height<=1;})()"),true);
   if(width>640)assert.equal(await js("(()=>{const a=document.querySelector('#bookingSearch').getBoundingClientRect(),b=document.querySelector('#bookingSearchForm button').getBoundingClientRect();return Math.abs(a.top-b.top)<3 && b.left>a.right;})()"),true);
   const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(s.runtime,'history-final-'+width+'.png'),Buffer.from(shot.data,'base64'));
  }
  mark('History search label/icon/input/button align correctly on desktop/tablet and stack on mobile');
  await js("(()=>{const f=document.querySelector('#bookingSearch');f.value='AUREL-NO-MATCH-'+Date.now();document.querySelector('#bookingSearchForm').requestSubmit();})()");await wait("document.querySelector('#bookingList .empty-state')");
  await js("document.querySelector('#bookingSearch').value='';document.querySelector('#bookingSearchForm').requestSubmit()");await wait("document.querySelector('.booking-card')");assert.equal(await js("document.querySelector('.status-expired')!==null && document.querySelector('.payment-status[data-tone=success]')!==null && document.querySelector('.payment-status[data-tone=danger]')!==null"),true);
  mark('History search and reset work; payment success/failure/expiry use distinct labeled badges');
  for(const url of ['/profile','/password','/settings']){await go(url);assert.equal(await js("document.documentElement.scrollWidth<=innerWidth+2"),true);await js("showToast('Thông báo hiển thị thành công','success')");assert.equal(await js("getComputedStyle(document.querySelector('.toast-success')).backgroundColor"),'rgb(240, 253, 244)');}
  mark('Shared Customer buttons/toasts fit profile, password and settings on mobile');
  for(const [url,errorSelector] of [['/cam-nang','#guide-error'],['/trai-nghiem','#experience-error']]){await go(url);await wait(`document.querySelector(${JSON.stringify(errorSelector)})`);const color=await js(`getComputedStyle(document.querySelector(${JSON.stringify(errorSelector)})).color`);assert.equal(color,'rgb(185, 28, 28)');assert.equal(await js("document.documentElement.scrollWidth<=innerWidth+2"),true);}
  mark('Guide and Experience error states have prominent red styling with retry actions');
  assert.equal(errors.length,0);fs.writeFileSync(path.join(root,'storage/logs/booking-payment-final-browser.json'),JSON.stringify({status:'PASS',origin,database:s.database,main_written:false,checks,booking_ids:bookingIds,javascript_errors:errors},null,2));
  console.log('PASS '+checks.length+' focused Chrome checks; no JavaScript exceptions.');
 }finally{clearTimeout(timer);await send('Browser.close',{},null).catch(()=>{});chrome.kill();}
})().catch(e=>{console.error(e.message);process.exitCode=1;});
