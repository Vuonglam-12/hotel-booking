// Tests the private, static review only. No application server or database.
const fs = require('node:fs');
const path = require('node:path');
const {pathToFileURL, fileURLToPath} = require('node:url');
const {spawn} = require('node:child_process');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const work = fs.realpathSync(process.argv[2]);
const allowed = 'C:\\Users\\ASUS\\AppData\\Local\\AUREL\\content-review';
assert.equal(path.dirname(work), allowed);
const preview = path.join(work, 'review', 'index.html');
const resultFile = path.join(work, 'evidence', 'preview-browser-checks.json');
const review = JSON.parse(fs.readFileSync(path.join(work,'review/review-data.json'),'utf8'));
const normalized = JSON.parse(fs.readFileSync(path.join(work,'review/normalized-drafts.json'),'utf8'));
const baseline = JSON.parse(fs.readFileSync(path.join(work,'evidence/round2-baseline.json'),'utf8'));
const browser = spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', [
    '--headless=new', '--no-first-run', '--no-default-browser-check', '--disable-background-networking',
    '--disable-component-update', '--disable-sync', '--disable-extensions', '--disable-gpu',
    '--user-data-dir='+path.join(work, 'chrome-profile'), '--remote-debugging-pipe'
], {windowsHide:true, stdio:['ignore','ignore','pipe','pipe','pipe']});
const pending = new Map(), errors = [], requests = [], checks = [], screenshots = [];
let counter=0, buffer='', chromeLog='', session;
browser.stderr.on('data', chunk=>chromeLog+=chunk);
browser.stdio[4].setEncoding('utf8');
const send = (method, params={}, sid) => new Promise((resolve,reject)=>{
    const id=++counter, timer=setTimeout(()=>{pending.delete(id);reject(new Error('CDP timeout: '+method));},15000);
    pending.set(id,{resolve,reject,timer});
    browser.stdio[3].write(JSON.stringify({id,method,params,...(sid?{sessionId:sid}:{})})+'\0');
});
browser.stdio[4].on('data', chunk=>{
    buffer+=chunk; let end;
    while((end=buffer.indexOf('\0'))!==-1){
        const message=JSON.parse(buffer.slice(0,end));buffer=buffer.slice(end+1);
        const item=pending.get(message.id);
        if(item){pending.delete(message.id);clearTimeout(item.timer);message.error?item.reject(new Error(JSON.stringify(message.error))):item.resolve(message.result);}
        else if(message.method==='Runtime.exceptionThrown')errors.push(message.params.exceptionDetails.exception?.description||message.params.exceptionDetails.text);
        else if(message.method==='Log.entryAdded' && message.params.entry.level==='error')errors.push(message.params.entry.text);
        else if(message.method==='Network.requestWillBeSent')requests.push(message.params.request.url);
    }
});
const page = (method,params={})=>send(method,params,session);
const evaluate = async expression=>{
    const result=await page('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});
    if(result.exceptionDetails)throw new Error(JSON.stringify(result.exceptionDetails));
    return result.result.value;
};
const delay=ms=>new Promise(resolve=>setTimeout(resolve,ms));
const wait=async expression=>{for(let i=0;i<80;i++){if(await evaluate(expression))return;await delay(100);}throw new Error('Wait failed: '+expression);};
const mark=name=>checks.push({name,status:'PASS'});
const visible="[...document.querySelectorAll('.exp-card')].filter(c=>!c.hidden)";
const set=async (selector,value)=>evaluate(`(()=>{const e=document.querySelector(${JSON.stringify(selector)});e.value=${JSON.stringify(value)};e.dispatchEvent(new Event('input',{bubbles:true}));})()`);
const shot=async name=>{
    const result=await page('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
    const file=path.join(work,'evidence',name+'.png');fs.writeFileSync(file,Buffer.from(result.data,'base64'));screenshots.push(file);
};
(async()=>{
    try{
        const target=await send('Target.createTarget',{url:'about:blank'});
        ({sessionId:session}=await send('Target.attachToTarget',{targetId:target.targetId,flatten:true}));
        for(const method of ['Page.enable','Runtime.enable','Network.enable','Log.enable'])await page(method);
        await page('Emulation.setDeviceMetricsOverride',{width:1440,height:1100,deviceScaleFactor:1,mobile:false});
        await page('Page.navigate',{url:pathToFileURL(preview).href});
        await wait("document.readyState==='complete' && document.querySelectorAll('.exp-card').length===21");
        assert.equal(await evaluate(visible+'.length'),21);mark('All 21 draft cards and UTF-8 content render');
        assert.equal(await evaluate("document.querySelector('#place-2 h3').textContent"),JSON.parse(fs.readFileSync(path.join(work,'review/review-data.json'),'utf8')).records.find(r=>r.id===2).title);
        assert.equal(await evaluate("[...document.querySelectorAll('.review-photo img')].every(i=>i.complete&&i.naturalWidth>0)"),true);
        assert.equal(await evaluate("document.querySelectorAll('.review-photo img').length"),15);mark('All fifteen optimized local JPEGs load');
        assert.equal(await evaluate("document.querySelectorAll('.review-credit a').length"),30);mark('Each prepared image has source and license links');
        const allowedCategories=['tham-quan','am-thuc','vui-choi','dia-phuong'];
        assert.equal(normalized.records.length,21);
        assert.equal(normalized.records.every(r=>allowedCategories.includes(r.experience_category)&&r.publish_status==='draft'&&r.review_status==='needs_owner_approval'),true);
        for(const old of baseline.images)assert.equal(crypto.createHash('sha256').update(fs.readFileSync(old.file)).digest('hex'),old.sha256);
        mark('All categories normalized, all records remain draft, original seven photo hashes preserved');
        assert.deepEqual(review.records.filter(r=>r.photo_state!=='prepared').map(r=>r.id),[15,18,22,23,24,27]);
        assert.equal(review.records.find(r=>r.id===20).credit.author,'Diane Selwyn');
        assert.equal(review.records.find(r=>r.id===23).notes.some(n=>n.includes('All rights reserved')),true);
        assert.equal(review.records.every(r=>r.content_status!=='supported'||r.sources.length>0),true);
        mark('Replacement photo and blocked/missing sources are correctly distinguished');
        for(const lid of ['1','2','7']){await set('#review-location',lid);assert.equal(await evaluate(visible+'.length'),7);}
        mark('Three destination filters each show seven matching drafts');
        await set('#review-location','');
        for(const [state,n] of [['prepared',15],['missing',6]]){await set('#review-photo-state',state);assert.equal(await evaluate(visible+'.length'),n);}
        mark('Prepared and missing photo filters show fifteen and six records');
        await set('#review-photo-state','');
        for(const [state,n] of [['ready',14],['needs-info',7]]){await set('#review-readiness',state);assert.equal(await evaluate(visible+'.length'),n);}
        assert.deepEqual(await evaluate(visible+'.map(c=>Number(c.dataset.id))'),[6,15,18,22,23,24,27]);
        mark('Editorial readiness filter lists all seven incomplete records');
        await set('#review-readiness','');await set('#review-query','quoc tu giam');
        assert.equal(await evaluate(visible+'.length'),1);assert.equal(await evaluate(visible+'[0].dataset.id'),'2');mark('Vietnamese search works without accent marks');
        await set('#review-query','no-review-match');assert.equal(await evaluate("!document.querySelector('#review-empty').hidden"),true);
        await evaluate("document.querySelector('#review-reset').click()");assert.equal(await evaluate(visible+'.length'),21);mark('Empty state and reset work');
        await evaluate("document.querySelector('#place-2 details').open=true");
        assert.equal(await evaluate("document.querySelector('#place-2 details').textContent.includes('di_tich')"),true);
        assert.equal(await evaluate("document.querySelectorAll('.review-detail dl').length"),21);mark('All draft details include current/proposed locality and category comparison');
        await evaluate("document.querySelector('#place-2 details').open=false;document.querySelector('[data-photo=\"2\"]').click()");
        await wait("document.querySelector('#review-lightbox').open && document.querySelector('#lightbox-image').naturalWidth>0");
        assert.equal(await evaluate("document.querySelector('#lightbox-credit').textContent.includes('Arjunullas')"),true);
        await shot('review-photo-full-frame');
        await page('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
        await page('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});
        await wait("!document.querySelector('#review-lightbox').open && document.activeElement.dataset.photo==='2'");mark('Full-frame photo dialog, credit, Escape and focus return work');
        for(const width of [1440,768,390,320]){
            await page('Emulation.setDeviceMetricsOverride',{width,height:1100,deviceScaleFactor:1,mobile:false});
            await evaluate('scrollTo(0,0)');await delay(80);
            assert.equal(await evaluate("document.documentElement.scrollWidth<=innerWidth"),true);
            const columns=await evaluate("getComputedStyle(document.querySelector('.exp-grid')).gridTemplateColumns.split(' ').length");
            assert.equal(columns,width>=1024?3:width>=640?2:1);
            await shot('review-'+width);mark('Responsive layout without horizontal overflow at '+width+'px');
        }
        assert.deepEqual(errors,[]);
        const unexpected=requests.filter(url=>{
            if(url.startsWith('data:')||url==='about:blank')return false;
            if(!url.startsWith('file:'))return true;
            const file=fileURLToPath(url);return !file.startsWith(work+path.sep);
        });
        assert.deepEqual(unexpected,[]);mark('No runtime/CSP errors or external/application requests');
        fs.writeFileSync(resultFile,JSON.stringify({status:'PASS',checks,requests,screenshots,database_connections:0,application_bootstrap:false},null,2)+'\n');
        process.stdout.write(JSON.stringify({status:'PASS',checks:checks.length,screenshots:screenshots.length,database_connections:0})+'\n');
    }catch(error){fs.writeFileSync(resultFile,JSON.stringify({status:'FAIL',error:error.message,checks,errors,requests,chromeLog:chromeLog.slice(-2000)},null,2)+'\n');throw error;}
    finally{try{await send('Browser.close');}catch{}browser.kill();for(const item of pending.values())clearTimeout(item.timer);}
})().catch(error=>{process.stderr.write(error.stack+'\n');process.exitCode=1;});
