// Anonymous read-only public hotel price research. Never clicks reserve, logs in or submits a booking.
const fs=require('node:fs'),path=require('node:path'),{spawn}=require('node:child_process');
const url=new URL(process.argv[2]),key=process.argv[3]||'quote';
if(!['my.trip.com','www.trip.com','www.booking.com','www.agoda.com','www.expedia.com','www.expedia.com.vn','hotels.cloudbeds.com','book-directonline.com'].includes(url.hostname)||!/^[a-z0-9-]+$/.test(key))throw new Error('Unexpected public source');
const work=path.resolve('storage/app/private/room-catalog-update-20261031/browser');fs.mkdirSync(work,{recursive:true});
const chrome=spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',['--headless=new','--no-first-run','--no-default-browser-check','--disable-background-networking','--disable-component-update','--disable-sync','--disable-extensions','--disable-gpu','--remote-debugging-pipe','--user-data-dir='+path.join(work,'profile-'+key+'-'+Date.now())],{windowsHide:true,stdio:['ignore','ignore','pipe','pipe','pipe']});
let session,seq=0,buffer='';const pending=new Map(),jobs=[],responses=[];
function send(method,params={},target=session){return new Promise((resolve,reject)=>{const id=++seq,timer=setTimeout(()=>{pending.delete(id);reject(new Error('Browser command timeout: '+method));},15000);pending.set(id,{resolve,reject,timer});chrome.stdio[3].write(JSON.stringify({id,method,params,...(target?{sessionId:target}:{})})+'\0');});}
chrome.stdio[4].on('data',chunk=>{buffer+=chunk;let end;while((end=buffer.indexOf('\0'))>=0){const event=JSON.parse(buffer.slice(0,end));buffer=buffer.slice(end+1);if(event.id){const p=pending.get(event.id);if(p){clearTimeout(p.timer);pending.delete(event.id);event.error?p.reject(new Error(event.error.message)):p.resolve(event.result);}}
 else if(event.method==='Network.responseReceived' && /roomlist|hotelroom|room-list|searchrooms|gethotelrates|availability|rateplan|rates|offers|\/rooms|\/rooms\/|\/properties|\/property/i.test(event.params.response.url) && /json|text\//i.test(event.params.response.mimeType)){
  const {requestId,response}=event.params;jobs.push((async()=>{try{await new Promise(r=>setTimeout(r,300));const body=await send('Network.getResponseBody',{requestId});const filename=key+'-response-'+responses.length+'.txt';fs.writeFileSync(path.join(work,filename),body.base64Encoded?Buffer.from(body.body,'base64'):body.body);responses.push({url:response.url,status:response.status,file:filename});}catch{}})());
 }}});
async function evaluate(expression){const r=await send('Runtime.evaluate',{expression,returnByValue:true});return r.result.value;}
(async()=>{try{
 const {targetId}=await send('Target.createTarget',{url:'about:blank'},null);session=(await send('Target.attachToTarget',{targetId,flatten:true},null)).sessionId;
 await send('Page.enable');await send('Runtime.enable');await send('Network.enable');try{await send('Page.navigate',{url:url.href});}catch{}
 await new Promise(r=>setTimeout(r,25000));
 const text=await evaluate('document.body?.innerText||""'),title=await evaluate('document.title');fs.writeFileSync(path.join(work,key+'-text.txt'),text||'');
 await Promise.allSettled(jobs);fs.writeFileSync(path.join(work,key+'-responses.json'),JSON.stringify(responses,null,2));
 console.log(JSON.stringify({key,title,text_characters:text?.length,rate_responses:responses.length,source_url:url.href}));
 }catch(e){console.log(JSON.stringify({key,error:e.message}));process.exitCode=1;}finally{try{await send('Browser.close',{},null);}catch{chrome.kill();}}})();
