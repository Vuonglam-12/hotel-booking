/* Real Chrome/CDP + HTTP + guarded MySQL fixture. No live API or gateway is permitted. */
const fs=require('node:fs'); const path=require('node:path'); const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const cfg=JSON.parse(fs.readFileSync(process.argv[2],'utf8').replace(/^\uFEFF/,''));
assert.match(cfg.origin,/^http:\/\/127\.0\.0\.1:\d+$/); assert.match(cfg.runtime,/roomtype-v2-tests[\\/][a-f0-9]{12}$/);
const workspace=path.resolve(__dirname,'../..');
assert.ok(path.resolve(cfg.runtime).startsWith(path.join(workspace,'storage/app/private/roomtype-v2-tests')+path.sep));
assert.equal(path.resolve(process.argv[2]),path.join(path.resolve(cfg.runtime),'browser-staging.json'));
assert.equal(path.resolve(cfg.stage_descriptor),path.join(path.resolve(cfg.runtime),'staging-session.json'));
assert.equal(path.dirname(path.resolve(cfg.profile)),path.resolve(cfg.runtime));
assert.match(path.basename(path.resolve(cfg.profile)),/^browser-profile(?:-[a-z0-9-]+)?$/);
const port=Number(new URL(cfg.origin).port);assert.ok(port>=49152&&port<=65535);
const sockets=execFileSync('netstat',['-ano','-p','tcp'],{encoding:'utf8'});
assert.ok(sockets.split(/\r?\n/).some(line=>{const fields=line.trim().split(/\s+/);return fields[1]==='127.0.0.1:'+port&&fields.at(-1)===String(cfg.http_pid);}), 'HTTP port must belong to the server launched for this staging descriptor');
const stage=JSON.parse(fs.readFileSync(cfg.stage_descriptor,'utf8')); assert.equal(stage.synthetic_only,true);
assert.match(stage.database,/^aurel_roomtype_v2_test_[a-f0-9]{12}$/);
const active=fs.readFileSync(path.join(cfg.profile,'DevToolsActivePort'),'utf8').trim().split(/\r?\n/);
let ws,seq=0,session; const pending=new Map(); const checks=[]; const errors=[]; const blocked=new Set();
function send(method,params={},sid=session) { return new Promise((resolve,reject)=>{const id=++seq;const timer=setTimeout(()=>{pending.delete(id);reject(new Error('CDP timeout: '+method));},20000);pending.set(id,{resolve,reject,timer});ws.send(JSON.stringify({id,method,params,...(sid?{sessionId:sid}:{})}));}); }
async function evalJS(expression) { const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw new Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result.value; }
async function wait(expression) { const end=Date.now()+18000;while(Date.now()<end){if(await evalJS(expression))return;await new Promise(r=>setTimeout(r,150));}throw new Error('UI timeout: '+expression); }
async function go(url) { await send('Page.navigate',{url:cfg.origin+url});await wait("document.readyState==='complete'"); }
async function check(name,expression) { assert.equal(await evalJS(expression),true,name);checks.push({name,passed:true}); }
async function api(url,method='GET',body,admin=false) { const result=await evalJS(`(async()=>{const r=await fetch(${JSON.stringify(url)},{method:${JSON.stringify(method)},headers:{Accept:'application/json','Content-Type':'application/json',Authorization:'Bearer '+localStorage.getItem(${JSON.stringify(admin?'admin_token':'token')})},${body===undefined?'':'body:JSON.stringify('+JSON.stringify(body)+'),'} });return {status:r.status,data:await r.json()};})()`);return result; }
(async()=>{
    ws=new WebSocket('ws://127.0.0.1:'+active[0]+active[1]);
    await new Promise((resolve,reject)=>{ws.onopen=resolve;ws.onerror=reject;});
    ws.onmessage=e=>{ const m=JSON.parse(e.data); if(m.id){const p=pending.get(m.id);if(!p)return;clearTimeout(p.timer);pending.delete(m.id);m.error?p.reject(new Error(m.error.message)):p.resolve(m.result);return;}
        if(m.method==='Runtime.exceptionThrown') errors.push(m.params.exceptionDetails.exception?.description||m.params.exceptionDetails.text);
        if(m.method==='Fetch.requestPaused'){const url=m.params.request.url;const allowed=url.startsWith(cfg.origin+'/')||url==='about:blank'||url.startsWith('data:');if(!allowed)blocked.add(new URL(url).origin);send(allowed?'Fetch.continueRequest':'Fetch.failRequest',allowed?{requestId:m.params.requestId}:{requestId:m.params.requestId,errorReason:'BlockedByClient'},m.sessionId).catch(()=>{});}
    };
    const target=await send('Target.createTarget',{url:'about:blank'},null);session=(await send('Target.attachToTarget',{targetId:target.targetId,flatten:true},null)).sessionId;
    await send('Page.enable');await send('Runtime.enable');await send('Fetch.enable',{patterns:[{urlPattern:'*'}]});
    await send('Emulation.setDeviceMetricsOverride',{width:1440,height:1000,deviceScaleFactor:1,mobile:false});
    await go('/');await wait("document.querySelector('.hotel-card')!==null");
    await check('Home renders hotels from staging catalog',"document.querySelector('.hotel-card')!==null");
    const login=await evalJS(`(async()=>{const r=await fetch('/api/login',{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json'},body:JSON.stringify({email:${JSON.stringify(stage.customer_email)},password:${JSON.stringify(stage.password)}})});const d=await r.json();if(r.ok){localStorage.setItem('token',d.token);localStorage.setItem('user',JSON.stringify(d.user));}return r.status;})()`);assert.equal(login,200);checks.push({name:'Customer login via actual staging API',passed:true});
    const unauth=await evalJS("fetch('/api/admin/bookings',{headers:{Accept:'application/json'}}).then(r=>r.status)");assert.equal(unauth,401);checks.push({name:'Admin API rejects unauthenticated browser',passed:true});
    const resumeBooking=Number(process.env.AUREL_SEPAY_EXTERNAL_RESUME_BOOKING||0);
    if(resumeBooking>0) {
        await go('/payments/bookings/'+resumeBooking);
        await wait("document.getElementById('booking-summary')?.textContent.trim().length>0");
        await wait("!document.getElementById('banking-ack').disabled&&document.querySelector('#banking-instructions img')!==null");
        const paymentState=await api('/api/payments/'+resumeBooking);
        assert.equal(paymentState.status,200);
        assert.equal(paymentState.data.booking.status,'pending');
        assert.equal(paymentState.data.payment.payment_status,'pending');
        assert.equal(paymentState.data.booking.summary.payment_method,'banking');
        assert.ok(paymentState.data.banking_instructions?.qr_image_url);
        const result={checks:[...checks,{name:'Verified QR rendered for pending Banking payment',passed:true}],errors,
            blocked_external_origins:[...blocked],booking_id:resumeBooking,payment_id:paymentState.data.payment.id,
            amount:paymentState.data.payment.amount,transfer_reference:paymentState.data.payment.transaction_id,
            database:stage.database,synthetic_only:true,real_gateway_e2e:false,scope:'sepay-external-callback-prepare'};
        fs.writeFileSync(path.join(cfg.runtime,'sepay-external-prepare.json'),JSON.stringify(result,null,2));
        assert.deepEqual(errors,[],'Uncaught browser exceptions');
        console.log(JSON.stringify({passed:result.checks.length,booking_id:resumeBooking,payment_id:result.payment_id,
            amount:result.amount,transfer_reference:result.transfer_reference,synthetic_only:true}));
        await send('Target.closeTarget',{targetId:target.targetId},null);ws.close();return;
    }
    await go('/hotels/1');await wait("typeof HolidayVietDetailRooms!=='undefined'");
    await check('Checkout remains disabled without selection',"document.getElementById('submit-booking-btn').disabled");
    await evalJS("document.getElementById('hv-stay-checkin-trigger').click()");await check('Date picker opens',"!document.getElementById('hv-stay-date-dialog').hidden");
    const checkInLabel=stage.check_in.split('-').reverse().join('/');const checkOutLabel=stage.check_out.split('-').reverse().join('/');
    await evalJS(`Array.from(document.querySelectorAll('.hv-stay-day')).find(b=>b.getAttribute('aria-label')===${JSON.stringify(checkInLabel)}&&!b.disabled).click()`);
    await evalJS(`Array.from(document.querySelectorAll('.hv-stay-day')).find(b=>b.getAttribute('aria-label')===${JSON.stringify(checkOutLabel)}&&!b.disabled).click();document.getElementById('hv-stay-done').click()`);
    await check('Date range applies checkout-exclusive dates',`document.getElementById('check-in').value===${JSON.stringify(stage.check_in)}&&document.getElementById('check-out').value===${JSON.stringify(stage.check_out)}`);
    await evalJS("document.getElementById('hv-guest-trigger').click();document.querySelector('[data-kind=adults] [data-step=\"1\"]').click();document.getElementById('hv-guest-done').click()");
    await check('Guest picker preserves total guests',"Number(document.getElementById('guests').value)===3");
    await evalJS("const q=document.getElementById('room-quantity');q.value=2;q.dispatchEvent(new Event('input',{bubbles:true}));document.querySelector('[data-room-type-id] [data-open-room]').click()");
    await wait("!document.getElementById('room-modal-select').disabled");await evalJS("document.getElementById('room-modal-select').click()");
    await wait("!document.getElementById('submit-booking-btn').disabled");
    await evalJS("document.getElementById('submit-booking-btn').click()");
    await check('Summary opens for one type and quantity 2',"document.getElementById('v2-checkout').open&&document.getElementById('v2-checkout-summary').textContent.includes('2 phòng')");
    await evalJS("document.getElementById('v2-checkout-confirm').click()");await wait("location.pathname.startsWith('/payments/bookings/')||Boolean(document.getElementById('v2-checkout-error')?.textContent.trim())");
    const checkoutState=await evalJS("({path:location.pathname,feedback:document.getElementById('v2-checkout-error')?.textContent.trim()||'',summary:document.getElementById('booking-summary')?.textContent||''})");
    assert.ok(checkoutState.path.startsWith('/payments/bookings/')&&checkoutState.summary.includes('2 phòng'),'Checkout did not reach payment page: '+JSON.stringify(checkoutState));
    const bookingId=await evalJS("Number(location.pathname.split('/').at(-1))");
    if(process.env.AUREL_SEPAY_EXTERNAL_PREPARE==='1') {
        await wait("!document.getElementById('banking-ack').disabled&&document.querySelector('#banking-instructions img')!==null");
        const paymentState=await api('/api/payments/'+bookingId);
        assert.equal(paymentState.status,200);
        assert.equal(paymentState.data.booking.status,'pending');
        assert.equal(paymentState.data.payment.payment_status,'pending');
        assert.equal(paymentState.data.booking.summary.payment_method,'banking');
        assert.ok(paymentState.data.banking_instructions?.qr_image_url);
        const result={checks:[...checks,{name:'Verified QR rendered for pending Banking payment',passed:true}],errors,
            blocked_external_origins:[...blocked],booking_id:bookingId,payment_id:paymentState.data.payment.id,
            amount:paymentState.data.payment.amount,transfer_reference:paymentState.data.payment.transaction_id,
            database:stage.database,synthetic_only:true,real_gateway_e2e:false,scope:'sepay-external-callback-prepare'};
        fs.writeFileSync(path.join(cfg.runtime,'sepay-external-prepare.json'),JSON.stringify(result,null,2));
        assert.deepEqual(errors,[],'Uncaught browser exceptions');
        console.log(JSON.stringify({passed:result.checks.length,booking_id:bookingId,payment_id:result.payment_id,
            amount:result.amount,transfer_reference:result.transfer_reference,synthetic_only:true}));
        await send('Target.closeTarget',{targetId:target.targetId},null);ws.close();return;
    }
    await check('QR payment stays pending with unconfigured bank',"document.getElementById('banking-ack').disabled&&document.getElementById('banking-instructions').textContent.includes('Chưa có')");
    const booking=(await api('/api/bookings/'+bookingId)).data.booking;assert.equal(booking.status,'pending');assert.equal(booking.total_price,'2000000.00');assert.equal(booking.booking_rooms.length,1);assert.equal(booking.booking_rooms[0].room_id,null);checks.push({name:'Actual MySQL Booking snapshot and no physical room',passed:true});
    await evalJS("localStorage.removeItem('admin_token');localStorage.removeItem('admin_account')");
    await go('/admin/login');await evalJS(`document.getElementById('email').value=${JSON.stringify(stage.admin_email)};document.getElementById('password').value=${JSON.stringify(stage.password)};document.getElementById('btn-login').click()`);await wait("location.pathname==='/admin'&&document.getElementById('admin-shell')?.hidden===false");
    checks.push({name:'Admin login form and Dashboard auth guard',passed:true});
    await go('/admin/bookings');await wait("document.querySelector('#bookingTableBody tr[onclick]')!==null");
    await check('No direct pending→confirmed button',"Array.from(document.querySelectorAll('button')).every(b=>!b.getAttribute('onclick')?.includes(\"'confirmed'\"))");
    await evalJS("document.querySelector('#bookingTableBody tr[onclick]').click()");
    const pay=booking.payment.id;
    await wait(`document.getElementById('bank-submit-${pay}')!==null`);
    await evalJS(`document.getElementById('bank-reference-${pay}').value='BROWSER_ACCEPTANCE_${pay}';document.getElementById('bank-time-${pay}').value=new Date(Date.now()-new Date().getTimezoneOffset()*60000).toISOString().slice(0,19);document.getElementById('bank-attest-${pay}').checked=true;document.getElementById('bank-submit-${pay}').click()`);
    await wait("document.querySelector('.badge-confirmed')!==null");checks.push({name:'Admin banking verification via rendered statement form',passed:true});
    const verified=(await api('/api/bookings/'+bookingId)).data.booking;assert.equal(verified.status,'confirmed');assert.equal(verified.payment.payment_status,'success');checks.push({name:'Confirmed state returned to Customer',passed:true});
    const tick=JSON.parse(execFileSync('php',['tests/Support/roomtype_v2_staging_control.php',cfg.stage_descriptor,'scheduler-tick'],{cwd:path.resolve(__dirname,'../..'),encoding:'utf8'}));assert.equal(tick.exit_code,0);checks.push({name:'Guarded expiry/delivery scheduler tick',passed:true});
    const pdf=await evalJS(`(async()=>{const r=await fetch('/api/invoices/${bookingId}/pdf',{headers:{Authorization:'Bearer '+localStorage.getItem('token')}});const b=new Uint8Array(await r.arrayBuffer());return {status:r.status,prefix:String.fromCharCode(...b.slice(0,5))};})()`);assert.equal(pdf.status,200);assert.equal(pdf.prefix,'%PDF-');checks.push({name:'Customer invoice PDF from actual staging endpoint',passed:true});
    const invoiceAudit=await api('/api/admin/bookings/'+bookingId+'/payment-audit','GET',undefined,true);assert.equal(invoiceAudit.data.effects[0].state,'delivered');checks.push({name:'Email array acceptance reflected after commit',passed:true});
    if(process.env.AUREL_PAYMENT_E2E_ONLY==='1') {
        const result={checks,errors,blocked_external_origins:[...blocked],booking_id:bookingId,database:stage.database,synthetic_only:true,real_gateway_e2e:false,real_mailbox_e2e:false,scope:'booking-checkout-payment'};
        fs.writeFileSync(path.join(cfg.runtime,'payment-e2e-results.json'),JSON.stringify(result,null,2));assert.deepEqual(errors,[],'Uncaught browser exceptions');console.log(JSON.stringify({passed:checks.length,uncaught_errors:errors.length,synthetic_only:true,scope:result.scope}));
        await send('Target.closeTarget',{targetId:target.targetId},null);ws.close();return;
    }
    const cancelled=await api('/api/bookings/'+bookingId+'/cancel','POST',{},false);assert.equal(cancelled.data.booking.status,'cancelled');checks.push({name:'Customer cancellation via staging API',passed:true});
    const expiring=await api('/api/bookings','POST',{hotel_id:1,room_type_id:booking.booking_rooms[0].room_type_id,quantity:1,num_guests:2,check_in:'2026-10-18',check_out:'2026-10-19',payment_method:'banking'});assert.equal(expiring.status,201);
    const expire=JSON.parse(execFileSync('php',['tests/Support/roomtype_v2_staging_control.php',cfg.stage_descriptor,'expire',String(expiring.data.booking.id)],{cwd:path.resolve(__dirname,'../..'),encoding:'utf8'}));assert.equal(expire.exit_code,0);
    await go('/payments/bookings/'+expiring.data.booking.id);await wait("document.getElementById('payment-feedback')?.textContent.includes('hủy hoặc hết hạn')");checks.push({name:'Expired payment state rendered without resurrection',passed:true});
    await go('/bookings');await wait("document.body.textContent.includes('2 phòng')");checks.push({name:'Customer History quantity rendered',passed:true});
    await go('/admin/reconciliation');await wait("document.getElementById('reconciliation-feedback')?.textContent.includes('100')");checks.push({name:'Admin reconciliation queue rendered',passed:true});
    await go('/admin/hotels/1/room-types');await wait("document.querySelector('[data-type]')!==null");
    await evalJS("document.getElementById('type-name').value='Browser acceptance type';document.getElementById('type-price').value='777777.13';document.getElementById('type-capacity').value='3';document.getElementById('type-total').value='2';document.getElementById('type-form').requestSubmit()");
    await wait("document.getElementById('type-feedback').textContent.includes('Đã lưu')");
    const typeList=await api('/api/admin/hotels/1/room-types','GET',undefined,true);const createdType=typeList.data.room_types.find(t=>t.name==='Browser acceptance type');assert.equal(createdType.base_price,'777777.13');assert.equal(createdType.total_rooms,2);checks.push({name:'Admin owned Type creation uses exact price and total',passed:true});
    await evalJS("document.getElementById('type-image').value='image/img_room/room (1).jpg';document.getElementById('type-image-primary').checked=true;document.getElementById('type-gallery-form').requestSubmit()");
    await wait("document.querySelector('#type-gallery img')!==null");checks.push({name:'Admin Type gallery links an existing local asset',passed:true});
    await evalJS("document.getElementById('type-price').value='888888.17';document.getElementById('type-form').requestSubmit()");await wait("document.getElementById('type-list').textContent.includes('888888.17')");checks.push({name:'Admin Type edit preserves exact decimal text',passed:true});
    await evalJS("document.getElementById('type-disable').click()");await wait("document.getElementById('type-feedback').textContent.includes('ngừng bán')");
    const publicTypes=await api('/api/hotels/1/room-types');assert.equal(publicTypes.data.room_types.some(t=>t.id===createdType.id),false);checks.push({name:'Inactive Type no longer offered to Customer',passed:true});
    await go('/deals');await check('Deals has no fabricated discount/reference price/voucher',"!document.querySelector('.discount-badge,.price-original,.voucher-card')&&document.body.textContent.includes('Chưa có chương trình')");
    await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});await go('/hotels/1');
    await check('Mobile detail avoids horizontal overflow',"document.documentElement.scrollWidth<=window.innerWidth+2");
    const shot=await send('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});fs.writeFileSync(path.join(cfg.runtime,'phase2c-mobile-detail.png'),Buffer.from(shot.data,'base64'));
    const logoutAdmin=await api('/api/admin/logout','POST',{},true);assert.equal(logoutAdmin.status,200);
    const adminAfter=await api('/api/admin/me','GET',undefined,true);assert.equal(adminAfter.status,401);
    const customerAfter=await api('/api/bookings/my');assert.equal(customerAfter.status,200);checks.push({name:'Admin logout preserves Customer session',passed:true});
    const logoutCustomer=await api('/api/logout','POST',{});assert.equal(logoutCustomer.status,200);assert.equal((await api('/api/bookings/my')).status,401);checks.push({name:'Customer logout revokes only its own token',passed:true});
    const result={checks,errors,blocked_external_origins:[...blocked],booking_id:bookingId,database:stage.database,synthetic_only:true,real_gateway_e2e:false,real_mailbox_e2e:false};
    fs.writeFileSync(path.join(cfg.runtime,'phase2c-browser-results.json'),JSON.stringify(result,null,2));assert.deepEqual(errors,[],'Uncaught browser exceptions');console.log(JSON.stringify({passed:checks.length,uncaught_errors:errors.length,synthetic_only:true}));
    await send('Target.closeTarget',{targetId:target.targetId},null);ws.close();
})().catch(e=>{fs.writeFileSync(path.join(cfg.runtime,'phase2c-browser-failure.json'),JSON.stringify({checks,errors,error:e.message},null,2));console.error(e.message);if(ws)ws.close();process.exitCode=1;});
