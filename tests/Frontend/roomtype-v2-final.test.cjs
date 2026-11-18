const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const root=path.resolve(__dirname,'../..');
const admin=fs.readFileSync(path.join(root,'resources/views/prepared-v2/banking-admin-script.blade.php'),'utf8').replace(/^<script>\s*/,'').replace(/\s*<\/script>\s*$/,'');
const customer=fs.readFileSync(path.join(root,'resources/views/prepared-v2/booking-payment.blade.php'),'utf8').match(/<script>([\s\S]*?)<\/script>/)[1].replace('@json($bookingId)','901');
const reconciliation=fs.readFileSync(path.join(root,'resources/views/prepared-v2/reconciliation.blade.php'),'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
const tick=()=>new Promise(resolve=>setImmediate(resolve));
function adminHarness(checked=true) {
    const fields={'bank-reference-1':{value:'SYNTHETIC_REF'},'bank-amount-1':{value:'777777.13'},'bank-time-1':{value:'2026-10-12T08:00:00'},
        'bank-attest-1':{checked},'bank-submit-1':{disabled:false}};
    const calls=[];const messages=[];
    const context=vm.createContext({document:{getElementById:id=>fields[id]},showToast:x=>messages.push(x),closeModalDirect:()=>{},loadBookings:()=>{},
        adminApi:async(url,o)=>{calls.push({url,body:JSON.parse(o.body)});return{ok:true,json:async()=>({outcome:'late_reconciliation'})};}});
    vm.runInContext(admin,context);return{context,calls,messages,fields};
}
test('Banking verification requires explicit attestation before sending',async()=>{
    const h=adminHarness(false); await h.context.verifyBankingPayment(901,1);assert.equal(h.calls.length,0);assert.equal(h.messages.length,1);
});
test('Reconciliation page script parses with unmatched VNPay receipts',()=>{
    new vm.Script(reconciliation);
});
test('Admin sends exact decimal text and statement reference, and labels late funds for reconciliation',async()=>{
    const h=adminHarness();await h.context.verifyBankingPayment(901,1);assert.equal(h.calls.length,1);
    assert.equal(h.calls[0].url,'/bookings/901/payments/1/verify-banking');assert.equal(h.calls[0].body.amount,'777777.13');
    assert.equal(h.calls[0].body.evidence_source,'bank_statement');assert.equal(h.calls[0].body.funds_received,true);
    assert.match(h.messages[0],/đối soát/);assert.equal(h.fields['bank-submit-1'].disabled,false);
});
function customerHarness(bank=true,status='pending',vnpay=false,target='https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?vnp_TxnRef=SYNTHETIC') {
    const nodes=new Map();const node=id=>{if(!nodes.has(id))nodes.set(id,{innerHTML:'',textContent:'',disabled:false,addEventListener:(name,fn)=>{node(id)[name]=fn;}});return nodes.get(id);};
    const calls=[];
    const payload={vnpay_ready:vnpay,booking:{status,expires_at:'2099-01-01T00:00:00Z',summary:{hotel:'<script>unsafe</script>',total:'777777.13',payment_method:vnpay?'vnpay':'banking',status,
        lines:[{name:'Standard',quantity:2,nights:3,unit_price:'500000.00'}]}},payment:{display_status:'pending',transaction_id:'SYNTHETIC_REF'},
        banking_instructions:bank?{bank_name:'Synthetic bank',account_name:'Synthetic fixture',account_number:'TEST-ONLY'}:null};
    const redirects=[];
    const context=vm.createContext({document:{getElementById:node},Date,URL,window:{location:{assign:url=>redirects.push(url)},setInterval:()=>0},
        api:async(url,o)=>{calls.push({url,o});return{ok:true,json:async()=>url==='/payments/create'?{payment_url:target}:payload};}});
    vm.runInContext(customer,context);return{nodes,node,calls,context,redirects};
}
test('Customer acknowledgement does not claim verification or successful payment',async()=>{
    const h=customerHarness();await tick();assert.equal(h.node('banking-ack').disabled,false);await h.node('banking-ack').click();
    assert.equal(h.calls[1].url,'/payments/manual');const body=JSON.parse(h.calls[1].o.body);
    assert.deepEqual(body,{booking_id:901,payment_method:'banking'});assert.match(h.node('payment-feedback').textContent,/chờ xác minh/);
    assert.doesNotMatch(h.node('booking-summary').innerHTML,/<script>unsafe/);assert.match(h.node('booking-summary').innerHTML,/777777.13/);
});
test('Missing bank configuration disables transfer acknowledgement',async()=>{
    const h=customerHarness(false);await tick();assert.equal(h.node('banking-ack').disabled,true);assert.match(h.node('banking-instructions').innerHTML,/Chưa có thông tin ngân hàng/);
});
test('Cancelled booking keeps transfer acknowledgement disabled',async()=>{
    const h=customerHarness(true,'cancelled');await tick();assert.equal(h.node('banking-ack').disabled,true);assert.match(h.node('payment-feedback').textContent,/hủy/);
});
test('Prepared VNPay action uses the backend URL and never marks the Booking paid',async()=>{
    const h=customerHarness(false,'pending',true);await tick();assert.equal(h.node('vnpay-start').disabled,false);
    await h.node('vnpay-start').click();assert.equal(h.calls[1].url,'/payments/create');assert.equal(h.redirects.length,1);
    assert.equal(JSON.parse(h.calls[1].o.body).booking_id,901);assert.doesNotMatch(h.node('payment-feedback').textContent,/thành công|đã xác nhận/);
});
test('Unexpected payment target cannot redirect the Customer',async()=>{
    const h=customerHarness(false,'pending',true,'https://unexpected.example.test/payment');await tick();await h.node('vnpay-start').click();
    assert.equal(h.redirects.length,0);assert.match(h.node('payment-feedback').textContent,/Chưa tạo/);
});
if(process.argv[2]) {
    const runtime=path.resolve(process.argv[2]);assert.ok(runtime.startsWith(path.join(root,'storage/app/private/roomtype-v2-tests')+path.sep));
    for(const page of ['b5-admin-bookings.html','b5-booking-payment.html']) test('Rendered inline scripts parse: '+page,()=>{
        const html=fs.readFileSync(path.join(runtime,page),'utf8');let count=0;
        for(const m of html.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script>/gi)) {
            if(/\bsrc=|application\/ld\+json|application\/json/.test(m[1])||!m[2].trim())continue;
            new vm.Script(m[2],{filename:page});count++;
        }
        assert.ok(count>0);
    });
}
