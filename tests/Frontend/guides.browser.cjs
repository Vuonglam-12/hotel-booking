// Real Laravel routes + owned SQLite/MySQL, real uploads, disposable Chrome profile. No production connection exists.
// Usage: node tests/Frontend/guides.browser.cjs [optional Chrome executable]
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
    const setup = JSON.parse(execFileSync('php', mysql?['tests/Support/guide_browser_setup.php']:['-d','extension=pdo_sqlite','-d','extension=sqlite3','tests/Support/guide_browser_setup.php'], {cwd:root,encoding:'utf8'}));
    const stage = JSON.parse(fs.readFileSync(setup.stage_file,'utf8'));
    assert.equal(stage.synthetic_only && stage.driver === (mysql?'mysql':'sqlite'),true);
    if(mysql){assert.match(stage.database,/^aurel_catalog_test_[a-f0-9]{12}$/);fs.mkdirSync(path.join(stage.runtime,'public'));fs.symlinkSync(path.join(stage.runtime,'uploads'),path.join(stage.runtime,'public/storage'),'junction');}
    const sources = ['resources/views/Layout.blade.php','resources/views/partials/layout/header.blade.php','resources/views/partials/layout/search.blade.php',
        'resources/views/admin/layouts/admin.blade.php','resources/views/admin/guides.blade.php',
        ...fs.readdirSync(path.join(root,'resources/views/guides')).filter(file=>file.endsWith('.blade.php')).map(file=>'resources/views/guides/'+file)];
    const css = await postcss([tailwind({content:sources.map(file=>({raw:fs.readFileSync(path.join(root,file),'utf8'),extension:'html'}))})])
        .process(fs.readFileSync(path.join(root,'resources/css/app.css'),'utf8'),{from:undefined});
    const shell = ['design-system.css','hotel-explore.css','site-shell.css'].map(file=>fs.readFileSync(path.join(root,'resources/css',file),'utf8').replace(/^@import .*;\r?\n/gm,'')).join('\n');
    fs.writeFileSync(path.join(stage.runtime,'browser.css'),css.css+'\n'+shell);
    const reservation = net.createServer(); await new Promise(resolve=>reservation.listen(0,'127.0.0.1',resolve));
    const port = reservation.address().port; await new Promise(resolve=>reservation.close(resolve));
    const origin = 'http://127.0.0.1:'+port;
    const serverArgs=mysql?['-S','127.0.0.1:'+port,'-t',path.join(stage.runtime,'public'),'tests/Support/catalog_mysql_browser_router.php']:['-d','extension=pdo_sqlite','-d','extension=sqlite3','-S','127.0.0.1:'+port,'tests/Support/guide_browser_router.php'];
    const server = spawn('php',serverArgs,
        {cwd:root,env:{...process.env,AUREL_GUIDE_BROWSER_STAGE:setup.stage_file},windowsHide:true,stdio:['ignore','pipe','pipe']});
    let serverLog=''; server.stdout.on('data',chunk=>serverLog+=chunk); server.stderr.on('data',chunk=>serverLog+=chunk);
    const browser = spawn((process.argv[2] && !process.argv[2].startsWith('--') ? process.argv[2] : null) || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
        ['--headless=new','--no-first-run','--no-default-browser-check','--disable-background-networking','--disable-component-update','--disable-sync','--disable-extensions','--disable-gpu','--user-data-dir='+path.join(stage.runtime,'chrome-profile'),'--remote-debugging-pipe'],
        {stdio:['ignore','ignore','pipe','pipe','pipe'],windowsHide:true});
    const uiDeadline=process.argv.includes('--admin-semantics')?setTimeout(()=>{console.error('Admin UI test exceeded 180 seconds');browser.kill();server.kill();process.exitCode=1;},180000):null;
    const pending = new Map(); let id=0, buffer='', chromeLog=''; const errors=[], checks=[], screenshots=[], responses=[];
    browser.stderr.on('data',chunk=>chromeLog+=chunk); browser.stdio[4].setEncoding('utf8');
    let sessionId, failList=false, slowList=false, failCategoryCreate=false;
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
        if(process.argv.includes('--admin-semantics')) {
            const fixtureHotel={id:1,name:'Synthetic UI hotel',status:'active',location:{id:72,name:'Fixture city'},amenities:[],description:'UI fixture only'};
            const fixtureReview={id:910,rating:4,comment:'Synthetic review',created_at:'2026-10-20',user:{name:'Fixture user'},customer:{name:'Fixture user'},hotel:fixtureHotel};
            const fixtureBooking={id:910,status:'pending',guest_name:'Fixture guest',customer:{name:'Fixture guest'},hotel:fixtureHotel,check_in:'2026-11-01',check_out:'2026-11-02',total_price:100};
            const paginated=data=>({data,total:data.length,per_page:15,current_page:1,last_page:1,from:1,to:data.length});
            if(url.pathname==='/api/admin/dashboard')return json({pending_bookings:1,pending_reviews:1,recent_bookings:[fixtureBooking],pending_reviews_list:[fixtureReview],revenue_7days:[]});
            if(url.pathname==='/api/admin/hotels')return json({...paginated([fixtureHotel]),stats:{}});
            if(url.pathname==='/api/admin/hotels/1')return json({hotel:fixtureHotel});
            if(url.pathname==='/api/admin/customers')return json({stats:{total:1,new_month:1,total_booking:1,total_revenue:100},customers:paginated([{id:910,name:'Fixture customer',bookings_count:1,reviews_count:1}])});
            if(url.pathname==='/api/admin/bookings')return json(paginated([fixtureBooking]));
            if(url.pathname==='/api/admin/reviews')return json(paginated([fixtureReview]));
            if(url.pathname==='/api/admin/reviews/910' && event.request.method==='DELETE')return json({message:'Deleted isolated UI fixture'});
            if(url.pathname==='/api/admin/revenue')return json({total_revenue:100,monthly_revenue:[],top_hotels:[{...fixtureHotel,total_revenue:100}]});
        }
        if(url.pathname==='/api/hotels')return json({data:[],last_page:1}); // Unchanged hotel search has its own regression suite.
        if(url.pathname==='/api/admin/dashboard')return json({pending_bookings:0,pending_reviews:0});
        if(url.pathname==='/api/admin/guides/categories' && event.request.method==='POST' && failCategoryCreate){failCategoryCreate=false;return page('Fetch.fulfillRequest',{requestId:event.requestId,responseCode:503,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:Buffer.from('{"message":"Category service temporarily unavailable"}').toString('base64')});}
        if(url.pathname==='/api/guides' && failList){failList=false;return page('Fetch.fulfillRequest',{requestId:event.requestId,responseCode:503,responseHeaders:[{name:'Content-Type',value:'application/json'}],body:Buffer.from('{"message":"isolated temporary error"}').toString('base64')});}
        if(url.pathname==='/api/guides' && slowList)await delay(650);
        return page('Fetch.continueRequest',{requestId:event.requestId});
    }
    const evaluate=async expression=>{const result=await page('Runtime.evaluate',{expression,awaitPromise:true,returnByValue:true,userGesture:true});if(result.exceptionDetails)throw new Error(JSON.stringify(result.exceptionDetails));return result.result.value;};
    const wait=async expression=>{const end=Date.now()+18000;while(Date.now()<end){if(await evaluate(expression))return;await delay(70);}throw new Error('Timed out: '+expression);};
    const click=selector=>evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`);
    const fill=(selector,value)=>evaluate(`document.querySelector(${JSON.stringify(selector)}).value=${JSON.stringify(value)}`);
    const change=(selector,value)=>evaluate(`document.querySelector(${JSON.stringify(selector)}).value=${JSON.stringify(value)};document.querySelector(${JSON.stringify(selector)}).dispatchEvent(new Event('change',{bubbles:true}))`);
    const mark=message=>{checks.push(message);console.log('PASS '+message);};
    const navigate=async(url,width=1440,height=1000)=>{await page('Emulation.setDeviceMetricsOverride',{width,height,screenWidth:width,screenHeight:height,deviceScaleFactor:1,mobile:false});await page('Page.navigate',{url:origin+url});};
    const ready=()=>wait("document.getElementById('guide-results')?.getAttribute('aria-busy')==='false'");
    const shot=async(name,width,height)=>{const image=await page('Page.captureScreenshot',{format:'png',captureBeyondViewport:true,clip:{x:0,y:0,width,height,scale:1}});const file=path.join(stage.runtime,name+'.png');fs.writeFileSync(file,Buffer.from(image.data,'base64'));screenshots.push(file);};
    const api=async endpoint=>{const response=await readHttp(origin+endpoint,{Accept:'application/json',Authorization:'Bearer '+stage.admin_token});return {status:response.status,data:JSON.parse(response.body)};};
    try {
        let healthBody='';
        for(let attempt=0;attempt<70;attempt++){try{healthBody=(await readHttp(origin+(mysql?'/__catalog/health':'/__guide/health'))).body;const health=JSON.parse(healthBody);if(health.synthetic_only&&health.driver===stage.driver)break;}catch{}await delay(100);if(attempt===69)throw new Error('Fixture server unavailable: '+healthBody.slice(0,1200)+' '+serverLog.slice(-1000));}
        const target=await send('Target.createTarget',{url:'about:blank'});({sessionId}=await send('Target.attachToTarget',{targetId:target.targetId,flatten:true}));
        await page('Page.enable');await page('Runtime.enable');await page('Network.enable');await page('Fetch.enable',{patterns:[{urlPattern:'*'}]});
        await page('Page.bringToFront');
        await page('Page.addScriptToEvaluateOnNewDocument',{source:`if(location.origin===${JSON.stringify(origin)})localStorage.setItem('admin_token',${JSON.stringify(stage.admin_token)});`});

        if(process.argv.includes('--admin-semantics')){
            const routes=[['/admin','#reviewsWrap .btn-reject'],['/admin/hotels','.hotel-row-actions'],['/admin/hotels/1','#hotelStatusAction'],['/admin/customers','#customerTbody .btn-detail'],['/admin/bookings','.bk-table .badge'],['/admin/reviews','#reviewTableBody .btn-delete'],['/admin/revenue','.transaction-table'],['/admin/experiences','#exa-rows tr'],['/admin/guides','#ga-rows tr']];
            for(const width of [1440,768,390]){
                await page('Emulation.setTouchEmulationEnabled',{enabled:width<600});
                for(const [route,selector] of routes){
                    await navigate(route,width,900);await wait("document.querySelector('#admin-shell')?.hidden===false");
                    // Booking table ID is intentionally independent of status logic.
                    await wait(route==='/admin/bookings'?"document.querySelector('.bk-table .badge')":`document.querySelector(${JSON.stringify(selector)})`);
                    await delay(230); // Let the existing auth/sidebar transitions finish before measuring/capturing.
                    assert.equal(await evaluate('document.documentElement.scrollWidth<=innerWidth+1'),true,`${route} overflow at ${width}`);
                    assert.equal(await evaluate("Boolean(document.querySelector('style')&&document.querySelector('#adminConfirm'))"),true);
                    if(width===1440&&route==='/admin'){
                        await wait("document.querySelector('#adminSidebar').getBoundingClientRect().width>=247");
                        assert.equal(await evaluate("document.querySelector('.btn-approve').disabled"),true);
                        assert.equal(await evaluate("document.querySelector('.btn-reject').dataset.adminTone"),'danger');
                        await shot('admin-dashboard-desktop',width,900);
                    }
                }
                mark(`All 9 Admin screens render without page overflow at ${width}px`);
            }
            await navigate('/admin/guides');await wait("document.querySelector('#ga-create')?.disabled===false && document.querySelector('#ga-rows [data-admin-action=archive]')");
            await page('Emulation.setTouchEmulationEnabled',{enabled:false});
            const css=await evaluate("(()=>{const b=document.querySelector('#ga-create'),r=document.querySelector('#ga-rows [data-admin-action=archive]');return {primary:getComputedStyle(b).backgroundColor,secondary:getComputedStyle(r).backgroundColor,archive:r.dataset.adminTone,edit:document.querySelector('#ga-rows [data-admin-action=edit]').dataset.adminTone,icons:!!b.querySelector('use')&&!!r.querySelector('use')}})()");
            assert.equal(css.primary,'rgb(37, 99, 235)');assert.equal(css.archive,'neutral');assert.equal(css.edit,'primary');assert.notEqual(css.primary,css.secondary);assert.equal(css.icons,true);mark('Primary, edit and archive actions have distinct tones and real icons');
            await page('Input.dispatchKeyEvent',{type:'keyDown',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});await page('Input.dispatchKeyEvent',{type:'keyUp',key:'Tab',code:'Tab',windowsVirtualKeyCode:9});
            await evaluate("document.querySelector('#ga-create').focus()");assert.equal(await evaluate("getComputedStyle(document.querySelector('#ga-create')).outlineStyle"),'solid');
            await evaluate("document.querySelector('#ga-create').disabled=true");assert.equal(await evaluate("getComputedStyle(document.querySelector('#ga-create')).cursor"),'not-allowed');await evaluate("document.querySelector('#ga-create').disabled=false");mark('Keyboard focus and disabled controls remain visually distinct');
            const hover=await evaluate("(()=>{const r=document.querySelector('#ga-create').getBoundingClientRect();return {x:r.x+r.width/2,y:r.y+r.height/2}})()");await page('Input.dispatchMouseEvent',{type:'mouseMoved',...hover});await delay(200);assert.equal(await evaluate("getComputedStyle(document.querySelector('#ga-create')).backgroundColor"),'rgb(29, 78, 216)');mark('Primary button hover is visibly different');
            await click('#ga-create');await wait("document.querySelector('#ga-editor').open");
            await fill('#ga-title','Admin semantic isolated draft');await fill('#ga-category','Danh mục kiểm thử');
            await evaluate("document.querySelector('#ga-body-visual').innerHTML='<h2>UI heading</h2><p>Isolated content</p>';document.querySelector('#ga-body-visual').dispatchEvent(new InputEvent('input',{bubbles:true}))");
            await click('#ga-submit');await wait("!document.querySelector('#ga-editor').open");await wait("document.querySelector('#ga-rows strong')?.textContent==='Admin semantic isolated draft'");
            assert.equal(await evaluate("document.querySelector('#adminToast').dataset.adminTone"),'success');
            await click('#ga-rows [data-admin-action=edit]');await wait("document.querySelector('#ga-editor').open");
            assert.equal(await evaluate("document.querySelector('#ga-body-visual h2').textContent"),'UI heading');
            await evaluate("adminBusy(document.querySelector('#ga-submit'),true)");assert.equal(await evaluate("getComputedStyle(document.querySelector('#ga-submit'),'::before').animationName"),'admin-spin');
            await evaluate("adminBusy(document.querySelector('#ga-submit'),false)");await click('[data-ga-close]');mark('Shared UI preserves formatted draft save/reopen, success feedback and loading state');
            await click('#ga-rows [data-admin-action=archive]');await wait("!document.querySelector('#ga-notice').hidden");await click('#ga-archived-tab');await wait("document.querySelector('#ga-rows [data-admin-action=restore]')");
            assert.equal(await evaluate("document.querySelector('#ga-rows [data-admin-action=restore]').dataset.adminTone"),'success');
            await click('#ga-rows [data-admin-action=restore]');await wait("document.querySelector('#ga-status-filter').value==='draft'&&document.querySelector('#ga-rows strong')?.textContent==='Admin semantic isolated draft'");mark('Archive/filter/restore retains the article and returns it to drafts with distinct buttons');
            for(const tone of ['success','warning','error']){await evaluate(`adminToast('Isolated '+${JSON.stringify(tone)},10000,${JSON.stringify(tone)})`);assert.equal(await evaluate("!!document.querySelector('#adminToastIcon use')"),true);assert.equal(await evaluate("document.querySelector('#adminToast').getAttribute('role')"),tone==='error'?'alert':'status');}
            await click('#adminToastClose');assert.equal(await evaluate("document.querySelector('#adminToast').classList.contains('show')"),false);mark('Success, warning and error notifications include text, icons, live semantics and dismiss');
            await navigate('/admin/reviews');await wait("document.querySelector('.btn-delete')?.dataset.adminTone==='danger'");await click('.btn-delete');await wait("document.querySelector('#adminConfirm').open");
            assert.equal(await evaluate("document.activeElement.id"),'adminConfirmCancel');
            await page('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});await page('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});await wait("!document.querySelector('#adminConfirm').open");
            await click('.btn-delete');await wait("document.querySelector('#adminConfirm').open");await click('#adminConfirmSubmit');await wait("!document.querySelector('#adminConfirm').open");mark('Danger confirmation defaults to Cancel, supports Escape and explicit confirmation');
            await navigate('/admin/guides',320,740);await wait("document.querySelector('#ga-create')?.disabled===false");await click('#ga-create');await wait("document.querySelector('#ga-editor').open");
            await evaluate("window.confirmResult=null;void adminConfirm({title:'Xóa danh mục?',message:'UI fixture only',confirmText:'Xóa danh mục'}).then(v=>window.confirmResult=v)");await wait("document.querySelector('#adminConfirm').open");
            assert.equal(await evaluate("(()=>{const r=document.querySelector('#adminConfirm').getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.bottom<=innerHeight})()"),true);
            await click('#adminConfirmCancel');assert.equal(await evaluate("window.confirmResult"),false);assert.equal(await evaluate("document.querySelector('#ga-editor').open"),true);await shot('admin-guides-mobile-form',320,740);mark('Confirmation fits a 320px viewport and closes back to the existing editor modal');
            await click('[data-ga-close]');await click('#sidebarOpen');await wait("document.documentElement.classList.contains('sidebar-drawer-open')");await click('#sidebarClose');await wait("!document.documentElement.classList.contains('sidebar-drawer-open')");mark('Mobile navigation drawer still opens and closes');
            const pairs=[['#2563EB','#FFFFFF'],['#15803D','#F0FDF4'],['#92400E','#FFFBEB'],['#B91C1C','#FEF2F2'],['#475569','#F1F5F9'],['#475569','#F8F8F8']];
            const luminance=hex=>{const rgb=hex.match(/\w\w/g).map(h=>parseInt(h,16)/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4);return rgb[0]*.2126+rgb[1]*.7152+rgb[2]*.0722;};
            for(const [a,b] of pairs){const x=luminance(a),y=luminance(b);assert.ok((Math.max(x,y)+.05)/(Math.min(x,y)+.05)>=4.5,`contrast ${a}/${b}`);}mark('All 6 shared text/background color pairs meet WCAG AA 4.5:1');
            assert.deepEqual(errors,[]);return;
        }
        if(process.argv.includes('--editor-toolbar')){
            const name='Visual toolbar editor fixture';await navigate('/admin/guides');await wait("document.querySelector('#ga-create') && !document.querySelector('#ga-create').disabled");await click('#ga-create');await wait("document.querySelector('#ga-editor').open");
            await fill('#ga-title',name);await fill('#ga-excerpt','Formatted test article, isolated only.');await fill('#ga-category','Danh mục kiểm thử');await fill('#ga-author','Synthetic author');
            await evaluate("(()=>{const e=document.querySelector('#ga-body-visual');e.innerHTML='<p>Đoạn đầu có phần được chọn và phần còn lại.</p><p>Đoạn tiêu đề mẫu.</p><p>Lời gợi ý cần trích dẫn.</p><p>Điểm ghé thứ nhất.</p><p>Điểm ghé thứ hai.</p><p>Bước chuẩn bị thứ nhất.</p><p>Bước chuẩn bị thứ hai.</p>';e.dispatchEvent(new InputEvent('input',{bubbles:true}));})()");
            const select=async(first,last=first)=>{await evaluate(`(()=>{const e=document.querySelector('#ga-body-visual');e.focus();const w=document.createTreeWalker(e,NodeFilter.SHOW_TEXT),nodes=[];let n;while(n=w.nextNode())nodes.push(n);const a=nodes.find(n=>n.textContent.includes(${JSON.stringify(first)})),b=nodes.find(n=>n.textContent.includes(${JSON.stringify(last)}));if(!a||!b)throw new Error('Fixture selection text missing');const r=document.createRange();r.setStart(a,a.textContent.indexOf(${JSON.stringify(first)}));r.setEnd(b,b.textContent.indexOf(${JSON.stringify(last)})+${JSON.stringify(last)}.length);getSelection().removeAllRanges();getSelection().addRange(r);})()`);await delay(40);};
            await select('phần được chọn');await click('[data-wrap="strong"]');assert.equal(await evaluate("document.querySelector('#ga-body-visual :is(b,strong)')?.textContent"),'phần được chọn');
            await select('được chọn');await evaluate("document.querySelector('[data-wrap=em]').focus()");await page('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',windowsVirtualKeyCode:13,nativeVirtualKeyCode:13,text:'\r'});await page('Input.dispatchKeyEvent',{type:'keyUp',key:'Enter',code:'Enter',windowsVirtualKeyCode:13});assert.equal(await evaluate("document.querySelector('#ga-body-visual :is(i,em)')?.textContent"),'được chọn');mark('Bold and italic apply only to the selection, including keyboard toolbar activation');
            await select('Đoạn tiêu đề mẫu.');await click('[data-wrap="h2"]');assert.equal(await evaluate("document.querySelector('#ga-body-visual h2')?.textContent"),'Đoạn tiêu đề mẫu.');await click('[data-wrap="h3"]');assert.equal(await evaluate("document.querySelectorAll('#ga-body-visual h2').length"),0);assert.equal(await evaluate("document.querySelector('#ga-body-visual h3')?.textContent"),'Đoạn tiêu đề mẫu.');await click('[data-wrap="p"]');assert.equal(await evaluate("document.querySelectorAll('#ga-body-visual h3').length"),0);await click('[data-wrap="h2"]');mark('Paragraph/H2/H3 replace the current paragraph type instead of inserting literal HTML');
            await select('Lời gợi ý cần trích dẫn.');await click('[data-wrap="blockquote"]');assert.equal(await evaluate("document.querySelector('#ga-body-visual blockquote')?.textContent"),'Lời gợi ý cần trích dẫn.');
            await select('Điểm ghé thứ nhất.','Điểm ghé thứ hai.');await click('[data-wrap="ul"]');assert.equal(await evaluate("document.querySelectorAll('#ga-body-visual ul li').length"),2);
            await select('Bước chuẩn bị thứ nhất.','Bước chuẩn bị thứ hai.');await click('[data-wrap="ol"]');assert.equal(await evaluate("document.querySelectorAll('#ga-body-visual ol li').length"),2);mark('Quote, bulleted list and numbered list format the selected paragraphs');
            await click('#ga-preview-button');await wait("!document.querySelector('#ga-preview').hidden");assert.equal(await evaluate("Boolean(document.querySelector('#ga-preview h2')&&document.querySelector('#ga-preview blockquote')&&document.querySelector('#ga-preview ul li')&&document.querySelector('#ga-preview ol li'))"),true);const previewHeading=await evaluate("getComputedStyle(document.querySelector('#ga-preview h2')).fontSize");
            await click('#ga-submit');await wait("!document.querySelector('#ga-editor').open || !document.querySelector('#ga-form-error').hidden");assert.equal(await evaluate("document.querySelector('#ga-form-error').hidden"),true,await evaluate("document.querySelector('#ga-form-error').textContent"));await wait("!document.querySelector('#ga-table').hidden");
            let post=(await api('/api/admin/guides?q='+encodeURIComponent(name))).data.data[0];assert.equal(post.status,'draft');const openSaved=async()=>{await wait(`[...document.querySelectorAll('#ga-rows tr')].some(row=>row.querySelector('strong')?.textContent===${JSON.stringify(name)})`);await evaluate(`[...document.querySelectorAll('#ga-rows tr')].find(row=>row.querySelector('strong')?.textContent===${JSON.stringify(name)}).querySelector('.ga-actions button').click()`);await wait("document.querySelector('#ga-editor').open");};await openSaved();
            assert.equal(await evaluate("Boolean(document.querySelector('#ga-body-visual h2')&&document.querySelector('#ga-body-visual :is(b,strong) :is(i,em)')&&document.querySelector('#ga-body-visual blockquote')&&document.querySelectorAll('#ga-body-visual ul li').length===2&&document.querySelectorAll('#ga-body-visual ol li').length===2)"),true);assert.equal(await evaluate("document.querySelector('#ga-body').value"),post.body_html);mark('Formatted preview and save/close/reopen retain all formatting through MySQL');
            await evaluate("(()=>{const e=document.querySelector('#ga-body-visual');e.focus();const r=document.createRange();r.selectNodeContents(e);r.collapse(false);getSelection().removeAllRanges();getSelection().addRange(r);const d=new DataTransfer();d.setData('text/plain','Nội dung dán an toàn.');d.setData('text/html','<img src=x onerror=window.guidePwned=1><script>window.guidePwned=1</script>');e.dispatchEvent(new ClipboardEvent('paste',{clipboardData:d,bubbles:true,cancelable:true}));})()");
            assert.equal(await evaluate("!window.guidePwned && !document.querySelector('#ga-body-visual img, #ga-body-visual script') && document.querySelector('#ga-body-visual').textContent.includes('Nội dung dán an toàn.')"),true);await click('#ga-preview-button');await wait("!document.querySelector('#ga-preview').hidden");assert.equal(await evaluate("!document.querySelector('#ga-preview img,#ga-preview script') && !window.guidePwned"),true);mark('Pasted HTML cannot execute scripts or inject images into the editor/preview');
            await change('#ga-status','published');await click('#ga-submit');await wait("!document.querySelector('#ga-editor').open");post=(await api('/api/admin/guides/'+post.id)).data;
            await navigate('/cam-nang/'+post.slug);await wait("document.querySelector('.gd-article-body h2')");assert.equal(await evaluate("getComputedStyle(document.querySelector('.gd-article-body h2')).fontSize"),previewHeading);assert.equal(await evaluate("Boolean(document.querySelector('.gd-article-body blockquote')&&document.querySelectorAll('.gd-article-body ul li').length===2&&document.querySelectorAll('.gd-article-body ol li').length===2)&&!window.guidePwned"),true);mark('Customer detail uses the same formatted HTML and body styling as preview');
            for(const width of [768,390,320]){await navigate('/admin/guides',width,900);await wait("document.querySelector('#ga-create') && !document.querySelector('#ga-create').disabled");await click('#ga-create');await wait("document.querySelector('#ga-editor').open");await evaluate("document.querySelector('.ga-toolbar').scrollIntoView({block:'center'})");assert.equal(await evaluate("(()=>{const t=document.querySelector('.ga-toolbar'),e=document.querySelector('#ga-body-visual'),d=document.querySelector('#ga-editor').getBoundingClientRect();return document.documentElement.scrollWidth<=innerWidth&&t.scrollWidth<=t.clientWidth+1&&e.getBoundingClientRect().right<=d.right})()"),true);if(width===390)await shot('guide-visual-editor-mobile',width,900);mark('Visual editor and wrapping toolbar fit '+width+'px');}
            assert.deepEqual(errors,[]);mark('No browser JavaScript exceptions');return;
        }

        if(process.argv.includes('--archive-datetime')){
            await page('Emulation.setTimezoneOverride',{timezoneId:'Asia/Bangkok'});
            await navigate('/admin/guides');await wait("document.querySelector('#ga-create') && !document.querySelector('#ga-create').disabled && !document.querySelector('#ga-table').hidden");
            const seed=(await api('/api/admin/guides?q='+encodeURIComponent('Cẩm nang kiểm thử 01'))).data.data[0];assert.ok(seed.cover_path);
            const file=path.join(stage.runtime,'uploads',seed.cover_path.slice(8)),assetHash=require('node:crypto').createHash('sha256').update(fs.readFileSync(file)).digest('hex');
            const findRow=`[...document.querySelectorAll('#ga-rows tr')].find(row=>row.querySelector('strong')?.textContent===${JSON.stringify(seed.title)})`;
            const openSeed=async()=>{await evaluate(findRow+".querySelector('.ga-actions button').click()");await wait("document.querySelector('#ga-editor').open");};
            const saveEditor=async()=>{await click('#ga-submit');await wait("!document.querySelector('#ga-editor').open || !document.querySelector('#ga-form-error').hidden");assert.equal(await evaluate("document.querySelector('#ga-form-error').hidden"),true,await evaluate("document.querySelector('#ga-form-error').textContent"));await wait("!document.querySelector('#ga-table').hidden");};
            await fill('#ga-query',seed.title);await change('#ga-status-filter','published');await evaluate("document.querySelector('#ga-filters').requestSubmit()");await wait(findRow+"!==undefined");
            await evaluate(findRow+".querySelector('.ga-actions button:nth-child(2)').click()");await wait("!document.querySelector('#ga-notice').hidden && !document.querySelector('#ga-undo').hidden");
            await click('#ga-view-archived');await wait("document.querySelector('#ga-archived-tab').getAttribute('aria-pressed')==='true' && !document.querySelector('#ga-table').hidden");assert.equal(await evaluate("document.querySelector('#ga-status-filter').disabled && document.querySelector('#ga-status-filter').value===''"),true);
            let archived=(await api('/api/admin/guides/'+seed.id)).data;assert.equal(archived.status,'draft');assert.ok(archived.archived_at);assert.equal(archived.body_html,seed.body_html);assert.equal(archived.cover_path,seed.cover_path);mark('Archive remains discoverable even after a published-status filter; content and cover preserved');
            await evaluate(findRow+".querySelector('.ga-actions button').click()");await wait("document.querySelector('#ga-active-tab').getAttribute('aria-pressed')==='true' && document.querySelector('#ga-status-filter').value==='draft' && !document.querySelector('#ga-table').hidden");
            let restored=(await api('/api/admin/guides/'+seed.id)).data;assert.equal(restored.archived_at,null);assert.equal(restored.status,'draft');assert.equal((await api('/api/guides/'+seed.slug)).status,404);mark('Restore returns to the draft list and never republishes');
            await evaluate(findRow+".querySelector('.ga-actions button:nth-child(2)').click()");await wait("!document.querySelector('#ga-undo').hidden && !document.querySelector('#ga-undo').disabled");await click('#ga-undo');await wait("document.querySelector('#ga-undo').hidden && !document.querySelector('#ga-table').hidden");
            restored=(await api('/api/admin/guides/'+seed.id)).data;assert.equal(restored.body_html,seed.body_html);assert.equal(restored.cover_path,seed.cover_path);assert.equal(restored.slug,seed.slug);assert.equal(require('node:crypto').createHash('sha256').update(fs.readFileSync(file)).digest('hex'),assetHash);mark('Undo restores the same ID, slug, body and actual image bytes');
            const payload={};for(const key of ['title','excerpt','body_html','category_id','location_id','author_name','is_featured'])payload[key]=seed[key];payload.status='draft';payload.published_at='2039-03-02T00:04:37+07:00';
            await evaluate(`(async()=>{const response=await adminApi('/guides/${seed.id}',{method:'PUT',body:JSON.stringify(${JSON.stringify(payload)})});if(!response.ok)throw new Error('Owned timestamp fixture failed');})()`);
            await openSeed();assert.equal(await evaluate("document.querySelector('#ga-published-at-date').value"),'02/03/2039');assert.equal(await evaluate("document.querySelector('#ga-published-at-time').value"),'00:04:37');await fill('#ga-excerpt','An unrelated edit preserves the saved instant and seconds.');await saveEditor();
            assert.equal((await api('/api/admin/guides/'+seed.id)).data.published_at,'2039-03-01T17:04:37.000000Z');mark('Editing without touching the picker preserves the original instant and seconds in UTC');
            await openSeed();await evaluate("document.querySelector('#ga-published-at-date').scrollIntoView({block:'center'})");await click('#ga-published-at-date + button');await wait("!document.querySelector('.acp-panel').hidden");
            await evaluate("document.querySelector('.acp-footer button').click()");assert.equal(await evaluate("document.querySelector('.acp-panel').hidden"),true);await click('#ga-published-at-date + button');assert.equal(await evaluate("Boolean(document.querySelector('.acp-day[aria-current=date]'))"),true);await page('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape'});assert.equal(await evaluate("document.querySelector('.acp-panel').hidden && document.querySelector('#ga-editor').open"),true);mark('Today is marked; Escape closes only the compact popup');
            await fill('#ga-published-at-date','29/02/2028');await evaluate("document.querySelector('#ga-published-at-date').dispatchEvent(new Event('change'))");await click('#ga-published-at-date + button');assert.equal(await evaluate("document.querySelector('.acp-title').textContent"),'Tháng 2 năm 2028');
            await click('.acp-nav[data-nav="1"]');assert.equal(await evaluate("document.querySelector('.acp-title').textContent"),'Tháng 3 năm 2028');await click('.acp-nav[data-nav="-1"]');await evaluate("document.querySelector('[data-date=\"2028-02-29\"]').focus()");await page('Input.dispatchKeyEvent',{type:'keyDown',key:'ArrowRight',code:'ArrowRight'});await page('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',windowsVirtualKeyCode:13,nativeVirtualKeyCode:13,text:'\r'});await page('Input.dispatchKeyEvent',{type:'keyUp',key:'Enter',code:'Enter',windowsVirtualKeyCode:13,nativeVirtualKeyCode:13});await wait("document.querySelector('.acp-panel').hidden");assert.equal(await evaluate("document.querySelector('#ga-published-at-date').value"),'01/03/2028');mark('One-month calendar navigates months and leap-day keyboard selection correctly');
            await fill('#ga-published-at-time','08:15');await evaluate("document.querySelector('#ga-published-at-time').dispatchEvent(new Event('change'))");await click('#ga-published-at-time + button');await click('[data-unit="hour"] [data-value="22"]');await evaluate("document.querySelector('[data-unit=\"minute\"] [data-value=\"36\"]').focus()");await page('Input.dispatchKeyEvent',{type:'keyDown',key:'ArrowDown',code:'ArrowDown'});await page('Input.dispatchKeyEvent',{type:'keyDown',key:'Enter',code:'Enter',windowsVirtualKeyCode:13,nativeVirtualKeyCode:13,text:'\r'});await page('Input.dispatchKeyEvent',{type:'keyUp',key:'Enter',code:'Enter',windowsVirtualKeyCode:13,nativeVirtualKeyCode:13});await wait("document.querySelector('.acp-panel').hidden");assert.equal(await evaluate("document.querySelector('#ga-published-at-time').value"),'22:37');
            await saveEditor();assert.equal((await api('/api/admin/guides/'+seed.id)).data.published_at,'2028-03-01T15:37:00.000000Z');mark('24-hour mouse and keyboard selection saves the exact expected MySQL timestamp');
            await openSeed();for(const [width,height] of [[1440,900],[768,700],[390,650],[320,520]]){
                await page('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:false});await evaluate("document.querySelector('#ga-published-at-date').scrollIntoView({block:'center'})");await click('#ga-published-at-date + button');await wait("!document.querySelector('.acp-panel').hidden");await delay(100);
                assert.equal(await evaluate("(()=>{const p=document.querySelector('.acp-panel').getBoundingClientRect(),d=document.querySelector('#ga-editor').getBoundingClientRect();return p.left>=Math.max(0,d.left)&&p.right<=Math.min(innerWidth,d.right)+1&&p.top>=Math.max(0,d.top)&&p.bottom<=Math.min(innerHeight,d.bottom)+1&&document.documentElement.scrollWidth<=innerWidth})()"),true);
                await evaluate("document.querySelector('#ga-editor').scrollTop+=8");await delay(100);assert.equal(await evaluate("document.querySelector('.acp-panel').hidden || document.querySelector('.acp-panel').getBoundingClientRect().bottom<=innerHeight"),true);await shot('guide-compact-calendar-'+width,width,height);
                if(!await evaluate("document.querySelector('.acp-panel').hidden"))await page('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape'});
                await click('#ga-published-at-time + button');await wait("!document.querySelector('.acp-panel').hidden");assert.equal(await evaluate("(()=>{const r=document.querySelector('.acp-panel').getBoundingClientRect(),d=document.querySelector('#ga-editor').getBoundingClientRect();return r.left>=Math.max(0,d.left)&&r.right<=Math.min(innerWidth,d.right)+1&&r.top>=Math.max(0,d.top)&&r.bottom<=Math.min(innerHeight,d.bottom)+1})()"),true);await page('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape'});mark('Calendar and time popup stay within scrollable modal at '+width+'px');
            }
            await page('Emulation.setDeviceMetricsOverride',{width:1440,height:900,deviceScaleFactor:1,mobile:false});await evaluate("document.querySelector('#ga-published-at-date').scrollIntoView({block:'center'})");await click('#ga-published-at-date + button');
            // Click the editor header, which stays inside the modal and outside the picker.
            const header=await evaluate("(()=>{const r=document.querySelector('.ga-dialog-head').getBoundingClientRect(),d=document.querySelector('#ga-editor').getBoundingClientRect();return {x:d.left+12,y:d.top+12}})()");await page('Input.dispatchMouseEvent',{type:'mousePressed',...header,button:'left',clickCount:1});await page('Input.dispatchMouseEvent',{type:'mouseReleased',...header,button:'left',clickCount:1});assert.equal(await evaluate("document.querySelector('.acp-panel').hidden"),true);mark('Outside pointer closes the popup without closing the editor');
            assert.deepEqual(errors,[]);mark('No browser JavaScript exceptions');return;
        }

        if(process.argv.includes('--category-free-input')){
            const name='Nhật ký hành trình riêng',title='Free category input fixture';
            const openNew=async()=>{await click('#ga-create');await wait("document.querySelector('#ga-editor').open");};
            const save=async()=>{await click('#ga-submit');await wait("!document.querySelector('#ga-editor').open || !document.querySelector('#ga-form-error').hidden");assert.equal(await evaluate("document.querySelector('#ga-form-error').hidden"),true,await evaluate("document.querySelector('#ga-form-error').textContent"));await wait("!document.querySelector('#ga-table').hidden");};
            const savedPost=async text=>(await api('/api/admin/guides?q='+encodeURIComponent(text))).data.data[0];
            await navigate('/admin/guides');await wait("document.querySelector('#ga-create') && !document.querySelector('#ga-create').disabled && !document.querySelector('#ga-table').hidden");
            await openNew();assert.equal(await evaluate("document.querySelector('#ga-category').tagName"),'INPUT');await fill('#ga-category',name);await click('#ga-editor [data-ga-close]');
            assert.equal((await api('/api/admin/guides/meta')).data.categories.some(c=>c.name===name),false);mark('Free category text can be entered and cancelled without creating data');
            await openNew();await fill('#ga-title',title);await fill('#ga-category','  Nhật ký   hành trình riêng  ');await save();
            let post=await savedPost(title);const categoryId=post.category_id;assert.equal(post.category.name,name);assert.equal(post.status,'draft');assert.equal((await api('/api/guides/'+post.slug)).status,404);mark('Saving free category text creates a category and links the private draft through existing APIs');
            const beforeCount=(await api('/api/admin/guides/meta')).data.categories.length;
            await evaluate(`document.querySelector('#ga-rows tr .ga-actions button').click()`);await wait("document.querySelector('#ga-editor').open");assert.equal(await evaluate("document.querySelector('#ga-category').value"),name);await fill('#ga-excerpt','Edited draft keeps its existing category.');await save();
            post=await savedPost(title);assert.equal(post.category_id,categoryId);assert.equal((await api('/api/admin/guides/meta')).data.categories.length,beforeCount);mark('Opening and editing an existing draft keeps the category name and ID without duplicates');
            await openNew();await fill('#ga-title',title+' reuse');await fill('#ga-category','nhật ký hành trình riêng');await save();
            assert.equal((await savedPost(title+' reuse')).category_id,categoryId);assert.equal((await api('/api/admin/guides/meta')).data.categories.length,beforeCount);mark('Existing category is reused when typed with different casing');
            await openNew();await fill('#ga-title',title+' retry');await fill('#ga-category','Danh mục sau lỗi tạm thời');failCategoryCreate=true;await click('#ga-submit');
            await wait("!document.querySelector('#ga-form-error').hidden && !document.querySelector('#ga-submit').disabled");assert.equal(await evaluate("document.querySelector('#ga-editor').open && !document.querySelector('#ga-fieldset').disabled"),true);assert.equal((await api('/api/admin/guides?q='+encodeURIComponent(title+' retry'))).data.meta.total,0);await save();assert.equal((await savedPost(title+' retry')).category.name,'Danh mục sau lỗi tạm thời');mark('Category API failure preserves the editor and allows retry without a duplicate article');
            await navigate('/admin/guides',390,900);await wait("document.querySelector('#ga-create') && !document.querySelector('#ga-create').disabled");await openNew();assert.equal(await evaluate("document.documentElement.scrollWidth<=innerWidth && document.querySelector('#ga-category').getBoundingClientRect().right<=innerWidth"),true);await shot('guide-free-category-mobile',390,900);mark('Free category input fits the mobile editor');
            assert.deepEqual(errors,[]);mark('No browser JavaScript exceptions');return;
        }

        if(process.argv.includes('--layout')){
            for(const width of [1440,768,320]){
                await navigate('/cam-nang/'+stage.detail_slug,width,1000);
                await wait("document.readyState==='complete' && document.querySelector('.gd-cover')?.naturalWidth>0");
                await evaluate("window.scrollTo({top:0,left:0,behavior:'instant'});new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)))");
                assert.equal(await evaluate("document.querySelector('.hv-header').getBoundingClientRect().top===0 && document.querySelector('.gd-page').getBoundingClientRect().top>=document.querySelector('.hv-header').getBoundingClientRect().bottom-1 && document.documentElement.scrollWidth<=innerWidth"),true);
                await shot('guide-detail-layout-'+width,width,1000);mark('Detail and shared Header layout '+width);
            }
            assert.deepEqual(errors,[]);return;
        }


        slowList=true;await navigate('/cam-nang');await wait("document.querySelector('#guide-loading') && !document.querySelector('#guide-loading').hidden");mark('Client loading');
        // Nine rows on page one include the featured article: eight remain in the grid.
        await ready();slowList=false;assert.equal(await evaluate("document.querySelectorAll('#guide-grid .gd-card').length"),8);
        assert.equal(await evaluate("[...document.querySelectorAll('.nav-link')].find(a=>a.textContent==='Cẩm nang').getAttribute('href').endsWith('/cam-nang')"),true);
        assert.equal(await evaluate("document.querySelector('#guide-featured h2').textContent"),'Cẩm nang kiểm thử 01');
        assert.equal(await evaluate("document.querySelector('footer').textContent.includes('HolidayViet')"),false);mark('Unified listing, synthetic featured fixture, Header route and AUREL footer');
        await wait("document.querySelector('#guide-featured img')?.complete && document.querySelector('#guide-featured img')?.naturalWidth>0");await shot('guides-desktop',1440,1000);
        await click('[data-category="kiem-thu"]');await ready();assert.equal(await evaluate("document.querySelector('#guide-featured').hidden && [...document.querySelectorAll('#guide-grid .gd-category')].every(n=>n.textContent==='Danh mục kiểm thử')"),true);mark('Category filtering');
        await fill('#guide-query','khong-co-ket-qua-fixture');await evaluate("document.querySelector('#guide-search').requestSubmit()");await ready();assert.equal(await evaluate("!document.querySelector('#guide-empty').hidden"),true);mark('Search and empty state');
        await click('#guide-reset');await ready();failList=true;await click('[data-category="kiem-thu"]');await ready();assert.equal(await evaluate("!document.querySelector('#guide-error').hidden"),true);
        await click('#guide-retry');await ready();assert.equal(await evaluate("document.querySelector('#guide-error').hidden && document.querySelectorAll('#guide-grid .gd-card').length>0"),true);mark('503 error and retry');
        await click('[data-category=""]');await ready();await click('#guide-next');await ready();assert.equal(await evaluate("document.querySelectorAll('#guide-grid .gd-card').length"),3);await click('#guide-prev');await ready();mark('Pagination');
        for(const width of [1024,768,390,320]){
            await navigate('/cam-nang',width,900);await ready();
            assert.equal(await evaluate("document.documentElement.scrollWidth<=innerWidth && [...document.querySelectorAll('.gd-card,.gd-search input,.gd-featured')].every(n=>n.getBoundingClientRect().left>=0 && n.getBoundingClientRect().right<=innerWidth)"),true);
            assert.equal(await evaluate("getComputedStyle(document.querySelector('#guide-grid')).gridTemplateColumns.split(' ').length"),width>=1024?3:width>=640?2:1);
            await wait("document.querySelector('#guide-featured img')?.complete && document.querySelector('#guide-featured img')?.naturalWidth>0");await shot('guides-'+width,width,900);mark('Client responsive '+width);
        }
        await navigate('/blog/1');await wait("document.querySelector('.gd-notice') && document.readyState==='complete'");assert.equal(await evaluate('location.pathname'),'/cam-nang');mark('Complete legacy content remains a draft until editorial approval');
        await navigate('/cam-nang/'+stage.detail_slug);await wait("document.readyState==='complete' && document.querySelector('.gd-article-body h2')");
        assert.equal(await evaluate("document.querySelector('.gd-article-body').textContent.includes('Góc nhìn kiểm thử') && !document.querySelector('.gd-article-body script') && !document.querySelector('#commentSection')"),true);
        assert.equal(await evaluate("document.querySelector('.gd-cover').naturalWidth>0"),true);await shot('guide-synthetic-detail',1440,1000);mark('Synthetic detail, actual cover and no sample comments');
        await navigate('/blog/3');await wait("document.querySelector('.gd-notice') && document.readyState==='complete'");assert.equal(await evaluate("location.pathname"),'/cam-nang');mark('Missing legacy content keeps a working compatibility URL');
        await navigate('/cam-nang/huong-dan-kiem-thu-1',390,900);await wait("document.querySelector('.gd-article-body') && document.readyState==='complete'");
        assert.equal(await evaluate("document.body.textContent.includes('Lưu trú tại Hà Nội') && document.documentElement.scrollWidth<=innerWidth"),true);
        assert.equal(await evaluate("document.body.textContent.includes('Khám phá Hà Nội')"),!mysql);
        await shot('guide-related-mobile',390,900);mark('Real location, experience and hotel relations on mobile');

        await navigate('/admin/guides');await wait("document.querySelector('#ga-create') && !document.querySelector('#ga-create').disabled && !document.querySelector('#ga-table').hidden");await shot('guides-admin',1440,1000);
        await click('#ga-categories');await wait("document.querySelector('#ga-category-dialog').open");await fill('#ga-category-name','Danh mục CRUD browser');await evaluate("document.querySelector('#ga-category-form').requestSubmit()");
        await wait("[...document.querySelectorAll('#ga-category-list strong')].some(n=>n.textContent==='Danh mục CRUD browser')");
        let category=(await api('/api/admin/guides/meta')).data.categories.find(c=>c.name==='Danh mục CRUD browser');assert.ok(category);await click('#ga-category-close');mark('Admin creates a category through protected API');
        await click('#ga-create');await wait("document.querySelector('#ga-editor').open");
        const name='CRUD guide browser fixture',body='<h2>Đề mục thật trong fixture</h2><p><strong>Định dạng an toàn.</strong> '+('Nội dung chỉ phục vụ kiểm thử cô lập. '.repeat(8))+'</p><script>window.guidePwned=1</script><img src=x onerror="window.guidePwned=2">';
        await fill('#ga-title',name);await fill('#ga-excerpt','Tóm tắt bài kiểm thử, không xuất bản trên database chính.');await fill('#ga-category',category.name);await change('#ga-location','72');await fill('#ga-author','Tác giả fixture');await fill('#ga-body',body);
        await click('#ga-preview-button');await wait("!document.querySelector('#ga-preview').hidden");assert.equal(await evaluate("document.querySelector('#ga-preview strong')?.textContent==='Định dạng an toàn.' && !document.querySelector('#ga-preview script') && !window.guidePwned"),true);mark('Server-sanitized formatted preview blocks JavaScript');
        const setFile=async filename=>{const doc=await page('DOM.getDocument');const node=await page('DOM.querySelector',{nodeId:doc.root.nodeId,selector:'#ga-cover-file'});await page('DOM.setFileInputFiles',{nodeId:node.nodeId,files:[filename]});await wait("!document.querySelector('#ga-cover-image').hidden && document.querySelector('#ga-cover-image').naturalWidth>0");};
        await setFile(stage.upload_file);await click('#ga-submit');
        await wait("!document.querySelector('#ga-editor').open || !document.querySelector('#ga-form-error').hidden");assert.equal(await evaluate("document.querySelector('#ga-form-error').hidden"),true,await evaluate("document.querySelector('#ga-form-error').textContent"));
        await wait("!document.querySelector('#ga-table').hidden && [...document.querySelectorAll('#ga-rows strong')].some(n=>n.textContent==='CRUD guide browser fixture')");
        let saved=(await api('/api/admin/guides?q='+encodeURIComponent(name))).data.data[0];const slug=saved.slug,firstCover=saved.cover_path;assert.equal(saved.status,'draft');assert.equal((await api('/api/guides/'+slug)).status,404);assert.ok(saved.cover_url);assert.ok(!saved.body_html.includes('<script'));mark('Admin saves a draft with a real upload; draft remains private');
        const openSaved=async()=>{await evaluate("[...document.querySelectorAll('#ga-rows tr')].find(tr=>tr.textContent.includes('CRUD guide browser fixture')).querySelector('.ga-actions button').click()");await wait("document.querySelector('#ga-editor').open");};
        await openSaved();await fill('#ga-title',name+' edited');await change('#ga-status','published');await setFile(stage.replacement_file);await click('#ga-submit');await wait("!document.querySelector('#ga-editor').open");
        saved=(await api('/api/admin/guides/'+saved.id)).data;const secondCover=saved.cover_path;assert.equal(saved.slug,slug);assert.notEqual(firstCover,secondCover);assert.equal((await api('/api/guides/'+slug)).status,200);
        assert.ok(fs.existsSync(path.join(stage.runtime,'uploads',firstCover.slice(8))));mark('Publish, replace cover and preserve original asset and slug');
        await navigate('/cam-nang/'+slug);await wait("document.readyState==='complete' && document.querySelector('.gd-article-body strong')");
        assert.equal(await evaluate("!window.guidePwned && document.querySelector('.gd-article-head h1').textContent==='CRUD guide browser fixture edited' && document.querySelector('.gd-cover').naturalWidth>0"),true);mark('Published detail retains formatting without executing injected content');
        await navigate('/admin/guides');await wait("document.querySelector('#ga-create') && !document.querySelector('#ga-create').disabled && !document.querySelector('#ga-table').hidden");
        await fill('#ga-query',name);await evaluate("document.querySelector('#ga-filters').requestSubmit()");await wait("!document.querySelector('#ga-table').hidden && document.querySelectorAll('#ga-rows tr').length===1");
        await openSaved();await click('#ga-remove-cover');await click('#ga-submit');await wait("!document.querySelector('#ga-editor').open");assert.equal((await api('/api/admin/guides/'+saved.id)).data.cover_url,null);assert.ok(fs.existsSync(path.join(stage.runtime,'uploads',secondCover.slice(8))));mark('Remove cover link without deleting archived assets');
        await click('#ga-categories');await wait("document.querySelector('#ga-category-dialog').open");
        await evaluate("[...document.querySelectorAll('.ga-category-row')].find(row=>row.textContent.includes('Danh mục CRUD browser')).querySelector('button').click()");await click('#ga-category-active');await evaluate("document.querySelector('#ga-category-form').requestSubmit()");
        await wait("[...document.querySelectorAll('.ga-category-row')].some(row=>row.textContent.includes('Danh mục CRUD browser') && row.textContent.includes('Đang ẩn'))");assert.equal((await api('/api/guides/'+slug)).status,404);
        await evaluate("[...document.querySelectorAll('.ga-category-row')].find(row=>row.textContent.includes('Danh mục CRUD browser')).querySelector('button').click()");await click('#ga-category-active');await evaluate("document.querySelector('#ga-category-form').requestSubmit()");await wait("[...document.querySelectorAll('.ga-category-row')].some(row=>row.textContent.includes('Danh mục CRUD browser') && row.textContent.includes('Đang hiển thị'))");await click('#ga-category-close');assert.equal((await api('/api/guides/'+slug)).status,200);mark('Category hide/show controls public visibility');
        await wait("!document.querySelector('#ga-table').hidden");await openSaved();await change('#ga-status','draft');await click('#ga-submit');await wait("!document.querySelector('#ga-editor').open");assert.equal((await api('/api/guides/'+slug)).status,404);mark('Admin hides an article by returning to draft');
        await wait("!document.querySelector('#ga-table').hidden");await evaluate("document.querySelector('#ga-rows .ga-actions button:nth-child(2)').click()");
        await wait("!document.querySelector('#ga-empty').hidden");await change('#ga-archive-filter','1');await evaluate("document.querySelector('#ga-filters').requestSubmit()");
        await wait("!document.querySelector('#ga-table').hidden && document.querySelector('#ga-rows').textContent.includes('Khôi phục')");await click('#ga-rows .ga-actions button');
        await wait("!document.querySelector('#ga-table').hidden && document.querySelector('#ga-active-tab').getAttribute('aria-pressed')==='true'");saved=(await api('/api/admin/guides/'+saved.id)).data;assert.equal(saved.archived_at,null);assert.equal(saved.status,'draft');mark('Archive and restore keep data and return to draft');
        for(const width of [768,390,320]){await navigate('/admin/guides',width,900);await wait("document.querySelector('#ga-create') && !document.querySelector('#ga-create').disabled");await click('#ga-create');await wait("document.querySelector('#ga-editor').open");assert.equal(await evaluate("document.documentElement.scrollWidth<=innerWidth && document.querySelector('#ga-title').getBoundingClientRect().right<=innerWidth && document.querySelector('#ga-body-visual').getBoundingClientRect().right<=innerWidth"),true);await shot('guides-admin-'+width,width,900);mark('Admin editor responsive '+width);}
        assert.deepEqual(errors,[]);mark('No browser JavaScript exceptions');
    } catch (error) {
        if(sessionId) {
            fs.writeFileSync(path.join(stage.runtime,'failure.html'),await evaluate('document.documentElement.outerHTML').catch(()=>''));
            const diagnostic=await evaluate('JSON.stringify({images:[...document.images].map(i=>({src:i.src,loaded:i.naturalWidth})),dialog:document.querySelector("#ga-editor")?.open,activeElement:document.activeElement.outerHTML.slice(0,300),formError:document.querySelector("#ga-form-error")?.textContent,header:document.querySelector(".hv-header")?.getBoundingClientRect().toJSON(),guide:document.querySelector(".gd-page")?.getBoundingClientRect().toJSON()})').catch(()=>'');
            fs.writeFileSync(path.join(stage.runtime,'failure.json'),diagnostic);
            await shot('failure',1440,1000).catch(()=>{});
        }
        throw error;
    } finally {
        clearTimeout(uiDeadline);
        fs.writeFileSync(path.join(stage.runtime,'browser-report.json'),JSON.stringify({synthetic_only:true,driver:stage.driver,checks,errors,screenshots,responses},null,2));
        fs.writeFileSync(path.join(stage.runtime,'server.log'),serverLog);browser.kill();server.kill();console.log('Guide browser artifacts: '+stage.runtime);
    }
})().catch(error=>{console.error(error.stack);process.exitCode=1;});
