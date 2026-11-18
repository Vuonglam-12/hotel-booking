// Real Laravel routes + runner-owned MySQL database, real uploads, disposable Chrome profile. No production connection exists.
// Usage: node tests/Frontend/catalog-mysql.browser.cjs [optional Chrome executable]
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
    const descriptor=process.env.AUREL_CATALOG_MYSQL_STAGE;
    assert.ok(descriptor && path.isAbsolute(descriptor),'Explicit owned MySQL descriptor required');
    const settings=JSON.parse(fs.readFileSync(descriptor,'utf8'));
    const stage={...settings,...JSON.parse(fs.readFileSync(path.join(settings.runtime,'browser-state.json'),'utf8'))};
    assert.equal(stage.synthetic_only,true);assert.match(stage.database,/^aurel_catalog_test_[a-f0-9]{12}$/);
    assert.equal(path.basename(stage.runtime),'aurel-catalog-mysql-'+stage.nonce);
    fs.mkdirSync(path.join(stage.runtime,'public'));
    fs.symlinkSync(path.join(stage.runtime,'uploads'),path.join(stage.runtime,'public','storage'),process.platform==='win32'?'junction':'dir');
    assert.equal(fs.realpathSync(path.join(stage.runtime,'public','storage')),fs.realpathSync(path.join(stage.runtime,'uploads')));
    const sources = ['resources/views/Layout.blade.php','resources/views/partials/layout/header.blade.php','resources/views/partials/layout/search.blade.php',
        'resources/views/admin/layouts/admin.blade.php','resources/views/admin/guides.blade.php','resources/views/admin/experiences.blade.php',
        ...fs.readdirSync(path.join(root,'resources/views/experiences')).filter(file=>file.endsWith('.blade.php')).map(file=>'resources/views/experiences/'+file),
        ...fs.readdirSync(path.join(root,'resources/views/guides')).filter(file=>file.endsWith('.blade.php')).map(file=>'resources/views/guides/'+file)];
    const css = await postcss([tailwind({content:sources.map(file=>({raw:fs.readFileSync(path.join(root,file),'utf8'),extension:'html'}))})])
        .process(fs.readFileSync(path.join(root,'resources/css/app.css'),'utf8'),{from:undefined});
    const shell = ['design-system.css','hotel-explore.css','site-shell.css'].map(file=>fs.readFileSync(path.join(root,'resources/css',file),'utf8').replace(/^@import .*;\r?\n/gm,'')).join('\n');
    fs.writeFileSync(path.join(stage.runtime,'browser.css'),css.css+'\n'+shell);
    const reservation = net.createServer(); await new Promise(resolve=>reservation.listen(0,'127.0.0.1',resolve));
    const port = reservation.address().port; await new Promise(resolve=>reservation.close(resolve));
    const origin = 'http://127.0.0.1:'+port;
    const server = spawn('php',['-S','127.0.0.1:'+port,'-t',path.join(stage.runtime,'public'),path.join(root,'tests/Support/catalog_mysql_browser_router.php')],
        {cwd:root,env:{...process.env,AUREL_CATALOG_MYSQL_STAGE:descriptor},windowsHide:true,stdio:['ignore','pipe','pipe']});
    let serverLog=''; server.stdout.on('data',chunk=>serverLog+=chunk); server.stderr.on('data',chunk=>serverLog+=chunk);
    const browser = spawn((process.argv[2] && !process.argv[2].startsWith('--') ? process.argv[2] : null) || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        ['--headless=new','--no-first-run','--no-default-browser-check','--disable-background-networking','--disable-component-update','--disable-sync','--disable-extensions','--disable-gpu','--user-data-dir='+path.join(stage.runtime,'chrome-profile'),'--remote-debugging-pipe'],
        {stdio:['ignore','ignore','pipe','pipe','pipe'],windowsHide:true});
    const pending = new Map(); let id=0, buffer='', chromeLog=''; const errors=[], checks=[], screenshots=[], responses=[];
    browser.stderr.on('data',chunk=>chromeLog+=chunk); browser.stdio[4].setEncoding('utf8');
    let sessionId;
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
        return page('Fetch.continueRequest',{requestId:event.requestId});
    }
    const evaluate=async expression=>{const result=await page('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true,userGesture:true});if(result.exceptionDetails)throw new Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
    const wait=async expression=>{const end=Date.now()+18000;while(Date.now()<end){if(await evaluate(expression))return;await delay(70);}throw new Error('Timed out: '+expression);};
    const click=selector=>evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
    const fill=(selector,value)=>evaluate(`document.querySelector(${JSON.stringify(selector)}).value=${JSON.stringify(value)}`);
    const change=(selector,value)=>evaluate(`document.querySelector(${JSON.stringify(selector)}).value=${JSON.stringify(value)};document.querySelector(${JSON.stringify(selector)}).dispatchEvent(new Event('change',{bubbles:true}))`);
    const mark=message=>{checks.push(message);console.log('PASS '+message);};
    const navigate=async(url,width=1440,height=1000)=>{await page('Emulation.setDeviceMetricsOverride',{width,height,screenWidth:width,screenHeight:height,deviceScaleFactor:1,mobile:false});await page('Page.navigate',{url:origin+url});await wait("document.readyState==='complete' && "+(url.startsWith('/blog')?"location.pathname.startsWith('/cam-nang')":"location.pathname==="+JSON.stringify(new URL(url,origin).pathname)));};
    const ready=module=>wait("document.getElementById('"+(module==='guides'?'guide':'experience')+"-results')?.getAttribute('aria-busy')==='false'");
    const shot=async(name,width,height)=>{const image=await page('Page.captureScreenshot',{format:'png',captureBeyondViewport:true,clip:{x:0,y:0,width,height,scale:1}});const file=path.join(stage.runtime,name+'.png');fs.writeFileSync(file,Buffer.from(image.data,'base64'));screenshots.push(file);};
    const api=async endpoint=>{const response=await readHttp(origin+endpoint,{Accept:'application/json',...(endpoint.startsWith('/api/admin')?{Authorization:'Bearer '+stage.admin_token}:{})});return {status:response.status,data:JSON.parse(response.body)};};
    const control=(action,module)=>execFileSync('php',['tests/Support/catalog_mysql_control.php',action,module],{cwd:root,env:{...process.env,AUREL_CATALOG_MYSQL_STAGE:descriptor},encoding:'utf8'});
    const publicImage=async(url,expectedFile)=>{
        const bytes=await new Promise((resolve,reject)=>http.get(url,response=>{const chunks=[];assert.equal(response.statusCode,200);assert.match(response.headers['content-type'],/^image\//);response.on('data',c=>chunks.push(c));response.on('end',()=>resolve(Buffer.concat(chunks)));response.on('error',reject);}).on('error',reject));
        const digest=value=>require('node:crypto').createHash('sha256').update(value).digest('hex');
        assert.equal(digest(bytes),digest(fs.readFileSync(expectedFile)));
    };
    const setFiles=async(selector,files)=>{const doc=await page('DOM.getDocument');const node=await page('DOM.querySelector',{nodeId:doc.root.nodeId,selector});await page('DOM.setFileInputFiles',{nodeId:node.nodeId,files});};
    const expReady=()=>wait("document.querySelector('#exa-create') && !document.querySelector('#exa-create').disabled && !document.querySelector('#exa-table').hidden");
    const guideReady=()=>wait("document.querySelector('#ga-create') && !document.querySelector('#ga-create').disabled && !document.querySelector('#ga-table').hidden");
    try {
        let healthBody='';
        for(let attempt=0;attempt<70;attempt++){try{healthBody=(await readHttp(origin+'/__catalog/health')).body;const health=JSON.parse(healthBody);if(health.synthetic_only){assert.equal(health.driver,'mysql');assert.equal(health.identity.db,stage.database);assert.equal(health.identity.uuid,stage.server_uuid);break;}}catch{}await delay(100);if(attempt===69)throw new Error('Fixture server unavailable: '+healthBody.slice(0,1200)+' '+serverLog.slice(-1000));}
        const target=await send('Target.createTarget',{url:'about:blank'});({sessionId}=await send('Target.attachToTarget',{targetId:target.targetId,flatten:true}));
        await page('Page.enable');await page('Runtime.enable');await page('Network.enable');await page('Fetch.enable',{patterns:[{urlPattern:'*'}]});
        await page('Page.addScriptToEvaluateOnNewDocument',{source:`if(location.origin===${JSON.stringify(origin)})localStorage.setItem('admin_token',${JSON.stringify(stage.admin_token)});`});

        mark('Owned MySQL identity and actual public/storage junction');
        await navigate('/trai-nghiem');await ready('experiences');assert.equal(await evaluate("document.querySelectorAll('.exp-card').length"),12);await shot('mysql-experiences-desktop',1440,1000);
        await navigate('/cam-nang');await ready('guides');assert.equal(await evaluate("document.querySelectorAll('#guide-grid .gd-card').length"),9);await wait("document.querySelector('#guide-featured img')?.naturalWidth>0");await shot('mysql-guides-desktop',1440,1000);mark('Both catalogs coexist on one MySQL schema');
        await click('#guide-next');await ready('guides');assert.equal(await evaluate("document.querySelectorAll('#guide-grid .gd-card').length"),3);
        await fill('#guide-query','NO_MATCH_MYSQL');await evaluate("document.querySelector('#guide-search').requestSubmit()");await ready('guides');assert.equal(await evaluate("!document.querySelector('#guide-empty').hidden"),true);await click('#guide-reset');await ready('guides');await click('[data-category="mysql-fixture"]');await ready('guides');assert.equal(await evaluate("[...document.querySelectorAll('#guide-grid .gd-category')].every(n=>n.textContent==='Danh mục MySQL kiểm thử')"),true);mark('Guide MySQL filtering, pagination and empty state');
        control('fault-on','guides');
        try{await evaluate("document.querySelector('#guide-search').requestSubmit()");await ready('guides');assert.equal(await evaluate("!document.querySelector('#guide-error').hidden"),true);}finally{control('fault-off','guides');}
        await click('#guide-retry');await ready('guides');assert.equal(await evaluate("document.querySelector('#guide-error').hidden && document.querySelectorAll('.gd-card').length>0"),true);mark('Real MySQL schema fault and Guide 503/retry');
        await navigate('/trai-nghiem');await ready('experiences');await click('#experience-next');await ready('experiences');assert.equal(await evaluate("document.querySelectorAll('.exp-card').length"),2);
        await change('#experience-location','vn-phu-quoc');await ready('experiences');assert.equal(await evaluate("[...document.querySelectorAll('.exp-card-place')].every(n=>n.textContent==='Phú Quốc')"),true);
        await fill('#experience-query','NO_MATCH_MYSQL');await evaluate("document.querySelector('#experience-filters').requestSubmit()");await ready('experiences');assert.equal(await evaluate("!document.querySelector('#experience-empty').hidden"),true);await click('#experience-reset');await ready('experiences');mark('Experience MySQL location, pagination and empty state');
        control('fault-on','experiences');
        try{await click('[data-experience-category="am-thuc"]');await ready('experiences');assert.equal(await evaluate("!document.querySelector('#experience-error').hidden"),true);}finally{control('fault-off','experiences');}
        await click('#experience-retry');await ready('experiences');assert.equal(await evaluate("document.querySelector('#experience-error').hidden && document.querySelectorAll('.exp-card').length>0"),true);mark('Real MySQL schema fault and Experience 503/retry');
        await navigate('/trai-nghiem/'+stage.experience_slug);await wait("document.querySelectorAll('[data-experience-photo]').length===2 && document.querySelector('[data-experience-photo] img').naturalWidth>0");assert.equal(await evaluate("document.querySelectorAll('.exp-hotel').length"),1);
        await click('[data-experience-photo="0"]');await wait("document.querySelector('#experience-lightbox').open");await click('[data-lightbox-next]');assert.equal(await evaluate("document.querySelector('#experience-lightbox-count').textContent"),'2 / 2');mark('Experience detail/gallery and matching hotel on MySQL');
        await navigate('/cam-nang/'+stage.guide_slug);assert.equal(await evaluate("document.body.textContent.includes('Khám phá Hà Nội') && document.body.textContent.includes('Lưu trú tại Hà Nội')"),true);
        const linked=(await api('/api/guides/'+stage.guide_slug)).data.data;assert.equal(linked.hotels.length,1);assert.equal(linked.hotels[0].name,'Khách sạn kiểm thử Hà Nội');assert.ok(linked.experiences.every(e=>e.location.name==='Hà Nội'));await publicImage(linked.cover_url,stage.upload_file);mark('Guide reuses same-location experiences/hotels and actual image bytes');
        await navigate('/blog/1');assert.equal(await evaluate('location.pathname'),'/cam-nang');assert.equal(await evaluate("Boolean(document.querySelector('.gd-notice'))"),true);await navigate('/blog/3');assert.equal(await evaluate("Boolean(document.querySelector('.gd-notice'))"),true);await navigate('/blog');assert.equal(await evaluate('location.pathname'),'/cam-nang');mark('Blog legacy imports stay pending review on MySQL');
        for(const width of [768,390,320]){
            for(const [url,module,selector]of [['/trai-nghiem','experiences','.exp-page'],['/cam-nang','guides','.gd-page']]){
                await navigate(url,width,900);await ready(module);await evaluate("window.scrollTo({top:0,behavior:'instant'});new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)))");
                assert.equal(await evaluate("document.documentElement.scrollWidth<=innerWidth && document.querySelector("+JSON.stringify(selector)+").getBoundingClientRect().top>=document.querySelector('.hv-header').getBoundingClientRect().bottom-1"),true);
                assert.equal(await evaluate("[...document.querySelectorAll('.mobile-nav-link')].some(a=>a.textContent==='Cẩm nang' && a.href.endsWith('/cam-nang')) && !document.querySelector('footer').textContent.includes('HolidayViet')"),true);await shot('mysql-'+module+'-'+width,width,900);
            }mark('Shared Header/Footer and both Client layouts '+width);
        }
        await navigate('/admin/experiences');await expReady();await click('#exa-create');await wait("document.querySelector('#exa-editor').open");
        const experienceName='MySQL browser experience';await fill('#exa-name',experienceName);await change('#exa-location','72');await change('#exa-category','tham-quan');await fill('#exa-description','Synthetic cross-module integration fixture.');await fill('#exa-source','https://example.test/mysql-fixture');await fill('#exa-image-source','Synthetic runner images, isolated MySQL only.');
        await setFiles('#exa-files',[stage.upload_file,stage.replacement_file]);await wait("document.querySelectorAll('#exa-new-images img').length===2");await click('[data-new-image="1"]');await click('#exa-active');await click('#exa-verify');await click('#exa-publish');await click('#exa-submit');
        await wait("!document.querySelector('#exa-editor').open || !document.querySelector('#exa-form-error').hidden");assert.equal(await evaluate("document.querySelector('#exa-form-error').hidden"),true,await evaluate("document.querySelector('#exa-form-error').textContent"));
        let experience=(await api('/api/admin/attractions?q='+encodeURIComponent(experienceName))).data.data[0];assert.equal(experience.images.length,2);let experiencePublic=(await api('/api/experiences/'+experience.slug)).data.data;await publicImage(experiencePublic.cover.url,stage.replacement_file);mark('Admin Experience upload, chosen cover, verification and publication on MySQL');
        await navigate('/admin/guides');await guideReady();await click('#ga-create');await wait("document.querySelector('#ga-editor').open");
        const guideName='MySQL browser guide';await fill('#ga-title',guideName);await fill('#ga-excerpt','Synthetic MySQL integration article, never production content.');const category=(await api('/api/admin/guides/meta')).data.categories.find(c=>c.slug==='mysql-fixture');
        await change('#ga-category',String(category.id));await change('#ga-location','72');await fill('#ga-author','Tác giả fixture');await fill('#ga-body','<h2>Liên kết tích hợp</h2><p>'+('Synthetic MySQL browser article. '.repeat(10))+'</p>');await setFiles('#ga-cover-file',[stage.upload_file]);await click('#ga-submit');
        await wait("!document.querySelector('#ga-editor').open || !document.querySelector('#ga-form-error').hidden");assert.equal(await evaluate("document.querySelector('#ga-form-error').hidden"),true,await evaluate("document.querySelector('#ga-form-error').textContent"));
        let guide=(await api('/api/admin/guides?q='+encodeURIComponent(guideName))).data.data[0];assert.equal(guide.status,'draft');assert.equal((await api('/api/guides/'+guide.slug)).status,404);
        await wait("!document.querySelector('#ga-table').hidden");await evaluate("[...document.querySelectorAll('#ga-rows tr')].find(tr=>tr.textContent.includes('MySQL browser guide')).querySelector('.ga-actions button').click()");await wait("document.querySelector('#ga-editor').open");await change('#ga-status','published');await click('#ga-submit');await wait("!document.querySelector('#ga-editor').open");
        let guidePublic=(await api('/api/guides/'+guide.slug)).data.data;assert.ok(guidePublic.experiences.some(e=>e.id===experience.id));await publicImage(guidePublic.cover_url,stage.upload_file);assert.notEqual(guidePublic.cover_url,experiencePublic.cover.url);mark('Admin Guide draft/publication with independent cover and same-location links');
        await navigate('/cam-nang/'+guide.slug);assert.equal(await evaluate("document.body.textContent.includes('MySQL browser experience') && document.body.textContent.includes('Khách sạn kiểm thử Hà Nội')"),true);mark('Client Guide reflects newly managed Experience and matching hotel');
        await navigate('/admin/experiences');await expReady();await fill('#exa-query',experienceName);await evaluate("document.querySelector('#exa-filters').requestSubmit()");await wait("!document.querySelector('#exa-table').hidden && document.querySelectorAll('#exa-rows tr').length===1");
        await click('#exa-rows .exa-actions button');await wait("document.querySelector('#exa-editor').open");await fill('#exa-name',experienceName+' edited');await click('#exa-verify');await click('#exa-submit');await wait("!document.querySelector('#exa-editor').open");
        guidePublic=(await api('/api/guides/'+guide.slug)).data.data;assert.ok(guidePublic.experiences.some(e=>e.id===experience.id&&e.name===experienceName+' edited'));mark('Admin Experience edit is reflected in Guide without changing slug');
        await wait("!document.querySelector('#exa-table').hidden");await click('#exa-rows button[aria-label]');await wait("!document.querySelector('#exa-table').hidden && document.querySelector('#exa-rows button[aria-label]').textContent==='Đang tắt'");assert.ok(!(await api('/api/guides/'+guide.slug)).data.data.experiences.some(e=>e.id===experience.id));
        await click('#exa-rows button[aria-label]');await wait("!document.querySelector('#exa-table').hidden && document.querySelector('#exa-rows button[aria-label]').textContent==='Đang bật'");assert.ok((await api('/api/guides/'+guide.slug)).data.data.experiences.some(e=>e.id===experience.id));mark('Experience active state immediately updates Guide suggestions');
        await evaluate("window.confirm=()=>true;document.querySelector('#exa-rows .exa-actions button:last-child').click()");await wait("!document.querySelector('#exa-empty').hidden");await change('#exa-archive-filter','1');await wait("!document.querySelector('#exa-table').hidden && document.querySelector('#exa-rows').textContent.includes('Khôi phục')");await click('#exa-rows .exa-actions button');await wait("!document.querySelector('#exa-empty').hidden");
        experience=(await api('/api/admin/attractions/'+experience.id)).data;assert.equal(experience.experience_verified_at,null);assert.equal(experience.is_active,false);assert.equal(experience.images.length,2);mark('Experience archive/restore keeps images and clears publication/verification');
        await navigate('/admin/guides');await guideReady();await fill('#ga-query',guideName);await evaluate("document.querySelector('#ga-filters').requestSubmit()");await wait("!document.querySelector('#ga-table').hidden && document.querySelectorAll('#ga-rows tr').length===1");await click('#ga-rows .ga-actions button');await wait("document.querySelector('#ga-editor').open");await fill('#ga-title',guideName+' edited');await click('#ga-submit');await wait("!document.querySelector('#ga-editor').open");assert.equal((await api('/api/guides/'+guide.slug)).data.data.title,guideName+' edited');
        await wait("!document.querySelector('#ga-table').hidden");await click('#ga-rows .ga-actions button:nth-child(2)');await wait("!document.querySelector('#ga-empty').hidden");await change('#ga-archive-filter','1');await evaluate("document.querySelector('#ga-filters').requestSubmit()");await wait("!document.querySelector('#ga-table').hidden && document.querySelector('#ga-rows').textContent.includes('Khôi phục')");await click('#ga-rows .ga-actions button');await wait("!document.querySelector('#ga-empty').hidden");
        guide=(await api('/api/admin/guides/'+guide.id)).data;assert.equal(guide.status,'draft');assert.equal(guide.archived_at,null);assert.ok(guide.cover_url);await publicImage(guide.cover_url,stage.upload_file);mark('Guide edit/archive/restore keeps slug and actual cover while returning to draft');
        for(const [url,button,dialog,field]of [['/admin/experiences','#exa-create','#exa-editor','#exa-name'],['/admin/guides','#ga-create','#ga-editor','#ga-title']]){
            await navigate(url,390,900);await wait("document.querySelector("+JSON.stringify(button)+") && !document.querySelector("+JSON.stringify(button)+").disabled");await click(button);await wait("document.querySelector("+JSON.stringify(dialog)+").open");assert.equal(await evaluate("document.documentElement.scrollWidth<=innerWidth && document.querySelector("+JSON.stringify(field)+").getBoundingClientRect().right<=innerWidth"),true);await shot('mysql-'+(url.includes('experiences')?'experience':'guide')+'-admin-mobile',390,900);
        }mark('Both Admin editors render MySQL metadata on mobile');assert.deepEqual(errors,[]);mark('No JavaScript exceptions or real external side effects');
    } catch (error) {
        if(sessionId) {
            fs.writeFileSync(path.join(stage.runtime,'failure.html'),await evaluate('document.documentElement.outerHTML').catch(()=>''));
            const diagnostic=await evaluate('JSON.stringify({images:[...document.images].map(i=>({src:i.src,loaded:i.naturalWidth})),dialog:document.querySelector("#ga-editor")?.open,activeElement:document.activeElement.outerHTML.slice(0,300),formError:document.querySelector("#ga-form-error")?.textContent,header:document.querySelector(".hv-header")?.getBoundingClientRect().toJSON(),guide:document.querySelector(".gd-page")?.getBoundingClientRect().toJSON()})').catch(()=>'');
            fs.writeFileSync(path.join(stage.runtime,'failure.json'),diagnostic);
            await shot('failure',1440,1000).catch(()=>{});
        }
        throw error;
    } finally {
        fs.writeFileSync(path.join(stage.runtime,'browser-report.json'),JSON.stringify({synthetic_only:true,driver:'mysql',database:stage.database,server_uuid:stage.server_uuid,origin,storage_realpath:fs.realpathSync(path.join(stage.runtime,'public','storage')),checks,errors,screenshots,responses},null,2));
        fs.writeFileSync(path.join(stage.runtime,'server.log'),serverLog);browser.kill();server.kill();console.log('Catalog MySQL browser artifacts: '+stage.runtime);
    }
})().catch(error=>{console.error(error.stack);process.exitCode=1;});
