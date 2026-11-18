// Capture only. No price edits, bookings, payment simulation or functional test suite.
const fs=require('node:fs'),path=require('node:path'),{spawn}=require('node:child_process');
const d=JSON.parse(fs.readFileSync(process.argv[2],'utf8').replace(/^\uFEFF/,''));
if(d.read_only!==true||!/^http:\/\/127\.0\.0\.1:\d+$/.test(d.origin))throw Error('Read-only loopback descriptor required');
const out=path.resolve('artifacts/surcharge-20261101');fs.mkdirSync(out,{recursive:true});
let chrome,session,sequence=0,buffer='';const pending=new Map();
function send(method,params={},target=session){return new Promise((resolve,reject)=>{const id=++sequence,timer=setTimeout(()=>{pending.delete(id);reject(Error(method+' timed out'));},25000);pending.set(id,{resolve,reject,timer});chrome.stdio[3].write(JSON.stringify({id,method,params,...(target?{sessionId:target}:{})})+'\0');});}
async function js(expression){const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true,userGesture:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;}
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
async function wait(expression){const until=Date.now()+30000;while(Date.now()<until){if(await js(expression))return;await delay(100);}throw Error('Page did not finish rendering');}
async function go(url){await send('Page.navigate',{url:d.origin+url});await wait('document.readyState==="complete"&&location.href==='+JSON.stringify(d.origin+url));}
async function shot(name,selector){await js('document.activeElement?.blur();document.querySelector('+JSON.stringify(selector)+').scrollIntoView({block:"start"});scrollBy(0,-100)');await delay(250);const clip=await js('(()=>{const r=document.querySelector('+JSON.stringify(selector)+').getBoundingClientRect();return{x:Math.max(0,r.x+scrollX-10),y:Math.max(0,r.y+scrollY-10),width:r.width+20,height:r.height+20,scale:1}})()');const r=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:true,clip});fs.writeFileSync(path.join(out,name),Buffer.from(r.data,'base64'));}
(async()=>{
    chrome=spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',['--headless=new','--no-first-run','--disable-background-networking','--disable-component-update','--disable-sync','--disable-extensions','--disable-gpu','--remote-debugging-pipe','--user-data-dir='+path.join(d.runtime,'capture-'+Date.now())],{windowsHide:true,stdio:['ignore','ignore','pipe','pipe','pipe']});
    chrome.stdio[4].setEncoding('utf8');chrome.stdio[4].on('data',chunk=>{buffer+=chunk;let end;while((end=buffer.indexOf('\0'))>=0){const m=JSON.parse(buffer.slice(0,end));buffer=buffer.slice(end+1);const op=pending.get(m.id);if(op){pending.delete(m.id);clearTimeout(op.timer);m.error?op.reject(Error(m.error.message)):op.resolve(m.result);}else if(m.method==='Fetch.requestPaused'){const u=new URL(m.params.request.url),allow=u.origin===d.origin||['data:','blob:'].includes(u.protocol);send(allow?'Fetch.continueRequest':'Fetch.failRequest',allow?{requestId:m.params.requestId}:{requestId:m.params.requestId,errorReason:'BlockedByClient'}).catch(()=>{});}}});
    const target=await send('Target.createTarget',{url:'about:blank'},null);session=(await send('Target.attachToTarget',{targetId:target.targetId,flatten:true},null)).sessionId;
    await send('Page.enable');await send('Runtime.enable');await send('Fetch.enable',{patterns:[{urlPattern:'*'}]});
    await send('Page.addScriptToEvaluateOnNewDocument',{source:'if(location.origin==='+JSON.stringify(d.origin)+')localStorage.setItem("admin_token","readonly-browser-review")'});
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1050,deviceScaleFactor:1,mobile:false});
    await go('/admin/hotels/61');await wait('!!document.querySelector("#tabRoomTypes")&&!document.getElementById("hotelDetailHeader").hidden');
    await js('document.getElementById("tabRoomTypes").click()');await wait('!!document.querySelector("[data-edit-room=\\\"64\\\"]")');
    await js('document.querySelector("[data-edit-room=\\\"64\\\"]").click()');await wait('!document.getElementById("rtRateEditor").hidden&&document.getElementById("rtRateRows").textContent.includes("100.000")');
    await shot('admin-surcharge.png','#rtRateEditor');
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:1000,deviceScaleFactor:1,mobile:true});
    await go('/hotels/61?room_type_id=64&check_in=2026-10-31&check_out=2026-11-01&quantity=1');
    await wait('!!window.HolidayVietDetailPricing?.getQuote(64)&&!document.getElementById("price-estimate").hidden');
    await shot('customer-surcharge.png','#price-estimate');
    process.stdout.write(JSON.stringify({capture_only:true,historical_date:'2026-10-31',files:['admin-surcharge.png','customer-surcharge.png']})+'\n');
})().catch(e=>{process.stderr.write(e.message+'\n');process.exitCode=1;}).finally(async()=>{try{await send('Browser.close',{},null);}catch{}for(const op of pending.values())clearTimeout(op.timer);chrome?.kill();});
