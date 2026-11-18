// Actual HTTP -> Laravel -> owned MySQL -> SSE. Groq/Geoapify responses are mocked by the PHP fixture.
const fs=require('fs'),path=require('path'),http=require('http'),net=require('net'),assert=require('assert/strict'),{spawn}=require('child_process');
const root=process.cwd(),settingsPath=path.resolve('storage/app/private/roomtype-v2-tests/1a749d8138b1/itinerary-test-environment.json');
const settings=JSON.parse(fs.readFileSync(settingsPath,'utf8').replace(/^\uFEFF/,''));
assert.equal(settings.host,'127.0.0.1');assert.notEqual(Number(settings.port),3306);assert.equal(settings.initialized_here,true);
const pause=ms=>new Promise(r=>setTimeout(r,ms));let php,port;
async function request(message,id){return new Promise((resolve,reject)=>{
    const start=Date.now(),chunks=[];let firstAt=null;
    const r=http.request({hostname:'127.0.0.1',port,path:'/api/chat/fixture/message',method:'POST',headers:{Accept:'text/event-stream','Content-Type':'application/json','X-Test-Owner':settings.owner_token,'X-Aurel-Request-Id':id,'Idempotency-Key':id}},res=>{
        res.on('data',chunk=>{firstAt??=Date.now()-start;chunks.push(chunk);});res.on('error',reject);
        res.on('end',()=>{try{
            assert.equal(res.statusCode,200);assert.match(res.headers['content-type'],/text\/event-stream/);assert.equal(res.headers['x-test-providers'],'fake');
            const text=Buffer.concat(chunks).toString('utf8'),events=text.trim().split('\n\n').map(frame=>{const lines=frame.split('\n');return {event:lines[0].slice(7),...JSON.parse(lines[1].slice(6))};});
            for(const event of events)assert.equal(event.request_id,id);assert.equal(events.at(-1).event,'result');
            const elapsed=Date.now()-start;assert.ok(firstAt<elapsed-80,'Progress must arrive over HTTP before work completes');
            resolve({id,first_ms:firstAt,total_ms:elapsed,codes:events.filter(e=>e.event==='progress').map(e=>e.code),result:events.at(-1).result});
        }catch(e){reject(e);}});
    });r.setTimeout(20000,()=>{r.destroy();reject(Error('HTTP integration timeout'));});r.on('error',reject);r.end(JSON.stringify({message}));
});}
(async()=>{
    const listener=net.createServer();await new Promise(r=>listener.listen(0,'127.0.0.1',r));port=listener.address().port;await new Promise(r=>listener.close(r));
    php=spawn('php',['-d','output_buffering=0','-d','zlib.output_compression=0','-S','127.0.0.1:'+port,'tests/Support/itinerary_progress_router.php'],{cwd:root,env:{...process.env,AUREL_ITINERARY_TEST_SETTINGS:settingsPath},windowsHide:true,stdio:['ignore','ignore','ignore']});
    for(let i=0;i<40;i++){const ready=await new Promise(r=>{const s=net.connect(port,'127.0.0.1');s.once('connect',()=>{s.destroy();r(true);});s.once('error',()=>r(false));});if(ready)break;await pause(100);}
    const cases=[];
    let r=await request('Giải bài toán giúp tôi','http-simple-request');assert.deepEqual(r.codes,['processing_request','reading_context','considering']);cases.push(r);
    r=await request('Lên lịch Sài Gòn 2 ngày, 2 người, 5 triệu','http-plan-request');for(const code of ['searching_places','creating_itinerary','calculating_route','checking_itinerary'])assert.ok(r.codes.includes(code));cases.push(r);
    r=await request('Lên lịch Sài Gòn','http-missing-days');assert.deepEqual(r.result.assistant.quick_replies,['Đi 1 ngày','Đi 2 ngày','Đi 3 ngày']);assert.ok(!r.codes.includes('creating_itinerary'));cases.push(r);
    const proof=JSON.parse(fs.readFileSync(path.join(root,'storage/app/private/itinerary-review-20261027/progress-http-db-proof.json'),'utf8'));assert.equal(proof.rollback_verified,true);assert.equal(proof.main_database_writes,0);
    const result={status:'PASS',http_cases:cases.length,providers:'mocked',mysql:'owned isolated',main_database_writes:0,cases:cases.map(({result,...rest})=>rest),rollback_verified:true};
    fs.writeFileSync(path.join(root,'storage/app/private/itinerary-review-20261027/progress-http-result.json'),JSON.stringify(result,null,2));console.log(JSON.stringify(result));
})().catch(error=>{console.error(error.message);process.exitCode=1;}).finally(()=>php?.kill());
