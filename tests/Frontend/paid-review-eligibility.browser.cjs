// Paid review eligibility acceptance against the owned restored MySQL clone only.
const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict'),crypto=require('node:crypto'),{spawn}=require('node:child_process');
const root=path.resolve(__dirname,'../..'),proof=JSON.parse(fs.readFileSync(path.join(root,'storage/logs/booking-local-stage.json'),'utf8'));
const s=JSON.parse(fs.readFileSync(proof.descriptor,'utf8')),origin=s.origin;
assert.equal(origin,'http://127.0.0.1:8012');assert.equal(s.synthetic_only&&s.restored_clone,true);assert.equal(s.database,proof.restored_clone);assert.notEqual(s.port,3306);
const pause=ms=>new Promise(r=>setTimeout(r,ms));
(async()=>{
 const health=await new Promise((res,rej)=>{http.get(origin+'/__local-demo/health',r=>{let b='';r.on('data',c=>b+=c);r.on('end',()=>{try{res(JSON.parse(b));}catch{rej(new Error('Clone unavailable'));}});}).on('error',rej);});assert.equal(health.database,s.database);assert.equal(health.isolated,true);
 const chrome=spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',['--headless=new','--no-first-run','--no-default-browser-check','--disable-background-networking','--disable-component-update','--disable-sync','--disable-extensions','--disable-gpu','--user-data-dir='+path.join(s.runtime,'chrome-notifications-'+Date.now()),'--remote-debugging-pipe'],{windowsHide:true,stdio:['ignore','ignore','pipe','pipe','pipe']});
 let seq=0,buffer='',session;const pending=new Map(),checks=[],errors=[],bookingIds=[];
 const timer=setTimeout(()=>{chrome.kill();process.exitCode=1;},140000);
 const send=(method,params={},sid=session)=>new Promise((resolve,reject)=>{const id=++seq,t=setTimeout(()=>{pending.delete(id);reject(new Error('CDP timeout '+method));},18000);pending.set(id,{resolve,reject,t});chrome.stdio[3].write(JSON.stringify({id,method,params,...(sid?{sessionId:sid}:{})})+'\0');});
 chrome.stdio[4].setEncoding('utf8');chrome.stdio[4].on('data',chunk=>{buffer+=chunk;let i;while((i=buffer.indexOf('\0'))!==-1){const m=JSON.parse(buffer.slice(0,i));buffer=buffer.slice(i+1);const p=pending.get(m.id);if(p){pending.delete(m.id);clearTimeout(p.t);m.error?p.reject(new Error(m.error.message)):p.resolve(m.result);}else if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description||m.params.exceptionDetails.text);else if(m.method==='Fetch.requestPaused'){const u=new URL(m.params.request.url),ok=u.origin===origin||['data:','blob:'].includes(u.protocol);send(ok?'Fetch.continueRequest':'Fetch.failRequest',{requestId:m.params.requestId,...(ok?{}:{errorReason:'BlockedByClient'})},m.sessionId).catch(()=>{});}}});
 const js=async expression=>{const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true,userGesture:true});if(r.exceptionDetails)throw new Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;};
 const wait=async expression=>{const until=Date.now()+18000;while(Date.now()<until){try{if(await js(expression))return;}catch(e){if(!e.message.includes('Cannot read properties of null')&&!e.message.includes('is not defined'))throw e;}await pause(150);}throw new Error('UI timed out: '+expression);};
 const go=async url=>{await js('window.__aurelLeaving=true');await send('Page.navigate',{url:origin+url});await wait(`!window.__aurelLeaving && location.pathname===${JSON.stringify(url)} && document.readyState==='complete'`);};
 const click=selector=>js(`document.querySelector(${JSON.stringify(selector)}).click()`);
 const api=(url,method='GET',data,admin=false)=>js(`(async()=>{const r=await fetch(${JSON.stringify(url)},{method:${JSON.stringify(method)},headers:{Accept:'application/json','Content-Type':'application/json',Authorization:'Bearer '+localStorage.getItem(${JSON.stringify(admin?'admin_token':'token')})},${data===undefined?'':'body:JSON.stringify('+JSON.stringify(data)+'),'}});return {status:r.status,data:await r.json()};})()`);
 const mark=text=>{checks.push(text);console.log('PASS '+text);};
 const offset=crypto.randomInt(1010,1100),day=n=>{const d=new Date(s.check_in+'T12:00:00Z');d.setUTCDate(d.getUTCDate()+offset+n);return d.toISOString().slice(0,10);};
 const create=async n=>{const r=await api('/api/bookings','POST',{hotel_id:46,room_type_id:20,quantity:1,num_guests:2,check_in:day(n),check_out:day(n+2),payment_method:'demo',request_key:crypto.randomUUID()});assert.equal(r.status,201,JSON.stringify(r.data));bookingIds.push(r.data.booking_id);return r.data.booking_id;};
 const approve=async id=>{const d=(await api('/api/demo/bookings/'+id)).data;const r=await api('/api/admin/demo/bookings/'+id+'/simulate','POST',{action:'success',payment_id:d.payment.id},true);assert.equal(r.status,200);return r.data;};
 try{
  const target=await send('Target.createTarget',{url:'about:blank'},null);session=(await send('Target.attachToTarget',{targetId:target.targetId,flatten:true},null)).sessionId;
  for(const method of ['Page.enable','Runtime.enable','Network.enable'])await send(method);
  await send('Fetch.enable',{patterns:[{urlPattern:'*'}]});await send('Emulation.setDeviceMetricsOverride',{width:1280,height:900,deviceScaleFactor:1,mobile:false});
  await go('/login');for(const admin of [false,true])await js(`(async()=>{const r=await fetch(${JSON.stringify(admin?'/api/admin/login':'/api/login')},{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json'},body:JSON.stringify({email:${JSON.stringify(admin?s.admin_email:s.customer_email)},password:${JSON.stringify(s.password)}})});const d=await r.json();if(!r.ok)throw new Error('Clone login refused');localStorage.setItem(${JSON.stringify(admin?'admin_token':'token')},d.token);localStorage.setItem(${JSON.stringify(admin?'admin':'user')},JSON.stringify(d.user||d.admin));})()`);

  const previous=(await api('/api/reviews/my')).data;
  for(const row of previous.filter(r=>r.hotel_id===46)){
   assert.ok(/^\[AUREL (feedback|paid-review) acceptance\]/.test(row.comment||''),'Refuse to delete unrelated clone review');
   assert.equal((await api('/api/reviews/'+row.id,'DELETE')).status,200);
  }
  const id=await create(0);
  await go('/bookings');await wait("document.querySelector('[data-booking-id=\""+id+"\"]')");
  assert.equal(await js("document.querySelector('[data-booking-id=\""+id+"\"] [data-action=review]')===null"),true);
  let attempt=await api('/api/reviews','POST',{hotel_id:46,booking_id:id,rating:4,comment:'[AUREL paid-review acceptance] blocked before payment',payment_status:'success'});
  assert.equal(attempt.status,422);mark('Unpaid booking has no Write Review button and a forged success request is refused');
  let state=(await api('/api/demo/bookings/'+id)).data;
  let failure=await api('/api/admin/demo/bookings/'+id+'/simulate','POST',{action:'failed',payment_id:state.payment.id},true);assert.equal(failure.status,200);
  await go('/bookings');await wait("document.querySelector('[data-booking-id=\""+id+"\"] .payment-status[data-tone=danger]')");
  assert.equal(await js("document.querySelector('[data-booking-id=\""+id+"\"] [data-action=review]')===null"),true);
  assert.equal((await api('/api/reviews','POST',{hotel_id:46,booking_id:id,rating:4})).status,422);mark('Failed payment keeps reviews blocked in both Customer UI and API');
  assert.equal((await api('/api/demo/bookings/'+id+'/retry','POST',{})).status,200);await go('/bookings');await wait("_notificationReady && document.querySelector('[data-booking-id=\""+id+"\"]')");
  await approve(id);await wait("document.querySelector('[data-booking-id=\""+id+"\"] [data-action=review]')");
  state=(await api('/api/demo/bookings/'+id)).data;assert.equal(state.booking.status,'confirmed');assert.equal(state.payment.payment_status,'success');
  mark('Successful payment automatically enables Write Review while the booking is still confirmed');
  await click('[data-booking-id="'+id+'"] [data-action=review]');await wait("document.querySelector('#reviewOverlay').classList.contains('open')");
  await click('[data-star="4"]');await js("document.querySelector('#reviewText').value='[AUREL paid-review acceptance] Paid owner can submit.'");await click('#btnSubmitReview');await wait("!document.querySelector('#reviewOverlay')");
  const mine=(await api('/api/reviews/my')).data.find(r=>r.booking_id===id);assert.ok(mine);assert.equal(mine.status,'pending');
  assert.equal((await api('/api/demo/bookings/'+id)).data.booking.status,'confirmed');assert.equal((await api('/api/reviews/'+mine.id,'DELETE')).status,200);
  mark('Paid owner can submit a pending review without a stay-completion step; approval remains required');
  assert.equal(errors.length,0,JSON.stringify(errors));
  fs.writeFileSync(path.join(root,'storage/logs/paid-review-eligibility-browser.json'),JSON.stringify({status:'PASS',database:s.database,main_written:false,checks,booking_ids:bookingIds,javascript_errors:errors},null,2));console.log('PASS '+checks.length+' focused Chrome checks; no main database writes.');
 }finally{clearTimeout(timer);await send('Browser.close',{},null).catch(()=>{});chrome.kill();}
})().catch(e=>{console.error(e.message);process.exitCode=1;});
