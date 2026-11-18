// Anonymous, read-only public prices. No reservations, login, or CAPTCHA handling.
const fs=require('node:fs'),path=require('node:path'),{spawn}=require('node:child_process');
const jobs=JSON.parse(fs.readFileSync(process.argv[2],'utf8').replace(/^\uFEFF/,''));
const allowed=new Set(['www.google.com','eholiday.vn','www.expedia.com.vn','www.traveloka.com','mytour.vn']);
const work=path.resolve('storage/app/private/room-admin-update-20261102/sources');fs.mkdirSync(work,{recursive:true});
for(const job of jobs)if(!allowed.has(new URL(job.url).hostname)||!/^[-a-z0-9]+$/.test(job.key))throw Error('Unexpected public source');
const chrome=spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',['--headless=new','--no-first-run','--no-default-browser-check','--disable-background-networking','--disable-component-update','--disable-sync','--disable-extensions','--disable-gpu','--remote-debugging-pipe','--user-data-dir='+path.join(work,'anonymous-'+Date.now())],{windowsHide:true,stdio:['ignore','ignore','ignore','pipe','pipe']});
let seq=0,buffer='';const pending=new Map();
function send(method,params={},session){return new Promise((resolve,reject)=>{const id=++seq,timer=setTimeout(()=>{pending.delete(id);reject(Error(method+' timeout'));},15000);pending.set(id,{resolve,reject,timer});chrome.stdio[3].write(JSON.stringify({id,method,params,...(session?{sessionId:session}:{})})+'\0');});}
chrome.stdio[4].on('data',chunk=>{buffer+=chunk;let end;while((end=buffer.indexOf('\0'))>=0){const e=JSON.parse(buffer.slice(0,end));buffer=buffer.slice(end+1);if(e.id){const p=pending.get(e.id);if(p){clearTimeout(p.timer);pending.delete(e.id);e.error?p.reject(Error(e.error.message)):p.resolve(e.result);}}}});
const blocked=new Set();
let cursor=0;
async function worker(){while(cursor<jobs.length){const job=jobs[cursor++];
    const hostname=new URL(job.url).hostname;if(blocked.has(hostname)){console.log(JSON.stringify({key:job.key,skipped:'source challenge'}));continue;}
    let targetId;
    try{
        targetId=(await send('Target.createTarget',{url:'about:blank'})).targetId;const session=(await send('Target.attachToTarget',{targetId,flatten:true})).sessionId;
        await send('Page.enable',{},session);await send('Runtime.enable',{},session);try{await send('Page.navigate',{url:job.url},session);}catch{}
        await new Promise(resolve=>setTimeout(resolve,22000));
        if(job.expand_room_rates){
            await send('Runtime.evaluate',{expression:'(()=>{const button=[...document.querySelectorAll("button,[role=button]")].find(e=>e.innerText.trim()==="Get price");if(button&&!/captcha|human|phần mềm tự động/i.test(document.title))button.click();})()'},session);
            await new Promise(resolve=>setTimeout(resolve,4000));
        }
        const result=(await send('Runtime.evaluate',{expression:'JSON.stringify({title:document.title,url:location.href,text:document.body?.innerText||"",inputs:[...document.querySelectorAll("input")].filter(i=>/date|check.?in|check.?out|nhận phòng|trả phòng/i.test(i.name+" "+i.id+" "+i.type+" "+i.getAttribute("aria-label"))).map(i=>({name:i.name,value:i.value,label:i.getAttribute("aria-label")})),date_labels:[...document.querySelectorAll("[aria-label]")].map(i=>i.getAttribute("aria-label")).filter(x=>/2026|nhận phòng|trả phòng/i.test(x)).slice(0,30)})',returnByValue:true},session)).result.value;
        const snapshot={...job,...JSON.parse(result),checked_on:job.checked_on||'2026-11-04'};
        snapshot.all_text=(await send('Runtime.evaluate',{expression:'(()=>{const body=document.body.cloneNode(true);body.querySelectorAll("script,style,svg,iframe,noscript").forEach(e=>e.remove());return body.textContent;})()',returnByValue:true},session)).result.value;
        snapshot.challenge=/captcha|verify you are human|xác nhận bạn là|unusual traffic|robot or human|phần mềm tự động|hãy thể hiện mình là con người/i.test(snapshot.title+' '+snapshot.text);if(snapshot.challenge)blocked.add(hostname);
        fs.writeFileSync(path.join(work,job.key+'.json'),JSON.stringify(snapshot,null,2));fs.writeFileSync(path.join(work,job.key+'-text.txt'),snapshot.text);fs.writeFileSync(path.join(work,job.key+'-all-text.txt'),snapshot.all_text);
        console.log(JSON.stringify({key:job.key,title:snapshot.title,characters:snapshot.text.length,challenge:snapshot.challenge}));
    }catch(error){console.log(JSON.stringify({key:job.key,error:error.message}));}finally{if(targetId)try{await send('Target.closeTarget',{targetId});}catch{}}
}}
(async()=>{try{await Promise.all([worker(),worker()]);}finally{try{await send('Browser.close');}catch{chrome.kill();}}})();
