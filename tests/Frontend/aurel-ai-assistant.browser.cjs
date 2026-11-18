// Browser exercises the real frontend against an in-memory API fixture. Never contacts Laravel/main DB/providers.
const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict'),{spawn}=require('node:child_process');
const root=process.cwd(),runtime=path.join(root,'storage/app/private/aurel-ai-assistant-ui');fs.mkdirSync(runtime,{recursive:true});
const preview=path.join(root,'storage/app/private/customer-account-phase2/preview'),publicRoot=path.join(root,'public');
const checks=[],errors=[],requests=[],sessions=new Map(),replays=new Map();let chrome,session,origin,seq=0,buffer='',nextSession=1;
const pending=new Map(),pause=ms=>new Promise(r=>setTimeout(r,ms));
const mime={'.css':'text/css','.js':'text/javascript','.svg':'image/svg+xml','.png':'image/png','.woff2':'font/woff2','.woff':'font/woff','.jpg':'image/jpeg'};
const server=http.createServer(async(request,response)=>{
    const u=new URL(request.url,origin);requests.push({path:u.pathname,method:request.method,key:request.headers['idempotency-key'],requestId:request.headers['x-aurel-request-id']});
    const json=(data,status=200)=>{response.writeHead(status,{'Content-Type':'application/json'});response.end(JSON.stringify(data));};
    if(u.pathname.startsWith('/api/')){
        if(request.headers.authorization!=='Bearer browser-fixture-only')return json({message:'Unauthenticated'},401);
        if(u.pathname==='/api/chat'&&request.method==='GET')return json([...sessions.values()].filter(s=>!u.searchParams.has('status')||(s.status||'active')===u.searchParams.get('status')).map(s=>({id:s.id,title:s.title,status:s.status||'active'})));
        if(u.pathname==='/api/chat/start'&&request.method==='POST'){const id=nextSession++;sessions.set(id,{id,title:'Cuộc trò chuyện mới',messages:[]});return json({session_id:id},201);}
        if(/^\/api\/chat\/\d+\/messages\/\d+\/route\/\d+$/.test(u.pathname))return json({geometry:{type:'LineString',coordinates:[[106.702,10.77],[106.706,10.773],[106.708,10.778],[106.712,10.78]]},mode:'drive',distance_metres:1500,time_seconds:320});
        const closing=u.pathname.match(/^\/api\/chat\/(\d+)\/close$/);
        if(closing&&request.method==='PUT'){const thread=sessions.get(Number(closing[1]));if(!thread)return json({},404);thread.status='closed';return json({status:'closed',session_id:thread.id});}
        const match=u.pathname.match(/^\/api\/chat\/(\d+)(\/message)?$/);if(!match)return json({message:'missing fixture'},404);
        const thread=sessions.get(Number(match[1]));if(!thread)return json({},404);
        if(request.method==='DELETE'){sessions.delete(thread.id);return json({message:'Hội thoại đã xóa',session_id:thread.id});}
        if(!match[2])return json(thread);
        let raw='';for await(const chunk of request)raw+=chunk;const text=JSON.parse(raw).message,key=request.headers['idempotency-key'];
        if(replays.has(key))return json(replays.get(key));
        if(text==='Mất kết nối'&&!thread.failed){thread.failed=true;return json({message:'Máy chủ tạm thời chưa sẵn sàng.'},503);}
        if(text==='Mạng lỗi'){response.destroy();return;}
        const streaming=request.headers.accept?.includes('text/event-stream');let eventSequence=0;
        const event=(name,data,requestId=request.headers['x-aurel-request-id'],sequence=++eventSequence)=>{if(!response.destroyed)response.write('event: '+name+'\ndata: '+JSON.stringify({request_id:requestId,sequence,...data})+'\n\n');};
        if(streaming){
            response.writeHead(200,{'Content-Type':'text/event-stream;charset=utf-8','Cache-Control':'no-cache'});
            event('progress',{code:'processing_request'});
            if(text==='Lỗi API SSE'){event('error',{code:'groq_quota',message:'AI đang đạt giới hạn yêu cầu. Bạn thử lại sau nhé.',retryable:true});response.end();return;}
            if(text==='Luồng ngắt'){response.end();return;}
            if(text==='Chờ lâu'||text==='Hủy chờ'){event('progress',{code:'considering'});await pause(1500);response.end();return;}
            if(text==='Theo dõi tiến trình'){
                event('progress',{code:'considering'});await pause(350);
                event('progress',{code:'searching_hotels'},'another-request-id',900);
                event('progress',{code:'searching_hotels'},request.headers['x-aurel-request-id'],1);
                event('progress',{code:'searching_places'});await pause(450);
            }else if(text==='Mã mới'){event('progress',{code:'future_backend_code',message:'INTERNAL_TEXT_MUST_NOT_APPEAR'});await pause(450);}
            else if(text==='Chậm A'){event('progress',{code:'searching_places'});await pause(1100);event('progress',{code:'searching_hotels'});await pause(200);}
            else if(text==='Ngày 2 nhẹ hơn'){event('progress',{code:'revising_day',day:2});await pause(450);}
            else event('progress',{code:/lịch|ngày|Lên/.test(text)?'creating_itinerary':'searching_places'});
        }
        const timeline=/lịch|ngày|Lên/.test(text),days=Array.from({length:6},(_,i)=>({day_number:i+1,date:null,items:[
            {title:'Fixture landmark '+i,kind:'place',description:'Địa điểm trong API thử nghiệm.',start_time:'08:30',end_time:'09:30',coordinates:[106.7+i/1000,10.77],source:'Geoapify / OpenStreetMap',source_url:origin+'/',image:{url:origin+'/image/placeholders/accommodation.svg',alt:'Ảnh fixture',author:'Fixture author',source:'Fixture source',source_url:origin+'/',license:'CC BY-SA 4.0',license_url:'https://creativecommons.org/licenses/by-sa/4.0/'}},
            {title:'Nghỉ ngơi',kind:'break',description:'Khoảng nghỉ.',start_time:'09:30',end_time:'09:50'},
            {title:'Fixture museum '+i,kind:'place',description:'Nguồn định tuyến fixture.',start_time:'09:55',end_time:'10:55',coordinates:[106.71+i/1000,10.78],travel_from_previous:{time_seconds:300,distance_metres:1000,source:'Geoapify Route Matrix'}}]}));
        if(timeline)days[2].items[0].ticket={label:'Vé tham quan fixture',tiers:[{amount:160000,label:'Điều kiện vé fixture'}],note:'Giá từ HTTP fixture, không phải báo giá thật.',checked_at:'2026-10-28',source_url:origin+'/'};
        const components=timeline?[{type:'timeline',title:'Lịch trình từ API thử nghiệm',transport:'drive',provisional:true,days,assumptions:['Chưa chốt ngày đi.'],warnings:['Giờ mở cửa chưa xác minh.'],budget:{limit:text.includes('linh hoạt')?null:5000000,flexible:text.includes('linh hoạt'),status:'incomplete'}}]:[{type:'places',items:[{id:'fixture:1',name:'Fixture landmark',description:'Dữ liệu đến từ API.',source:'AUREL',source_url:origin+'/',image:{url:origin+'/image/placeholders/accommodation.svg',alt:'Fixture',author:'Fixture author',source_url:origin+'/',source:'Fixture'}}]}];
        if(!timeline)components[0].items[0].website=origin+'/';
        if(text==='Xem Suối Tiên'){
            const venue=JSON.parse(fs.readFileSync(path.join(root,'resources/content/itinerary/visit-facts.json'),'utf8')).places.find(p=>p.wikidata_id==='Q2930683');
            components.splice(0,components.length,{type:'places',items:[{...venue,id:'fixture-reviewed-venue',name:venue.names[0],source:'Nguồn thông tin đã đối chiếu',source_url:venue.website,ticket:{...venue.ticket,checked_at:venue.reviewed_on},image:{...venue.image,url:origin+venue.image.url}}]});
        }
        const result={message:text==='Dài'?Array(30).fill('Nội dung dài từ API dùng kiểm tra cuộn màn hình.').join('\n\n'):'Gợi ý trả về từ API, không phải phản hồi được tạo bởi JavaScript.',session_id:thread.id,assistant:{intent:timeline?'plan':'explore',components,quick_replies:['Lên lịch 6 ngày']}};
        if(text==='Kiểm tra thứ tự card'){
            const question='Bạn muốn thêm chỗ nghỉ (khách sạn) hoặc ăn uống không?';
            result.message='Giới thiệu các điểm phù hợp.\n\n'+question+'\n\nThông tin hữu ích vẫn được giữ.\n\n'+question.replaceAll(' ','  ');
            result.assistant.question=question;result.assistant.state={location_id:901};result.assistant.quick_replies=['Có','Không'];
            components.push({type:'hotels',items:[{id:501,name:'Fixture hotel',url:origin+'/',source:'Fixture'}]},
                {type:'budget',limit:5000000},{type:'destinations',items:[{id:901,name:'Hồ Chí Minh',source:'Điểm đến AUREL'}]});
        }
        if(text==='Chỉ hỏi một câu'){
            result.message='Bạn muốn đi mấy ngày?';result.assistant.question=result.message;components.splice(0,components.length);
        }
        if(text==='Chọn điểm đến'||text==='So sánh điểm đến'){
            result.message='Bạn thích địa phương nào trong các gợi ý này?';result.assistant.question=result.message;result.assistant.state={location_id:null};
            if(text==='So sánh điểm đến')result.assistant.state.location_id=901;
            components.splice(0,components.length,{type:'destinations',items:[{id:901,name:'Hồ Chí Minh'},{id:902,name:'Hà Nội'}]});
        }
        if(text==='Kiểm tra bố cục ảnh'){
            const note='Fixture photo — © Fixture Photographer. '+origin+'/ | CC BY-SA 4.0 https://creativecommons.org/licenses/by-sa/4.0/ | Đổi kích thước tối đa 1280px, nén JPEG progressive, bỏ EXIF và chỉnh hướng EXIF nếu có; không cắt ảnh.';
            components.splice(0,components.length,{type:'places',items:['Bưu điện Trung tâm Sài Gòn','Nhà thờ Đức Bà Sài Gòn','Địa đạo Củ Chi'].map((name,i)=>({id:'aurel:'+(51+i),name,description:'Mô tả fixture cho điểm tham quan.',source:'AUREL · Trải nghiệm đã duyệt',source_url:origin+'/',image:{url:origin+'/image/placeholders/accommodation.svg',alt:name,source:'Ảnh Trải nghiệm AUREL',source_url:origin+'/',...(i===0?{author:note}:i===1?{attribution_note:note+' | Ghi công bổ sung trong fixture.'}:{author:'Fixture Author Three',license:'CC BY 4.0',license_url:'https://creativecommons.org/licenses/by/4.0/'})}}))});
            result.message='AUREL gợi ý:\n- Bưu điện Trung tâm Sài Gòn (aurel:51)\n- Nhà thờ Đức Bà Sài Gòn (aurel:52)\n- Địa đạo Củ Chi (aurel:53)';
            result.assistant.quick_replies=['Gợi ý khách sạn AUREL'];
        }
        result.message_id=thread.messages.length+2;
        thread.title=text;thread.messages.push({id:thread.messages.length+1,role:'user',content:text},{id:result.message_id,role:'bot',content:result.message,entities_json:{assistant:result.assistant}});replays.set(key,result);
        await pause(150);
        if(streaming){
            const packet=Buffer.from('event: result\ndata: '+JSON.stringify({request_id:request.headers['x-aurel-request-id'],sequence:++eventSequence,result})+'\n\n');
            const multibyte=packet.findIndex(byte=>byte>=0xc0),split=multibyte>0?multibyte+1:Math.floor(packet.length/2);
            if(!response.destroyed){response.write(packet.subarray(0,split));await pause(5);response.end(packet.subarray(split));}
        }else json(result);return;
    }
    if(request.method!=='GET'){response.writeHead(405);response.end();return;}
    if(u.pathname==='/itineraries'){response.setHeader('Content-Type','text/html;charset=utf-8');response.end(fs.readFileSync(path.join(preview,'itineraries.html'),'utf8').replaceAll('http://127.0.0.1/',origin+'/'));return;}
    const file=path.resolve(publicRoot,'.'+decodeURIComponent(u.pathname));if(!file.startsWith(publicRoot+path.sep)||!fs.existsSync(file)||!fs.statSync(file).isFile()){response.writeHead(404);response.end();return;}
    response.setHeader('Content-Type',mime[path.extname(file)]||'application/octet-stream');fs.createReadStream(file).pipe(response);
});
const send=(method,params={},sid=session)=>new Promise((resolve,reject)=>{const id=++seq,timer=setTimeout(()=>{pending.delete(id);reject(Error(method+' timeout'));},15000);pending.set(id,{resolve,reject,timer});chrome.stdio[3].write(JSON.stringify({id,method,params,...(sid?{sessionId:sid}:{})})+'\0');});
const js=async expression=>{const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true,userGesture:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;};
const wait=async expression=>{for(let i=0;i<100;i++){if(await js(expression))return;await pause(100);}throw Error('Wait: '+expression);};
const check=async(name,expression)=>{assert.equal(await js(expression),true,name);checks.push(name);};
const click=selector=>js('document.querySelector('+JSON.stringify(selector)+').click()');
const type=text=>js('document.getElementById("aiInput").value='+JSON.stringify(text)+';document.getElementById("aiInput").dispatchEvent(new Event("input",{bubbles:true}))');
const submit=async text=>{await type(text);await js('document.getElementById("aiComposer").requestSubmit()');await wait('document.getElementById("aiMessages").getAttribute("aria-busy")==="false"');};
const viewport=async(width,height=950)=>{await send('Emulation.setTouchEmulationEnabled',{enabled:width<768,maxTouchPoints:1});await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<768});await pause(180);};
const shot=async name=>{await pause(150);const r=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync(path.join(runtime,name+'.png'),Buffer.from(r.data,'base64'));};
const timer=setTimeout(()=>{errors.push('180 second limit');chrome?.kill();server.closeAllConnections?.();server.close();process.exitCode=1;},180000);
(async()=>{
    await new Promise(r=>server.listen(0,'127.0.0.1',r));origin='http://127.0.0.1:'+server.address().port;
    chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-background-networking','--disable-sync','--disable-extensions','--remote-debugging-pipe','--user-data-dir='+path.join(runtime,'chrome-'+Date.now())],{windowsHide:true,stdio:['ignore','ignore','ignore','pipe','pipe']});
    chrome.stdio[4].setEncoding('utf8');chrome.stdio[4].on('data',chunk=>{buffer+=chunk;let index;while((index=buffer.indexOf('\0'))!==-1){const m=JSON.parse(buffer.slice(0,index));buffer=buffer.slice(index+1);const task=pending.get(m.id);if(task){pending.delete(m.id);clearTimeout(task.timer);m.error?task.reject(Error(m.error.message)):task.resolve(m.result);}else if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description||m.params.exceptionDetails.text);else if(m.method==='Fetch.requestPaused'){
        const url=new URL(m.params.request.url),allowed=url.origin===origin;
        // Basemap delivery is deliberately mocked in browser tests; live routing is checked separately.
        if(url.origin==='https://tile.openstreetmap.org')send('Fetch.fulfillRequest',{requestId:m.params.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'image/png'}],body:'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='},m.sessionId).catch(()=>{});
        else{if(!allowed)errors.push('Unexpected network origin');send(allowed?'Fetch.continueRequest':'Fetch.failRequest',{requestId:m.params.requestId,...(allowed?{}:{errorReason:'BlockedByClient'})},m.sessionId).catch(()=>{});}
    }}});
    const target=await send('Target.createTarget',{url:'about:blank'},null);session=(await send('Target.attachToTarget',{targetId:target.targetId,flatten:true},null)).sessionId;
    await send('Page.enable');await send('Runtime.enable');await send('Fetch.enable',{patterns:[{urlPattern:'*'}]});await viewport(1440);
    await send('Page.addScriptToEvaluateOnNewDocument',{source:'const nativeTimeout=window.setTimeout;window.setTimeout=(fn,ms,...args)=>nativeTimeout(fn,window.__fastAIWait?(ms===20000?50:ms===300000?250:ms):ms,...args);'});
    await send('Page.navigate',{url:origin+'/itineraries'});await wait('document.readyState==="complete" && !!document.getElementById("aiInput")');
    await check('Standalone welcome has no legacy form or fake history','!document.getElementById("itineraryForm") && document.querySelectorAll(".ai-history-thread").length===0 && document.getElementById("aiWelcomeTitle").textContent==="Chuyến đi tiếp theo của bạn?"');
    await check('White workspace and friendly introduction','getComputedStyle(document.querySelector(".ai-workspace")).backgroundColor==="rgb(255, 255, 255)" && document.querySelector(".ai-welcome-note").textContent.includes("ngân sách") && !document.body.innerText.includes("Geoapify")');
    await submit('Sài Gòn có gì chơi?');await check('Guest gets login link without any API write','!!document.querySelector(".ai-error a[href$=login]")');assert.equal(requests.filter(r=>r.method==='POST').length,0);checks.push('No anonymous write');
    await js('localStorage.setItem("token","browser-fixture-only")');await click('.ai-new-top');await submit('Sài Gòn có gì chơi?');
    await check('API reply and sourced image are displayed','document.querySelector(".ai-answer-text").textContent.includes("API") && document.querySelectorAll("[data-component=places] article").length===1 && document.querySelector("figcaption").textContent.includes("Fixture author")');
    await check('Every displayed place has a working source link','Array.from(document.querySelectorAll("[data-component=places] article")).every(card=>Array.from(card.querySelectorAll("a")).some(a=>a.textContent==="Xem nguồn" && a.href.startsWith(location.origin)))');
    await check('Reference website links use AUREL blue','Array.from(document.querySelectorAll(".ai-source-link")).filter(a=>a.textContent==="Website tham khảo").every(a=>getComputedStyle(a).color==="rgb(37, 99, 235)") && Array.from(document.querySelectorAll(".ai-source-link")).some(a=>a.textContent==="Website tham khảo")');
    await check('History contains only server-backed conversations','document.querySelectorAll(".ai-history-thread").length===1');
    await shot('advice-desktop');await submit('Lên lịch 6 ngày');
    await check('Timeline and unknown-price budget are sourced from API','document.querySelectorAll(".ai-day-tabs button").length===6 && document.querySelector("[data-component=budget]").textContent.includes("5.000.000") && !document.querySelector(".ai-budget-bar")');
    await click('.ai-day-tabs button:nth-child(2)');await check('Day switching keeps selected content','document.querySelector(".ai-day-tabs button:nth-child(2)").getAttribute("aria-selected")==="true" && document.querySelector(".ai-timeline").textContent.includes("Fixture landmark 1")');
    await js('document.querySelector(".ai-day-tabs button:nth-child(2)").dispatchEvent(new KeyboardEvent("keydown",{key:"ArrowRight",bubbles:true}))');
    await check('Timeline keyboard navigation','document.querySelector(".ai-day-tabs button:nth-child(3)").getAttribute("aria-selected")==="true"');
    await check('Verified ticket conditions and source are visible','document.querySelector(".ai-ticket").textContent.includes("160.000") && document.querySelector(".ai-ticket a").textContent.includes("28/10/2026")');
    await click('.ai-timeline>.ai-small-button');await wait('!!document.querySelector(".leaflet-overlay-pane path")');
    await check('Real basemap and routed shape render with attribution','document.querySelectorAll(".leaflet-tile").length>0 && document.querySelectorAll(".ai-map-marker").length===2 && document.querySelector(".leaflet-control-attribution").textContent.includes("OpenStreetMap") && !document.querySelector("[data-component=route]").textContent.includes("Geoapify")');
    assert.ok(requests.some(r=>/\/messages\/\d+\/route\/3$/.test(r.path)));checks.push('Map requests the selected day of the saved assistant message');
    await js('document.querySelector(".ai-map-canvas").scrollIntoView({block:"center"})');await shot('timeline-desktop');
    await submit('<img src=x onerror=alert(1)>');await check('User text cannot inject HTML','!document.querySelector(".ai-user-bubble img")');
    await click('.ai-new-top');await submit('Mất kết nối');await check('Error state retains message and retry','!!document.querySelector(".ai-error button") && document.querySelectorAll(".ai-message-user").length===1');
    const retryKey=requests.filter(r=>r.path.endsWith('/message')).at(-1).key;await click('.ai-error button');await wait('document.getElementById("aiMessages").getAttribute("aria-busy")==="false"');
    assert.equal(requests.filter(r=>r.path.endsWith('/message')).at(-1).key,retryKey);checks.push('Retry preserves idempotency key');await check('Retry does not duplicate local user message','!document.querySelector(".ai-error") && document.querySelectorAll(".ai-message-user").length===1');
    await type('Dòng 1');await js('document.getElementById("aiInput").dispatchEvent(new KeyboardEvent("keydown",{key:"Enter",shiftKey:true,bubbles:true,cancelable:true}))');await check('Shift Enter does not submit','document.querySelectorAll(".ai-message-user").length===1');
    await js('document.getElementById("aiInput").dispatchEvent(new KeyboardEvent("keydown",{key:"Enter",isComposing:true,bubbles:true,cancelable:true}))');await check('IME Enter does not submit','document.querySelectorAll(".ai-message-user").length===1');
    await js('document.getElementById("aiInput").dispatchEvent(new KeyboardEvent("keydown",{key:"Enter",bubbles:true,cancelable:true}))');await wait('document.getElementById("aiMessages").getAttribute("aria-busy")==="false"');await check('Enter submits API message','document.querySelectorAll(".ai-message-user").length===2');
    await submit('Dài');await check('Long answer remains in scroll container','document.querySelector(".ai-scroll").scrollHeight>document.querySelector(".ai-scroll").clientHeight');await shot('long-desktop');
    await viewport(390,844);await click('.ai-new-top');await check('Mobile welcome has no horizontal overflow','document.documentElement.scrollWidth<=innerWidth && document.getElementById("aiComposer").getBoundingClientRect().right<=innerWidth');
    await click('#aiToggleHistory');await pause(200);await check('Mobile history traps workspace','document.getElementById("aiHistory").getAttribute("aria-modal")==="true" && document.querySelector(".ai-workspace").inert');
    await js('document.dispatchEvent(new KeyboardEvent("keydown",{key:"Escape",bubbles:true}))');await check('Escape closes mobile history','document.getElementById("aurelAi").dataset.historyOpen==="false" && !document.querySelector(".ai-workspace").inert');
    await submit('Lên lịch 6 ngày linh hoạt');await check('Mobile timeline and composer stay inside viewport','document.documentElement.scrollWidth<=innerWidth && document.getElementById("aiComposer").getBoundingClientRect().bottom<=innerHeight');
    await check('Flexible budget does not display missing-budget errors or repeat a question','document.querySelector("[data-component=budget]").textContent.includes("Ngân sách linh hoạt") && !document.querySelector("[data-component=budget] .ai-quick-replies") && !document.body.innerText.includes("Chưa cung cấp")');
    await click('.ai-timeline>.ai-small-button');await wait('!!document.querySelector(".leaflet-overlay-pane path")');
    await check('Mobile map stays inside viewport','document.querySelector(".ai-map-canvas").getBoundingClientRect().right<=innerWidth && document.documentElement.scrollWidth<=innerWidth');
    await js('document.querySelector(".ai-map-canvas").scrollIntoView({block:"center"})');await shot('timeline-mobile');
    await send('Page.reload');await wait('document.readyState==="complete" && document.querySelectorAll(".ai-history-thread").length>=3');await click('.ai-history-thread');await wait('document.getElementById("aiMessages").getAttribute("aria-busy")==="false"');
    await check('Stored MySQL-shaped messages restore formatted result components','document.querySelectorAll(".ai-message").length>=2 && !!document.querySelector("[data-component=timeline]")');
    await click('.ai-new-top');await type('Theo dõi tiến trình');await js('document.getElementById("aiComposer").requestSubmit()');
    await wait('document.querySelector(".ai-loading-text")?.textContent==="Đang suy nghĩ…"');
    await check('Normal model work displays thinking and cannot be closed mid-reply','document.getElementById("aiCloseConversation").disabled && document.querySelector(".ai-loading-text").textContent==="Đang suy nghĩ…"');
    await js('window.__progressNode=document.querySelector(".ai-loading-text");window.__progressHeight=document.querySelector(".ai-loading").getBoundingClientRect().height');
    await wait('document.querySelector(".ai-loading-text")?.textContent==="Đang tìm những điểm đến phù hợp…"');
    await check('SSE updates one existing status line and ignores wrong IDs or stale sequence','window.__progressNode===document.querySelector(".ai-loading-text") && document.querySelectorAll(".ai-loading-text").length===1 && Math.abs(document.querySelector(".ai-loading").getBoundingClientRect().height-window.__progressHeight)<2');
    await check('Progress has a polite live region for screen readers','document.getElementById("aiAnnouncer").getAttribute("aria-live")==="polite" && document.getElementById("aiAnnouncer").textContent==="Đang tìm những điểm đến phù hợp…"');
    await shot('progress-mobile');await wait('document.getElementById("aiMessages").getAttribute("aria-busy")==="false"');
    await check('Terminal result clears progress and fragmented UTF-8 is decoded correctly','!document.querySelector(".ai-loading") && !document.querySelector(".ai-answer-text").textContent.includes("�")');
    await click('.ai-new-top');await type('Mã mới');await js('document.getElementById("aiComposer").requestSubmit()');
    await wait('document.querySelector(".ai-loading-text")?.textContent==="Đang xem xét yêu cầu của bạn…"');
    await check('Unknown backend status uses the default without showing arbitrary payload text','!document.body.innerText.includes("INTERNAL_TEXT_MUST_NOT_APPEAR")');await wait('document.getElementById("aiMessages").getAttribute("aria-busy")==="false"');
    await click('.ai-new-top');await type('Ngày 2 nhẹ hơn');await js('document.getElementById("aiComposer").requestSubmit()');
    await wait('document.querySelector(".ai-loading-text")?.textContent==="Đang điều chỉnh lịch trình ngày 2…"');checks.push('Validated day appears in revision status');await wait('document.getElementById("aiMessages").getAttribute("aria-busy")==="false"');
    await click('.ai-new-top');await type('Chậm A');await js('document.getElementById("aiComposer").requestSubmit()');await wait('document.querySelector(".ai-loading-text")?.textContent==="Đang tìm những điểm đến phù hợp…"');
    await click('.ai-new-top');await submit('Yêu cầu B');await pause(1400);
    await check('Late events from a parallel old thread never overwrite the new thread','!document.querySelector(".ai-loading") && document.querySelector(".ai-message-user").textContent==="Yêu cầu B"');
    await click('.ai-new-top');const beforeDrop=requests.filter(r=>r.path.endsWith('/message')).length;await submit('Luồng ngắt');
    await check('Disconnect ends loading and offers manual retry','!!document.querySelector(".ai-error button") && !document.querySelector(".ai-loading")');
    assert.equal(requests.filter(r=>r.path.endsWith('/message')).length,beforeDrop+1);checks.push('Disconnect does not automatically re-submit the task');
    await click('.ai-new-top');await submit('Mạng lỗi');await check('Network failure is localized and clears loading','!!document.querySelector(".ai-error button") && document.querySelector(".ai-error").textContent.includes("Kết nối phản hồi đã ngắt") && !document.querySelector(".ai-loading") && !document.body.innerText.includes("Failed to fetch")');
    await click('.ai-new-top');await submit('Lỗi API SSE');await check('SSE terminal error clears status and offers retry','document.querySelector(".ai-error").textContent.includes("giới hạn yêu cầu") && !!document.querySelector(".ai-error button") && !document.querySelector(".ai-loading")');
    await click('.ai-new-top');await js('window.__fastAIWait=true');await type('Chờ lâu');await js('document.getElementById("aiComposer").requestSubmit()');
    await wait('document.querySelector(".ai-loading-text")?.textContent==="Mình vẫn đang xử lý, bạn chờ một chút nhé…"');checks.push('Long-wait notice reports elapsed waiting only');
    await wait('document.getElementById("aiMessages").getAttribute("aria-busy")==="false"');await check('Maximum timeout clears spinner and permits safe manual retry','!!document.querySelector(".ai-error button") && document.querySelector(".ai-error").textContent.includes("mất nhiều thời gian") && !document.querySelector(".ai-loading")');
    await js('window.__fastAIWait=false');await click('.ai-new-top');await type('Hủy chờ');await js('document.getElementById("aiComposer").requestSubmit()');await wait('!!document.querySelector(".ai-loading-text")');
    await js('window.dispatchEvent(new Event("pagehide"))');await wait('document.getElementById("aiMessages").getAttribute("aria-busy")==="false"');
    await check('Cancellation cleans up status and timers','!document.querySelector(".ai-loading") && document.querySelector(".ai-error").textContent.includes("Đã ngắt chờ")');
    await click('.ai-new-top');await send('Emulation.setEmulatedMedia',{features:[{name:'prefers-reduced-motion',value:'reduce'}]});await type('Theo dõi tiến trình');await js('document.getElementById("aiComposer").requestSubmit()');await wait('!!document.querySelector(".ai-loading-dots i")');
    await check('Reduced motion stops loading animation and mobile layout stays inside viewport','getComputedStyle(document.querySelector(".ai-loading-dots i")).animationName==="none" && document.documentElement.scrollWidth<=innerWidth');await wait('document.getElementById("aiMessages").getAttribute("aria-busy")==="false"');
    await click('.ai-new-top');await submit('Xem Suối Tiên');await wait('document.querySelector(".ai-place-photo img")?.naturalWidth>0');
    await check('Reviewed Suoi Tien card shows real image description ticket and attribution','document.querySelector("[data-component=places]").textContent.includes("180.000") && document.querySelector("[data-component=places]").textContent.includes("công viên khô") && document.querySelector("figcaption").textContent.includes("Syced") && document.querySelector(".ai-place-photo img").src.includes("suoi-tien.jpg")');await shot('suoi-tien-mobile');
    await click('.ai-new-top');await submit('Kiểm tra thứ tự card');
    await check('Introduction precedes every visual panel and the follow-up comes after all panels','(()=>{const b=document.querySelector(".ai-response-body"),intro=b.querySelector(".ai-answer-text"),q=b.querySelector(".ai-follow-up"),panels=[...b.querySelectorAll("[data-component]")];return panels.length===3 && panels.every(p=>(intro.compareDocumentPosition(p)&Node.DOCUMENT_POSITION_FOLLOWING)!==0 && (p.compareDocumentPosition(q)&Node.DOCUMENT_POSITION_FOLLOWING)!==0) && q.nextElementSibling.classList.contains("ai-quick-replies")})()');
    await check('Only the known follow-up is deduplicated and all introduction content is preserved','(()=>{const b=document.querySelector(".ai-response-body"),intro=b.querySelector(".ai-answer-text");return intro.textContent.includes("Giới thiệu các điểm phù hợp.") && intro.textContent.includes("Thông tin hữu ích vẫn được giữ.") && !intro.textContent.includes("Bạn muốn thêm") && (b.textContent.match(/Bạn muốn thêm/g)||[]).length===1})()');
    await check('Redundant selected-city card is removed without an empty grid','!document.querySelector("[data-component=destinations]") && !document.querySelector(".ai-response-body").textContent.includes("Điểm đến AUREL") && document.documentElement.scrollWidth<=innerWidth');
    const storedOrder=[...sessions.values()].find(s=>s.title==='Kiểm tra thứ tự card');assert.equal(storedOrder.messages.at(-1).entities_json.assistant.components.at(-1).items.length,1);checks.push('Display filtering does not mutate stored response data');
    await send('Page.reload');await wait('document.readyState==="complete" && [...document.querySelectorAll(".ai-history-thread")].some(b=>b.title==="Kiểm tra thứ tự card")');
    await js('[...document.querySelectorAll(".ai-history-thread")].find(b=>b.title==="Kiểm tra thứ tự card").click()');await wait('document.getElementById("aiMessages").getAttribute("aria-busy")==="false"');
    await check('Stored history uses the same ordering without duplicate question or selected-city card','!!document.querySelector(".ai-follow-up") && !document.querySelector("[data-component=destinations]") && (document.querySelector(".ai-response-body").textContent.match(/Bạn muốn thêm/g)||[]).length===1');
    await click('.ai-new-top');await submit('Chỉ hỏi một câu');
    await check('Question-only reply stays a single normal paragraph','document.querySelector(".ai-answer-text").textContent==="Bạn muốn đi mấy ngày?" && document.querySelectorAll(".ai-answer-text p").length===1 && !document.querySelector(".ai-follow-up") && !document.querySelector("[data-component]")');
    await click('.ai-new-top');await submit('Chọn điểm đến');
    await check('Destination choices remain available when no location has been selected','document.querySelectorAll("[data-component=destinations] article").length===2 && document.querySelectorAll("[data-component=destinations] button").length===2 && !!document.querySelector(".ai-follow-up")');
    await click('.ai-new-top');await submit('So sánh điểm đến');
    await check('Multiple destination choices preserve the selected city for comparisons','document.querySelectorAll("[data-component=destinations] article").length===2 && document.querySelector("[data-component=destinations]").textContent.includes("Hồ Chí Minh") && document.querySelector("[data-component=destinations]").textContent.includes("Hà Nội")');
    await click('.ai-new-top');await submit('Kiểm tra bố cục ảnh');await wait('[...document.querySelectorAll(".ai-card-media img")].every(i=>i.naturalWidth>0)');
    await check('Old messages hide internal reference IDs branding and technical processing notes','!document.getElementById("aiMessages").innerText.includes("AUREL") && !/aurel:\\d+/i.test(document.getElementById("aiMessages").innerText) && !document.getElementById("aiMessages").innerText.includes("EXIF") && document.querySelectorAll("[data-component=places] article").length===3');
    await viewport(1440);await js('document.getElementById("aiInput").focus()');
    await check('Card images icons and titles align across desktop columns','(()=>{const c=[...document.querySelectorAll("[data-component=places] article")],top=c.map(n=>n.querySelector(".ai-card-heading").getBoundingClientRect().top),heights=c.map(n=>n.querySelector("img").getBoundingClientRect().height);return Math.max(...top)-Math.min(...top)<2 && Math.max(...heights)-Math.min(...heights)<1 && c.every(n=>n.querySelector(".ai-card-symbol").getBoundingClientRect().left<n.querySelector("h3").getBoundingClientRect().left)})()');
    await check('Long photo notes start collapsed and credit links remain accessible','[...document.querySelectorAll(".ai-photo-credits")].every(n=>!n.open) && document.querySelector(".ai-photo-credit-content").textContent.includes("Fixture Photographer") && [...document.querySelectorAll(".ai-photo-credit-content a")].some(a=>a.hostname==="creativecommons.org")');
    await click('.ai-photo-credits summary');await check('Expanded photo credits keep licensing without mislabeling raw notes as an author','document.querySelector(".ai-photo-credits").open && document.querySelector(".ai-photo-credit-content").textContent.includes("CC BY-SA 4.0") && !document.querySelector(".ai-photo-credit-content").textContent.includes("EXIF")');await click('.ai-photo-credits summary');await shot('aligned-place-cards-desktop');
    await viewport(390,844);await check('Photo cards and collapsed credits fit mobile without branding or overflow','document.documentElement.scrollWidth<=innerWidth && [...document.querySelectorAll(".ai-card-heading")].every(h=>h.getBoundingClientRect().right<=innerWidth) && !document.getElementById("aiMessages").innerText.includes("AUREL")');await shot('aligned-place-cards-mobile');await viewport(1440);await js('document.getElementById("aiInput").focus()');
    await send('Input.dispatchMouseEvent',{type:'mouseMoved',x:400,y:200});
    await check('Desktop close icons are hidden until hover or keyboard focus','getComputedStyle(document.querySelector(".ai-thread-close")).opacity==="0" && getComputedStyle(document.getElementById("aiCloseConversation")).opacity==="0"');
    const rowRect=await js('(()=>{const r=document.querySelector(".ai-history-row:nth-child(2)").getBoundingClientRect();return {x:r.x+30,y:r.y+r.height/2}})()');
    await send('Input.dispatchMouseEvent',{type:'mouseMoved',...rowRect});
    await check('Hover reveals only the targeted row close button','getComputedStyle(document.querySelector(".ai-history-row:nth-child(2) .ai-thread-close")).opacity==="1" && getComputedStyle(document.querySelector(".ai-history-row:first-child .ai-thread-close")).opacity==="0"');
    const activeBefore=await js('document.getElementById("aurelAi").dataset.conversationId'),rowsBefore=await js('document.querySelectorAll(".ai-history-thread").length');
    await click('.ai-history-row:nth-child(2) .ai-thread-close');await wait('document.querySelectorAll(".ai-history-thread").length==='+String(rowsBefore-1));
    await check('Deleting another conversation preserves the current conversation','document.getElementById("aurelAi").dataset.conversationId==='+JSON.stringify(activeBefore)+' && document.getElementById("aiSessionNotice").textContent==="Hội thoại đã xóa"');
    const deletedId=Number(requests.filter(r=>r.method==='DELETE').at(-1).path.split('/')[3]);assert.equal(sessions.has(deletedId),false);checks.push('Delete removes the stored conversation and messages');
    await js('document.querySelector(".ai-new-top").focus()');
    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});
    await check('Keyboard focus reveals the top bar close button','document.activeElement.id==="aiCloseConversation" && getComputedStyle(document.getElementById("aiCloseConversation")).opacity==="1"');
    await click('#aiCloseConversation');await wait('document.getElementById("aurelAi").dataset.mode==="welcome"');
    await check('Deleting the active conversation opens a clean composer','document.getElementById("aiCloseConversation").hidden && document.getElementById("aiInput").value===""');
    const expectedOpen=sessions.size;
    await send('Page.reload');await wait('document.readyState==="complete" && document.querySelectorAll(".ai-history-thread").length==='+String(expectedOpen));checks.push('Deleted conversations do not return after reload');
    await viewport(390,844);await click('#aiToggleHistory');
    await check('Touch controls remain usable without hover and do not overflow','getComputedStyle(document.querySelector(".ai-thread-close")).opacity==="1" && document.querySelector(".ai-thread-close").getBoundingClientRect().width>=30 && document.documentElement.scrollWidth<=innerWidth');
    assert.deepEqual(errors,[]);checks.push('No JavaScript errors or external network');console.log(JSON.stringify({status:'PASS',checks:checks.length,names:checks,main_database_writes:0}));
})().catch(error=>{errors.push(error.message);console.error(error.stack);process.exitCode=1;}).finally(async()=>{clearTimeout(timer);fs.writeFileSync(path.join(runtime,'results.json'),JSON.stringify({checks,errors,requests},null,2));chrome?.kill();server.closeAllConnections?.();server.close();for(const p of pending.values())clearTimeout(p.timer);});
