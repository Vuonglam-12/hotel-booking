const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const file=path.resolve(process.argv[2]);const cfg=JSON.parse(fs.readFileSync(file,'utf8').replace(/^\uFEFF/,''));
const root=path.resolve(__dirname,'../../storage/app/private/roomtype-v2-tests');
assert.ok(file.startsWith(root+path.sep));assert.equal(file,path.join(path.resolve(cfg.runtime),'browser-staging.json'));
assert.equal(path.resolve(cfg.profile),path.join(path.resolve(cfg.runtime),'browser-profile'));
const active=fs.readFileSync(path.join(cfg.profile,'DevToolsActivePort'),'utf8').trim().split(/\r?\n/);
const ws=new WebSocket('ws://127.0.0.1:'+active[0]+active[1]);
ws.onopen=()=>ws.send(JSON.stringify({id:1,method:'Browser.close'}));ws.onclose=()=>console.log('Owned headless browser closed.');
ws.onerror=()=>{process.exitCode=1;};setTimeout(()=>{ws.close();},5000).unref();
