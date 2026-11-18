// Real Laravel + owned synthetic MySQL. GET-only stage; no login, booking writes or email.
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict'),{spawn}=require('node:child_process');
const root=process.cwd(),stage=JSON.parse(fs.readFileSync(path.join(root,'storage/logs/hotel-search-http.json'),'utf8'));
assert.equal(stage.owned_test,true);const origin=stage.origin,runtime=path.join(stage.runtime,'search-browser');fs.mkdirSync(runtime,{recursive:true});
let chrome,session,buffer='',seq=0,failSuggestion=false;const pending=new Map(),checks=[],errors=[],requests=[];
const timer=setTimeout(()=>{chrome?.kill();process.exit(1);},120000);
const pause=ms=>new Promise(resolve=>setTimeout(resolve,ms));
const send=(method,params={},sid=session)=>new Promise((resolve,reject)=>{const id=++seq,t=setTimeout(()=>{pending.delete(id);reject(Error(method+' timeout'));},12000);pending.set(id,{resolve,reject,t});chrome.stdio[3].write(JSON.stringify({id,method,params,...(sid?{sessionId:sid}:{})})+'\0');});
const js=async expression=>{const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true,userGesture:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;};
const wait=async expression=>{for(let i=0;i<100;i++){if(await js(expression))return;await pause(100);}const debug=await js('({path:location.pathname,query:location.search,input:document.getElementById("searchInput")?.value,expanded:document.getElementById("searchInput")?.getAttribute("aria-expanded"),dropdown:document.getElementById("searchDropdown")?.innerText,hidden:document.getElementById("searchDropdown")?.className,active:document.activeElement?.id})');throw Error('Wait '+expression+' '+JSON.stringify(debug));};
const click=selector=>js('document.querySelector('+JSON.stringify(selector)+').click()');
const check=async(name,expression)=>{assert.equal(await js(expression),true,name);checks.push(name);};
const type=async value=>{await js('document.getElementById("searchInput").focus();document.getElementById("searchInput").value='+JSON.stringify(value)+';document.getElementById("searchInput").dispatchEvent(new Event("input",{bubbles:true}))');};
async function go(url){await js('window.__leaving=true');await send('Page.navigate',{url:origin+url});await wait('!window.__leaving && document.readyState==="complete" && !!window.HolidayVietSearch');}
(async()=>{
 chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-background-networking','--disable-sync','--disable-extensions','--remote-debugging-pipe','--user-data-dir='+path.join(runtime,'chrome-'+Date.now())],{windowsHide:true,stdio:['ignore','ignore','ignore','pipe','pipe']});
 chrome.stdio[4].setEncoding('utf8');chrome.stdio[4].on('data',chunk=>{buffer+=chunk;let i;while((i=buffer.indexOf('\0'))!==-1){const m=JSON.parse(buffer.slice(0,i));buffer=buffer.slice(i+1);const p=pending.get(m.id);if(p){pending.delete(m.id);clearTimeout(p.t);m.error?p.reject(Error(m.error.message)):p.resolve(m.result);}else if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description||m.params.exceptionDetails.text);else if(m.method==='Fetch.requestPaused'){
  const u=new URL(m.params.request.url);const allowed=m.params.request.method==='GET'&&u.origin===origin;requests.push({path:u.pathname,method:m.params.request.method,allowed});
  if(allowed&&u.pathname==='/api/hotels/suggestions'&&failSuggestion){failSuggestion=false;send('Fetch.fulfillRequest',{requestId:m.params.requestId,responseCode:503,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:Buffer.from('{"message":"Temporary test failure"}').toString('base64')},m.sessionId).catch(()=>{});}
  else send(allowed?'Fetch.continueRequest':'Fetch.failRequest',{requestId:m.params.requestId,...(allowed?{}:{errorReason:'BlockedByClient'})},m.sessionId).catch(()=>{});
 }}});
 const target=await send('Target.createTarget',{url:'about:blank'},null);session=(await send('Target.attachToTarget',{targetId:target.targetId,flatten:true},null)).sessionId;
 await send('Page.enable');await send('Runtime.enable');await send('Fetch.enable',{patterns:[{urlPattern:'*'}]});await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
 await go('/');await wait('document.getElementById("allHotelsGrid").getAttribute("aria-busy")==="false"');
 await js('window.__headerHeight=document.querySelector(".hv-header").getBoundingClientRect().height;document.getElementById("searchInput").focus()');await wait('document.querySelectorAll("#searchDropdown [role=option]").length===2');
 await check('Empty focus shows only actual active destinations and real hotel counts','document.getElementById("searchDropdown").textContent.includes("Điểm đến gợi ý") && document.getElementById("searchDropdown").textContent.includes("2 khách sạn") && !document.getElementById("searchDropdown").textContent.includes("Địa phương ẩn")');
 await check('Airbnb-style destination popup is anchored below the bar without resizing the header','getComputedStyle(document.getElementById("searchDropdown")).position==="absolute" && document.getElementById("searchDropdown").getBoundingClientRect().top>=document.getElementById("heroSearchBar").getBoundingClientRect().bottom && Math.abs(document.querySelector(".hv-header").getBoundingClientRect().height-__headerHeight)<2');
 await type('ho guom');await wait('document.querySelectorAll("#searchDropdown [role=option]").length===1 && document.getElementById("searchDropdown").textContent.includes("Hồ Gươm")');
 await js('document.getElementById("searchInput").dispatchEvent(new KeyboardEvent("keydown",{key:"ArrowDown",bubbles:true}));document.getElementById("searchInput").dispatchEvent(new KeyboardEvent("keydown",{key:"Enter",bubbles:true}))');
 await check('Hotel suggestions match without accents and select the stable hotel ID','document.getElementById("searchHotelId").value==="2" && !document.getElementById("searchHotelId").disabled && document.getElementById("searchDropdown").classList.contains("hidden")');
 await type('ha noi');await wait('document.querySelectorAll("#searchDropdown [role=option]").length===3');
 await click('#search-suggestion-0');await check('Destination selection clears a previous hotel selection','document.getElementById("searchLocationId").value==="1" && !document.getElementById("searchLocationId").disabled && document.getElementById("searchHotelId").disabled');
 await click('#search-dates-toggle');
 const dates=await js('(()=>{const d=new Date(document.getElementById("checkInInput").min+"T00:00:00Z");d.setUTCDate(d.getUTCDate()+10);const arrival=d.toISOString().slice(0,10);d.setUTCDate(d.getUTCDate()+3);return {arrival,departure:d.toISOString().slice(0,10)}})()');
 await click('[data-calendar-date="'+dates.arrival+'"]');await click('[data-calendar-date="'+dates.departure+'"]');await click('#search-dates-done');
 await check('Date picker stores the exact selected stay without timezone conversion','document.getElementById("checkInInput").value==='+JSON.stringify(dates.arrival)+' && document.getElementById("checkOutInput").value==='+JSON.stringify(dates.departure)+' && document.getElementById("search-dates-panel").hidden');
 await click('#search-guests-toggle');
 for(const kind of ['adults','children','children','infants'])await click('[data-guest-kind="'+kind+'"][data-guest-step="1"]');
 await click('#search-guests-panel .hv-picker-actions [data-search-picker-close]');
 await check('Guest categories sum to the actual requested headcount','document.getElementById("guestsInput").value==="6" && document.getElementById("searchAdults").value==="3" && document.getElementById("searchChildren").value==="2" && document.getElementById("searchInfants").value==="1"');
 await click('.hv-search-submit');await wait('new URLSearchParams(location.search).get("guests")==="6" && document.getElementById("allHotelsGrid").getAttribute("aria-busy")==="false"');
  await check('Search loading state clears after the response','!document.querySelector(".hv-search-submit").disabled && !document.querySelector(".hv-search-submit").hasAttribute("aria-busy")');
 await check('Search submits dates and all guest counts to the real API and updates the URL','new URLSearchParams(location.search).get("check_in")==='+JSON.stringify(dates.arrival)+' && new URLSearchParams(location.search).get("children")==="2" && document.querySelectorAll(".hv-hotel-card").length===2 && document.querySelector("[data-hotel-id=\\"2\\"] [data-card-field=availability]").textContent.includes("2 phòng cho 6 khách")');
 const current=await js('location.pathname+location.search');await go(current);await wait('document.getElementById("allHotelsGrid").getAttribute("aria-busy")==="false"');await click('#search-guests-toggle');
 await check('Reload preserves the chosen guest groups','document.querySelector("[data-guest-count=adults]").textContent==="3" && document.querySelector("[data-guest-count=children]").textContent==="2" && document.querySelector("[data-guest-count=infants]").textContent==="1"');
 await click('#search-guests-panel .hv-picker-actions [data-search-picker-close]');
 const detail=await js('(()=>{const u=new URL(document.querySelector("[data-hotel-id=\\"2\\"] [data-hotel-link]").href);return u.pathname+u.search})()');
 await check('Hotel links retain dates, guests and the matching room quantity','new URL(document.querySelector("[data-hotel-id=\\"2\\"] [data-hotel-link]").href).searchParams.get("quantity")==="2"');
 await go(detail);await wait('document.getElementById("booking-availability")?.dataset.availability==="success"');
 await check('Detail uses the matching room type and quantities, with an actual availability recheck','document.getElementById("guests").value==="6" && document.getElementById("room-quantity").value==="2" && document.getElementById("room-select").value==="1" && document.getElementById("check-in").value==='+JSON.stringify(dates.arrival));
  await click('#hv-guest-trigger');await check('Detail also retains adults, children and infants','document.querySelector("#hv-guest-overlay [data-kind=adults] [data-count]").textContent==="3" && document.querySelector("#hv-guest-overlay [data-kind=children] [data-count]").textContent==="2" && document.querySelector("#hv-guest-overlay [data-kind=infants] [data-count]").textContent==="1"');
 await go('/');await wait('document.getElementById("allHotelsGrid").getAttribute("aria-busy")==="false"');
 await click('#search-guests-toggle');for(let i=0;i<18;i++)await click('[data-guest-kind="adults"][data-guest-step="1"]');
 await check('Guest picker enforces the same maximum as the hotel form','document.getElementById("guestsInput").value==="20" && Array.from(document.querySelectorAll("[data-guest-step=\\"1\\"]")).every(b=>b.disabled)');
 await click('#search-guests-panel .hv-picker-actions [data-search-picker-close]');await type('da lat');await wait('document.getElementById("searchDropdown").textContent.includes("Đà Lạt")');await click('#search-suggestion-0');await click('.hv-search-submit');
 await wait('new URLSearchParams(location.search).get("guests")==="20" && document.getElementById("allHotelsGrid").getAttribute("aria-busy")==="false"');
 await check('Insufficient capacity produces a real empty result without an API error','document.querySelectorAll(".hv-hotel-card").length===0 && document.querySelector(".hv-home-empty").textContent.includes("Không tìm thấy") && document.getElementById("hotelLoadError").hidden');
 await js('history.back()');await wait('location.search==="" && document.getElementById("guestsInput").value==="2" && document.getElementById("allHotelsGrid").getAttribute("aria-busy")==="false"');
 await check('Browser Back restores search fields and results','document.getElementById("searchInput").value==="" && document.getElementById("checkInInput").value==="" && document.querySelectorAll(".hv-hotel-card").length===3');
 await js('history.forward()');await wait('new URLSearchParams(location.search).get("guests")==="20" && document.getElementById("guestsInput").value==="20"');
 await check('Browser Forward restores the actual trip context','document.getElementById("searchInput").value==="Đà Lạt" && document.getElementById("searchLocationId").value==="2"');
 await go('/');failSuggestion=true;await js('document.getElementById("searchInput").focus()');await wait('!!document.querySelector(".hv-suggestion-retry")');
 await check('Suggestion load errors are distinct from no matches','document.getElementById("searchDropdown").textContent.includes("Chưa thể tải gợi ý")');
 await click('.hv-suggestion-retry');await wait('document.querySelectorAll("#searchDropdown [role=option]").length===2');
 await type('no-existing-hotel-xyz');await wait('document.getElementById("searchDropdown").textContent.includes("Không tìm thấy")');
 await check('An unknown destination has an honest empty suggestion state','!document.querySelector(".hv-suggestion-retry") && document.querySelectorAll("#searchDropdown [role=option]").length===0');
 for(const width of [375,768,1440]) {
  await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:width<768});await go('/');await js('document.getElementById("searchInput").focus()');await wait('document.querySelectorAll("#searchDropdown [role=option]").length===2');
  await check('Search popup fits '+width+'px','document.documentElement.scrollWidth<=innerWidth && document.getElementById("searchDropdown").getBoundingClientRect().left>=0 && document.getElementById("searchDropdown").getBoundingClientRect().right<=innerWidth && document.getElementById("searchDropdown").getBoundingClientRect().bottom<=innerHeight');
  const shot=await send('Page.captureScreenshot',{format:'png'});fs.writeFileSync(path.join(runtime,'search-'+width+'.png'),Buffer.from(shot.data,'base64'));
  await js('document.getElementById("searchInput").dispatchEvent(new KeyboardEvent("keydown",{key:"Escape",bubbles:true}))');await check('Escape closes suggestions at '+width+'px','document.getElementById("searchDropdown").classList.contains("hidden")');
 }
 assert.deepEqual(errors,[],'No browser script errors');assert.ok(requests.every(r=>!r.allowed||r.method==='GET'));
})().catch(error=>{errors.push(error.stack);process.exitCode=1;}).finally(async()=>{
 clearTimeout(timer);fs.writeFileSync(path.join(root,'storage/logs/hotel-search-browser.json'),JSON.stringify({origin,owned_synthetic_mysql:true,get_only:true,checks,errors},null,2));console.log(JSON.stringify({checks:checks.length,passed:checks.length,errors}));
 if(chrome){try{await send('Browser.close',{},null);}catch{}chrome.kill();}
});
