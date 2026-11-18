// Real compiled Blade and frontend, isolated in-memory API; no application DB or live providers.
const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict'),{spawn,spawnSync}=require('node:child_process');
const root=process.cwd(),runtime=path.join(root,'storage/app/private/itinerary-planner-ui'),publicRoot=path.join(root,'public');
const checks=[],errors=[],requests=[],saved=new Map(),proposalPhotos=new Map();let chrome,session,origin,seq=0,buffer='',chatId=1,nextTrip=100,nextItem=1000,nextMessage=50;const pending=new Map();
const photoFixture={url:'/__fixture__/place-photo.svg',alt:'Ảnh kiểm thử địa điểm',author:'Fixture photographer',license:'CC0 1.0',license_url:'https://creativecommons.org/publicdomain/zero/1.0/'};
const thuongBacPhoto=JSON.parse(fs.readFileSync(path.join(root,'resources/content/itinerary/place-photos.json'),'utf8')).places[0].image;
const photoKey=r=>[r.message_id,r.day_number,r.item_index].join('/');
const pause=ms=>new Promise(r=>setTimeout(r,ms)),mime={'.css':'text/css','.js':'text/javascript','.svg':'image/svg+xml','.jpg':'image/jpeg','.png':'image/png','.woff2':'font/woff2','.woff':'font/woff'};
const visitDate=(start,day)=>{if(!start)return null;const date=new Date(start+'T00:00:00Z');date.setUTCDate(date.getUTCDate()+day);return date.toISOString().slice(0,10);};
const backendHours=(item,date)=>{
    if(item.source_place_id!==501)return {status:'unknown',visit_date:date,label:'Chưa có thông tin giờ hoạt động',suggestions:[]};
    const result=spawnSync('php',[path.join(root,'tests/Support/opening_hours_browser_fixture.php')],{input:JSON.stringify({...item,date}),encoding:'utf8',windowsHide:true});
    assert.equal(result.status,0,result.stderr);return JSON.parse(result.stdout);
};
function resource(data,id,revision=1){return {id,revision,schema_version:2,provisional:!data.visit_start_date,start_date:data.visit_start_date || null,title:'Chuyến đi Hồ Chí Minh',location:{id:data.location_id,name:data.location_id===901?'Hồ Chí Minh':'Hà Nội'},
    status:'saved',total_days:data.duration_days,party:{total:data.people,adults:data.people,children:[],infants:[]},stay_mode:data.stay_mode,budget:{limit:data.estimated_budget},
    lodging:{hotel_id:null},days:data.days.map((d,di)=>({date:visitDate(data.visit_start_date,di),items:d.items.map(i=>{
        const old=saved.get(id)?.days.flatMap(day=>day.items).find(item=>item.id===i.id),source=i.photo_reference?proposalPhotos.get(photoKey(i.photo_reference)):null;
        const image=source?.title===i.title && source.address===(i.place_name||'')?source.image:old?.title===i.title && old.place_name===i.place_name?old.image:null;
        const {photo_reference,...fields}=i;return {...fields,id:i.id||nextItem++,image,photo_lookup:Boolean(source||old?.photo_lookup)};
    })}))};}
const server=http.createServer(async(req,res)=>{
    const u=new URL(req.url,origin),json=(body,status=200)=>{res.writeHead(status,{'Content-Type':'application/json'});res.end(JSON.stringify(body));};
    if(u.pathname==='/__fixture__/place-photo.svg'){res.setHeader('Content-Type','image/svg+xml');return res.end('<svg xmlns="http://www.w3.org/2000/svg" width="800" height="500" viewBox="0 0 800 500"><rect width="800" height="500" fill="#e8f2fa"/><path d="M0 350 180 190 370 350 530 140 800 350V500H0Z" fill="#a3c2d8"/><path d="M190 310H610V450H190ZM160 310 400 180 640 310Z" fill="#43677f"/><text x="400" y="475" text-anchor="middle" font-family="sans-serif" font-size="22" fill="#142b49">Ảnh kiểm thử</text></svg>');}
    let raw='';for await(const chunk of req)raw+=chunk;const data=raw?JSON.parse(raw):null;requests.push({path:u.pathname,method:req.method,data});
    if(u.pathname.startsWith('/api/')){
        if(req.headers.authorization!=='Bearer planner-browser-fixture')return json({message:'Unauthenticated'},401);
        if(u.pathname==='/api/me')return json({id:41,name:'Khách kiểm tra',role:'user'});
        if(u.pathname.startsWith('/api/notifications'))return json({data:[],unread_count:0});
        if(u.pathname==='/api/hotels')return json({data:[],last_page:1});
        if(u.pathname==='/api/v2/itineraries/destinations')return json({data:[{id:901,name:'Hồ Chí Minh'},{id:72,name:'Hà Nội'}],place_restrictions:[{location_name:'Hồ Chí Minh',names:['Điểm đã đóng'],status:'closed_permanently'}]});
        if(u.pathname==='/api/v2/itineraries/planner/check-hours'){if(!data?.days)return json({message:'Missing trip data'},422);return json({days:data.days.map((d,i)=>({date:visitDate(data.visit_start_date,i),items:d.items.map(item=>({opening_check:backendHours(item,visitDate(data.visit_start_date,i))}))}))});}
        if(u.pathname==='/api/v2/itineraries/planner/place-photo'){
            await pause(120);
            const source=data.photo_reference?proposalPhotos.get(photoKey(data.photo_reference)):saved.get(data.itinerary_id)?.days.flatMap(d=>d.items).find(i=>i.id===data.item_id);
            if(!source || source.title!==data.title)return json({message:'Place changed'},422);
            const image=source.title==='Thương Bạc'?thuongBacPhoto:source.image || null;source.image=image;return json({image});
        }
        if(u.pathname==='/api/v2/itineraries/places/501/hours')return json({opening_check:backendHours({source_place_id:501},u.searchParams.get('date')),hours_text:'Giờ tham khảo theo dữ liệu mẫu'});
        if(u.pathname==='/api/v2/itineraries/planner/generate'){await pause(450);return json({message:'Dịch vụ AI đang tạm gián đoạn.'},503);}
        if(u.pathname==='/api/v2/itineraries/planner'){const id=nextTrip++,trip=resource(data,id);saved.set(id,trip);return json({data:trip},201);}
        if(u.pathname==='/api/v2/itineraries')return json({data:[...saved.values()],meta:{current_page:1,last_page:1}});
        if(u.pathname==='/api/itineraries')return json([{id:1,schema_version:1,title:'Lịch cũ vẫn còn',destination:'Hồ Chí Minh',total_days:1}]);
        if(u.pathname==='/api/itineraries/1')return json({id:1,schema_version:1,title:'Lịch cũ vẫn còn',destination:'Hồ Chí Minh',total_days:1,days:[{day_number:1,items:[{title:'Hoạt động cũ',start_time:'08:00',end_time:'09:00'}]}]});
        const match=u.pathname.match(/^\/api\/v2\/itineraries\/(\d+)(\/planner|\/pdf)?$/);
        if(match){const id=Number(match[1]),trip=saved.get(id);if(!trip)return json({},404);
            if(match[2]==='/pdf'){res.writeHead(200,{'Content-Type':'application/pdf'});return res.end('%PDF-1.4\nBrowser download fixture');}
            if(match[2]==='/planner'){if(data.expected_revision!==trip.revision)return json({},409);const updated=resource(data,id,trip.revision+1);saved.set(id,updated);return json({data:updated});}
            return json({data:trip});}
        if(u.pathname==='/api/chat')return json([]);
        if(u.pathname==='/api/chat/start')return json({session_id:chatId++},201);
        if(/\/message$/.test(u.pathname)){
            const requestedDate=data.message.match(/^Ngày đi (\d{4}-\d{2}-\d{2})$/)?.[1];
            if(requestedDate){const current=data.planner_context;return json({message:'Đề xuất dùng ngày đi đã cung cấp.',message_id:nextMessage++,assistant:{state:{location_id:current.location_id,duration_days:current.duration_days},components:[{type:'timeline',target_day:null,visit_start_date:requestedDate,days:current.days.map((d,i)=>({day_number:i+1,date:visitDate(requestedDate,i),items:d.items.map(item=>({...item,kind:item.place_name?'place':'break',address:item.place_name}))})),budget:{status:'incomplete'}}]}});}
            if(data.message==='Gợi ý địa điểm có giờ')return json({message:'Bạn có thể tham khảo địa điểm này.',message_id:nextMessage++,assistant:{state:{location_id:901},components:[{type:'places',items:[
                {id:'sheet:501',source_place_id:501,provider:'google_sheet',name:'Owned hours café',address:'Địa chỉ mẫu',maps_url:'https://www.google.com/maps/search/?api=1&query=Owned%20hours%20cafe',source:'Thông tin công khai'}
            ]}]}});
            if(data.message==='Gợi ý quán từ Sheet')return json({message:'Bạn có thể tham khảo các địa điểm này.',message_id:nextMessage++,assistant:{state:{location_id:901},components:[{type:'places',items:[
                {id:'sheet:1',provider:'google_sheet',name:'Owned Sheet café',address:'Địa chỉ gốc',phone:'0123456789',maps_url:'https://www.google.com/maps/search/?api=1&query=Original%20sheet%20branch',maps_link_type:'Tìm theo tên + địa chỉ; chưa xác thực ghim',price_reference:{min:null,max:null},source:'Thông tin công khai · cần kiểm tra lại'},
                {id:'sheet:2',provider:'google_sheet',name:'Owned free park',maps_url:'https://goo.gl/maps/ownedPark',price_reference:{min:0,max:0,unit:'Khu vực công cộng'},source:'Thông tin công khai'},
                {id:'sheet:3',provider:'google_sheet',name:'Owned unsafe row',maps_url:'https://www.google.com.evil.invalid/maps/',price_reference:{min:null,max:null}}
            ]}]}});
            if(data.message==='Gợi ý điểm đã đóng')return json({message:'Tham quan Điểm đã đóng.',message_id:nextMessage++,assistant:{state:{location_id:901},components:[
                {type:'places',items:[{name:'Điểm đã đóng',address:'Địa chỉ cũ'}]},
                {type:'timeline',days:[{day_number:1,items:[{kind:'place',title:'Điểm đã đóng',start_time:'09:00',end_time:'10:00'}]}]}
            ]}});
            if(data.message.includes('Gợi ý khách sạn')){
                const names=['Brilliant Hotel Danang','Hyatt Regency Danang Resort and Spa','InterContinental Danang Sun Peninsula','Novotel Danang Premier Han River'];
                return json({message:'Dưới đây là một số khách sạn tại Đà Nẵng mà bạn có thể cân nhắc: '+names.join(', ')+'.',message_id:nextMessage++,assistant:{state:{location_id:901},components:[{type:'hotels',items:names.map((name,i)=>({id:i+1,name,address:'Đà Nẵng',url:'/hotels/'+(i+1)}))}]}});
            }
            const expand=data.message.includes('chia ra nhiều ngày'),partialResize=data.message.includes('Chỉnh riêng ngày 2 nhưng đổi số ngày'),afternoon=data.message.includes('thêm lịch buổi chiều');
            const duration=expand?4:partialResize?5:data.planner_context.duration_days;
            const days=Array.from({length:duration},(_,i)=>({day_number:i+1,items:expand?
                [{kind:'place',title:'Khám phá ngày '+(i+1),description:'Tham quan, khám phá địa điểm và nghỉ ngơi theo sở thích.',address:'Địa điểm tham khảo ngày '+(i+1),start_time:'09:00',end_time:'10:00',image:photoFixture}]:
                i===1?[{kind:'place',title:'Bảo tàng được gợi ý',description:'Gợi ý từ API kiểm thử',address:'Địa chỉ tham khảo',start_time:'09:00',end_time:'10:00'}]:data.planner_context.days[i]?.items||[]}));
            if(expand)days[3].items=[{kind:'place',title:'Thương Bạc',address:'Thương Bạc, Huế',coordinates:[107.5855685,16.46803325],start_time:'09:00',end_time:'10:00',image:null}];
            if(data.message.includes('Đề xuất thiếu ngày'))days.at(-1).day_number=1;
            if(afternoon)days[0].items=[...data.planner_context.days[0].items,{kind:'place',title:'Khám phá buổi chiều',description:'Bạn có thể khám phá vào buổi chiều.',address:'Điểm tham quan buổi chiều',start_time:'13:30',end_time:'14:30',image:photoFixture}];
            const messageId=nextMessage++;for(const day of days)day.items.forEach((item,index)=>{if(item.kind==='place')proposalPhotos.set(photoKey({message_id:messageId,day_number:day.day_number,item_index:index}),item);});
            return json({message:expand?'Mình đề xuất chia chuyến đi thành 4 ngày. Bạn có thể áp dụng hoặc bỏ qua.':'Mình đề xuất thay đổi ngày 2. Bạn có thể áp dụng hoặc bỏ qua.',message_id:messageId,
                assistant:{state:{location_id:data.message.includes('Đổi điểm đến')?72:901,duration_days:duration,stay_mode:data.planner_context.stay_mode},
                    components:[{type:'timeline',title:'Đề xuất lịch trình',target_day:afternoon?1:expand?null:2,target_window:afternoon?{start:'13:00',end:'17:30'}:null,provisional:true,days,budget:{status:'incomplete'},warnings:[],assumptions:[]}],quick_replies:[]}});
        }
        return json({message:'Unknown fixture'},404);
    }
    if(u.pathname==='/itineraries'){res.setHeader('Content-Type','text/html;charset=utf-8');return res.end(fs.readFileSync(path.join(runtime,'itineraries.html'),'utf8').replaceAll('http://localhost',origin));}
    if(u.pathname==='/header-test.css'){
        const manifest=JSON.parse(fs.readFileSync(path.join(publicRoot,'build/manifest.json'),'utf8'));
        res.setHeader('Content-Type','text/css');return res.end(['resources/css/app.css','resources/css/site-shell.css'].map(key=>fs.readFileSync(path.join(publicRoot,'build',manifest[key].file),'utf8')).join('\n'));
    }
    const file=path.resolve(publicRoot,'.'+decodeURIComponent(u.pathname));if(!file.startsWith(publicRoot+path.sep)||!fs.existsSync(file)||!fs.statSync(file).isFile()){res.writeHead(404);return res.end();}
    res.setHeader('Content-Type',mime[path.extname(file)]||'application/octet-stream');fs.createReadStream(file).pipe(res);
});
const send=(method,params={},sid=session)=>new Promise((resolve,reject)=>{const id=++seq,timer=setTimeout(()=>{pending.delete(id);reject(Error(method+' timed out'));},15000);pending.set(id,{resolve,reject,timer});chrome.stdio[3].write(JSON.stringify({id,method,params,...(sid?{sessionId:sid}:{})})+'\0');});
const js=async expression=>{const r=await send('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true,userGesture:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value;};
const wait=async expression=>{for(let i=0;i<100;i++){if(await js(expression))return;await pause(70);}throw Error('Wait failed: '+expression);};
const check=async(name,expression)=>{assert.equal(await js(expression),true,name);checks.push(name);};
const click=selector=>js('document.querySelector('+JSON.stringify(selector)+').click()');
const answerConfirm=answer=>js('new Promise(resolve=>{document.getElementById("tripConfirmDialog").addEventListener("close",resolve,{once:true});document.getElementById('+JSON.stringify('tripConfirm')+'+'+JSON.stringify(answer)+').click();})');
const pointerClick=async selector=>{
    await js('document.querySelector('+JSON.stringify(selector)+').scrollIntoView({block:"center",behavior:"instant"})');await pause(100);
    const point=await js('(()=>{const b=document.querySelector('+JSON.stringify(selector)+'),r=b.getBoundingClientRect(),x=r.x+r.width/2,y=r.y+r.height/2,hit=document.elementFromPoint(x,y);return {x,y,visible:b.contains(hit),hit:hit?.className};})()');
    if(!point.visible)await shot('unreachable-proposal');
    assert(point.visible,'The proposal button must be reachable by a pointer: '+JSON.stringify(point));
    await send('Input.dispatchMouseEvent',{type:'mousePressed',x:point.x,y:point.y,button:'left',clickCount:1});
    await send('Input.dispatchMouseEvent',{type:'mouseReleased',x:point.x,y:point.y,button:'left',clickCount:1});
};
const fill=values=>js('for(const [id,value] of Object.entries('+JSON.stringify(values)+')){const n=document.getElementById(id);n.value=value;n.dispatchEvent(new Event("input",{bubbles:true}));}');
const shot=async name=>{await pause(100);const r=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync(path.join(runtime,name+'.png'),Buffer.from(r.data,'base64'));};
const viewport=async(width,height=950)=>{await send('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:width<768});await pause(100);};
const add=async(title,day=0,start='09:00',end='10:00')=>{await click('[data-day="'+day+'"] .trip-day-add');await fill({tripActivityName:title,tripActivityPlace:'Địa điểm '+title,tripActivityStart:start,tripActivityEnd:end,tripActivityNotes:'Khám phá địa điểm và nghỉ ngơi theo sở thích.'});await js('document.getElementById("tripActivityForm").requestSubmit()');await wait('!document.getElementById("tripActivityDialog").open');};
const chat=async(message='Chỉnh ngày 2')=>{await fill({aiInput:message});await js('document.getElementById("aiComposer").requestSubmit()');await wait('document.querySelectorAll(".trip-proposal").length>0 && !document.querySelector(".ai-loading")');};
const useTripDate=async date=>{await fill({aiInput:'Ngày đi '+date});await js('document.getElementById("aiComposer").requestSubmit()');await wait('!!document.querySelector(".trip-proposal[data-resolved=false]") && !document.querySelector(".ai-loading")');await click('.trip-proposal[data-resolved=false] button:first-child');await wait('window.AurelPlanner.visitDate()==='+JSON.stringify(date));};
const timer=setTimeout(()=>{errors.push('Browser test timeout');chrome?.kill();server.closeAllConnections?.();server.close();process.exitCode=1;},150000);
(async()=>{
    await new Promise(r=>server.listen(0,'127.0.0.1',r));origin='http://127.0.0.1:'+server.address().port;
    chrome=spawn('C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--disable-gpu','--no-first-run','--no-default-browser-check','--disable-background-networking','--disable-sync','--disable-extensions','--remote-debugging-pipe','--user-data-dir='+path.join(runtime,'chrome-'+Date.now())],{windowsHide:true,stdio:['ignore','ignore','ignore','pipe','pipe']});
    chrome.stdio[4].setEncoding('utf8');chrome.stdio[4].on('data',chunk=>{buffer+=chunk;let i;while((i=buffer.indexOf('\0'))!==-1){const m=JSON.parse(buffer.slice(0,i));buffer=buffer.slice(i+1);const p=pending.get(m.id);if(p){pending.delete(m.id);clearTimeout(p.timer);m.error?p.reject(Error(m.error.message)):p.resolve(m.result);}else if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description||m.params.exceptionDetails.text);else if(m.method==='Fetch.requestPaused'){const u=new URL(m.params.request.url),allowed=u.origin===origin;if(!allowed)errors.push('External request: '+u.origin+u.pathname);send(allowed?'Fetch.continueRequest':'Fetch.failRequest',{requestId:m.params.requestId,...(allowed?{}:{errorReason:'BlockedByClient'})},m.sessionId).catch(()=>{});}}});
    const target=await send('Target.createTarget',{url:'about:blank'},null);session=(await send('Target.attachToTarget',{targetId:target.targetId,flatten:true},null)).sessionId;
    await send('Page.enable');await send('Runtime.enable');await send('Fetch.enable',{patterns:[{urlPattern:'*'}]});
    await send('Browser.setDownloadBehavior',{behavior:'allow',downloadPath:runtime});
    await send('Page.addScriptToEvaluateOnNewDocument',{source:'if(location.origin==='+JSON.stringify(origin)+'){localStorage.setItem("token","planner-browser-fixture");localStorage.setItem("user",JSON.stringify({id:41,name:"Khách kiểm tra",role:"user"}));}'});
    await viewport(1440);await send('Page.navigate',{url:origin+'/itineraries'});await wait('document.readyState==="complete" && !document.getElementById("tripGenerate").disabled');
    await check('Step 1 has only trip facts and real destination options','!document.getElementById("tripSetup").hidden && document.getElementById("tripEditor").hidden && !document.querySelector("#tripForm input[type=date]") && document.getElementById("tripDestination").options.length===3 && !document.getElementById("itineraryCreate")');
    await check('AI planner uses the customer account sidebar and top bar','!!document.getElementById("sidebar") && document.querySelector(".topbar-start").textContent.includes("Không gian của bạn") && document.querySelector(".sidebar-nav-item.active")?.dataset.nav==="itineraries" && !document.querySelector("nav.hv-header,.hv-footer")');
    await check('Itinerary content stays inside the account workspace','document.getElementById("tripPlanner").getBoundingClientRect().top>=document.querySelector(".topbar").getBoundingClientRect().bottom-1 && document.getElementById("tripPlanner").getBoundingClientRect().left>=document.getElementById("sidebar").getBoundingClientRect().right');
    await shot('step1-desktop');
    await click('[name=tripStay][value=day_trip]');await check('Day trip limits duration to one','document.getElementById("tripDays").value==="1" && document.getElementById("tripDays").disabled');
    await click('[name=tripStay][value=own_accommodation]');await fill({tripDestination:901,tripDays:3,tripPeople:2});
    await js('document.getElementById("tripForm").requestSubmit()');
    await check('AI generation shows a live waiting status and prevents duplicate requests','!document.getElementById("tripGenerationStatus").hidden && document.getElementById("tripForm").getAttribute("aria-busy")==="true" && document.getElementById("tripGenerate").disabled && document.getElementById("tripNewButton").disabled && document.getElementById("tripGenerationElapsed").textContent.startsWith("Đã chờ")');
    await wait('!document.getElementById("tripManualFallback").hidden');await check('Generation status stops when the API fails','document.getElementById("tripGenerationStatus").hidden && !document.getElementById("tripGenerate").disabled');
    await check('AI failure is visible with an explicit manual option','!document.getElementById("tripSetup").hidden && document.getElementById("tripNotice").textContent.includes("gián đoạn")');
    await click('#tripContinueManual');await wait('!document.getElementById("tripEditor").hidden');
    await check('Laptop columns use the requested 60/40 ratio','(()=>{const b=document.querySelector(".trip-board").getBoundingClientRect(),a=document.querySelector(".trip-assistant").getBoundingClientRect();return Math.abs(b.width/a.width-1.5)<.03 && Math.abs(b.top-a.top)<2})()');
    await js('document.getElementById("aiExamples").open=true');await click('[data-ai-example=places]');await wait('!!document.querySelector(".trip-proposal") && !document.querySelector(".ai-loading")');
    assert.equal(requests.filter(r=>r.path.endsWith('/message')).at(-1).data.message,'Gợi ý địa điểm tham quan tại Hồ Chí Minh');checks.push('Embedded assistant suggestions use the active trip after standalone cleanup');await click('.ai-new-top');
    await add('Dạo phố');await add('Ăn trưa',0,'11:00','12:00');
    await check('Manual add displays activity, place and times','document.querySelectorAll(".trip-activity").length===2 && document.getElementById("tripDaysList").textContent.includes("Địa điểm Dạo phố")');
    await wait('!!document.querySelector(".trip-opening-hours .angel-hours-summary")');
    await check('Missing hours show one neutral clock line without invented hours or a dropdown','(()=>{const h=document.querySelector(".trip-opening-hours");return h.textContent==="Chưa có thông tin giờ hoạt động" && !h.querySelector("details") && h.querySelector(".angel-hours-summary").dataset.status==="unknown" && !!h.querySelector("svg")})()');
    await check('A historical open status falls back to an unconfirmed hours label','(()=>{const h=document.createElement("div");window.AngelOpeningHours.render(h,{has_data:true,status:"open",summary:"Đang mở cửa · Đóng cửa vào 22:00",evaluated_at:new Date(Date.now()-180000).toISOString(),weekly:[]});return h.querySelector("summary").dataset.status==="unknown" && h.querySelector("summary").textContent.startsWith("Giờ hoạt động:") && !h.querySelector("summary").textContent.includes("Đang mở cửa")})()');
    await check('Place links open Google Maps using the actual place query','(()=>{const a=document.querySelector(".trip-map-link"),u=new URL(a.href);return u.hostname==="www.google.com" && u.pathname==="/maps/search/" && u.searchParams.get("api")==="1" && u.searchParams.get("query").includes("Dạo phố") && a.target==="_blank"})()');
    await click('[data-day="0"] [data-item="1"] button[aria-label$=" lên"]');
    await check('Reordering changes the actual activity order','document.querySelector(".trip-activity h3").textContent==="Ăn trưa"');
    await click('[data-day="0"] [data-item="0"] .trip-activity-actions button');await fill({tripActivityName:'Ăn trưa mới',tripActivityDay:1,tripActivityStart:'12:00',tripActivityEnd:'11:00'});
    await js('document.getElementById("tripActivityForm").requestSubmit()');await check('Invalid end time keeps the editor open','document.getElementById("tripActivityDialog").open && !document.getElementById("tripActivityEnd").checkValidity()');
    await fill({tripActivityEnd:'13:00'});await js('document.getElementById("tripActivityForm").requestSubmit()');
    await check('Editing moves the activity to another day and changes its time','document.querySelector(".trip-day:nth-child(2) .trip-activity").textContent.includes("12:00") && document.querySelector(".trip-day:nth-child(2) h3").textContent==="Ăn trưa mới"');
    await click('[data-day="1"] .trip-remove');await check('Delete removes the activity','!document.querySelector(".trip-day:nth-child(2) .trip-activity")');await click('#tripUndo');
    await check('Undo restores deleted data','document.querySelector(".trip-day:nth-child(2) h3").textContent==="Ăn trưa mới"');
    await click('#tripChangeInfo');await fill({tripPeople:3,tripDays:4});await js('document.getElementById("tripForm").requestSubmit()');
    await check('Returning to trip facts preserves existing activities','document.querySelectorAll(".trip-day").length===4 && document.querySelectorAll(".trip-activity").length===2 && document.getElementById("tripSummary").textContent.includes("3 người")');
    await chat();await check('AI only offers a change until accepted','document.querySelector(".trip-day:nth-child(2) h3").textContent==="Ăn trưa mới" && document.querySelectorAll(".trip-proposal button").length===2');
    assert.equal(requests.filter(r=>r.path.endsWith('/message')).at(-1).data.planner_context.days[1].items[0].title,'Ăn trưa mới');checks.push('AI receives the current manual itinerary');
    await click('.trip-proposal button:last-child');await check('Skip leaves the manual itinerary intact','document.querySelector(".trip-day:nth-child(2) h3").textContent==="Ăn trưa mới"');
    await click('.ai-new-top');await chat();await click('.trip-proposal button:first-child');
    await check('Apply updates only the proposed day','document.querySelector(".trip-day:nth-child(2) h3").textContent==="Bảo tàng được gợi ý" && document.querySelector(".trip-day:nth-child(1) h3").textContent==="Dạo phố"');
    await click('#tripUndo');await check('AI application can be undone','document.querySelector(".trip-day:nth-child(2) h3").textContent==="Ăn trưa mới"');
    await click('.ai-new-top');await chat();await add('Đi dạo chiều',2,'15:00','16:00');await click('.trip-proposal button:first-child');
    await check('Stale AI proposal cannot overwrite a newer edit','document.getElementById("tripNotice").textContent.includes("đã được chỉnh") && document.querySelector(".trip-day:nth-child(3) h3").textContent==="Đi dạo chiều"');
    await click('#tripSave');await wait('document.getElementById("tripNotice").textContent.includes("Đã lưu lịch trình")');
    assert.equal(saved.size,1);assert.equal([...saved.values()][0].budget.limit,null);checks.push('Saving persists the actual manual itinerary and optional budget');
    await click('#tripPdf');await wait('document.getElementById("tripNotice").textContent.includes("Đã tải PDF")');assert(requests.some(r=>/\/pdf$/.test(r.path)));checks.push('PDF button requests and downloads a PDF');
    await click('#tripSavedButton');await wait('document.querySelector(".trip-library-row")');await click('.trip-library-row button');await wait('!document.getElementById("tripLibraryDialog").open');
    await check('Saved itinerary reloads through its API','document.querySelectorAll(".trip-activity").length===3 && document.getElementById("tripSaveStatus").textContent==="Đã lưu"');
    await click('[data-day="0"] .trip-remove');await click('#tripSave');await wait('document.getElementById("tripSaveStatus").textContent==="Đã lưu"');
    await click('#tripUndo');await click('#tripSave');await wait('document.getElementById("tripSaveStatus").textContent==="Đã lưu"');
    const restored=requests.filter(r=>/\/planner$/.test(r.path)&&r.method==='PUT').at(-1).data.days[0].items[0];assert.equal(restored.id,null);checks.push('Undo after a saved deletion safely recreates the deleted activity');
    await click('#tripChangeInfo');await fill({tripDays:1});await js('document.getElementById("tripForm").requestSubmit()');
    await wait('document.getElementById("tripConfirmDialog").open');await click('#tripConfirmAccept');await wait('document.querySelectorAll(".trip-day").length===1');
    await click('#tripSave');await wait('document.getElementById("tripSaveStatus").textContent==="Đã lưu"');
    const oneDay=await js('window.AurelPlanner.context()'),savedId=[...saved.keys()][0];
    const expandMessage='Có thêm được 3 -> 4 ngày không ý là tôi muốn chia ra nhiều ngày có thể chơi ấy cụ thể';
    await click('.ai-new-top');await chat(expandMessage);
    await check('Duration proposal clearly describes 1 to 4 days without applying it','document.querySelector(".trip-proposal").textContent.includes("1 ngày sang 4 ngày") && window.AurelPlanner.context().duration_days===1 && document.querySelectorAll(".trip-day").length===1');
    assert.equal(saved.get(savedId).total_days,1);
    await check('Itinerary and AI text have readable sizes and contrast',`(()=>{
        const luminance=color=>{const values=color.match(/[\\d.]+/g).slice(0,3).map(v=>{v=Number(v)/255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4});return values[0]*.2126+values[1]*.7152+values[2]*.0722;};
        const contrast=(a,b)=>{const x=luminance(a),y=luminance(b);return (Math.max(x,y)+.05)/(Math.min(x,y)+.05);};
        return [['.trip-activity-description',14],['.trip-activity-place',12],['.trip-activity-time span',12],['.trip-activity-actions button',12],['.trip-proposal p',13],['.trip-assistant textarea',14]].every(([selector,size])=>{const style=getComputedStyle(document.querySelector(selector));return parseFloat(style.fontSize)>=size && contrast(style.color,'rgb(255,255,255)')>=4.5;}) && (()=>{const style=getComputedStyle(document.querySelector('.trip-proposal .trip-button-primary'));return style.color==='rgb(255, 255, 255)' && contrast(style.color,style.backgroundColor)>=4.5;})();
    })()`);
    await js('document.querySelector(".trip-proposal").scrollIntoView({block:"nearest"})');await shot('duration-proposal-desktop');await click('.trip-proposal button:last-child');
    assert.deepEqual(await js('window.AurelPlanner.context()'),oneDay);checks.push('Skipping a longer proposal preserves the one-day trip');
    await click('.ai-new-top');await chat(expandMessage);await pointerClick('.trip-proposal button:first-child');
    await check('Apply expands all days and synchronizes the summary and trip form','window.AurelPlanner.context().duration_days===4 && document.querySelectorAll(".trip-day").length===4 && document.querySelectorAll(".trip-activity").length===4 && document.getElementById("tripSummary").textContent.startsWith("4 ngày") && document.getElementById("tripDays").value==="4"');
    assert.equal(saved.get(savedId).total_days,1);checks.push('Applying a duration change stays local until Save');
    await click('#tripUndo');assert.deepEqual(await js('window.AurelPlanner.context()'),oneDay);
    await check('Undo restores the original day count, activities and form','document.querySelectorAll(".trip-day").length===1 && document.getElementById("tripDays").value==="1"');
    await click('.ai-new-top');await chat(expandMessage);await click('.trip-proposal button:first-child');await click('#tripSave');await wait('document.getElementById("tripSaveStatus").textContent==="Đã lưu"');
    assert.equal(saved.size,1);assert.equal(saved.get(savedId).total_days,4);assert.equal(saved.get(savedId).days.length,4);
    const expandedWrite=requests.filter(r=>/\/planner$/.test(r.path)&&r.method==='PUT').at(-1).data;
    assert.equal(expandedWrite.duration_days,4);assert.equal(expandedWrite.days.length,4);assert.equal(expandedWrite.people,oneDay.people);assert.equal(expandedWrite.stay_mode,oneDay.stay_mode);checks.push('Saving expanded AI days keeps the trip ID and other trip facts');
    await click('#tripUndo');await check('A saved expansion can also be undone safely','window.AurelPlanner.context().duration_days===1 && document.getElementById("tripDays").value==="1"');
    await click('#tripSave');await wait('document.getElementById("tripSaveStatus").textContent==="Đã lưu"');assert.equal(saved.get(savedId).total_days,1);
    await click('.ai-new-top');await chat(expandMessage);await click('.trip-proposal button:first-child');await click('#tripSave');await wait('document.getElementById("tripSaveStatus").textContent==="Đã lưu"');
    await click('#tripSavedButton');await wait('document.querySelector(".trip-library-row")');await click('.trip-library-row button');await wait('!document.getElementById("tripLibraryDialog").open');
    await check('Reopening the expanded trip retains all four days','window.AurelPlanner.context().duration_days===4 && document.querySelectorAll(".trip-activity").length===4 && document.querySelector(".trip-day:nth-child(4) h3").textContent==="Thương Bạc"');
    await js('document.querySelector(".trip-day:nth-child(4)").scrollIntoView({block:"center"})');
    await wait('document.querySelector(".trip-day:nth-child(4) .ai-place-photo img")?.naturalWidth>0');
    await check('The fourth place loads its real photo without an image-search substitute','document.querySelector(".trip-day:nth-child(4) .ai-place-photo img").src.endsWith("/image/ai-places/thuong-bac-hue.jpg") && !document.querySelector(".trip-photo-search")');
    assert(requests.some(r=>r.path.endsWith('/planner/place-photo')));checks.push('Missing photos use the authenticated source-bound lookup');
    await pointerClick('.trip-day:nth-child(4) .ai-place-photo-preview');await wait('document.querySelector("#aiPhotoViewer>img")?.naturalWidth>0');
    await check('The actual Thuong Bac photo opens with its author and public-domain credit','document.getElementById("aiPhotoViewerTitle").textContent==="Thương Bạc" && document.getElementById("aiPhotoViewer").textContent.includes("Lưu Ly") && document.getElementById("aiPhotoViewer").textContent.includes("Public domain")');await shot('thuong-bac-real-photo');await click('.ai-photo-close');await wait('!document.getElementById("aiPhotoViewer").open');
    await wait('document.querySelector(".trip-activity .ai-place-photo img")?.naturalWidth>0');
    await check('Place photos survive Apply, Save and reopening','document.querySelectorAll(".trip-activity .ai-place-photo").length===4 && !document.getElementById("tripDaysList").textContent.includes("Chi phí chưa xác định")');
    assert(expandedWrite.days[0].items[0].photo_reference.message_id>0);assert.equal(expandedWrite.days[0].items[0].photo_reference.day_number,1);assert.equal(expandedWrite.days[0].items[0].photo_reference.item_index,0);assert(!('image' in expandedWrite.days[0].items[0]));checks.push('Save sends an owned photo reference instead of trusting image metadata');
    await pointerClick('.trip-activity .ai-place-photo-preview');await wait('document.getElementById("aiPhotoViewer")?.open && document.querySelector("#aiPhotoViewer>img")?.naturalWidth>0');
    await check('Clicking a photo opens an attributed full-size view','document.getElementById("aiPhotoViewerTitle").textContent==="Khám phá ngày 1" && document.getElementById("aiPhotoViewer").textContent.includes("Fixture photographer") && document.getElementById("aiPhotoViewer").textContent.includes("CC0 1.0")');await shot('photo-viewer-desktop');
    await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
    await wait('!document.getElementById("aiPhotoViewer").open && !document.documentElement.classList.contains("ai-photo-open")');
    await check('Escape closes the photo and restores focus','!document.getElementById("aiPhotoViewer").open && document.activeElement.classList.contains("ai-place-photo-preview") && !document.documentElement.classList.contains("ai-photo-open")');
    await click('[data-day="0"] .trip-activity-actions button');await fill({tripActivityPlace:'Địa điểm khách vừa đổi'});await js('document.getElementById("tripActivityForm").requestSubmit()');
    await check('Changing a place removes the old photo and never substitutes an image-search link','!document.querySelector(".trip-day:first-child .ai-place-photo") && document.querySelector(".trip-day:first-child .trip-photo-status").textContent.includes("Chưa có ảnh phù hợp") && !document.querySelector(".trip-photo-search") && !window.AurelPlanner.context().days[0].items[0].photo_reference');
    await click('#tripUndo');await check('Undo restores the matching place photo','!!document.querySelector(".trip-day:first-child .ai-place-photo")');
    await click('[data-day="0"] .trip-day-add');await fill({tripActivityName:'Thời gian tự do',tripActivityNotes:'Nghỉ ngơi hoặc tự khám phá; không phát sinh đặt dịch vụ hoặc chi phí đã xác nhận.',tripActivityStart:'11:00',tripActivityEnd:'12:00'});await js('document.getElementById("tripActivityForm").requestSubmit()');
    await check('Breaks use friendly guidance without an unknown-cost or photo-search row','(()=>{const card=document.querySelector(".trip-day:first-child .trip-activity:last-of-type");return card.textContent.includes("Bạn có thể nghỉ ngơi, lấy lại sức") && !card.querySelector(".trip-activity-cost,.trip-photo-search")})()');
    await click('[data-day="0"] .trip-activity:last-of-type .trip-activity-actions button');await fill({tripActivityNotes:'Hẹn bạn lúc 15:00 ở cổng phía Tây.',tripActivityCost:150000});await js('document.getElementById("tripActivityForm").requestSubmit()');
    await check('Custom notes and explicit activity costs remain editable and visible','(()=>{const card=document.querySelector(".trip-day:first-child .trip-activity:last-of-type");return card.textContent.includes("Hẹn bạn lúc 15:00 ở cổng phía Tây.") && card.querySelector(".trip-activity-cost").textContent.includes("150.000")})()');
    await click('#tripUndo');await click('#tripUndo');await click('#tripSave');await wait('document.getElementById("tripSaveStatus").textContent==="Đã lưu"');
    await click('#tripSavedButton');await wait('!!document.querySelector(".trip-library-row")');
    await check('Saved-trip library has a circular refresh icon to the left of Close','document.querySelector(".trip-library-navigation button:first-child").id==="tripLibraryRefresh" && document.querySelector("#tripLibraryRefresh use").getAttribute("href").endsWith("#refresh") && !document.getElementById("tripLibraryRefresh").hasAttribute("data-trip-close") && document.querySelector(".trip-library-navigation button:last-child").getAttribute("aria-label")==="Đóng"');
    const beforeLibraryRefresh=await js('window.AurelPlanner.context()'),libraryReads=requests.filter(r=>r.path==='/api/v2/itineraries'||r.path==='/api/itineraries').length;
    await click('#tripLibraryRefresh');await wait('!document.getElementById("tripLibraryRefresh").disabled && !!document.querySelector(".trip-library-row")');
    assert.equal(requests.filter(r=>r.path==='/api/v2/itineraries'||r.path==='/api/itineraries').length,libraryReads+2);assert.deepEqual(await js('window.AurelPlanner.context()'),beforeLibraryRefresh);await check('Refresh reloads both saved-trip APIs and keeps the dialog and current itinerary open','document.getElementById("tripLibraryDialog").open && window.AurelPlanner.context().duration_days===4');await click('.trip-library-navigation button:last-child');
    const beforeAfternoon=await js('window.AurelPlanner.context()');await click('.ai-new-top');await chat('Ngày 1 cho tôi thêm lịch buổi chiều đi');
    await check('Afternoon revision stays a proposal until accepted','document.querySelector(".trip-proposal").textContent.includes("buổi chiều ngày 1") && document.querySelectorAll(".trip-activity").length===4');
    await click('.trip-proposal button:first-child');const afterAfternoon=await js('window.AurelPlanner.context()');
    assert.deepEqual(afterAfternoon.days[0].items[0],beforeAfternoon.days[0].items[0]);assert.deepEqual(afterAfternoon.days.slice(1),beforeAfternoon.days.slice(1));assert.equal(afterAfternoon.days[0].items[1].title,'Khám phá buổi chiều');checks.push('Applying an afternoon proposal preserves the morning activity, its ID and every other day');
    await check('Google Maps links also appear inside AI timelines','[...document.querySelectorAll(".ai-map-link")].every(a=>new URL(a.href).hostname==="www.google.com") && !!document.querySelector(".ai-map-link")');
    await click('#tripUndo');assert.deepEqual(await js('window.AurelPlanner.context()'),beforeAfternoon);checks.push('Afternoon changes can be undone without losing the original morning');await click('#tripSave');await wait('document.getElementById("tripSaveStatus").textContent==="Đã lưu"');
    await click('.ai-new-top');await fill({aiInput:'Gợi ý khách sạn tại Đà Nẵng'});await js('document.getElementById("aiComposer").requestSubmit()');await wait('document.querySelectorAll("[data-component=hotels] .ai-result-card").length===4 && !document.querySelector(".ai-loading")');
    await check('Inline hotel names become four separate list entries','document.querySelectorAll(".ai-answer-text li").length===4 && document.querySelector(".ai-answer-text li").textContent==="Brilliant Hotel Danang"');
    await check('View hotel links use the blue action style and open the actual hotel page','[...document.querySelectorAll("[data-component=hotels] a.ai-source-link")].every(a=>getComputedStyle(a).color==="rgb(37, 99, 235)" && new URL(a.href).pathname.startsWith("/hotels/"))');await shot('hotels-list-desktop');
    await click('.ai-new-top');await fill({aiInput:'Gợi ý quán từ Sheet'});await js('document.getElementById("aiComposer").requestSubmit()');await wait('document.querySelectorAll("[data-component=places] .ai-result-card").length===3 && !document.querySelector(".ai-loading")');
    await check('Sheet place access uses the original stored Maps URL and safe new-tab attributes','(()=>{const a=document.querySelector("[data-component=places] .ai-result-card a");return a.textContent==="Truy cập địa điểm" && a.getAttribute("href")==="https://www.google.com/maps/search/?api=1&query=Original%20sheet%20branch" && a.target==="_blank" && a.rel.includes("noopener")})()');
    await check('Missing Sheet prices stay hidden, an explicit zero remains visible, and an unsafe URL has no button','(()=>{const c=document.querySelectorAll("[data-component=places] .ai-result-card");return !c[0].textContent.includes("Giá tham khảo") && c[0].textContent.includes("0123456789") && c[0].textContent.includes("chưa xác thực ghim") && c[1].textContent.includes("Giá tham khảo: 0") && !c[2].querySelector("a") && !c[0].querySelector("img")})()');
    for(const [prompt,notice] of [['Đổi điểm đến','khác điểm đến'],['Chỉnh riêng ngày 2 nhưng đổi số ngày','đủ các ngày hợp lệ'],['Đề xuất thiếu ngày','đủ các ngày hợp lệ']]){
        const before=await js('window.AurelPlanner.context()');await click('.ai-new-top');await chat(prompt);await click('.trip-proposal button:first-child');
        assert.deepEqual(await js('window.AurelPlanner.context()'),before);assert((await js('document.getElementById("tripNotice").textContent')).includes(notice));checks.push('Unsafe proposal leaves the current trip intact: '+prompt);
    }
    const beforeClosed=await js('window.AurelPlanner.context()');await click('.ai-new-top');await fill({aiInput:'Gợi ý điểm đã đóng'});await js('document.getElementById("aiComposer").requestSubmit()');
    await wait('document.querySelector(".ai-answer-text")?.textContent.includes("đã được báo đóng cửa") && !document.querySelector(".ai-loading")');
    await check('Old closed place cards and timeline Apply controls are removed from chat','!document.querySelector(".trip-proposal") && !document.querySelector("[data-component=places] .ai-result-card") && !document.querySelector(".ai-answer-text").textContent.includes("Tham quan Điểm đã đóng")');
    await check('Closure restrictions distinguish cities and explicit permanent status','window.AurelPlanner.excluded({name:"Điểm đã đóng"},901) && !window.AurelPlanner.excluded({name:"Điểm đã đóng"},72) && window.AurelPlanner.excluded({title:"Khác",operating_status:"closed_permanently"}) && !window.AurelPlanner.excluded({title:"Khác",business_status:"CLOSED_TEMPORARILY"})');
    await js('(()=>{const p=window.AurelPlanner,c=p.context(),timeline={days:c.days.map((d,i)=>({day_number:i+1,items:i===0?[{title:"Điểm đã đóng",kind:"place",start_time:"09:00",end_time:"10:00"}]:d.items}))};const box=p.proposal(timeline,{plannerVersion:p.version()},{location_id:c.location_id,duration_days:c.duration_days});box.id="closedProposalTest";document.getElementById("tripPlanner").append(box);})()');
    await click('#closedProposalTest button:first-child');assert.deepEqual(await js('window.AurelPlanner.context()'),beforeClosed);await check('Applying a stale closed-place proposal cannot overwrite the itinerary','document.getElementById("tripNotice").textContent.includes("đã được báo đóng cửa")');await js('document.getElementById("closedProposalTest").remove()');
    await add('Điểm đã đóng');await check('An existing manually entered closed place remains editable with a visible warning','!!document.querySelector(".trip-activity .trip-schedule-warning") && document.querySelector(".trip-activity .trip-schedule-warning").textContent.includes("chọn điểm khác")');await click('#tripUndo');assert.deepEqual(await js('window.AurelPlanner.context()'),beforeClosed);await click('#tripSave');await wait('document.getElementById("tripSaveStatus").textContent==="Đã lưu"');
    await click('#tripChangeInfo');await click('#tripReturnToBoard');await click('.ai-new-top');await chat(expandMessage);
    await shot('step2-desktop');await viewport(1100,900);
    await check('Larger text fits both panes on a compact laptop','document.documentElement.scrollWidth<=innerWidth && [...document.querySelectorAll(".trip-board,.trip-assistant,.ai-topbar")].every(n=>n.scrollWidth<=n.clientWidth+1)');
    await viewport(390,844);await check('Mobile stacks both panes without horizontal overflow','document.querySelector(".trip-assistant").getBoundingClientRect().top>document.querySelector(".trip-board").getBoundingClientRect().top && document.documentElement.scrollWidth<=innerWidth');await shot('step2-mobile');
    await pointerClick('.trip-activity .ai-place-photo-preview');await check('Mobile photo viewer fits the screen and closes with its button','document.getElementById("aiPhotoViewer").open && document.getElementById("aiPhotoViewer").getBoundingClientRect().width<=innerWidth && document.documentElement.scrollWidth<=innerWidth');await shot('photo-viewer-mobile');await click('.ai-photo-close');await wait('!document.getElementById("aiPhotoViewer").open && !document.documentElement.classList.contains("ai-photo-open")');
    await js('document.getElementById("accountMenuToggle").focus()');await click('#accountMenuToggle');await wait('document.getElementById("sidebar").classList.contains("open")');
    await check('Mobile account menu opens with the AI navigation item active','!document.getElementById("sidebar").inert && document.querySelector(".sidebar-nav-item.active").dataset.nav==="itineraries"');
    await js('document.dispatchEvent(new KeyboardEvent("keydown",{key:"Escape",bubbles:true}))');
    await check('Mobile account menu closes and returns focus to its toggle','document.getElementById("sidebar").inert && !document.getElementById("sidebar").classList.contains("open") && document.activeElement.id==="accountMenuToggle"');
    await click('#tripChangeInfo');await check('Step 1 remains usable on mobile','document.documentElement.scrollWidth<=innerWidth && document.getElementById("tripForm").getBoundingClientRect().width<=innerWidth');await shot('step1-mobile');
    await click('#tripSavedButton');await wait('document.querySelectorAll(".trip-library-row").length===2');await click('.trip-library-row:last-child button');
    await wait('!document.getElementById("tripLibraryDialog").open');
    await check('Legacy itineraries remain available with their original activity data','document.querySelector(".trip-activity h3").textContent==="Hoạt động cũ" && document.getElementById("tripSave").disabled && document.getElementById("tripPdf").hidden');
    const savedBeforeNew=[...saved.keys()];await click('#tripNewButton');
    await check('New trip returns from a legacy/read-only itinerary to a clean destination form','window.AurelPlanner.context()===null && !document.getElementById("tripSetup").hidden && document.getElementById("tripDestination").value==="" && document.getElementById("tripDays").value==="3" && document.getElementById("tripReturnToBoard").hidden');
    await fill({tripDestination:72,tripDays:2,tripPeople:2});await js('document.getElementById("tripForm").requestSubmit()');await wait('!document.getElementById("tripManualFallback").hidden');await click('#tripContinueManual');await click('#tripSave');await wait('document.getElementById("tripSaveStatus").textContent==="Đã lưu"');
    assert.equal(saved.size,2);assert(savedBeforeNew.every(id=>saved.has(id)));checks.push('A new destination creates a separate trip and keeps previously saved trips');
    await add('Hoạt động chưa lưu');const unsaved=await js('window.AurelPlanner.context()');await click('#tripNewButton');await wait('document.getElementById("tripConfirmDialog").open');await answerConfirm('Cancel');assert.deepEqual(await js('window.AurelPlanner.context()'),unsaved);checks.push('Canceling New trip preserves unsaved activities');
    await click('#tripNewButton');await wait('document.getElementById("tripConfirmDialog").open');await answerConfirm('Accept');await wait('window.AurelPlanner.context()===null');assert.equal(saved.size,2);checks.push('Confirming New trip resets only the editor and leaves saved trips intact');
    await viewport(1440,1000);await fill({tripDestination:901,tripDays:2,tripPeople:2});await js('document.getElementById("tripForm").requestSubmit()');await wait('!document.getElementById("tripManualFallback").hidden');await click('#tripContinueManual');
    await useTripDate('2026-11-08');
    await check('The redundant visit-start date field is removed while the trip date comes from the existing proposal flow','!document.getElementById("tripVisitStart") && window.AurelPlanner.visitDate()==="2026-11-08"');
    await fill({aiInput:'Gợi ý địa điểm có giờ'});await js('document.getElementById("aiComposer").requestSubmit()');await wait('document.querySelector(".ai-place-hours")?.textContent.includes("Chủ nhật, 08/11: Nghỉ cả ngày")');
    await check('Place cards display current status independently of the selected visit date','document.querySelector(".ai-visit-date").value==="2026-11-08" && document.querySelector(".ai-place-hours summary").textContent.includes("Đang mở cửa") && document.querySelector(".ai-place-hours .angel-hours-visit").textContent.includes("Chủ nhật, 08/11: Nghỉ cả ngày")');
    await click('.ai-place-hours summary');await check('Hours dropdown exposes all weekdays, source, checked date and reference notice','document.querySelector(".ai-place-hours details").open && document.querySelectorAll(".ai-place-hours .angel-hours-week>div").length===7 && document.querySelector(".ai-place-hours .angel-hours-body").textContent.includes("31/10/2026") && document.querySelector(".ai-place-hours .angel-hours-body a").href.includes("example.invalid/opening-hours")');await click('.ai-place-hours summary');
    await click('[data-component=places] .ai-card-actions button');await js('document.getElementById("tripActivityForm").requestSubmit()');await wait('document.querySelector(".trip-day:nth-child(1) .trip-activity:nth-of-type(1) .trip-opening-hours")?.textContent.includes("Nghỉ cả ngày")');
    await useTripDate('2026-11-09');await wait('document.querySelector(".trip-day:nth-child(1) .trip-activity:nth-of-type(1) .trip-opening-hours")?.textContent.includes("Thứ hai, 09/11") && !document.querySelector(".trip-day:nth-child(1) .trip-activity:nth-of-type(1) .trip-opening-hours .trip-schedule-warning")');
    await check('The compact clock row sits immediately above Google Maps with the long notice inside the dropdown','(()=>{const h=document.querySelector(".trip-opening-hours");return !!h.querySelector("summary svg") && h.nextElementSibling.classList.contains("trip-map-link") && !h.querySelector("details").open && h.querySelector(".angel-hours-reference").closest(".angel-hours-body")!==null})()');
    await pointerClick('.trip-opening-hours summary');await check('The schedule can expand by keyboard-accessible details and collapse without changing activities','document.querySelector(".trip-opening-hours details").open && document.querySelectorAll(".trip-opening-hours .angel-hours-week>div").length===7 && window.AurelPlanner.context().days[0].items[0].start_time==="09:00"');await shot('hours-dropdown-desktop');await click('.trip-opening-hours summary');
    await check('Changing the trip date rechecks the activity and retains the chosen times','window.AurelPlanner.context().days[0].items[0].start_time==="09:00" && window.AurelPlanner.context().days[0].items[0].source_place_id===501');
    await click('[data-day="0"] .trip-activity-actions button');await fill({tripActivityStart:'11:00',tripActivityEnd:'15:00'});await js('document.getElementById("tripActivityForm").requestSubmit()');await wait('document.querySelector(".trip-day:nth-child(1) .trip-activity:nth-of-type(1) .trip-opening-hours")?.textContent.includes("vắt qua giờ nghỉ")');
    await check('A visit across the lunch break is warned and suggested rather than overwritten','window.AurelPlanner.context().days[0].items[0].start_time==="11:00" && window.AurelPlanner.context().days[0].items[0].end_time==="15:00" && document.querySelector(".trip-day:nth-child(1) .trip-activity:nth-of-type(1) .trip-opening-hours").textContent.includes("14:00–18:00")');
    await js('document.querySelector(".trip-board").scrollIntoView({block:"start"})');await shot('hours-warning-desktop');
    await click('[data-component=places] .ai-card-actions button');await js('document.getElementById("tripActivityForm").requestSubmit()');await click('[data-day="0"] [data-item="0"] .trip-activity-actions button:nth-child(3)');
    await wait('document.querySelector(".trip-day:nth-child(1) .trip-activity:nth-of-type(2) .trip-opening-hours")?.textContent.includes("vắt qua giờ nghỉ") && !document.querySelector(".trip-day:nth-child(1) .trip-activity:nth-of-type(1) .trip-opening-hours .trip-schedule-warning")');checks.push('Reordering keeps each backend warning attached to the correct activity');
    await click('[data-day="0"] [data-item="1"] .trip-activity-actions button');await fill({tripActivityDay:1});await js('document.getElementById("tripActivityForm").requestSubmit()');await wait('document.querySelector(".trip-day:nth-child(2) .trip-activity:nth-of-type(1) .trip-opening-hours")?.textContent.includes("Thứ ba, 10/11: Nghỉ cả ngày")');checks.push('Moving an activity to another day applies that date exception');
    await click('#tripSave');await wait('document.getElementById("tripSaveStatus").textContent==="Đã lưu" && document.querySelector(".trip-day:nth-child(2) .trip-activity:nth-of-type(1) .trip-opening-hours")?.textContent.includes("Nghỉ cả ngày")');
    await check('Saving preserves the real visit date, source identity and warning','window.AurelPlanner.visitDate()==="2026-11-09" && window.AurelPlanner.context().days[1].items[0].source_place_id===501');
    await useTripDate('2026-11-06');await click('[data-day="0"] .trip-activity-actions button');await fill({tripActivityStart:'23:00',tripActivityEnd:'01:00'});await click('#tripActivityOvernight');await js('document.getElementById("tripActivityForm").requestSubmit()');await wait('!document.getElementById("tripActivityDialog").open && document.querySelector(".trip-day:nth-child(1) .trip-activity:nth-of-type(1) .trip-opening-hours")?.textContent.includes("Thứ sáu, 06/11") && !document.querySelector(".trip-day:nth-child(1) .trip-activity:nth-of-type(1) .trip-opening-hours .trip-schedule-warning")');
    await check('Overnight edits are compared across both calendar dates by the backend','window.AurelPlanner.context().days[0].items[0].end_day_offset===1 && window.AurelPlanner.context().days[0].items[0].end_time==="01:00"');
    await js('(()=>{const i=document.querySelector(".ai-visit-date");i.value="2026-11-15";i.dispatchEvent(new Event("change"));})()');await wait('document.querySelector(".ai-place-hours")?.textContent.includes("Chủ nhật, 15/11: 10:00–16:00")');checks.push('A card-specific exception can reopen an otherwise closed Sunday');
    const beforeOutsideDate=await js('window.AurelPlanner.context()');await click('[data-component=places] .ai-card-actions button');assert.deepEqual(await js('window.AurelPlanner.context()'),beforeOutsideDate);await check('Adding a card outside the current trip dates asks for a date adjustment without overwriting the trip','!document.getElementById("tripActivityDialog").open && document.getElementById("tripNotice").textContent.includes("nằm ngoài lịch này")');
    await viewport(390,844);await check('Compact hours and the retained overnight controls fit on mobile','document.documentElement.scrollWidth<=innerWidth && !document.getElementById("tripVisitStart") && !!document.querySelector(".trip-opening-hours summary")');await shot('hours-mobile');
    assert.deepEqual(errors,[]);checks.push('No JavaScript exceptions or external requests');console.log(JSON.stringify({status:'PASS',checks:checks.length,names:checks}));
})().catch(e=>{errors.push(e.message);console.error(e.stack);process.exitCode=1;}).finally(()=>{clearTimeout(timer);fs.writeFileSync(path.join(runtime,'browser-results.json'),JSON.stringify({checks,errors,requests},null,2));chrome?.kill();server.closeAllConnections?.();server.close();for(const p of pending.values())clearTimeout(p.timer);});
