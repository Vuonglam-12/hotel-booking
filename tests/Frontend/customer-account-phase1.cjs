/* Local static Blade previews + synthetic fetch responses. No Laravel server or database. */
const fs = require('node:fs');
const path = require('node:path');
const http = require('node:http');
const assert = require('node:assert/strict');
const {spawn} = require('node:child_process');
const root = path.resolve(__dirname, '../..');
const runtime = path.join(root, 'storage/app/private/customer-account-phase1-completion');
const profile = path.join(runtime, 'browser-profile');
const checks = [], errors = [];
const server = http.createServer((req, res) => {
  const url = new URL(req.url, 'http://localhost');
  if (['/profile','/password','/bookings','/itineraries','/settings','/home-chat'].includes(url.pathname)) {
    res.setHeader('Content-Type','text/html; charset=utf-8');
    return res.end(fs.readFileSync(path.join(runtime,'preview',url.pathname.slice(1)+'.html')));
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
    localStorage.setItem('user',JSON.stringify({id:1,name:'Khách kiểm thử',email:'synthetic@example.test',role:'user'}));
    localStorage.setItem('admin_token','synthetic-admin-token');
  }
  if(!sessionStorage.getItem('__completionReady')) {
    localStorage.removeItem('__syntheticDobValue');localStorage.removeItem('__syntheticDobSupport');
    sessionStorage.setItem('__completionReady','true');
  }
  window.__requests=[];window.__logoutMode='network';window.__detailEnvelope=true;
  window.__dobSupported=localStorage.getItem('__syntheticDobSupport')==='true';
  window.__dobValue=JSON.parse(localStorage.getItem('__syntheticDobValue')||'null');window.__profileSaveFails=false;
  window.__booking={id:7,hotel_id:1,status:'confirmed',check_in:'2026-10-22',check_out:'2026-10-24',num_guests:4,total_price:'2000000.00',hotel:{name:'Khách sạn kiểm thử',images:[]},booking_rooms:[{quantity:2,nights:2,room_type:{name:'Phòng kiểm thử'}}],payment:{payment_method:null,payment_status:'pending'},invoice:null};
  window.fetch=async (input,opts={})=>{
    const url=new URL(String(input),location.origin); __requests.push({path:url.pathname,method:opts.method||'GET',body:opts.body});
    const response=(data,status=200)=>new Response(JSON.stringify(data),{status,headers:{'Content-Type':'application/json'}});
    if(url.pathname==='/api/logout') {
      if(__logoutMode==='network')throw new TypeError('Synthetic offline');
      if(__logoutMode==='server')return response({message:'Synthetic failure'},503);
      if(__logoutMode==='missing')return response({message:'Unconfirmed'});
      if(__logoutMode==='deferred')return new Promise(resolve=>window.__releaseLogout=()=>resolve(response({revoked:true})));
      return response({revoked:true});
    }
    if(url.pathname==='/api/me') {
      if(opts.method==='PUT') {
        if(__profileSaveFails)return response({message:'Synthetic profile failure'},503);
        const fields=JSON.parse(opts.body);
        if(Object.hasOwn(fields,'dob')){__dobValue=fields.dob||null;localStorage.setItem('__syntheticDobValue',JSON.stringify(__dobValue));}
      }
      return response({id:1,name:'Khách kiểm thử',email:'synthetic@example.test',phone:'0900000001',bookings:3,dob:__dobValue,profile_fields:{dob:__dobSupported},created_at:'2026-10-01',user:{name:'Khách kiểm thử',dob:__dobValue}});
    }
    if(url.pathname==='/api/bookings/my')return response({data:url.searchParams.has('status')?[]:[__booking],total:url.searchParams.has('status')?0:1,per_page:10,current_page:1,last_page:1,from:1,to:1});
    if(url.pathname==='/api/bookings/7')return response(__detailEnvelope?{booking:__booking}:__booking);
    if(url.pathname==='/api/itineraries')return response([]);
    if(url.pathname.startsWith('/api/itineraries/'))return response({message:'Synthetic save failure'},422);
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
  await go('profile');await wait('document.getElementById("stat-bookings").textContent==="3"');
  await check('DOB remains visible before migration with an honest disabled state','document.getElementById("profileName").value==="Khách kiểm thử" && getComputedStyle(document.getElementById("profileDobField")).display!=="none" && document.getElementById("profileDob").disabled && !document.getElementById("profileDobHint").hidden && document.getElementById("profileDob").value===""');
  await evaluate('saveProfile()');
  await check('Unsupported DOB is omitted from update payload','!JSON.parse(__requests.find(r=>r.method==="PUT").body).hasOwnProperty("dob")');
  await evaluate('localStorage.setItem("__syntheticDobSupport","true");__dobSupported=true;loadProfile()');
  await check('DOB is editable after schema support without a fabricated default','!document.getElementById("profileDob").disabled && document.getElementById("profileDobHint").hidden && document.getElementById("profileDob").value===""');
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
    for(const width of [1440,768,390,320]) {
      await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<768});
      await new Promise(resolve=>setTimeout(resolve,350));
      await check(page+' fits '+width+'px','document.documentElement.scrollWidth<=document.documentElement.clientWidth');
    }
    if(page==='profile') {await evaluate('toggleSidebar()');await check('Mobile navigation can open','document.getElementById("sidebar").classList.contains("open")');}
  }
  await go('home-chat');await evaluate('window.__xss=0;addChatMessage("user","<img src=x onerror=window.__xss=1>");addChatMessage("bot","<svg onload=window.__xss=1>\\nTiếng Việt")');
  await check('Home chat treats both roles as plain text','window.__xss===0 && !document.querySelector("#chat-messages img,#chat-messages svg") && document.getElementById("chat-messages").textContent.includes("<svg")');
  assert.equal(errors.length,0,'No browser script errors');
})().catch(error=>{errors.push(error.stack);process.exitCode=1;}).finally(async()=>{
  fs.writeFileSync(path.join(runtime,'browser-results.json'),JSON.stringify({synthetic_only:true,all_fetch_mocked:true,external_requests_blocked:true,checks,errors},null,2));
  console.log(JSON.stringify({checks:checks.length,passed:checks.filter(c=>c.passed).length,errors}));
  if(ws?.readyState===1){try{await send('Browser.close',{},null);}catch{} ws.close();}
  if(chrome&&!chrome.killed)chrome.kill();server.close();
});
