/* Phase 2D delta only. Local CDP + HTTP and owned synthetic descriptor; blocks external requests. */
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const cfg=JSON.parse(fs.readFileSync(process.argv[2],'utf8').replace(/^\uFEFF/,''));
const root=path.resolve(__dirname,'../..'),mode=process.argv[3]||'after'; assert.ok(['baseline','after','defensive','defensive-after','delta','legacy-renderer','legacy-renderer-after','home-delta'].includes(mode));
assert.match(cfg.origin,/^http:\/\/127\.0\.0\.1:\d+$/);assert.ok(path.resolve(cfg.runtime).startsWith(path.join(root,'storage/app/private/roomtype-v2-tests')+path.sep));
assert.equal(path.resolve(process.argv[2]),path.join(path.resolve(cfg.runtime),'browser-staging.json'));
assert.equal(path.resolve(cfg.profile),path.join(path.resolve(cfg.runtime),'browser-profile'));
assert.equal(path.resolve(cfg.stage_descriptor),path.join(path.resolve(cfg.runtime),'staging-session.json'));
const port=Number(new URL(cfg.origin).port);assert.ok(port>=49152&&port<=65535);
assert.ok(execFileSync('netstat',['-ano','-p','tcp'],{encoding:'utf8'}).split(/\r?\n/).some(l=>{const f=l.trim().split(/\s+/);return f[1]==='127.0.0.1:'+port&&f.at(-1)===String(cfg.http_pid);}));
const stage=JSON.parse(fs.readFileSync(cfg.stage_descriptor,'utf8'));assert.equal(stage.synthetic_only,true);assert.match(stage.database,/^aurel_roomtype_v2_test_[a-f0-9]{12}$/);
const active=fs.readFileSync(path.join(cfg.profile,'DevToolsActivePort'),'utf8').trim().split(/\r?\n/);
let ws,id=0,sid;const waiting=new Map(),errors=[],checks=[],pages=[],external=new Set();
const send=(method,params={},session=sid)=>new Promise((resolve,reject)=>{const n=++id,t=setTimeout(()=>{waiting.delete(n);reject(new Error(method+' timeout'));},20000);waiting.set(n,{resolve,reject,t});ws.send(JSON.stringify({id:n,method,params,...(session?{sessionId:session}:{})}));});
async function evaluate(expression){const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw new Error(r.exceptionDetails.text);return r.result.value;}
async function wait(expression){const end=Date.now()+20000;while(Date.now()<end){if(await evaluate(expression))return;await new Promise(r=>setTimeout(r,100));}throw new Error('Wait failed: '+expression);}
async function go(url){await send('Page.navigate',{url:cfg.origin+url});await wait("document.readyState==='complete'");}
async function check(name,expression){const passed=await evaluate(expression);checks.push({name,passed});if(['after','delta','home-delta'].includes(mode))assert.equal(passed,true,name);}
async function login(admin){await evaluate(`(async()=>{const r=await fetch(${JSON.stringify(admin?'/api/admin/login':'/api/login')},{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json'},body:JSON.stringify({email:${JSON.stringify(admin?stage.admin_email:stage.customer_email)},password:${JSON.stringify(stage.password)}})});if(!r.ok)throw new Error('Synthetic setup login failed');const d=await r.json();localStorage.setItem(${JSON.stringify(admin?'admin_token':'token')},d.token);localStorage.setItem(${JSON.stringify(admin?'admin_account':'user')},JSON.stringify(d.admin||d.user));})()`);}
(async()=>{
  ws=new WebSocket('ws://127.0.0.1:'+active[0]+active[1]);await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject;});
  ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id){const p=waiting.get(m.id);if(!p)return;clearTimeout(p.t);waiting.delete(m.id);m.error?p.reject(new Error(m.error.message)):p.resolve(m.result);return;}
    if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.text);
    if(m.method==='Fetch.requestPaused'){const u=m.params.request.url,allowed=u.startsWith(cfg.origin+'/')||u==='about:blank'||u.startsWith('data:');if(!allowed)external.add(new URL(u).origin);send(allowed?'Fetch.continueRequest':'Fetch.failRequest',allowed?{requestId:m.params.requestId}:{requestId:m.params.requestId,errorReason:'BlockedByClient'},m.sessionId).catch(()=>{});}};
  const target=await send('Target.createTarget',{url:'about:blank'},null);sid=(await send('Target.attachToTarget',{targetId:target.targetId,flatten:true},null)).sessionId;
  await send('Page.enable');await send('Runtime.enable');await send('Fetch.enable',{patterns:[{urlPattern:'*'}]});await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
  await go('/login');await login(false);await login(true);
  if(mode==='home-delta') {
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});await go('/');
    await check('Home input controls have accessible labels',"Array.from(document.querySelectorAll('input:not([type=hidden]),textarea,select')).every(e=>e.labels?.length||e.getAttribute('aria-label')||e.getAttribute('aria-labelledby'))");
    await evaluate("document.getElementById('chatToggleBtn').click()");
    await check('Open chat popup stays inside mobile viewport',"(()=>{const b=document.getElementById('chat-popup').getBoundingClientRect();return b.left>=0&&b.right<=document.documentElement.clientWidth})()");
    await check('Chat send button has an accessible name',"!!document.querySelector('#chat-popup button[onclick=\"sendChat()\"]').getAttribute('aria-label')");
    const result={mode,synthetic_only:true,checks,errors,no_message_sent:true,real_ai_provider_called:false};
    fs.writeFileSync(path.join(cfg.runtime,'phase2d-ui-home-delta.json'),JSON.stringify(result,null,2));console.log(JSON.stringify({checks:checks.length,passed:checks.filter(x=>x.passed).length,errors:errors.length}));
    await send('Target.closeTarget',{targetId:target.targetId},null);ws.close();assert.equal(errors.length,0);return;
  }
  if(mode.startsWith('legacy-renderer')) {
    await go('/profile');
    const source=fs.readFileSync(path.join(root,'resources/views/Dashboard.blade.php'),'utf8');
    const start=source.indexOf('function dashboardText(')>=0?source.indexOf('function dashboardText('):source.indexOf('async function loadItineraries()');
    const renderer=source.slice(start,source.indexOf('function closeItineraryModal(e)',start));
    const printStart=source.indexOf('function printItinerary()');
    const printer=source.slice(printStart,source.indexOf('async function loadProfile()',printStart));
    const fixture={id:900001,title:'<span data-phase2d-inert>Title</span>',status:'draft',total_days:1,items:[{day_number:1,type:'hotel',name:'<span data-phase2d-inert>Name</span>',description:'<span data-phase2d-inert>Description</span>',address:'<span data-phase2d-inert>Address</span>',time:'09:00'}]};
    const result=await evaluate(`(async()=>{const box=document.createElement('div');box.hidden=true;box.innerHTML='<div id="itineraries-list"></div><div id="itin-modal-title"></div><div id="itin-modal-meta"></div><input id="itin-modal-start-date"><div id="itin-modal-body"></div><div id="itineraryModal"></div>';document.body.appendChild(box);const api=async()=>({json:async()=>[${JSON.stringify(fixture)}]});const emptyState=()=>'';${renderer}\n${printer}\nawait loadItineraries();const cardSafe=!box.querySelector('#itineraries-list [data-phase2d-inert]');openItineraryModal(${JSON.stringify(fixture)});const modalSafe=!box.querySelector('#itin-modal-body [data-phase2d-inert]');let printed='';const original=window.open;window.open=()=>({document:{write:html=>printed=html,close(){}},focus(){},print(){}});try{printItinerary()}finally{window.open=original}const printable=document.createElement('div');printable.innerHTML=printed;const printSafe=!printable.querySelector('[data-phase2d-inert]');box.remove();return {cardSafe,modalSafe,printSafe};})()`);
    const report={mode,synthetic_only:true,inert_markup_only:true,no_business_database_mutations:true,synthetic_login_setup_creates_tokens:true,no_external_calls:true,...result,errors};
    fs.writeFileSync(path.join(cfg.runtime,'phase2d-'+mode+'.json'),JSON.stringify(report,null,2));console.log(JSON.stringify(report));
    await send('Target.closeTarget',{targetId:target.targetId},null);ws.close();
    if(mode.endsWith('after'))for(const key of ['cardSafe','modalSafe','printSafe'])assert.equal(result[key],true,key);return;
  }
  if(mode==='delta') {
    await go('/profile');await wait("typeof viewAllActivity==='function'");
    await evaluate("document.querySelector('.btn-activity-more').focus();viewAllActivity()");
    await check('Activity dialog takes keyboard focus',"document.querySelector('dialog[open]')?.contains(document.activeElement)===true");
    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await wait("!document.querySelector('dialog[open]')");
    await check('Escape closes dialog and returns focus to opener',"document.activeElement===document.querySelector('.btn-activity-more')");
    await go('/admin/hotels');await wait("typeof renderTable==='function'");
    await check('Admin hotel controls have labels',"Array.from(document.querySelectorAll('input:not([type=hidden]),select,textarea')).every(e=>e.labels?.length||e.getAttribute('aria-label')||e.getAttribute('aria-labelledby'))");
    await go('/admin/bookings');await wait("typeof renderTable==='function'");
    await check('Admin booking filters have labels',"Array.from(document.querySelectorAll('.filter-bar input,.filter-bar select')).every(e=>e.labels?.length||e.getAttribute('aria-label')||e.getAttribute('aria-labelledby'))");
    await go('/admin/hotels/1/room-types');await wait("document.querySelectorAll('[data-type]').length>1");
    await evaluate("window.__phase2d_originalAdminApi=adminApi;window.__phase2d_galleryPending=[];window.adminApi=(url,options)=>url.endsWith('/images')?new Promise(resolve=>window.__phase2d_galleryPending.push({url,resolve})):window.__phase2d_originalAdminApi(url,options);document.querySelectorAll('[data-type]')[0].click();document.querySelectorAll('[data-type]')[1].click()");
    await wait("window.__phase2d_galleryPending.length===2");
    await evaluate("window.__phase2d_galleryPending[1].resolve(new Response(JSON.stringify({images:[]}),{status:200,headers:{'Content-Type':'application/json'}}))");
    await wait("document.getElementById('type-gallery').textContent.includes('Chưa có ảnh')");
    await evaluate("window.__phase2d_galleryPending[0].resolve(new Response(JSON.stringify({images:[{id:900001,image_url:'image/stale-gallery-marker.png',is_primary:true}]}),{status:200,headers:{'Content-Type':'application/json'}}))");
    await new Promise(resolve=>setTimeout(resolve,100));
    await check('Late gallery response cannot replace the selected type',"!document.querySelector('#type-gallery img')&&document.querySelectorAll('[data-type]')[1].getAttribute('aria-pressed')==='true'");
    await evaluate("window.adminApi=window.__phase2d_originalAdminApi");
    await check('Unauthorized and validation responses keep private security headers',"(async()=>{const a=await fetch('/api/admin/bookings',{headers:{Accept:'application/json'}});const b=await fetch('/api/invoices?per_page=1000000',{headers:{Accept:'application/json',Authorization:'Bearer '+localStorage.getItem('token')}});return a.status===401&&a.headers.get('Referrer-Policy')==='no-referrer'&&a.headers.get('Cache-Control').includes('no-store')&&b.status===422&&b.headers.get('X-Content-Type-Options')==='nosniff';})()");
    const result={mode,synthetic_only:true,checks,errors,external_requests_blocked:true,real_gateway_e2e:false,real_mailbox_e2e:false};
    fs.writeFileSync(path.join(cfg.runtime,'phase2d-ui-delta.json'),JSON.stringify(result,null,2));console.log(JSON.stringify({checks:checks.length,passed:checks.filter(x=>x.passed).length,errors:errors.length}));
    await send('Target.closeTarget',{targetId:target.targetId},null);ws.close();assert.equal(errors.length,0);return;
  }
  if(mode.startsWith('defensive')) {
    await go('/admin/bookings');await wait("typeof renderTable==='function'");
    const bookingSafe=await evaluate("(()=>{const original=openModal;window.openModal=()=>{};window.__phase2d_event_marker=0;renderTable([{id:900001,status:'pending',total_price:'1000000.00',room_quantity:1,check_in:'2026-10-15',check_out:'2026-10-17',hotel:{name:'Synthetic hotel'},customer:{name:'&quot;}});window.__phase2d_event_marker=1;//',email:'fixture@example.test'}}]);document.querySelector('#bookingTableBody tr').click();window.openModal=original;return window.__phase2d_event_marker===0;})()");
    await go('/admin/hotels');await wait("typeof renderTable==='function'");
    const hotelSafe=await evaluate("(()=>{const original=openEditModal;window.openEditModal=()=>{};window.__phase2d_event_marker=0;renderTable([{id:900001,status:'active',star_rating:3,avg_rating:0,rooms_count:1,name:'&quot;});window.__phase2d_event_marker=1;//',address:'Synthetic address',location:{name:'Synthetic location'},amenities:[],images:[]}]);document.querySelector('#hotelTableBody .btn-edit').click();window.openEditModal=original;return window.__phase2d_event_marker===0;})()");
    const result={mode,synthetic_only:true,no_business_database_mutations:true,synthetic_login_setup_creates_tokens:true,renderer_read_credentials:false,real_credentials_used:false,external_requests_blocked:true,booking_handler_data_remains_data:bookingSafe,hotel_handler_data_remains_data:hotelSafe,errors};
    fs.writeFileSync(path.join(cfg.runtime,'phase2d-event-'+mode+'.json'),JSON.stringify(result,null,2));console.log(JSON.stringify(result));
    await send('Target.closeTarget',{targetId:target.targetId},null);ws.close();
    if(mode==='defensive-after'){assert.equal(bookingSafe,true);assert.equal(hotelSafe,true);}return;
  }
  await go('/profile');await wait("typeof loadActivityLog==='function'");await evaluate("localStorage.removeItem('activities');loadActivityLog()");
  await check('Profile empty state does not fabricate booking/activity',"!document.getElementById('activityList').textContent.includes('Sunrise') && document.getElementById('activityList').textContent.includes('Chưa')");
  await check('Profile has no unsupported verified claim',"!document.body.textContent.includes('Đã xác minh')");
  await check('Profile name input has associated label',"document.querySelector('label[for=profileName]')!==null");
  await evaluate("localStorage.setItem('activities',JSON.stringify([{title:'<img src=/invalid onerror=window.__phase2d_xss=1>',detail:'<script>unsafe</script>',time:'now',icon:'fa-clock'}]));loadActivityLog()");
  await new Promise(r=>setTimeout(r,350));await check('Local activity text cannot execute markup',"window.__phase2d_xss!==1 && document.getElementById('activityList').textContent.includes('<img')");
  await evaluate("document.querySelector('.btn-activity-more').focus();viewAllActivity()");
  await check('Activity modal has an accessible dialog name',"Array.from(document.querySelectorAll('[role=dialog]')).some(d=>d.getAttribute('aria-labelledby')&&document.getElementById(d.getAttribute('aria-labelledby')))");
  await evaluate("const d=Array.from(document.querySelectorAll('body > div,body > dialog')).find(e=>e.textContent.includes('Lịch sử hoạt động')&&(e.style.position==='fixed'||e.tagName==='DIALOG'));d?.querySelector('button')?.click()");
  await check('Closing activity modal removes the complete overlay',"!Array.from(document.querySelectorAll('body > div,body > dialog')).some(e=>e.textContent.includes('Lịch sử hoạt động')&&(e.style.position==='fixed'||e.tagName==='DIALOG')) && !Array.from(document.querySelectorAll('body > div')).some(e=>e.style.position==='fixed'&&!e.textContent.trim())");
  await evaluate("localStorage.setItem('activities','invalid-json')");
  const malformed=await evaluate("(()=>{try{loadActivityLog();return true}catch(e){return false}})()");checks.push({name:'Malformed local activity storage has a safe empty state',passed:malformed});if(mode==='after')assert.equal(malformed,true);
  await evaluate("localStorage.removeItem('activities')");
  await go('/admin/hotels/1/room-types');await wait("document.querySelector('[data-type]')!==null");
  await evaluate("document.querySelector('[data-type]').click()");
  await check('Selected RoomType is announced with aria-pressed',"document.querySelector('[data-type]').getAttribute('aria-pressed')==='true'");
  await go('/admin/reconciliation');
  await check('Reconciliation dialog has an accessible name',"!!document.getElementById('delivery-review-dialog').getAttribute('aria-labelledby') && !!document.getElementById(document.getElementById('delivery-review-dialog').getAttribute('aria-labelledby'))");
  const urls=['/','/?search=fixture','/hotels/1','/payments/bookings/999999','/payment/result','/bookings','/profile','/admin/hotels','/admin/bookings','/admin/hotels/1/room-types','/admin/reconciliation'];
  await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});
  for(const url of urls){await go(url);await new Promise(r=>setTimeout(r,160));const data=await evaluate("({width:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth,title:document.title,description:!!document.querySelector('meta[name=description]'),canonical:!!document.querySelector('link[rel=canonical]'),robots:document.querySelector('meta[name=robots]')?.content||'',h1:document.querySelectorAll('h1').length,unlabelled:Array.from(document.querySelectorAll('input:not([type=hidden]),select,textarea')).filter(e=>!e.closest('label')&&!e.labels?.length&&!e.getAttribute('aria-label')&&!e.getAttribute('aria-labelledby')).length,images:Array.from(document.images).map(i=>({local:i.src.startsWith(location.origin),lazy:i.loading==='lazy',width:i.getAttribute('width'),height:i.getAttribute('height'),decoded:i.complete&&i.naturalWidth>0})),resources:performance.getEntriesByType('resource').filter(r=>r.name.startsWith(location.origin)).map(r=>({bytes:r.transferSize,ms:r.duration,type:r.initiatorType}))})");pages.push({url,...data});}
  await go('/');await check('Public metadata uses the configured staging origin',"document.querySelector('link[rel=canonical]')?.href===location.origin+'/' && !!document.querySelector('meta[name=description]') && !!document.querySelector('meta[property=\"og:title\"]')");
  await go('/profile');await check('Private profile excludes search indexing',"document.querySelector('meta[name=robots]')?.content.includes('noindex')===true");
  await send('Page.captureScreenshot',{format:'png'}).then(r=>fs.writeFileSync(path.join(cfg.runtime,'phase2d-profile-mobile-'+mode+'.png'),Buffer.from(r.data,'base64')));
  const result={mode,synthetic_only:true,database:stage.database,checks,pages,errors,blocked_external_origins:[...external],real_gateway_e2e:false,real_mailbox_e2e:false};
  fs.writeFileSync(path.join(cfg.runtime,'phase2d-ui-'+mode+'.json'),JSON.stringify(result,null,2));
  console.log(JSON.stringify({mode,checks:checks.length,passed:checks.filter(x=>x.passed).length,errors:errors.length,pages:pages.length,report:'phase2d-ui-'+mode+'.json'}));
  await send('Target.closeTarget',{targetId:target.targetId},null);ws.close();
})().catch(e=>{console.error(e.message);process.exitCode=1;ws?.close();});
