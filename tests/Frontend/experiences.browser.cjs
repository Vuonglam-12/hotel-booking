// Real Laravel routes + owned SQLite or guarded runner-owned MySQL, uploads and disposable Chrome profile.
// Usage: node tests/Frontend/experiences.browser.cjs [optional Chrome executable]
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const http = require('node:http');
const assert = require('node:assert/strict');
const {spawn, execFileSync} = require('node:child_process');
const postcss = require('postcss');
const tailwind = require('tailwindcss');
const root = path.resolve(__dirname, '../..');
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
// Node 18's experimental fetch can truncate PHP's close-delimited responses on Windows.
const readHttp = (url, headers = {}) => new Promise((resolve,reject) => {
    const request=http.get(url,{headers:{Connection:'close',...headers}},response=>{
        let body='';response.setEncoding('utf8');response.on('data',chunk=>body+=chunk);
        response.on('end',()=>resolve({status:response.statusCode,body}));response.on('error',reject);
    });request.on('error',reject);request.setTimeout(4000,()=>request.destroy(new Error('Fixture HTTP timeout')));
});

(async () => {
    const mysql=Boolean(process.env.AUREL_CATALOG_MYSQL_STAGE);
    const setup = JSON.parse(execFileSync('php', ['-d','extension=pdo_sqlite','-d','extension=sqlite3','tests/Support/experience_browser_setup.php'], {cwd:root,encoding:'utf8'}));
    const stage = JSON.parse(fs.readFileSync(setup.stage_file,'utf8'));
    assert.equal(stage.synthetic_only && stage.driver === (mysql?'mysql':'sqlite'),true);
    if(mysql){
        assert.match(stage.database,/^aurel_catalog_test_[a-f0-9]{12}$/);
        fs.mkdirSync(path.join(stage.runtime,'public'));
        fs.symlinkSync(path.join(stage.runtime,'uploads'),path.join(stage.runtime,'public','storage'),process.platform==='win32'?'junction':'dir');
        assert.equal(fs.realpathSync(path.join(stage.runtime,'public','storage')),fs.realpathSync(path.join(stage.runtime,'uploads')));
    }
    const sources = ['resources/views/Layout.blade.php','resources/views/partials/layout/header.blade.php','resources/views/partials/layout/search.blade.php',
        'resources/views/admin/layouts/admin.blade.php','resources/views/admin/experiences.blade.php',
        ...fs.readdirSync(path.join(root,'resources/views/experiences')).filter(file=>file.endsWith('.blade.php')).map(file=>'resources/views/experiences/'+file)];
    const css = await postcss([tailwind({content:sources.map(file=>({raw:fs.readFileSync(path.join(root,file),'utf8'),extension:'html'}))})])
        .process(fs.readFileSync(path.join(root,'resources/css/app.css'),'utf8'),{from:undefined});
    const shell = ['design-system.css','hotel-explore.css','site-shell.css'].map(file=>fs.readFileSync(path.join(root,'resources/css',file),'utf8').replace(/^@import .*;\r?\n/gm,'')).join('\n');
    fs.writeFileSync(path.join(stage.runtime,'browser.css'),css.css+'\n'+shell);
    const reservation = net.createServer(); await new Promise(resolve=>reservation.listen(0,'127.0.0.1',resolve));
    const port = reservation.address().port; await new Promise(resolve=>reservation.close(resolve));
    const origin = 'http://127.0.0.1:'+port;
    const serverArgs=mysql ? ['-S','127.0.0.1:'+port,'-t',path.join(stage.runtime,'public'),path.join(root,'tests/Support/catalog_mysql_browser_router.php')]
        : ['-d','extension=pdo_sqlite','-d','extension=sqlite3','-S','127.0.0.1:'+port,'tests/Support/experience_browser_router.php'];
    const server = spawn('php',serverArgs,
        {cwd:root,env:{...process.env,AUREL_EXPERIENCE_BROWSER_STAGE:setup.stage_file},windowsHide:true,stdio:['ignore','pipe','pipe']});
    let serverLog=''; server.stdout.on('data',chunk=>serverLog+=chunk); server.stderr.on('data',chunk=>serverLog+=chunk);
    const browser = spawn((process.argv[2] && !process.argv[2].startsWith('--') ? process.argv[2] : null) || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        ['--headless=new','--no-first-run','--no-default-browser-check','--disable-background-networking','--disable-component-update','--disable-sync','--disable-extensions','--disable-gpu','--user-data-dir='+path.join(stage.runtime,'chrome-profile'),'--remote-debugging-pipe'],
        {stdio:['ignore','ignore','pipe','pipe','pipe'],windowsHide:true});
    const pending = new Map(); let id=0, buffer='', chromeLog=''; const errors=[], checks=[], screenshots=[], responses=[];
    browser.stderr.on('data',chunk=>chromeLog+=chunk); browser.stdio[4].setEncoding('utf8');
    let sessionId, failList=false, slowList=false;
    const send = (method,params={},session) => new Promise((resolve,reject)=>{
        const next=++id, timer=setTimeout(()=>{pending.delete(next);reject(new Error('CDP timeout '+method+' '+chromeLog.slice(-600)));},20000);
        pending.set(next,{resolve,reject,timer}); browser.stdio[3].write(JSON.stringify({id:next,method,params,...(session?{sessionId:session}:{})})+'\0');
    });
    const page = (method,params) => send(method,params,sessionId);
    browser.stdio[4].on('data',chunk=>{
        buffer+=chunk; let end;
        while((end=buffer.indexOf('\0'))!==-1){
            const message=JSON.parse(buffer.slice(0,end)); buffer=buffer.slice(end+1); const item=pending.get(message.id);
            if(item){pending.delete(message.id);clearTimeout(item.timer);message.error?item.reject(new Error(JSON.stringify(message.error))):item.resolve(message.result);}
            else if(message.method==='Runtime.exceptionThrown')errors.push(message.params.exceptionDetails.exception?.description || message.params.exceptionDetails.text);
            else if(message.method==='Network.responseReceived')responses.push({url:message.params.response.url,status:message.params.response.status,mime:message.params.response.mimeType});
            else if(message.method==='Fetch.requestPaused') handleRequest(message.params).catch(error=>errors.push(error.message));
        }
    });
    async function handleRequest(event){
        const url=new URL(event.request.url);
        const json=body=>page('Fetch.fulfillRequest',{requestId:event.requestId,responseCode:200,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:Buffer.from(JSON.stringify(body)).toString('base64')});
        if(url.origin!==origin && !['data:','blob:'].includes(url.protocol))return page('Fetch.failRequest',{requestId:event.requestId,errorReason:'BlockedByClient'});
        if(url.pathname==='/api/hotels')return json({data:[],last_page:1}); // Unchanged hotel search has its own regression suite.
        if(url.pathname==='/api/admin/dashboard')return json({pending_bookings:0,pending_reviews:0});
        if(url.pathname==='/api/experiences' && failList){failList=false;return page('Fetch.fulfillRequest',{requestId:event.requestId,responseCode:503,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:Buffer.from('{"message":"isolated temporary error"}').toString('base64')});}
        if(url.pathname==='/api/experiences' && slowList)await delay(650);
        return page('Fetch.continueRequest',{requestId:event.requestId});
    }
    const evaluate=async expression=>{const result=await page('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true,userGesture:true});if(result.exceptionDetails)throw new Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
    const wait=async expression=>{const end=Date.now()+18000;while(Date.now()<end){if(await evaluate(expression))return;await delay(70);}throw new Error('Timed out: '+expression);};
    const click=selector=>evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
    const fill=(selector,value)=>evaluate(`document.querySelector(${JSON.stringify(selector)}).value=${JSON.stringify(value)}`);
    const change=(selector,value)=>evaluate(`document.querySelector(${JSON.stringify(selector)}).value=${JSON.stringify(value)};document.querySelector(${JSON.stringify(selector)}).dispatchEvent(new Event('change',{bubbles:true}))`);
    const mark=message=>{checks.push(message);console.log('PASS '+message);};
    const navigate=async(url,width=1440,height=1000)=>{await page('Emulation.setDeviceMetricsOverride',{width,height,screenWidth:width,screenHeight:height,deviceScaleFactor:1,mobile:false});await page('Page.navigate',{url:origin+url});};
    const ready=()=>wait("document.getElementById('experience-results')?.getAttribute('aria-busy')==='false'");
    const shot=async(name,width,height)=>{const image=await page('Page.captureScreenshot',{format:'png',captureBeyondViewport:true,clip:{x:0,y:0,width,height,scale:1}});const file=path.join(stage.runtime,name+'.png');fs.writeFileSync(file,Buffer.from(image.data,'base64'));screenshots.push(file);};
    const api=async endpoint=>{const response=await readHttp(origin+endpoint,{Accept:'application/json',Authorization:'Bearer '+stage.admin_token});return {status:response.status,data:JSON.parse(response.body)};};
    try {
        let healthBody='';
        for(let attempt=0;attempt<70;attempt++){try{healthBody=(await readHttp(origin+(mysql?'/__catalog/health':'/__experience/health'))).body;const health=JSON.parse(healthBody);if(health.synthetic_only&&health.driver===stage.driver)break;}catch{}await delay(100);if(attempt===69)throw new Error('Fixture server unavailable: '+healthBody.slice(0,1200)+' '+serverLog.slice(-1000));}
        const target=await send('Target.createTarget',{url:'about:blank'});({sessionId}=await send('Target.attachToTarget',{targetId:target.targetId,flatten:true}));
        await page('Page.enable');await page('Runtime.enable');await page('Network.enable');await page('Fetch.enable',{patterns:[{urlPattern:'*'}]});
        await page('Page.addScriptToEvaluateOnNewDocument',{source:`if(location.origin===${JSON.stringify(origin)})localStorage.setItem('admin_token',${JSON.stringify(stage.admin_token)});`});
        if(process.argv.includes('--compact-time')){
            await navigate('/admin/experiences');await wait("document.querySelector('#exa-create') && !document.querySelector('#exa-create').disabled");await click('#exa-create');await wait("document.querySelector('#exa-editor').open");
            await fill('#exa-name','Compact time picker fixture');await change('#exa-location','72');await change('#exa-category','tham-quan');
            await evaluate("document.querySelector('#exa-open-time').scrollIntoView({block:'center'})");await click('#exa-open-time + button');await wait("!document.querySelector('#exa-open-picker').hidden");await click('#exa-open-picker [data-unit="hour"] [data-value="9"]');await click('#exa-open-picker [data-unit="minute"] [data-value="5"]');
            await click('#exa-close-time-time + button');await wait("!document.querySelector('#exa-close-time-picker').hidden");await click('#exa-close-time-picker [data-unit="hour"] [data-value="21"]');await click('#exa-close-time-picker [data-unit="minute"] [data-value="10"]');
            assert.equal(await evaluate("document.querySelector('#exa-open').value"),'09:05');assert.equal(await evaluate("document.querySelector('#exa-close-time').value"),'21:10');await click('#exa-submit');
            await wait("!document.querySelector('#exa-editor').open || !document.querySelector('#exa-form-error').hidden");assert.equal(await evaluate("document.querySelector('#exa-editor').open"),false,await evaluate("document.querySelector('#exa-form-error')?.textContent"));
            let post=(await api('/api/admin/attractions?q=Compact%20time%20picker%20fixture')).data.data[0];assert.equal(post.open_time.slice(0,5),'09:05');assert.equal(post.close_time.slice(0,5),'21:10');mark('Shared 24-hour picker saves opening/closing times through existing Experience API');
            await wait("!document.querySelector('#exa-table').hidden");await evaluate("[...document.querySelectorAll('#exa-rows tr')].find(row=>row.textContent.includes('Compact time picker fixture')).querySelector('.exa-actions button').click()");await wait("document.querySelector('#exa-editor').open");
            assert.equal(await evaluate("document.querySelector('#exa-open-time').value"),'09:05');assert.equal(await evaluate("document.querySelector('#exa-close-time-time').value"),'21:10');await fill('#exa-name','Compact time picker fixture edited');await click('#exa-submit');await wait("!document.querySelector('#exa-editor').open");const edited=(await api('/api/admin/attractions/'+post.id)).data;
            assert.equal(edited.open_time,post.open_time);assert.equal(edited.close_time,post.close_time);mark('Opening and saving an unrelated edit keeps existing time values');
            await navigate('/admin/experiences',390,650);await wait("document.querySelector('#exa-create') && !document.querySelector('#exa-create').disabled");await click('#exa-create');await wait("document.querySelector('#exa-editor').open");await evaluate("document.querySelector('#exa-open-time').scrollIntoView({block:'center'})");await click('#exa-open-time + button');await wait("!document.querySelector('#exa-open-picker').hidden");assert.equal(await evaluate("(()=>{const p=document.querySelector('#exa-open-picker').getBoundingClientRect(),d=document.querySelector('#exa-editor').getBoundingClientRect();return p.left>=Math.max(0,d.left)&&p.right<=Math.min(innerWidth,d.right)+1&&p.top>=Math.max(0,d.top)&&p.bottom<=Math.min(innerHeight,d.bottom)+1})()"),true);await delay(160);await shot('experience-compact-time-mobile',390,650);await page('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape'});assert.equal(await evaluate("document.querySelector('#exa-open-picker').hidden && document.querySelector('#exa-editor').open"),true);mark('Shared time popup fits the mobile Experience modal and Escape preserves the form');
            assert.deepEqual(errors,[]);mark('No browser JavaScript exceptions');return;
        }

        slowList=true;await navigate('/trai-nghiem');await wait("document.querySelector('#experience-loading') && !document.querySelector('#experience-loading').hidden");mark('Client loading state');
        await ready();slowList=false;assert.equal(await evaluate("document.querySelectorAll('.exp-card').length"),12);
        assert.equal(await evaluate("new URL(Array.from(document.querySelectorAll('#header-experiences a')).find(a=>a.textContent==='Trải nghiệm').href).pathname"),'/trai-nghiem');mark('Client listing and official Header route');
        await shot('experiences-desktop',1440,1000);
        await change('#experience-location','vn-phu-quoc');await ready();assert.equal(await evaluate("[...document.querySelectorAll('.exp-card-place')].every(p=>p.textContent==='Phú Quốc')"),true);mark('Stable location filter');
        await change('#experience-location','');await ready();await click('[data-experience-category="am-thuc"]');await ready();assert.equal(await evaluate("[...document.querySelectorAll('.exp-card .exp-badge')].every(p=>p.textContent==='Ẩm thực')"),true);mark('Category filter');
        await fill('#experience-query','not-a-fixture');await evaluate("document.querySelector('#experience-filters').requestSubmit()");await ready();assert.equal(await evaluate("!document.querySelector('#experience-empty').hidden"),true);mark('Client empty state');
        await click('#experience-reset');await ready();failList=true;await click('[data-experience-category="vui-choi"]');await ready();assert.equal(await evaluate("!document.querySelector('#experience-error').hidden"),true);
        await click('#experience-retry');await ready();assert.equal(await evaluate("document.querySelector('#experience-error').hidden && document.querySelectorAll('.exp-card').length>0"),true);mark('Client error and retry');
        await click('[data-experience-category=""]');await ready();await click('#experience-next');await ready();assert.equal(await evaluate("document.querySelectorAll('.exp-card').length"),3);await click('#experience-prev');await ready();mark('Client pagination');
        for(const width of [768,390,320]){
            await navigate('/trai-nghiem',width,900);await ready();
            assert.equal(await evaluate("document.documentElement.scrollWidth<=innerWidth && [...document.querySelectorAll('#experience-filters input,#experience-filters select,.exp-card')].every(n=>n.getBoundingClientRect().left>=0 && n.getBoundingClientRect().right<=innerWidth)"),true);
            assert.equal(await evaluate("getComputedStyle(document.querySelector('#experience-grid')).gridTemplateColumns.split(' ').length"),width===768?2:1);
            await shot('experiences-'+width,width,900);mark('Client responsive '+width);
        }
        await navigate('/trai-nghiem/kiem-thu-1');await wait("document.readyState==='complete' && document.querySelectorAll('[data-experience-photo]').length===2");
        assert.equal(await evaluate("document.querySelectorAll('.exp-hotel').length"),1);await click('[data-experience-photo="0"]');await wait("document.querySelector('#experience-lightbox').open && document.querySelector('#experience-lightbox-image').naturalWidth>0");
        await click('[data-lightbox-next]');assert.equal(await evaluate("document.querySelector('#experience-lightbox-count').textContent"),'2 / 2');
        await page('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});await page('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
        await wait("!document.querySelector('#experience-lightbox').open && document.activeElement.hasAttribute('data-experience-photo')");mark('Detail gallery, source, hotel relation and Escape focus');
        await shot('experience-detail',1440,1000);

        await navigate('/admin/experiences');await wait("document.querySelector('#exa-create') && !document.querySelector('#exa-create').disabled && !document.querySelector('#exa-table').hidden");
        await shot('experiences-admin',1440,1000);await click('#exa-create');await wait("document.querySelector('#exa-editor').open");
        const name='CRUD browser fixture';await fill('#exa-name',name);await change('#exa-location','72');await change('#exa-category','tham-quan');await fill('#exa-description','Synthetic browser content.');await fill('#exa-source','https://example.test/browser-fixture');await fill('#exa-image-source','Synthetic image for isolated browser testing only.');
        const documentRoot=await page('DOM.getDocument');const fileNode=await page('DOM.querySelector',{nodeId:documentRoot.root.nodeId,selector:'#exa-files'});
        await page('DOM.setFileInputFiles',{nodeId:fileNode.nodeId,files:[stage.upload_file]});
        await wait("document.querySelectorAll('#exa-new-images img').length===1");
        const originalLocations=(await api('/api/admin/locations')).data.map(item=>({id:item.id,name:item.name,code:item.code}));
        assert.deepEqual(await evaluate("[...document.querySelector('#exa-location').options].map(option=>Number(option.value)).sort((a,b)=>a-b)"),originalLocations.map(item=>item.id).sort((a,b)=>a-b));
        assert.equal(await evaluate("document.querySelector('#exa-submit').textContent"),'Lưu bản nháp');
        await click('#exa-submit');
        await wait("!document.querySelector('#exa-editor').open || !document.querySelector('#exa-form-error').hidden");
        assert.equal(await evaluate("document.querySelector('#exa-form-error').hidden"),true,await evaluate("document.querySelector('#exa-form-error').textContent"));
        await wait("[...document.querySelectorAll('#exa-rows strong')].some(n=>n.textContent==='CRUD browser fixture')");
        let saved=(await api('/api/admin/attractions?q='+encodeURIComponent(name))).data.data[0];
        assert.equal(saved.images.length,1);assert.equal(saved.experience_published_at,null);assert.equal((await api('/api/experiences/'+saved.slug)).status,404);
        assert.equal(await evaluate("document.querySelector('#adminToastMsg').textContent.includes('bản nháp')"),true);mark('Admin saves a draft with uploaded image and Customer cannot view it');
        await evaluate("[...document.querySelectorAll('#exa-rows tr')].find(tr=>tr.textContent.includes('CRUD browser fixture')).querySelector('.exa-actions button').click()");
        await click('#exa-active');await click('#exa-verify');await click('#exa-publish');
        assert.equal(await evaluate("document.querySelector('#exa-source').required && document.querySelector('#exa-category').required && document.querySelector('#exa-submit').textContent==='Lưu và xuất bản'"),true);
        await click('#exa-submit');
        await wait("!document.querySelector('#exa-editor').open || !document.querySelector('#exa-form-error').hidden");
        assert.equal(await evaluate("document.querySelector('#exa-form-error').hidden"),true,await evaluate("document.querySelector('#exa-form-error').textContent"));
        await wait("[...document.querySelectorAll('#exa-rows strong')].some(n=>n.textContent==='CRUD browser fixture')");
        saved=(await api('/api/admin/attractions?q='+encodeURIComponent(name))).data.data[0];
        assert.equal(saved.images.length,1);assert.ok(saved.experience_verified_at);assert.equal((await api('/api/experiences/'+saved.slug)).status,200);mark('Admin creates, uploads, verifies and publishes through protected APIs');
        const originalImage=saved.images[0].display_url;
        await navigate('/trai-nghiem/'+saved.slug);await wait("document.readyState==='complete' && document.querySelector('[data-experience-photo] img')?.naturalWidth>0");
        assert.equal(await evaluate("document.querySelector('h1').textContent"),name);mark('Customer detail renders the newly published Admin content and image');
        await navigate('/admin/experiences');await wait("document.querySelector('#exa-create') && !document.querySelector('#exa-create').disabled && !document.querySelector('#exa-table').hidden");
        await evaluate("[...document.querySelectorAll('#exa-rows tr')].find(tr=>tr.textContent.includes('CRUD browser fixture')).querySelector('.exa-actions button').click()");
        await fill('#exa-name',name+' edited');await fill('#exa-description','Updated browser CRUD content.');await fill('#exa-address','Isolated browser address');await change('#exa-location','405');
        await fill('#exa-image-source','Synthetic replacement image, isolated browser only.');
        const replacementRoot=await page('DOM.getDocument');const replacementNode=await page('DOM.querySelector',{nodeId:replacementRoot.root.nodeId,selector:'#exa-files'});
        await page('DOM.setFileInputFiles',{nodeId:replacementNode.nodeId,files:[stage.replacement_file]});
        await wait("document.querySelectorAll('#exa-new-images img').length===1");
        await click('#exa-images [data-delete-image]');await click('#exa-new-images [name="cover-image"]');
        await click('#exa-verify');await click('#exa-submit');await wait("!document.querySelector('#exa-editor').open || !document.querySelector('#exa-form-error').hidden");
        assert.equal(await evaluate("document.querySelector('#exa-form-error').hidden"),true,await evaluate("document.querySelector('#exa-form-error').textContent"));
        await wait("!document.querySelector('#exa-table').hidden");
        const edited=(await api('/api/experiences/'+saved.slug)).data.data;
        assert.equal(edited.name,name+' edited');assert.equal(edited.location.code,'vn-phu-quoc');assert.equal(edited.gallery.length,1);assert.notEqual(edited.cover.url,originalImage);
        assert.deepEqual((await api('/api/admin/locations')).data.map(item=>({id:item.id,name:item.name,code:item.code})),originalLocations);mark('Admin replaces image and edits an existing location without creating any location');
        await navigate('/trai-nghiem/'+saved.slug);await wait("document.readyState==='complete' && document.querySelector('[data-experience-photo] img')?.naturalWidth>0");
        assert.equal(await evaluate("document.querySelector('h1').textContent"),name+' edited');assert.equal(await evaluate("document.querySelector('.exp-description').textContent"),'Updated browser CRUD content.');
        assert.equal(await evaluate("new URL(document.querySelector('[data-experience-photo] img').src).pathname"),new URL(edited.cover.url,origin).pathname);mark('Customer reload shows updated content and replacement cover on the same URL');
        await navigate('/admin/experiences');await wait("document.querySelector('#exa-create') && !document.querySelector('#exa-create').disabled && !document.querySelector('#exa-table').hidden");
        await evaluate("[...document.querySelectorAll('#exa-rows tr')].find(tr=>tr.textContent.includes('CRUD browser fixture')).querySelector('button[aria-label]').click()");await delay(220);
        assert.equal((await api('/api/experiences/'+saved.slug)).status,404);mark('Admin disables public visibility');
        await evaluate("window.confirm=()=>true; [...document.querySelectorAll('#exa-rows tr')].find(tr=>tr.textContent.includes('CRUD browser fixture')).querySelector('.exa-actions button:last-child').click()");await delay(250);
        await change('#exa-archive-filter','1');await wait("!document.querySelector('#exa-table').hidden && [...document.querySelectorAll('#exa-rows tr')].some(tr=>tr.textContent.includes('CRUD browser fixture') && tr.textContent.includes('Khôi phục'))");
        await evaluate("[...document.querySelectorAll('#exa-rows tr')].find(tr=>tr.textContent.includes('CRUD browser fixture')).querySelector('.exa-actions button').click()");
        await wait("document.querySelector('#exa-loading').hidden && !document.querySelector('#exa-empty').hidden");
        saved=(await api('/api/admin/attractions/'+saved.id)).data;assert.equal(saved.experience_archived_at,null);assert.equal(saved.experience_verified_at,null);assert.equal(saved.images.length,1);mark('Archive/restore preserves record and image while returning to draft');
        await navigate('/admin/experiences',390,844);await wait("document.querySelector('#exa-create') && !document.querySelector('#exa-create').disabled");await click('#exa-create');
        assert.equal(await evaluate("document.documentElement.scrollWidth<=innerWidth && document.querySelector('#exa-name').getBoundingClientRect().right<=innerWidth"),true);await shot('experiences-admin-mobile',390,844);mark('Admin mobile editor');
        assert.deepEqual(errors,[]);mark('No browser JavaScript exceptions');
    } catch (error) {
        if(sessionId) {
            fs.writeFileSync(path.join(stage.runtime,'failure.html'),await evaluate('document.documentElement.outerHTML').catch(()=>''));
            const diagnostic=await evaluate('JSON.stringify({images:[...document.images].map(i=>({src:i.src,loaded:i.naturalWidth})),dialog:document.querySelector("#experience-lightbox")?.open,activeElement:document.activeElement.outerHTML,formError:document.querySelector("#exa-form-error")?.textContent})').catch(()=>'');
            fs.writeFileSync(path.join(stage.runtime,'failure.json'),diagnostic);
            await shot('failure',1440,1000).catch(()=>{});
        }
        throw error;
    } finally {
        fs.writeFileSync(path.join(stage.runtime,'browser-report.json'),JSON.stringify({synthetic_only:true,driver:stage.driver,checks,errors,screenshots,responses},null,2));
        fs.writeFileSync(path.join(stage.runtime,'server.log'),serverLog);browser.kill();server.kill();console.log('Experience browser artifacts: '+stage.runtime);
    }
})().catch(error=>{console.error(error.stack);process.exitCode=1;});
