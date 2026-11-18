/* Local static Blade previews + synthetic fetch responses. No Laravel server or database. */
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const assert = require('node:assert/strict');
const {spawn} = require('node:child_process');
const root = path.resolve(__dirname, '../..');
const runtime = path.join(root, 'storage/app/private/customer-account-phase2');
const profile = path.join(runtime, 'browser-profile');
const checks = [], errors = [], screenshots = [];
const server = http.createServer((req, res) => {
  const url = new URL(req.url, 'http://localhost');
  if (['/profile','/password','/bookings','/itineraries','/settings','/home-chat'].includes(url.pathname)) {
    res.setHeader('Content-Type','text/html; charset=utf-8');
    const preview = path.join(runtime,'preview',url.pathname.slice(1)+'.html');
    const html = fs.readFileSync(fs.existsSync(preview) ? preview : path.join(root,'storage/app/private/customer-account-phase1-completion/preview',url.pathname.slice(1)+'.html'),'utf8');
    const base='http://127.0.0.1:'+server.address().port;
    return res.end(html.replaceAll('http://127.0.0.1/',base+'/').replaceAll('http://localhost:8000/',base+'/').replaceAll('http:\\/\\/127.0.0.1\\/',base.replaceAll('/','\\/')+'\\/').replaceAll('http:\\/\\/localhost:8000\\/',base.replaceAll('/','\\/')+'\\/'));
  }
  if (url.pathname === '/login') {res.setHeader('Content-Type','text/html'); return res.end('<!doctype html><title>Synthetic login destination</title>');}
  const file = path.resolve(root, 'public', '.'+decodeURIComponent(url.pathname));
  if (file.startsWith(path.join(root,'public')+path.sep) && /\.(js|css|png|svg|woff2?)$/.test(file) && fs.existsSync(file)) {
    res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':file.endsWith('.svg')?'image/svg+xml':'application/octet-stream');return res.end(fs.readFileSync(file));
  }
  res.writeHead(404);res.end();
});
const synthetic = `
  if(location.pathname!='/login') {
    localStorage.setItem('token','synthetic-customer-token');
    localStorage.setItem('user',JSON.stringify({id:1,name:'Tài khoản kiểm thử',email:'synthetic@example.test',role:'user'}));
    localStorage.setItem('admin_token','synthetic-admin-token');
  }
  if(!sessionStorage.getItem('__completionReady')) {
    localStorage.removeItem('__syntheticDobValue');localStorage.removeItem('__syntheticDobSupport');
    sessionStorage.setItem('__completionReady','true');
  }
  window.__empty=sessionStorage.getItem('__empty')==='true'; window.__profileLoadFails=false; window.__avatarFails=true; window.__passwordFails=true; window.__cancelFails=true; window.__listFails=false; window.__itinerarySaveFails=true; window.__generationFails=true; window.__itineraries=[]; window.__requests=[];window.__logoutMode='network';window.__detailEnvelope=true;
  window.__dobSupported=localStorage.getItem('__syntheticDobSupport')==='true';
  window.__dobValue=JSON.parse(localStorage.getItem('__syntheticDobValue')||'null');window.__profileSaveFails=false;
  window.__booking={id:7,hotel_id:1,status:'confirmed',check_in:new Date(Date.now()+72*3600000).toISOString(),check_out:new Date(Date.now()+120*3600000).toISOString(),num_guests:4,total_price:'2000000.00',hotel:{name:'Khách sạn kiểm thử',images:[]},booking_rooms:[{quantity:2,nights:2,room_type:{name:'Phòng kiểm thử'}}],payment:{payment_method:null,payment_status:'pending'},invoice:null};
  window.fetch=async (input,opts={})=>{
    const url=new URL(String(input),location.origin); __requests.push({path:url.pathname,query:url.search,method:opts.method||'GET',body:opts.body});
    const response=(data,status=200)=>new Response(JSON.stringify(data),{status,headers:{'Content-Type':'application/json'}});
    if(url.pathname==='/api/logout') {
      if(__logoutMode==='network')throw new TypeError('Synthetic offline');
      if(__logoutMode==='server')return response({message:'Synthetic failure'},503);
      if(__logoutMode==='missing')return response({message:'Unconfirmed'});
      if(__logoutMode==='deferred')return new Promise(resolve=>window.__releaseLogout=()=>resolve(response({revoked:true})));
      return response({revoked:true});
    }
    if(url.pathname==='/api/me/avatar') {
      if(__avatarFails==='deferred')return new Promise(resolve=>window.__releaseAvatar=()=>resolve(response({message:'Synthetic avatar rejected'},422)));
      return response(__avatarFails?{message:'Synthetic avatar rejected'}:{avatar_url:'/image/placeholders/accommodation.svg'},__avatarFails?422:200);
    }
    if(url.pathname==='/api/me/password') {
      if(__passwordFails==='network')throw new TypeError('Synthetic offline');
      if(__passwordFails==='deferred')return new Promise(resolve=>window.__releasePassword=()=>resolve(response({message:'Đã đổi mật khẩu'})));
      return response(__passwordFails?{errors:{current_password:['Synthetic incorrect password']}}:{message:'Đã đổi mật khẩu'},__passwordFails?422:200);
    }
    if(url.pathname==='/api/me') {
      if(__profileLoadFails)throw new TypeError('Synthetic offline');
      if(opts.method==='PUT') {
        if(__profileSaveFails)return response({message:'Synthetic profile failure'},503);
        const fields=JSON.parse(opts.body);
        if(Object.hasOwn(fields,'dob')){__dobValue=fields.dob||null;localStorage.setItem('__syntheticDobValue',JSON.stringify(__dobValue));}
      }
      return response({id:1,name:'Tài khoản kiểm thử',email:'synthetic@example.test',phone:'0900000001',bookings:__empty?0:3,dob:__dobValue,profile_fields:{dob:__dobSupported},created_at:'2026-10-01',user:{name:'Tài khoản kiểm thử',dob:__dobValue}});
    }
    if(url.pathname==='/api/bookings/my') {
      if(window.__deferBookingStatus && window.__deferBookingStatus===url.searchParams.get('status') && url.searchParams.get('per_page')==='10')await new Promise(resolve=>window.__releaseBookingRequest=resolve);
      if(__listFails==='unauthorized')return response({message:'Unauthenticated'},401);
      if(__listFails)throw new TypeError('Synthetic offline');
      const search=(url.searchParams.get('search')||'').toLowerCase();
      const matchesSearch=!search || String(__booking.id).includes(search) || __booking.hotel.name.toLowerCase().includes(search);
      const list=!__empty&&matchesSearch&&(!url.searchParams.has('status')||url.searchParams.get('status')===__booking.status)?[__booking]:[];
      return response({data:list,total:list.length,per_page:10,current_page:Number(url.searchParams.get('page')||1),last_page:window.__pages||1,from:list.length?1:null,to:list.length||null});
    }
    if(url.pathname==='/api/bookings/7')return response(__detailEnvelope?{booking:__booking}:__booking);
    if(url.pathname==='/api/bookings/7/cancel') {if(__cancelFails)return response({message:'Synthetic cancellation rejected'},422);__booking.status='cancelled';return response({message:'Đã hủy'});}
    if(url.pathname==='/api/invoices/7/pdf')return new Response('%PDF-1.4 synthetic-only',{headers:{'Content-Type':'application/pdf'}});
    if(url.pathname==='/api/reviews/my')return response({data:[]});
    if(url.pathname==='/api/reviews')return response({message:'Synthetic review accepted'},201);
    if(url.pathname==='/api/itineraries'){if(__listFails)throw new TypeError('Synthetic offline');return response(__itineraries);}
    if(url.pathname==='/api/itineraries/generate')return __generationFails?response({errors:{destination:['Synthetic AI validation failure']}},422):response({message:'Đã tạo',itinerary:__generated});
    if(url.pathname.startsWith('/api/itineraries/')) {
      const id=Number(url.pathname.split('/').pop());
      if(opts.method==='PUT')return __itinerarySaveFails?response({message:'Synthetic save failure'},422):response({itinerary:{id,status:'saved'}});
      if(opts.method==='DELETE') {if(window.__deleteFails)return response({message:'Synthetic delete rejected'},422);__itineraries=__itineraries.filter(it=>it.id!==id);return response({message:'Đã xóa'});}
      return response(__itineraries.find(it=>it.id===id)||{message:'Not found'},__itineraries.some(it=>it.id===id)?200:404);
    }
    if(url.pathname==='/api/chat/start')return response({session_id:1},201);
    if(url.pathname==='/api/chat/1/message')return response({message:'<img src=x onerror=window.__xss=1>\\nDòng tiếng Việt',hotel:{id:'1 onclick=window.__xss=1',name:'<svg onload=window.__xss=1>'}});
    if(url.pathname==='/api/hotels')return response({data:[]});
    return response([]);
  };`;
let chrome, ws, sid, sequence = 0;
const pending = new Map();
function send(method, params={}, session=sid) {return new Promise((resolve,reject)=>{
  const id=++sequence,t=setTimeout(()=>reject(new Error(method+' timeout')),15000);
  pending.set(id,{resolve,reject,t});ws.send(JSON.stringify({id,method,params,...(session?{sessionId:session}:{})}));
});}
async function evaluate(expression) {const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true});if(r.exceptionDetails)throw Error(JSON.stringify(r.exceptionDetails));return r.result.value;}
async function wait(expression) {for(let i=0;i<100;i++){if(await evaluate(expression))return;await new Promise(r=>setTimeout(r,100));}throw Error('Wait: '+expression);}
async function check(name, expression) {
  const passed=await evaluate(expression);checks.push({name,passed});
  if (!passed) console.log(JSON.stringify(await evaluate('({active:document.activeElement?.id,width:innerWidth,sidebarOpen:document.getElementById("sidebar")?.classList.contains("open"),inert:document.getElementById("sidebar")?.inert,bodyOpen:document.body.classList.contains("sidebar-open"),expanded:document.getElementById("accountMenuToggle")?.getAttribute("aria-expanded")})')));
  if (!passed && name.includes('Avatar')) console.log(JSON.stringify(await evaluate('({src:document.getElementById("profileAvatarImg")?.src,busy:document.getElementById("avatarUploadButton")?.getAttribute("aria-busy"),status:document.getElementById("profileAvatarStatus")?.textContent,disabled:document.getElementById("avatarUploadButton")?.disabled})')));
  if (!passed && name.includes('fits')) console.log(JSON.stringify(await evaluate('({width:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth,offenders:Array.from(document.querySelectorAll("body *")).map(e=>({tag:e.tagName,cls:e.className,right:e.getBoundingClientRect().right,width:e.getBoundingClientRect().width,scroll:e.scrollWidth,client:e.clientWidth})).filter(e=>e.scroll>e.client+1).slice(0,15)})')));
  assert.equal(passed,true,name);
}
(async()=>{
  await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
  const origin='http://127.0.0.1:'+server.address().port;
  fs.mkdirSync(profile,{recursive:true});
  const portFile=path.join(profile,'DevToolsActivePort');if(fs.existsSync(portFile))fs.unlinkSync(portFile);
  chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',[
    '--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-background-networking',
    '--disable-component-update','--disable-sync','--disable-extensions','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'
  ],{windowsHide:true,stdio:'ignore'});
  for(let i=0;i<100&&!fs.existsSync(portFile);i++)await new Promise(r=>setTimeout(r,100));
  assert.ok(fs.existsSync(portFile),'Chrome debugger must start');
  const active=fs.readFileSync(portFile,'utf8').trim().split(/\r?\n/);
  ws=new WebSocket('ws://127.0.0.1:'+active[0]+active[1]);await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject;});
  ws.onmessage=e=>{const m=JSON.parse(e.data);if(m.id){const p=pending.get(m.id);if(!p)return;clearTimeout(p.t);pending.delete(m.id);return m.error?p.reject(Error(m.error.message)):p.resolve(m.result);}
    if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.text);
    if(m.method==='Fetch.requestPaused'){const allowed=m.params.request.url.startsWith(origin+'/')||m.params.request.url.startsWith('data:');send(allowed?'Fetch.continueRequest':'Fetch.failRequest',allowed?{requestId:m.params.requestId}:{requestId:m.params.requestId,errorReason:'BlockedByClient'},m.sessionId).catch(()=>{});}
  };
  const target=await send('Target.createTarget',{url:'about:blank'},null);sid=(await send('Target.attachToTarget',{targetId:target.targetId,flatten:true},null)).sessionId;
  await send('Page.enable');await send('Runtime.enable');await send('Fetch.enable',{patterns:[{urlPattern:'*'}]});
  await send('Page.addScriptToEvaluateOnNewDocument',{source:synthetic});
  async function go(page){await send('Page.navigate',{url:origin+'/'+page});await wait('document.readyState==="complete"');}
  async function shot(name) {
    await evaluate('document.fonts.ready');
    const metrics=await send('Page.getLayoutMetrics');const size=metrics.cssContentSize; const viewportOnly=/^(modal-|sidebar-)|cancel-error/.test(name);const data=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:!viewportOnly,...(viewportOnly?{}:{clip:{x:0,y:0,width:size.width,height:Math.min(size.height,10000),scale:1}})});
    const file='screenshots/'+name+'.png';fs.mkdirSync(path.join(runtime,'screenshots'),{recursive:true});fs.writeFileSync(path.join(runtime,file),Buffer.from(data.data,'base64'));screenshots.push(file);
  }
  if(process.env.AUREL_SETTINGS_ONLY==='1') {
    await go('settings');await wait('document.getElementById("settingsContent").hidden===false');
    await check('Settings route and sidebar state are correct','location.pathname==="/settings" && document.querySelector(".sidebar-nav-item.active")?.dataset.nav==="settings"');
    await check('Settings loading resolves into content','document.getElementById("settingsLoadState").hidden && !document.getElementById("settingsContent").hidden');
    await check('Transactional notifications remain mandatory','document.querySelector(".settings-required-badge").textContent.includes("Luôn bật") && document.body.textContent.includes("Booking, Payment, Invoice")');
    await check('Missing optional channel uses an honest empty state without a fake toggle','document.querySelector(".settings-empty-preference").textContent.includes("Chưa có tùy chọn") && !document.querySelector("input[type=checkbox]") && !__requests.some(r=>r.path.includes("preference"))');
    await check('Language currency and theme are readonly system information','document.querySelector(".settings-system-list").textContent.includes("Tiếng Việt") && document.querySelector(".settings-system-list").textContent.includes("VND") && document.querySelector(".settings-system-list").textContent.includes("Sáng") && document.querySelectorAll(".settings-system-list select").length===0');
    await evaluate('document.getElementById("accountMotion").value="reduced";document.getElementById("accountMotion").dispatchEvent(new Event("change"))');
    await check('Browser motion preference reports confirmed persistence','localStorage.getItem("aurel_account_motion")==="reduced" && document.documentElement.dataset.accountMotion==="reduced" && document.getElementById("motionStatus").classList.contains("success")');
    await go('settings');await wait('document.getElementById("settingsContent").hidden===false');
    await check('Browser motion preference survives reload','document.getElementById("accountMotion").value==="reduced" && document.documentElement.dataset.accountMotion==="reduced"');
    await evaluate('window.__originalSet=Storage.prototype.setItem;Storage.prototype.setItem=function(){throw new Error("Synthetic storage denied")};document.getElementById("accountMotion").value="system";document.getElementById("accountMotion").dispatchEvent(new Event("change"));Storage.prototype.setItem=__originalSet');
    await check('Storage failure is reported without claiming persistence','document.getElementById("motionStatus").classList.contains("error") && document.getElementById("motionStatus").textContent.includes("không cho phép lưu") && localStorage.getItem("aurel_account_motion")==="reduced"');
    for(const width of [320,375,768,1024,1440]) {
      await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<768});await new Promise(resolve=>setTimeout(resolve,100));
      await check('Settings fits '+width+'px','document.documentElement.scrollWidth<=document.documentElement.clientWidth && document.querySelector(".settings-grid").scrollWidth<=document.querySelector(".settings-grid").clientWidth');
    }
    assert.equal(errors.length,0,'No Settings browser script errors');
    return;
  }
  if(process.env.AUREL_BOOKINGS_ONLY==='1') {
    await go('bookings');await wait('!!document.querySelector(".booking-card")');
    await check('Booking list loads real API fixture independently of Checkout','document.querySelector(".booking-card").textContent.includes("BK-7") && document.querySelector(".booking-capability-note").textContent.includes("chưa khả dụng") && !document.querySelector("[data-action=resume]")');
    await check('Unknown payment method is never inferred as cash','document.querySelector(".payment-line").textContent.includes("Chưa xác định") && !document.querySelector(".payment-line").textContent.includes("Tiền mặt")');
    await evaluate('__detailEnvelope=false;document.querySelector("[data-action=detail]").click()');await wait('document.getElementById("detailContent").textContent.includes("BK-7")');
    await check('Legacy raw Booking detail renders Booking and Payment separately','document.getElementById("detailContent").textContent.includes("Đã xác nhận") && document.getElementById("detailContent").textContent.includes("Chờ thanh toán")');
    await evaluate('document.querySelector("[data-action=cancel]").click();document.getElementById("confirmModalOkBtn").click()');await wait('!document.getElementById("confirmModalOkBtn").disabled');
    await check('Rejected cancellation preserves Booking and confirmation state','__booking.status==="confirmed" && document.getElementById("confirmModal").classList.contains("open")');
    await evaluate('__cancelFails=false;document.getElementById("confirmModalOkBtn").click()');await wait('!document.getElementById("confirmModal").classList.contains("open")');
    await check('Acknowledged cancellation refreshes the Booking state','__booking.status==="cancelled" && document.querySelector(".booking-card").textContent.includes("Đã huỷ")');
    await evaluate('__booking.status="confirmed"');
    await evaluate('__detailEnvelope=true;__booking.invoice={id:8,invoice_no:"SYNTHETIC-8"};document.querySelector("[data-action=detail]").click()');await wait('!!document.querySelector("#detailContent [data-action=invoice]")');
    await check('V2 envelope renders only the issued Invoice action','document.getElementById("detailContent").textContent.includes("SYNTHETIC-8") && document.querySelectorAll("#detailContent [data-action=invoice]").length===1');
    await evaluate('document.querySelector("#detailContent [data-action=invoice]").click()');await wait('__requests.some(r=>r.path==="/api/invoices/7/pdf")');
    await check('Invoice uses the existing read-only GET endpoint','__requests.find(r=>r.path==="/api/invoices/7/pdf").method==="GET"');
    await evaluate('document.getElementById("bookingSearch").value="Khách sạn kiểm thử";document.getElementById("bookingSearchForm").requestSubmit()');await wait('__requests.some(r=>r.path==="/api/bookings/my" && r.query.includes("search="))');
    await check('Search submits backend filter without changing Booking state','__requests.some(r=>r.path==="/api/bookings/my" && r.query.includes("search=Kh")) && __booking.status==="confirmed" && !!document.querySelector(".booking-card")');
    await evaluate('__booking.status="expired";document.querySelector("[data-tab=expired]").click()');await wait('!!document.querySelector(".booking-card")');
    await check('Expired Booking remains distinct from Payment status','document.querySelector(".booking-card").textContent.includes("Hết hạn") && document.querySelector(".payment-line").textContent.includes("Chờ thanh toán")');
    await evaluate('__booking.status="cancelled";document.querySelector("[data-tab=cancelled]").click()');await wait('!!document.querySelector(".booking-card")');
    await check('Cancelled Booking renders without a payment action','document.querySelector(".booking-card").textContent.includes("Đã huỷ") && !document.querySelector("[data-action=resume]")');
    await evaluate('__booking.status="confirmed";__pages=3;document.getElementById("bookingSearch").value="";document.getElementById("bookingSearchForm").requestSubmit()');await wait('document.getElementById("bookingList").textContent.includes("bộ lọc")');
    await evaluate('document.querySelector("[data-tab=all]").click()');await wait('!!document.querySelector(".page-link[data-page=\\"2\\"]")');
    await evaluate('document.querySelector(".page-link[data-page=\\"2\\"]").click()');await wait('!!document.querySelector(".page-link[aria-current=page][data-page=\\"2\\"]")');
    await check('Pagination remains server-backed and keyboard accessible','document.querySelector(".page-link[aria-current=page]").tagName==="BUTTON" && __requests.some(r=>r.path==="/api/bookings/my")');
    await evaluate('__pages=1;__empty=true;document.getElementById("bookingSearch").value="không tồn tại";document.getElementById("bookingSearchForm").requestSubmit()');await wait('document.getElementById("bookingList").textContent.includes("từ khóa")');
    await check('Search empty state is explicit','document.getElementById("bookingList").textContent.includes("Không tìm thấy")');
    await evaluate('__empty=false;__listFails=true;document.getElementById("bookingSearch").value="lỗi mạng";document.getElementById("bookingSearchForm").requestSubmit()');await wait('!!document.getElementById("retryBookings")');
    await check('API failure clears loading and exposes retry','document.getElementById("bookingList").getAttribute("aria-busy")==="false" && !!document.getElementById("retryBookings")');
    await evaluate('__listFails=false;document.getElementById("bookingSearch").value="";document.getElementById("bookingSearchForm").requestSubmit()');await wait('!!document.querySelector(".booking-card")');
    for(const width of [320,375,768,1024,1440]) {
      await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<768});await new Promise(resolve=>setTimeout(resolve,100));
      await check('Booking history fits '+width+'px','document.documentElement.scrollWidth<=document.documentElement.clientWidth && document.getElementById("bookingHistoryApp").scrollWidth<=document.getElementById("bookingHistoryApp").clientWidth');
    }
    await evaluate('__listFails="unauthorized";document.getElementById("bookingSearch").value="hết phiên";document.getElementById("bookingSearchForm").requestSubmit()');await wait('location.pathname==="/login"');
    await check('Expired Customer session redirects to login','location.pathname==="/login"');
    assert.equal(errors.length,0,'No Booking browser script errors');
    return;
  }
  if(process.env.AUREL_PASSWORD_ONLY==='1') {
    await go('password');
    await check('Security route and sidebar state are correct','location.pathname==="/password" && document.querySelector(".sidebar-nav-item.active")?.dataset.nav==="password"');
    await check('Password fields expose labels and secure autocomplete','["pwCurrent","pwNew"].every(id=>document.querySelector(`label[for="${id}"]`)) && document.getElementById("pwCurrent").autocomplete==="current-password" && ["pwNew"].every(id=>document.getElementById(id).autocomplete==="new-password")');
    await evaluate('savePassword()');
    await check('Required validation is shown below every empty field','["pwCurrent","pwNew"].every(id=>document.getElementById(id).getAttribute("aria-invalid")==="true" && document.getElementById(id+"Error").textContent.length>0) && !__requests.some(r=>r.path==="/api/me/password")');
    await check('Password form has two masked inputs without confirmation or eye controls','document.querySelectorAll("#passwordForm input").length===2 && !document.getElementById("pwConfirm") && !document.querySelector("#passwordForm .password-toggle") && ["pwCurrent","pwNew"].every(id=>document.getElementById(id).type==="password")');
    await evaluate('document.getElementById("pwCurrent").value="current-password";document.getElementById("pwNew").value="new-password";savePassword()');
    await check('Simplified form retains the existing password API contract','JSON.parse(__requests.find(r=>r.path==="/api/me/password").body).new_password_confirmation==="new-password"');
    await check('API validation is mapped below the current-password field without clearing input','document.getElementById("pwCurrentError").textContent.includes("Synthetic incorrect password") && document.getElementById("pwCurrent").value==="current-password" && !document.getElementById("btnSavePw").disabled');
    await evaluate('__passwordFails="network";savePassword()');
    await check('Network failure retains entered secrets and reports retry state','["pwCurrent","pwNew"].every(id=>document.getElementById(id).value.length>0) && document.getElementById("pwErrorMsg").textContent.includes("giữ lại")');
    await evaluate('window.__passwordRequests=__requests.filter(r=>r.path==="/api/me/password").length;__passwordFails="deferred";window.__passwordWork=savePassword();savePassword();undefined');
    await check('Pending password update blocks duplicate submission','__requests.filter(r=>r.path==="/api/me/password").length===__passwordRequests+1 && document.getElementById("btnSavePw").disabled && document.getElementById("btnSavePw").getAttribute("aria-busy")==="true"');
    await evaluate('__releasePassword();__passwordWork');
    await check('Successful update clears secrets and restores submit state','["pwCurrent","pwNew"].every(id=>document.getElementById(id).value==="") && document.getElementById("pwSuccess").classList.contains("show") && !document.getElementById("btnSavePw").disabled');
    await check('Unsupported security features are not presented as active','!["2FA","Thiết bị đăng nhập","Đã bật"].some(label=>document.body.textContent.includes(label)) && document.body.textContent.includes("Quên mật khẩu")');
    for(const width of [320,375,768,1024,1440]) {
      await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<768});await new Promise(resolve=>setTimeout(resolve,100));
      await check('Security page fits '+width+'px','document.documentElement.scrollWidth<=document.documentElement.clientWidth && document.querySelector(".security-grid").scrollWidth<=document.querySelector(".security-grid").clientWidth');
    }
    assert.equal(errors.length,0,'No Security browser script errors');
    return;
  }
  await go('profile');await wait('document.getElementById("stat-bookings").textContent==="3"');
  await check('DOB remains visible before migration with an honest disabled state','document.getElementById("profileName").value==="Tài khoản kiểm thử" && getComputedStyle(document.getElementById("profileDobField")).display!=="none" && document.getElementById("profileDob").disabled && !document.getElementById("profileDobHint") && document.getElementById("profileDob").value===""');
  await evaluate('saveProfile()');
  await check('Unsupported DOB is omitted from update payload','!JSON.parse(__requests.find(r=>r.method==="PUT").body).hasOwnProperty("dob")');
  await evaluate('localStorage.setItem("__syntheticDobSupport","true");__dobSupported=true;loadProfile()');
  await check('DOB is editable after schema support without a fabricated default','!document.getElementById("profileDob").disabled && !document.getElementById("profileDobHint") && document.getElementById("profileDob").value===""');
  await evaluate('document.getElementById("profileDob").value="2000-02-29";saveProfile()');
  await check('DOB saves the exact calendar date','JSON.parse(__requests.filter(r=>r.method==="PUT").at(-1).body).dob==="2000-02-29" && __dobValue==="2000-02-29"');
  await go('profile');await wait('document.getElementById("profileDob").value==="2000-02-29"');
  await check('Full profile reload restores DOB without timezone conversion','!document.getElementById("profileDob").disabled && document.getElementById("profileDob").value==="2000-02-29"');
  await evaluate('__profileSaveFails=true;document.getElementById("profileDob").value="1990-01-01";saveProfile()');
  await check('Rejected profile update does not persist DOB or report success','__dobValue==="2000-02-29" && document.getElementById("profileErrorMsg").textContent.includes("Synthetic profile failure")');
  await evaluate('__profileSaveFails=false;loadProfile()');
  await evaluate('window.__writesBeforeFuture=__requests.filter(r=>r.method==="PUT").length;document.getElementById("profileDob").value="2030-01-01";saveProfile()');
  await check('Future DOB is rejected before submitting','__requests.filter(r=>r.method==="PUT").length===__writesBeforeFuture && document.getElementById("profileErrorMsg").textContent.includes("Ngày sinh")');
  await evaluate('document.getElementById("profileDob").value="";saveProfile()');
  await go('profile');await wait('document.getElementById("stat-bookings").textContent==="3"');
  await check('Clearing DOB reloads as empty without a replacement date','document.getElementById("profileDob").value==="" && __dobValue===null');
  await check('No fake loyalty, activity, payment cards','!["Thành viên Bạc","Visa ****","MoMo ****","Điểm thưởng"].some(s=>document.body.textContent.includes(s)) && !document.getElementById("activityList")');
  if(process.env.AUREL_PROFILE_ONLY==='1') {
    await evaluate('window.__avatarBefore=localStorage.getItem("user");__avatarFails="deferred";window.__avatarBytes=Uint8Array.from(atob("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII="),c=>c.charCodeAt(0));window.__avatarWork=handleAvatarUpload({files:[new File([__avatarBytes],"synthetic.png",{type:"image/png"})],value:""});undefined');
    await check('Avatar displays a temporary preview while upload is pending','document.getElementById("profileAvatarImg").src.startsWith("blob:") && document.getElementById("avatarUploadButton").getAttribute("aria-busy")==="true" && document.getElementById("profileAvatarStatus").textContent.includes("Đang tải")');
    await evaluate('__releaseAvatar();__avatarWork');
    await check('Rejected avatar rolls back preview and never changes cached account','!document.getElementById("profileAvatarImg").src.startsWith("blob:") && localStorage.getItem("user")===__avatarBefore && document.getElementById("profileAvatarStatus").textContent.includes("hoàn tác") && !document.getElementById("avatarUploadButton").disabled');
    await evaluate('__avatarFails=false;window.__avatarWork=handleAvatarUpload({files:[new File(["synthetic"],"synthetic.png",{type:"image/png"})],value:""});__avatarWork');
    await check('Confirmed avatar replaces preview and updates cached account','JSON.parse(localStorage.getItem("user")).avatar_url==="/image/placeholders/accommodation.svg" && document.getElementById("profileAvatarStatus").textContent.includes("đã được cập nhật")');
    await check('Profile account summary contains only avatar name and email','!!document.getElementById("profileAccountName") && !!document.getElementById("profileAccountEmail") && !document.getElementById("heroPhone")');
    for(const width of [320,375,768,1024,1440]) {
      await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<768});await new Promise(resolve=>setTimeout(resolve,100));
      await check('Profile fits '+width+'px','document.documentElement.scrollWidth<=document.documentElement.clientWidth && document.getElementById("profileContent").scrollWidth<=document.getElementById("profileContent").clientWidth');
    }
    assert.equal(errors.length,0,'No Profile browser script errors');
    return;
  }
  for(const mode of ['network','server','missing']) {
    await evaluate(`__logoutMode=${JSON.stringify(mode)};doLogout()`);
    await check('Logout '+mode+' retains token and reports unconfirmed','localStorage.getItem("token")==="synthetic-customer-token" && document.getElementById("toastContainer").textContent.includes("Chưa xác nhận đăng xuất")');
  }
  await evaluate('__logoutMode="deferred";window.__logoutWork=doLogout();doLogout()');
  await check('Only one logout request is pending','document.querySelector(".sidebar-logout").disabled && __requests.filter(r=>r.path==="/api/logout").length===4');
  await evaluate('localStorage.setItem("token","new-customer-session");__releaseLogout();__logoutWork');
  await check('Late acknowledgement does not erase newer login','localStorage.getItem("token")==="new-customer-session" && location.pathname==="/profile"');
  await evaluate('__logoutMode="success";doLogout()');await wait('location.pathname==="/login"');
  await check('Acknowledged logout clears only Customer session','localStorage.getItem("token")===null && localStorage.getItem("user")===null && localStorage.getItem("admin_token")==="synthetic-admin-token"');
  await go('bookings');await wait('!!document.querySelector(".booking-card")');
  await evaluate('document.querySelector("[data-action=detail]").click()');await wait('document.getElementById("detailContent").textContent.includes("Chưa xác định")');
  await check('V2 detail unwraps booking; pending unknown is never cash','document.getElementById("detailContent").textContent.includes("Chờ thanh toán") && !document.getElementById("detailContent").textContent.includes("Tiền mặt") && !document.querySelector("[data-action=invoice]")');
  await evaluate('__detailEnvelope=false;__booking.payment={payment_method:"vnpay",payment_status:"success"};__booking.invoice={id:8,invoice_no:"SYNTHETIC-8"};document.querySelector("[data-action=detail]").click()');
  await wait('document.getElementById("detailContent").textContent.includes("VNPay")');
  await check('Legacy detail preserves payment and issued invoice','document.getElementById("detailContent").textContent.includes("Đã thanh toán") && document.getElementById("detailContent").textContent.includes("SYNTHETIC-8")');
  await go('itineraries');await wait('typeof renderTags==="function"');
  await evaluate('window.__xss=0;addInterestTag("<img src=x onerror=window.__xss=1>");showItineraryResult({id:"1 onclick=window.__xss=1",destination:"<svg onload=window.__xss=1>",total_days:"<img>",start_date:"<img src=x onerror=window.__xss=1>",days:[{day_number:1,items:[{title:"<img src=x onerror=window.__xss=1>",description:"Tiếng Việt\\nDòng hai",item_type:"<svg onload=window.__xss=1>",start_time:"<img>",end_time:"10:00"}]}]})');
  await check('AI tags and itinerary metadata cannot create executable markup','window.__xss===0 && !document.querySelector("#itineraryResult svg[onload],#itineraryResult img[onerror],#interestsTagsWrapper img") && document.getElementById("interestsTagsWrapper").textContent.includes("<img")');
  await evaluate('document.getElementById("chatInput").value="<img src=x onerror=window.__xss=1>";sendCskhMessage()');
  await check('AI chat renders user and provider text with line breaks','window.__xss===0 && !document.querySelector("#chatMessages img,#chatMessages svg") && document.getElementById("chatMessages").textContent.includes("Dòng tiếng Việt") && getComputedStyle(document.querySelector("#chatMessages .iti-chat-msg:last-child")).whiteSpace==="pre-wrap"');
  await evaluate('document.getElementById("toastContainer").replaceChildren();saveItinerary(1)');
  await check('Rejected itinerary save does not report success','document.getElementById("toastContainer").textContent.includes("Chưa lưu được") && !document.querySelector(".btn-save-iti").disabled');
  for(const page of ['profile','password','bookings','itineraries','settings']) {
    await go(page);
    await check('Sidebar active '+page,`document.querySelectorAll('.sidebar-nav-item.active').length===1 && document.querySelector('.sidebar-nav-item.active').getAttribute('href')===${JSON.stringify('/'+page)}`);
    for(const width of [1440,1024,768,375,320]) {
      await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<768});
      await new Promise(resolve=>setTimeout(resolve,350));
      await check(page+' fits '+width+'px','document.documentElement.scrollWidth<=document.documentElement.clientWidth');
    }
    if(page==='profile') {await evaluate('toggleSidebar()');await check('Mobile navigation can open','document.getElementById("sidebar").classList.contains("open")');}
  }
  await go('home-chat');await evaluate('window.__xss=0;addChatMessage("user","<img src=x onerror=window.__xss=1>");addChatMessage("bot","<svg onload=window.__xss=1>\\nTiếng Việt")');
  await check('Home chat treats both roles as plain text','window.__xss===0 && !document.querySelector("#chat-messages img,#chat-messages svg") && document.getElementById("chat-messages").textContent.includes("<svg")');

  await send('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false});
  await go('profile');await wait('!document.getElementById("profileContent").hidden');
  await evaluate('__profileLoadFails=true;loadProfile()');
  await check('Profile load failure hides stale form and offers retry','document.getElementById("profileContent").hidden && document.getElementById("btnSaveProfile").disabled && document.getElementById("profileLoadState").textContent.includes("Thử lại")');
  await shot('profile-load-error-1440');
  await evaluate('__profileLoadFails=false;document.querySelector("#profileLoadState button").click()');await wait('!document.getElementById("profileContent").hidden');
  await check('Profile retry restores the real API values','document.getElementById("profileName").value==="Tài khoản kiểm thử" && !document.getElementById("btnSaveProfile").disabled');
  await evaluate('window.__beforeUpload=localStorage.getItem("user");const file=new File([new Uint8Array([137,80,78,71])],"synthetic.png",{type:"image/png"});handleAvatarUpload({files:[file],value:""})');
  await check('Rejected avatar upload never persists an unconfirmed preview','localStorage.getItem("user")===__beforeUpload && !document.getElementById("avatarUploadButton").disabled && document.getElementById("toastContainer").textContent.includes("Synthetic avatar rejected")');
  await evaluate('__avatarFails=false;handleAvatarUpload({files:[new File(["test"],"synthetic.png",{type:"image/png"})],value:""})');
  await check('Avatar success keeps existing upload API and updates confirmed URL','JSON.parse(localStorage.getItem("user")).avatar_url==="/image/placeholders/accommodation.svg" && __requests.filter(r=>r.path==="/api/me/avatar").length===2');
  await evaluate('window.__writesBeforeEmpty=__requests.filter(r=>r.method==="PUT").length;document.getElementById("profileName").value="";document.getElementById("btnSaveProfile").click()');
  await check('Profile native form validation refuses an empty name','__requests.filter(r=>r.method==="PUT").length===__writesBeforeEmpty && !document.getElementById("profileName").validity.valid');
  await go('password');
  await evaluate('savePassword()');
  await check('Password required fields use the shared inline error without submitting','document.getElementById("pwError").classList.contains("show") && !__requests.some(r=>r.path==="/api/me/password")');
  await evaluate('document.getElementById("pwCurrent").value="current-password";document.getElementById("pwNew").value="new-password";savePassword()');
  await check('Password server validation displays the field error and restores submit','document.getElementById("pwErrorMsg").textContent.includes("Synthetic incorrect password") && !document.getElementById("btnSavePw").disabled');
  await evaluate('__passwordFails=false;savePassword()');
  await check('Password success clears secrets but retains current and Admin tokens','["pwCurrent","pwNew"].every(id=>document.getElementById(id).value==="") && document.getElementById("pwSuccess").classList.contains("show") && localStorage.getItem("token")==="synthetic-customer-token" && localStorage.getItem("admin_token")==="synthetic-admin-token"');
  await check('Password inputs remain masked without eye controls','!document.querySelector("#passwordForm .password-toggle") && ["pwCurrent","pwNew"].every(id=>document.getElementById(id).type==="password")');
  await go('profile#password');await wait('location.pathname==="/password"');
  await check('Legacy profile password hash redirects to the security page','document.querySelector(".sidebar-nav-item.active").dataset.nav==="password"');
  await go('settings');await evaluate('document.getElementById("accountMotion").value="reduced";document.getElementById("accountMotion").dispatchEvent(new Event("change"))');
  await check('Settings reduced motion is applied and stored only in this browser','localStorage.getItem("aurel_account_motion")==="reduced" && document.documentElement.dataset.accountMotion==="reduced" && !__requests.length && document.getElementById("motionStatus").textContent.includes("Đã lưu")');
  await go('profile');await check('Reduced motion survives navigation','document.documentElement.dataset.accountMotion==="reduced"');
  await go('settings');await check('Settings reload restores selected preference','document.getElementById("accountMotion").value==="reduced"');
  await evaluate('window.__originalSet=Storage.prototype.setItem;Storage.prototype.setItem=function(){throw new Error("Synthetic storage denied")};document.getElementById("accountMotion").value="system";document.getElementById("accountMotion").dispatchEvent(new Event("change"));Storage.prototype.setItem=__originalSet');
  await check('Settings storage failure does not claim persistence','document.getElementById("motionStatus").textContent.includes("không cho phép lưu") && document.documentElement.dataset.accountMotion==="system" && localStorage.getItem("aurel_account_motion")==="reduced"');
  await evaluate('document.getElementById("accountMotion").dispatchEvent(new Event("change"))');
  await go('bookings');await wait('!!document.querySelector(".booking-card")');

  await evaluate('__deferBookingStatus="pending";document.querySelector("[data-tab=pending]").click();document.querySelector("[data-tab=confirmed]").click()');await wait('!!document.querySelector(".booking-card")');
  await evaluate('__releaseBookingRequest();__deferBookingStatus=null');await new Promise(r=>setTimeout(r,100));
  await check('Fast Booking filters discard an older response','document.querySelector(".tab-btn.active").dataset.tab==="confirmed" && !!document.querySelector(".booking-card")');
  await evaluate('window.__originalCheckIn=__booking.check_in;__booking.check_in=new Date(Date.now()+3600000).toISOString();document.querySelector("[data-tab=all]").click()');await wait('!!document.querySelector(".booking-card")');
  await check('Cancel action is hidden inside the existing 24-hour cutoff','!document.querySelector("[data-action=cancel]")');
  await evaluate('__booking.check_in=null;document.querySelector("[data-tab=confirmed]").click()');await wait('!!document.querySelector(".booking-card")');
  await check('Missing stay date does not imply cancellation eligibility','!document.querySelector("[data-action=cancel]")');
  await evaluate('__booking.check_in=__originalCheckIn;document.querySelector("[data-tab=all]").click()');await wait('!!document.querySelector("[data-action=cancel]")');
  await evaluate('document.querySelector("[data-action=cancel]").focus();document.querySelector("[data-action=cancel]").click()');
  await check('Booking cancel uses the accessible shared confirmation dialog','document.getElementById("confirmModal").classList.contains("open") && document.activeElement.id==="confirmModalCancelBtn" && document.getElementById("confirmModal").getAttribute("role")==="dialog"');
  await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Tab',code:'Tab',modifiers:8});await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Tab',code:'Tab',modifiers:8});
  await check('Confirmation focus is trapped within the dialog','document.activeElement.id==="confirmModalOkBtn"');
  await evaluate('document.getElementById("confirmModalOkBtn").click()');await wait('!document.getElementById("confirmModalOkBtn").disabled');
  await check('Rejected cancellation retains dialog and Booking state','document.getElementById("confirmModal").classList.contains("open") && __booking.status==="confirmed" && document.getElementById("toastContainer").textContent.includes("Synthetic cancellation rejected")');
  await shot('booking-cancel-error-fixture-1440');
  await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape'});
  await check('Escape closes confirmation and restores action focus','!document.getElementById("confirmModal").classList.contains("open") && document.activeElement.dataset.action==="cancel"');
  await evaluate('__booking.status="pending";__booking.payment={payment_method:"banking",payment_status:"pending"};__booking.banking_policy={confirmation_allowed:true,payment_deadline:"2099-01-01T00:00:00Z"};document.querySelector("[data-action=detail]").click()');await wait('document.getElementById("detailContent").textContent.includes("Chuyển khoản")');
  await check('Resume payment is hidden when actual payment page is unavailable','!document.querySelector("[data-action=resume]")');
  await evaluate('document.getElementById("bookingHistoryApp").dataset.paymentPage="true";document.querySelector("[data-action=detail]").click()');await wait('!!document.querySelector("[data-action=resume]")');
  await check('Eligible resume links only to the existing payment page without starting a charge','document.querySelector("[data-action=resume]").getAttribute("href")==="/payments/bookings/7" && !__requests.some(r=>/payment|vnpay|sepay/.test(r.path))');
  for(const mode of ['expired','reconciliation','success']) {
    await evaluate(`__booking.banking_policy.payment_deadline=${JSON.stringify(mode==='expired'?'2000-01-01T00:00:00Z':'2099-01-01T00:00:00Z')};__booking.payment.display_status=${JSON.stringify(mode==='expired'?'pending':mode)};document.querySelector('[data-action=detail]').click()`);await new Promise(r=>setTimeout(r,80));
    await check('Resume rejects '+mode,'!document.querySelector("[data-action=resume]")');
  }
  await evaluate('__booking.payment={payment_method:null,payment_status:"pending"};__booking.invoice={id:8,invoice_no:"SYNTHETIC-8"};document.querySelector("[data-action=detail]").click()');await wait('!!document.querySelector("#detailContent [data-action=invoice]")');
  await check('Invoice action exists only for an issued invoice','document.getElementById("detailContent").textContent.includes("SYNTHETIC-8") && !document.getElementById("detailContent").textContent.includes("Tiền mặt")');
  await evaluate('document.querySelector("#detailContent [data-action=invoice]").click()');await wait('__requests.some(r=>r.path==="/api/invoices/7/pdf")');
  await check('Invoice download uses the existing read-only endpoint','__requests.find(r=>r.path==="/api/invoices/7/pdf").method==="GET"');
  await evaluate('document.getElementById("toastContainer").replaceChildren();document.activeElement.blur()');
  for(const width of [320,375,768,1024,1440]) {
    await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<768});await new Promise(r=>setTimeout(r,100));
    await check('Populated Booking and detail fit '+width,'document.documentElement.scrollWidth<=innerWidth && document.getElementById("detailPanel").scrollHeight<=document.getElementById("detailPanel").clientHeight+1');
    await shot('bookings-detail-fixture-'+width);
  }
  await evaluate('__cancelFails=false;document.querySelector("[data-action=cancel]").click();document.getElementById("confirmModalOkBtn").click()');await wait('!document.getElementById("confirmModal").classList.contains("open")');
  await check('Confirmed cancellation updates the existing Booking list','__booking.status==="cancelled" && document.querySelector(".booking-card").textContent.includes("Đã huỷ")');
  await evaluate('__listFails=true;document.querySelector("[data-tab=pending]").click()');await wait('!!document.getElementById("retryBookings")');
  await check('Booking network failure offers retry and clears loading','document.getElementById("bookingList").getAttribute("aria-busy")==="false" && document.getElementById("retryBookings").textContent==="Thử lại"');
  await evaluate('__listFails=false;document.getElementById("retryBookings").click()');await wait('!document.getElementById("retryBookings")');
  await check('Filtered Booking empty state is explicit','document.getElementById("bookingList").textContent.includes("bộ lọc")');
  await evaluate('__pages=3;document.querySelector("[data-tab=all]").click()');await wait('!!document.querySelector(".page-link")');
  await evaluate('document.querySelector(".page-link[data-page=\\"2\\"]").click()');await wait('!!document.querySelector(".page-link[aria-current=page][data-page=\\"2\\"]")');
  await check('Booking pagination remains keyboard accessible','document.querySelector(".page-link[aria-current=page]").tagName==="BUTTON" && __requests.some(r=>r.path==="/api/bookings/my")');
  await evaluate('__booking.status="completed";document.querySelector("[data-tab=completed]").click()');await wait('!!document.querySelector("[data-action=review]")');
  await evaluate('document.querySelector("[data-action=review]").click()');
  await check('Existing review flow retains an accessible dialog','document.getElementById("reviewOverlay").getAttribute("aria-modal")==="true" && document.activeElement.id==="btnCancelReview"');
  await evaluate('document.querySelector("[data-star=\\"5\\"]").click();document.getElementById("reviewText").value="Đánh giá synthetic";document.getElementById("btnSubmitReview").click()');await wait('!document.getElementById("reviewOverlay")');
  await check('Existing review API is preserved with actual Booking and Hotel identifiers','JSON.parse(__requests.find(r=>r.path==="/api/reviews"&&r.method==="POST").body).booking_id===7 && !document.querySelector("[data-action=review]")');


  await go('itineraries');
  await evaluate('window.__xss=0;window.__fixtureItinerary={id:4,title:"Lịch trình kiểm thử <img src=x onerror=window.__xss=1>",destination:"Địa điểm kiểm thử",status:"saved",total_days:2,total_cost:250000,created_at:"2026-10-19",start_date:"2026-11-01",days:[{day_number:1,items:[{title:"Hoạt động kiểm thử",description:"Dữ liệu synthetic — chỉ phục vụ kiểm thử giao diện",item_type:"attraction",start_time:"08:30",end_time:"10:00",estimated_cost:250000}]},{day_number:2,items:[]}]};__itineraries=[__fixtureItinerary,{...__fixtureItinerary,id:5,title:"Bản nháp kiểm thử",status:"draft"}];loadItineraryList()');
  await check('Saved and draft itinerary list uses real API data with escaped titles','document.querySelectorAll(".iti-card").length===2 && document.querySelectorAll(".iti-card-badge.saved").length===1 && !document.querySelector(".iti-card img") && window.__xss===0');
  await evaluate('document.querySelector(".iti-filter[data-filter=saved]").click()');
  await check('AI saved filter changes the list and accessible active state','document.querySelectorAll(".iti-card").length===1 && document.querySelector(".iti-filter[data-filter=saved]").getAttribute("aria-pressed")==="true"');
  await evaluate('viewItinerary(4)');await wait('!!document.getElementById("dayContent")');
  await check('Saved itinerary timeline shows actual estimates without fake ratings','document.getElementById("itineraryResult").textContent.includes("250.000") && !document.querySelector(".btn-export-pdf,.cskh-header-dot") && document.querySelector(".btn-save-iti").disabled && window.__xss===0');
  await evaluate('document.getElementById("toastContainer").replaceChildren();document.activeElement.blur()');
  for(const width of [320,375,768,1024,1440]) {
    await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<768});await new Promise(r=>setTimeout(r,100));
    await check('Populated AI timeline fits '+width,'document.documentElement.scrollWidth<=innerWidth');await shot('itinerary-detail-fixture-'+width);
  }
  await evaluate('document.querySelector(".iti-day-tab[data-day=\\"2\\"]").click()');
  await check('Empty itinerary day is explicit and day tabs remain accessible','document.getElementById("dayContent").textContent.includes("Chưa có hoạt động") && document.querySelector(".iti-day-tab[data-day=\\"2\\"]").getAttribute("aria-pressed")==="true"');
  await evaluate('__deleteFails=true;deleteItinerary(4);document.getElementById("confirmModalOkBtn").click()');await wait('!document.getElementById("confirmModalOkBtn").disabled');
  await check('Rejected itinerary deletion keeps the record and confirmation','__itineraries.length===2 && document.getElementById("confirmModal").classList.contains("open") && document.getElementById("toastContainer").textContent.includes("Synthetic delete rejected")');
  await evaluate('__deleteFails=false;document.getElementById("confirmModalOkBtn").click()');await wait('!document.getElementById("confirmModal").classList.contains("open")');
  await check('Confirmed deletion hides the selected itinerary','__itineraries.length===1 && document.getElementById("itineraryResult").style.display==="none"');
  await evaluate('__itineraries=[{...__fixtureItinerary,id:4},{...__fixtureItinerary,id:5,status:"draft"}];loadItineraryList()');
  await evaluate('deleteAllDrafts();document.getElementById("confirmModalOkBtn").click()');await wait('!document.getElementById("confirmModal").classList.contains("open")');
  await check('Bulk delete only removes actual draft itineraries','__itineraries.length===1 && __itineraries[0].status==="saved" && !__requests.some(r=>r.path==="/api/itineraries/4"&&r.method==="DELETE"&&r.body)');
  await evaluate('openItineraryCreate();document.getElementById("itineraryDestination").value="Địa điểm kiểm thử";document.getElementById("itineraryStartDate").value="2026-11-01";document.getElementById("itineraryBudgetMax").value="3000000";addInterestTag("ẩm thực");document.getElementById("itineraryNotes").value="ghi chú kiểm thử";generateItinerary()');
  await check('AI generation error clears loading and never reports a result','!document.getElementById("generateBtn").disabled && document.getElementById("itineraryResult").getAttribute("aria-busy")==="false" && document.getElementById("itineraryResult").textContent.includes("Synthetic AI validation failure")');
  await check('AI form wires the existing start date, budget and notes contract','(()=>{const body=JSON.parse(__requests.find(r=>r.path==="/api/itineraries/generate").body);return body.start_date==="2026-11-01" && body.budget===3000000 && body.notes.includes("ẩm thực") && body.notes.includes("ghi chú kiểm thử")})()');
  await evaluate('__generationFails=false;__generated={...__fixtureItinerary,id:9,status:"draft"};generateItinerary()');await wait('!!document.querySelector(".btn-save-iti")');
  await evaluate('__itinerarySaveFails=false;saveItinerary(9)');
  await check('Real itinerary save integration reports success only after API acknowledgement','document.querySelector(".btn-save-iti").disabled && document.querySelector(".btn-save-iti").textContent.includes("Đã lưu") && __requests.some(r=>r.path==="/api/itineraries/9" && r.method==="PUT" && JSON.parse(r.body).status==="saved")');
  await evaluate('__listFails=true;loadItineraryList()');
  await check('AI list network error offers retry without fake empty success','document.getElementById("itineraryListContainer").textContent.includes("Chưa thể tải lịch trình") && document.getElementById("itineraryListContainer").getAttribute("aria-busy")==="false"');
  await evaluate('sessionStorage.setItem("__empty","true");localStorage.removeItem("__syntheticDobSupport");localStorage.removeItem("__syntheticDobValue")');
  for(const page of ['profile','bookings','itineraries','password','settings']) {
    await go(page);await evaluate('document.fonts.ready');
    if(page==='profile')await wait('!document.getElementById("profileContent").hidden');
    if(page==='bookings')await wait('!!document.querySelector("#bookingList .empty-state")');
    if(page==='itineraries')await wait('!!document.querySelector("#itineraryListContainer .empty-state")');
    for(const width of [320,375,768,1024,1440]) {
      await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<768});await new Promise(r=>setTimeout(r,100));
      await check('Preview '+page+' fits '+width,'document.documentElement.scrollWidth<=innerWidth && getComputedStyle(document.body).backgroundColor==="rgb(248, 248, 248)"');
      await check('Navigation breakpoint '+page+' '+width,`innerWidth<1024 ? document.getElementById('sidebar').inert && getComputedStyle(document.getElementById('accountMenuToggle')).display!=='none' : !document.getElementById('sidebar').inert && getComputedStyle(document.getElementById('accountMenuToggle')).display==='none'`);
      await shot(page+'-'+width);
    }
  }
  for(const width of [320,375,768]) {
    await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<768});
    await new Promise(resolve=>setTimeout(resolve,250));
    await evaluate('document.getElementById("accountMenuToggle").focus();document.getElementById("accountMenuToggle").click()');
    await check('Mobile sidebar open and focus '+width,'document.getElementById("accountMenuToggle").getAttribute("aria-expanded")==="true" && !document.getElementById("sidebar").inert && document.activeElement.id==="accountMenuClose" && document.getElementById("sidebar").getBoundingClientRect().right<=innerWidth');
    await shot('sidebar-'+width);
    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape'});
    await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape'});
    await wait('document.getElementById("sidebar").inert && document.activeElement.id==="accountMenuToggle"');
    await check('Mobile sidebar Escape and focus restore '+width,'document.getElementById("sidebar").inert && !document.body.classList.contains("sidebar-open") && document.activeElement.id==="accountMenuToggle"');
  }
  await evaluate('toggleSidebar()');await send('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false});await new Promise(r=>setTimeout(r,150));
  await check('Tablet to desktop resize resets drawer and body scroll','!document.getElementById("sidebar").inert && !document.body.classList.contains("sidebar-open")');
  await check('Vietnamese local font and account stylesheet load successfully','document.fonts.check("12px \\\"Be Vietnam Pro\\\"", "Thông tin cá nhân") && performance.getEntriesByType("resource").some(r=>r.name.includes("aurel-account.css")&&r.transferSize>0) && performance.getEntriesByType("resource").some(r=>r.name.endsWith(".woff2"))');

  await go('itineraries');await wait('!!document.querySelector("#itineraryListContainer .empty-state")');await evaluate('openItineraryCreate();document.getElementById("toggleAdvancedBtn").click()');
  await check('AI additional requirements expand with accessible state','document.getElementById("itineraryCreate").open && document.getElementById("toggleAdvancedBtn").getAttribute("aria-expanded")==="true" && document.getElementById("advancedFields").classList.contains("show")');
  for(const width of [320,375,768,1024,1440]) {
    await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<768});await new Promise(r=>setTimeout(r,100));
    await check('Expanded AI form fits '+width,'document.documentElement.scrollWidth<=innerWidth && document.getElementById("itineraryForm").scrollWidth<=document.getElementById("itineraryForm").clientWidth');await shot('itinerary-form-'+width);
    await evaluate('showConfirmModal("Xác nhận kiểm thử", "Nội dung tiếng Việt chỉ phục vụ kiểm thử giao diện.",()=>false,{confirmText:"Xác nhận"})');
    await check('Modal fits '+width,'(()=>{const rect=document.querySelector("#confirmModal .modal-box").getBoundingClientRect();return rect.left>=0 && rect.right<=innerWidth && rect.top>=0 && rect.bottom<=innerHeight})()');
    await shot('modal-'+width);await evaluate('closeConfirmModal()');
  }
  await evaluate('document.getElementById("itineraryDestination").value="Địa điểm kiểm thử";document.getElementById("itineraryBudgetMax").value="0";generateItinerary()');
  await check('Explicit zero budget is preserved instead of replaced by a default','JSON.parse(__requests.find(r=>r.path==="/api/itineraries/generate").body).budget===0');
  await evaluate('window.__xss=0;showToast("<img src=x onerror=window.__xss=1>","error")');
  await check('Shared toast preserves untrusted messages as plain text','window.__xss===0 && !document.querySelector("#toastContainer img") && document.getElementById("toastContainer").textContent.includes("<img")');

  const gallery='<!doctype html><html lang="vi"><meta charset="utf-8"><title>AUREL · Duyệt Profile giai đoạn 2</title><style>body{font:15px/1.7 system-ui;margin:40px auto;max-width:1160px;padding:0 20px;background:#f8f8f8;color:#193448}main{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:24px}article{background:white;border:1px solid #e5e8eb;border-radius:12px;padding:16px}img{width:100%;height:300px;object-fit:contain;object-position:top;background:#f8f8f8}a{color:inherit}h1{font-size:28px}h2{font-size:14px}</style><h1>AUREL · Profile / Customer Account</h1><p>Giai đoạn 2 — ảnh chụp Chrome tại 320 / 375 / 768 / 1024 / 1440px. Tài khoản kiểm thử và phản hồi API hoàn toàn synthetic, không truy cập dữ liệu khách hàng thật. Ảnh có chữ fixture dùng để kiểm chứng dữ liệu có nội dung; các ảnh còn lại dùng trạng thái trống trung thực.</p><main>'+screenshots.map(file=>'<article><h2>'+file.replace('screenshots/','').replace('.png','')+'</h2><a href="'+file+'"><img loading="lazy" src="'+file+'" alt="'+file+'"></a><a href="'+file+'">Xem ảnh đầy đủ</a></article>').join('')+'</main></html>';
  fs.writeFileSync(path.join(runtime,'index.html'),gallery);

  assert.equal(errors.length,0,'No browser script errors');
})().catch(error=>{errors.push(error.stack);process.exitCode=1;}).finally(async()=>{
  const resultsFile=process.env.AUREL_PROFILE_ONLY==='1'?'profile-browser-results.json':process.env.AUREL_PASSWORD_ONLY==='1'?'password-browser-results.json':process.env.AUREL_BOOKINGS_ONLY==='1'?'bookings-browser-results.json':process.env.AUREL_SETTINGS_ONLY==='1'?'settings-browser-results.json':'browser-results.json';
  fs.writeFileSync(path.join(runtime,resultsFile),JSON.stringify({synthetic_only:true,all_fetch_mocked:true,external_requests_blocked:true,screenshots,checks,errors},null,2));
  console.log(JSON.stringify({screenshots:screenshots.length,checks:checks.length,passed:checks.filter(c=>c.passed).length,errors}));
  if(ws?.readyState===1){try{await send('Browser.close',{},null);}catch{} ws.close();}
  if(chrome&&!chrome.killed)chrome.kill();server.close();
});
