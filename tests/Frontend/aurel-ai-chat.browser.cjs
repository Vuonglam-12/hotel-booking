// Self-contained UI fixtures. No Laravel database, live AI, customer token or external request.
const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict'),{spawn}=require('node:child_process');
const root=process.cwd(),runtime=path.join(root,'storage/app/private/aurel-ai-chat-ui');fs.mkdirSync(runtime,{recursive:true});
const preview=path.join(root,'storage/app/private/customer-account-phase2/preview'),publicRoot=path.join(root,'public');
const checks=[],errors=[],requests=[];let chrome,session,origin,seq=0,buffer='';const pending=new Map();
const pause=ms=>new Promise(r=>setTimeout(r,ms));
const mime={'.css':'text/css','.js':'text/javascript','.svg':'image/svg+xml','.png':'image/png','.woff2':'font/woff2','.woff':'font/woff','.jpg':'image/jpeg'};
const server=http.createServer((request,response)=>{
    const u=new URL(request.url,origin);requests.push({path:u.pathname,method:request.method});
    if(request.method!=='GET'){response.writeHead(405);response.end();return;}
    if(u.pathname.startsWith('/api/')){
        response.setHeader('Content-Type','application/json');
        if(u.pathname==='/api/profile')response.end(JSON.stringify({id:41,name:'Người xem giao diện',email:'ui@example.invalid',phone:null,avatar_url:null,total_bookings:0}));
        else response.end(JSON.stringify({data:[],unread_count:0,meta:{total:0,current_page:1,last_page:1},summary:{total:0}}));
        return;
    }
    const page={'/itineraries':'itineraries','/profile':'profile','/bookings':'bookings'}[u.pathname];
    if(page){response.setHeader('Content-Type','text/html;charset=utf-8');response.end(fs.readFileSync(path.join(preview,page+'.html'),'utf8').replaceAll('http://127.0.0.1/',origin+'/'));return;}
    const file=path.resolve(publicRoot,'.'+decodeURIComponent(u.pathname));
    if(!file.startsWith(publicRoot+path.sep)||!fs.existsSync(file)||!fs.statSync(file).isFile()){response.writeHead(404);response.end();return;}
    response.setHeader('Content-Type',mime[path.extname(file)]||'application/octet-stream');fs.createReadStream(file).pipe(response);
});
const send=(method,params={},sid=session)=>new Promise((resolve,reject)=>{
    const id=++seq,timer=setTimeout(()=>{pending.delete(id);reject(Error(method+' timed out'));},15000);pending.set(id,{resolve,reject,timer});
    chrome.stdio[3].write(JSON.stringify({id,method,params,...(sid?{sessionId:sid}:{})})+'\0');
});
const js=async expression=>{const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true,userGesture:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;};
const wait=async expression=>{for(let i=0;i<100;i++){if(await js(expression))return;await pause(100);}throw Error('Wait failed: '+expression);};
const check=async(name,expression)=>{const result=await js(expression);if(result!==true){const state=await js('(()=>{const h=document.getElementById("aiHistory");return {active:document.activeElement?.id,modal:h?.getAttribute("aria-modal"),hidden:h?.getAttribute("aria-hidden"),inert:h?.inert,workspaceInert:document.querySelector(".ai-workspace")?.inert,historyOpen:document.getElementById("aurelAi")?.dataset.historyOpen,visibility:h?getComputedStyle(h).visibility:null,left:h?.getBoundingClientRect().left,backdropHidden:document.getElementById("aiHistoryBackdrop")?.hidden}})()');throw Error(name+'; observed '+JSON.stringify(state));}checks.push(name);};
const click=selector=>js('document.querySelector('+JSON.stringify(selector)+').click()');
const type=text=>js('document.getElementById("aiInput").value='+JSON.stringify(text)+';document.getElementById("aiInput").dispatchEvent(new Event("input",{bubbles:true}))');
const newChat=()=>click('.ai-new-top');
async function ready(){await wait('document.getElementById("aiMessages").getAttribute("aria-busy")==="false"');await pause(50);}
async function example(kind){await js('document.getElementById("aiExamples").open=true');await click('[data-ai-example="'+kind+'"]');await ready();}
async function go(page){await js('window.__leaving=true');await send('Page.navigate',{url:origin+page});await wait('!window.__leaving && document.readyState==="complete" && !!document.getElementById("accountMain")');if(page==='/itineraries')await wait('document.getElementById("aiHistoryList").children.length>0');}
async function viewport(width,height=1000){await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<768});await pause(120);}
async function shot(name){await pause(100);const r=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync(path.join(runtime,name+'.png'),Buffer.from(r.data,'base64'));}
const timer=setTimeout(()=>{errors.push('180-second browser test limit exceeded');chrome?.kill();server.closeAllConnections?.();server.close();process.exitCode=1;},180000);
(async()=>{
    await new Promise(r=>server.listen(0,'127.0.0.1',r));origin='http://127.0.0.1:'+server.address().port;
    chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-background-networking','--disable-sync','--disable-extensions','--remote-debugging-pipe','--user-data-dir='+path.join(runtime,'chrome-'+Date.now())],{windowsHide:true,stdio:['ignore','ignore','ignore','pipe','pipe']});
    chrome.stdio[4].setEncoding('utf8');chrome.stdio[4].on('data',chunk=>{
        buffer+=chunk;let index;while((index=buffer.indexOf('\0'))!==-1){const m=JSON.parse(buffer.slice(0,index));buffer=buffer.slice(index+1);const task=pending.get(m.id);
            if(task){pending.delete(m.id);clearTimeout(task.timer);m.error?task.reject(Error(m.error.message)):task.resolve(m.result);}
            else if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description||m.params.exceptionDetails.text);
            else if(m.method==='Fetch.requestPaused'){
                const u=new URL(m.params.request.url),allowed=u.origin===origin&&m.params.request.method==='GET';
                if(!allowed)errors.push('Blocked unexpected network request: '+u.origin+u.pathname);
                send(allowed?'Fetch.continueRequest':'Fetch.failRequest',{requestId:m.params.requestId,...(allowed?{}:{errorReason:'BlockedByClient'})},m.sessionId).catch(()=>{});
            }
        }
    });
    const target=await send('Target.createTarget',{url:'about:blank'},null);session=(await send('Target.attachToTarget',{targetId:target.targetId,flatten:true},null)).sessionId;
    await send('Page.enable');await send('Runtime.enable');await send('Fetch.enable',{patterns:[{urlPattern:'*'}]});
    await send('Page.addScriptToEvaluateOnNewDocument',{source:'if(location.origin==='+JSON.stringify(origin)+'){if(location.pathname==="/itineraries"){localStorage.removeItem("token");localStorage.removeItem("user");}else{localStorage.setItem("token","ui-fixture-not-a-real-token");localStorage.setItem("user",JSON.stringify({id:41,name:"Người xem giao diện",role:"user"}));}}'});
    await viewport(1440);await go('/itineraries');
    await check('Welcome and four prompts without the legacy form','document.getElementById("aiWelcomeTitle").textContent==="Chuyến đi tiếp theo của bạn?" && document.querySelectorAll(".ai-suggestions>button").length===4 && !document.getElementById("itineraryForm")');
    await check('Topbar has no duplicate brand or demo labels','!document.querySelector(".ai-demo-badge,.ai-wordmark") && !/\\bdemo\\b/i.test(document.body.innerText) && document.getElementById("aiWelcome").textContent.includes("Nội dung minh họa")');
    await check('Workspace is white with a very light sidebar','getComputedStyle(document.querySelector(".ai-workspace")).backgroundColor==="rgb(255, 255, 255)" && getComputedStyle(document.getElementById("aiHistory")).backgroundColor==="rgb(248, 249, 251)"');
    await check('AI opens independently of the account shell or a login session','!localStorage.getItem("token") && !document.getElementById("sidebar") && !document.querySelector(".account-workspace") && location.pathname==="/itineraries"');
    await check('Three suggested conversations and desktop history','document.querySelectorAll(".ai-history-thread[data-sample=true]").length===3 && document.getElementById("aurelAi").dataset.historyOpen==="true"');
    await check('Sidebar uses one hamburger control in place of the close icon','document.querySelector("#aiCloseHistory use").getAttribute("href").endsWith("#menu") && document.getElementById("aiToggleHistory").hidden');
    await click('#aiCloseHistory');await check('Collapsing history restores the main hamburger','document.getElementById("aurelAi").dataset.historyOpen==="false" && !document.getElementById("aiToggleHistory").hidden');
    await click('#aiToggleHistory');await pause(250);
    await shot('welcome-desktop');
    await click('[data-ai-history-filter="mine"]');await check('History empty state','!!document.querySelector(".ai-history-empty") && document.querySelector(".ai-history-empty").textContent.includes("Chưa có cuộc trò chuyện")');
    await click('[data-ai-history-filter="all"]');
    await type('Dòng một');await js('document.getElementById("aiInput").focus()');
    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',windowsVirtualKeyCode:13,modifiers:8,text:'\r',unmodifiedText:'\r'});
    await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Enter',code:'Enter',windowsVirtualKeyCode:13,modifiers:8});
    await check('Shift+Enter inserts a line without sending','document.getElementById("aiInput").value.includes(String.fromCharCode(10)) && document.getElementById("aurelAi").dataset.mode==="welcome"');
    await js('document.getElementById("aiInput").dispatchEvent(new CompositionEvent("compositionstart"));document.getElementById("aiInput").dispatchEvent(new KeyboardEvent("keydown",{key:"Enter",bubbles:true,isComposing:true}));document.getElementById("aiInput").dispatchEvent(new CompositionEvent("compositionend"))');
    await check('Vietnamese IME does not submit composition','document.getElementById("aurelAi").dataset.mode==="welcome"');
    await type('<img src=x onerror="window.aiUnsafe=true"> Mình muốn một kỳ nghỉ.');
    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});
    await check('Enter sends, changes layout and displays loading','document.getElementById("aurelAi").dataset.mode==="chat" && !!document.querySelector(".ai-loading") && document.getElementById("aiSend").disabled');
    await ready();
    await check('User content is rendered safely as text','document.querySelector(".ai-user-bubble").textContent.includes("<img") && !window.aiUnsafe && !document.querySelector(".ai-user-bubble img")');
    await check('Composer is docked and answer is distinct from the user bubble','document.getElementById("aiComposer").parentElement.id==="aiComposerDock" && document.querySelector(".ai-user-bubble").getBoundingClientRect().left>document.querySelector(".ai-assistant-heading").getBoundingClientRect().left && !!document.querySelector(".ai-quick-replies button")');
    await newChat();await example('timeline');
    await check('Timeline displays five selectable days only for its response','document.querySelectorAll(".ai-day-tabs button").length===5 && document.querySelectorAll("[data-component]").length===1 && document.querySelector("[data-component]").dataset.component==="timeline"');
    await click('.ai-day-tabs button:last-child');await check('Selecting another day changes the active tab','document.querySelector(".ai-day-tabs button:last-child").getAttribute("aria-selected")==="true" && document.querySelectorAll(".ai-timeline-stop").length===3');
    await js('document.querySelector(".ai-day-tabs button:last-child").focus()');await send('Input.dispatchKeyEvent',{type:'keyDown',key:'ArrowLeft',code:'ArrowLeft',windowsVirtualKeyCode:37});
    await check('Day tabs support arrow-key navigation','document.querySelector(".ai-day-tabs button[aria-selected=true]").textContent==="Ngày 4"');
    await shot('timeline-desktop');
    await newChat();await example('destinations');
    await check('Destination cards use distinct accents without repeated labels','document.querySelectorAll("[data-component=destinations] article").length===3 && new Set(Array.from(document.querySelectorAll(".ai-card-visual"),el=>getComputedStyle(el).color)).size===3 && !/\\bdemo\\b/i.test(document.body.innerText)');
    await click('[data-component=destinations] article:nth-child(2) button');await ready();
    await check('Destination card interaction preserves the selected locality','document.querySelector(".ai-message:last-child [data-component=places]").textContent.includes("Hồ Xuân Hương")');
    await click('.ai-message:last-child .ai-quick-replies button');await ready();
    await check('Quick reply creates a contextual timeline','document.querySelector(".ai-message:last-child [data-component=timeline]").textContent.includes("Đà Lạt")');
    await newChat();await example('hotels');await check('Lodging cards remain clearly outside real booking data','document.querySelectorAll(".ai-hotels article").length===2 && document.getElementById("aiComposerDisclaimer").textContent.includes("Chưa kết nối AI hoặc dữ liệu thực tế") && document.querySelector(".ai-hotels").textContent.includes("Chưa nhận đặt phòng")');
    await newChat();await example('budget');await check('Budget retains the total and five line items','document.querySelector(".ai-budget-total strong").textContent==="4.000.000 ₫" && document.querySelectorAll(".ai-budget-line").length===5 && document.querySelector(".ai-panel-footer").textContent.includes("chưa phải báo giá")');
    await check('Budget colors match chart and legend, and amounts and shares total correctly','(()=>{const bars=[...document.querySelectorAll(".ai-budget-bar>span")],rows=[...document.querySelectorAll(".ai-budget-line")];return new Set(bars.map(el=>getComputedStyle(el).backgroundColor)).size===5 && bars.every((el,i)=>getComputedStyle(el).backgroundColor===getComputedStyle(rows[i].querySelector(".ai-budget-dot")).backgroundColor) && rows.reduce((sum,el)=>sum+Number(el.querySelector("strong").textContent.replace(/[^0-9]/g,"")),0)===4000000 && rows.reduce((sum,el)=>sum+parseFloat(el.querySelector(".ai-budget-percent").textContent),0)===100})()');
    await shot('budget-desktop');
    await newChat();await example('route');await click('.ai-route-stops button:nth-child(2)');await check('Route mock has working stop selection and no geographic claim','document.querySelector(".ai-route-stops button:nth-child(2)").getAttribute("aria-pressed")==="true" && document.querySelectorAll(".ai-route-map .is-active").length===1 && document.querySelector(".ai-panel-heading").textContent.includes("không theo tọa độ thật")');
    await newChat();await example('error');await shot('error-desktop');await check('Error state has an actionable retry','!!document.querySelector(".ai-error[role=alert]") && document.querySelector(".ai-error").textContent.includes("Tin nhắn của bạn vẫn ở đây")');
    await click('.ai-error button');await check('Retry enters loading','!!document.querySelector(".ai-loading")');await ready();await check('Retry recovers without duplicating the user message','!document.querySelector(".ai-error") && document.querySelectorAll(".ai-user-bubble").length===1 && !!document.querySelector("[data-component=destinations]")');
    await newChat();await example('long');await pause(500);await check('Long response is readable and scrolls within the conversation','document.querySelectorAll(".ai-response-body>h3").length===5 && document.getElementById("aiScroll").scrollHeight>document.getElementById("aiScroll").clientHeight && document.documentElement.scrollHeight<=innerHeight');
    await check('A long answer starts at its beginning instead of skipping unread content','document.querySelector(".ai-assistant-heading").getBoundingClientRect().top>=document.getElementById("aiScroll").getBoundingClientRect().top-2 && document.querySelector(".ai-assistant-heading").getBoundingClientRect().top<document.getElementById("aiScroll").getBoundingClientRect().top+60');
    await js('document.getElementById("aiExamples").open=true');await click('[data-ai-example="long"]');await js('document.getElementById("aiScroll").scrollTop=0');await ready();
    await check('An arriving reply does not pull a reader away from older text','document.getElementById("aiScroll").scrollTop<80');await click('#aiJump');await pause(600);await check('Jump to latest scrolls the conversation','document.getElementById("aiScroll").scrollTop>200');
    await newChat();await js('document.getElementById("aiExamples").open=true');await click('[data-ai-example="timeline"]');await newChat();await pause(1000);
    await check('Starting a new chat while a reply is pending keeps the new chat empty','document.getElementById("aurelAi").dataset.mode==="welcome" && document.querySelectorAll("#aiMessages .ai-message").length===0');
    await type('Ý tưởng đang viết dở');const draftThread=await js('document.getElementById("aurelAi").dataset.conversationId');
    await click('.ai-history-thread[aria-current="false"]');await click('.ai-new-top');await check('Returning to the empty conversation resets the composer intentionally','document.getElementById("aurelAi").dataset.mode==="welcome" && document.getElementById("aiInput").value===""');
    for(const width of [1440,1024,768,390,320]){
        await viewport(width,width<768?844:1000);await newChat();
        await check('Welcome fits viewport '+width,'document.documentElement.scrollWidth<=innerWidth && document.getElementById("aiComposer").getBoundingClientRect().right<=innerWidth && document.getElementById("aiComposer").getBoundingClientRect().left>=0');
    }
    await viewport(390,844);await newChat();await shot('welcome-mobile');
    await click('#aiToggleHistory');await pause(250);await check('Mobile history is an accessible drawer','document.getElementById("aiHistory").getAttribute("aria-modal")==="true" && document.querySelector(".ai-workspace").inert && document.activeElement.id==="aiCloseHistory" && !document.getElementById("aiHistoryBackdrop").hidden');
    await js('document.querySelector(".ai-history-footer a").focus()');await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});await check('Focus stays inside mobile history','document.getElementById("aiHistory").contains(document.activeElement)');
    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});await check('Escape closes history and restores focus','document.getElementById("aurelAi").dataset.historyOpen==="false" && document.activeElement.id==="aiToggleHistory" && !document.querySelector(".ai-workspace").inert');
    await click('#aiToggleHistory');await click('#aiHistoryBackdrop');await check('Backdrop closes the drawer','document.getElementById("aurelAi").dataset.historyOpen==="false"');
    await example('timeline');await shot('timeline-mobile');await check('Mobile timeline and bottom composer fit','document.documentElement.scrollWidth<=innerWidth && document.querySelector(".ai-panel").getBoundingClientRect().right<=innerWidth && document.getElementById("aiComposer").getBoundingClientRect().bottom<=innerHeight');
    await check('Mobile composer stays compact when empty','document.getElementById("aiComposer").getBoundingClientRect().height<90 && !/\\bdemo\\b/i.test(document.body.innerText)');
    await js('document.getElementById("aiExamples").open=true');await check('Examples popup fits on mobile','document.querySelector(".ai-examples-menu").getBoundingClientRect().right<=innerWidth && document.querySelector(".ai-examples-menu").getBoundingClientRect().left>=0');
    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await type('x'.repeat(2000));await check('Long composer text is bounded and counted','document.getElementById("aiInput").offsetHeight<=181 && !document.getElementById("aiInputCount").hidden && document.documentElement.scrollWidth<=innerWidth');
    await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});await check('Reduced motion is respected','getComputedStyle(document.querySelector(".ai-message")).animationName==="none"');
    await viewport(1440);await go('/profile');await check('Profile retains the existing account shell','!document.body.classList.contains("aurel-ai-page") && !document.querySelector("link[href*=aurel-ai-chat]") && getComputedStyle(document.getElementById("sidebar")).display!=="none" && document.querySelector(".page-header h1").textContent==="Thông tin cá nhân"');
    await go('/bookings');await check('Booking history retains its existing shell and controls','!document.body.classList.contains("aurel-ai-page") && !!document.getElementById("bookingSearchForm") && document.querySelector(".page-header h1").textContent==="Lịch sử đặt phòng"');
    await go('/itineraries');await check('Reload resets only the in-memory demo conversations','document.getElementById("aurelAi").dataset.mode==="welcome" && document.querySelectorAll(".ai-history-thread").length===3 && !Object.keys(localStorage).some(key=>/conversation|aurel_ai/.test(key))');
    assert.ok(requests.every(request=>request.method==='GET'),'No mutation request');
    assert.ok(!requests.some(request=>/^\/api\/(?:chat|itinerar|v2\/itinerar)/.test(request.path)),'No real AI or itinerary API called');
    assert.deepEqual(errors,[],'No JavaScript exceptions or external requests');
})().catch(async error=>{errors.push(error.stack);process.exitCode=1;try{await shot('failure');}catch{}}).finally(async()=>{
    clearTimeout(timer);
    const report={status:errors.length?'FAIL':'PASS',checks:checks.length,passed:checks,errors,static_fixture_only:true,database_connections:0,real_ai_requests:0,requests_mutated_data:0,screenshots:runtime};
    fs.writeFileSync(path.join(runtime,'browser-results.json'),JSON.stringify(report,null,2));console.log(JSON.stringify(report,null,2));
    if(chrome){try{await send('Browser.close',{},null);}catch{}chrome.kill();}
    server.closeAllConnections?.();server.close();
});
