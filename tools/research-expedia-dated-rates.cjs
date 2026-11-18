// Read-only anonymous hotel search. Fixed Expedia host; no booking or account actions.
const fs=require('node:fs'),path=require('node:path'),{spawn}=require('node:child_process');
const input=JSON.parse(fs.readFileSync(process.argv[2],'utf8').replace(/^\uFEFF/,''));
const work=path.resolve('storage/app/private/room-catalog-update-20261031/expedia');fs.mkdirSync(work,{recursive:true});
for(const j of input){const u=new URL(j.url);if(u.hostname!=='www.expedia.com.vn'||!/^\d+$/.test(String(j.hotel_id))||!/^\d{4}-\d{2}-\d{2}$/.test(j.check_in)||!/^\d{4}-\d{2}-\d{2}$/.test(j.check_out))throw new Error('Invalid public price search');}
const chrome=spawn('C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',['--headless=new','--no-first-run','--no-default-browser-check','--disable-background-networking','--disable-component-update','--disable-sync','--disable-extensions','--disable-gpu','--remote-debugging-pipe','--user-data-dir='+path.join(work,'anonymous-profile-'+Date.now())],{windowsHide:true,stdio:['ignore','ignore','pipe','pipe','pipe']});
let seq=0,buffer='';const pending=new Map();
function send(method,params={},session){return new Promise((resolve,reject)=>{const id=++seq,timer=setTimeout(()=>{pending.delete(id);reject(new Error(method+' timeout'));},20000);pending.set(id,{resolve,reject,timer});chrome.stdio[3].write(JSON.stringify({id,method,params,...(session?{sessionId:session}:{})})+'\0');});}
chrome.stdio[4].on('data',chunk=>{buffer+=chunk;let end;while((end=buffer.indexOf('\0'))>=0){const v=JSON.parse(buffer.slice(0,end));buffer=buffer.slice(end+1);if(v.id){const p=pending.get(v.id);if(p){clearTimeout(p.timer);pending.delete(v.id);v.error?p.reject(new Error(v.error.message)):p.resolve(v.result);}}}});
async function evaluate(s,expression){const r=await send('Runtime.evaluate',{expression,returnByValue:true},s);return r.result.value;}
async function research(j){
 const key=j.hotel_id+'-'+j.check_in,file=path.join(work,key+'.json');if(fs.existsSync(file)){const old=JSON.parse(fs.readFileSync(file,'utf8'));if(old.url===j.url && old.offers?.length)return {key,cached:true,offers:old.offers.length};}
 let targetId,session;
 try{
  targetId=(await send('Target.createTarget',{url:'about:blank'})).targetId;session=(await send('Target.attachToTarget',{targetId,flatten:true})).sessionId;
  await send('Page.enable',{},session);await send('Runtime.enable',{},session);try{await send('Page.navigate',{url:j.url},session);}catch{}
  const deadline=Date.now()+30000;let text='';
  while(Date.now()<deadline){await new Promise(r=>setTimeout(r,1500));text=await evaluate(session,'document.body?.innerText||""');if(text.includes('Giá hiện tại là')||text.includes('Access Denied')||text.includes('Xác nhận bạn'))break;}
  const state=await evaluate(session,`(()=>({title:document.title,url:location.href,headings:[...document.querySelectorAll('h1,h2')].slice(0,12).map(x=>x.innerText),stid:[...new Set([...document.querySelectorAll('[data-stid]')].map(x=>x.getAttribute('data-stid')))].filter(x=>/offer|unit|price|rate|room/.test(x))}))()`);
  const sections=text.split(/Xem tất cả ảnh /).slice(1);let offers=[];
  for(const section of sections){if(!section.includes('Giá hiện tại là'))continue;const room=section.split('\n')[0].trim(),price=section.match(/Giá hiện tại là\s+([\d.,\s]+)\s*₫/),total=section.match(/Tổng\s+([\d.,\s]+)\s*₫/);if(price&&total)offers.push({room,nightly_display_vnd:Number(price[1].replace(/\D/g,'')),total_including_taxes_vnd:Number(total[1].replace(/\D/g,'')),taxes_included:section.includes('bao gồm thuế'),capacity:Number(section.match(/(?:^|\n)(\d+) khách/)?.[1]||0),text:section.slice(0,4500)});}
  const result={...j,...state,checked_on:'2026-11-01',guests:{adults:2,children:0,rooms:1},offers,header:text.slice(0,1800)};
  fs.writeFileSync(path.join(work,key+'-text.txt'),text);fs.writeFileSync(file,JSON.stringify(result,null,2));return {key,title:state.title,offers:offers.length};
 }catch(e){return {key,error:e.message};}finally{if(targetId)try{await send('Target.closeTarget',{targetId});}catch{}}
}
(async()=>{let cursor=0;async function worker(){while(cursor<input.length){const job=input[cursor++];console.log(JSON.stringify(await research(job)));}}try{await Promise.all(Array.from({length:3},worker));}finally{try{await send('Browser.close');}catch{chrome.kill();}}})();
