<script>
(() => {
    const id=@json($bookingId),admin=@json($adminMode),localPayment=@json($localPayment ?? false),base=admin?'/api/admin/demo/bookings/':'/api/demo/bookings/';
    const $=id=>document.getElementById(id),labels={success:'Thành công',failed:'Thất bại',cancelled:'Đã hủy',expired:'Hết hạn',pending:'Chờ thanh toán',confirmed:'Đã xác nhận',completed:'Hoàn tất',reconciliation:'Đã nhận tiền · Cần đối soát',late_reconciliation:'Tiền đến sau khi đơn kết thúc · Đối soát',amount_mismatch_reconciliation:'Số tiền không khớp · Đối soát'};
    const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const money=v=>new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND',minimumFractionDigits:Number.isInteger(Number(v))?0:2,maximumFractionDigits:2}).format(Number(v));
    const reference=v=>String(v??'').replace(/^DEMO_LOCAL_/,'AUREL-PAY-').replace(/^DEMO_/,'AUREL-PAY-');
    let busy=false,state=null,expiredPosted=false;
    function syncActions(){
        if(!state)return;
        const b=state.booking,p=state.payment,released=['cancelled','expired'].includes(b.status);
        const lateRecorded=state.transactions.some(event=>event.outcome==='late_reconciliation');
        document.querySelectorAll('[data-demo-action]').forEach(button=>{
            const lateSuccess=localPayment&&admin&&released&&button.dataset.demoAction==='success'&&p.payment_status!=='success'&&!lateRecorded;
            button.disabled=busy||(!(b.status==='pending'&&p.payment_status==='pending')&&!lateSuccess);
        });
    }
    async function request(url,options={}) {
        const token=localStorage.getItem(admin?'admin_token':'token');if(!token){location.assign(admin?'/admin/login':'/login');throw new Error('Vui lòng đăng nhập.');}
        const r=await fetch(url,{...options,headers:{Accept:'application/json','Content-Type':'application/json',Authorization:'Bearer '+token,...options.headers}});
        const data=await r.json();if(!r.ok)throw new Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Chưa thể xử lý. Vui lòng cập nhật trạng thái.');return data;
    }
    function feedback(text,error=false,tone='info'){const node=$('demo-feedback');node.textContent=text;node.dataset.error=String(error);node.dataset.tone=error?'danger':tone;node.setAttribute('role',error?'alert':'status');}
    function qr(ref){const group=$('demo-qr-cells');if(!group)return;group.replaceChildren();let hash=0;for(const c of ref)hash=(hash*31+c.charCodeAt(0))>>>0;for(let y=2;y<23;y++)for(let x=2;x<23;x++){const f=[[2,2],[16,2],[2,16]].find(([a,b])=>x>=a&&x<a+7&&y>=b&&y<b+7);const on=f?(()=>{const a=x-f[0],b=y-f[1];return a===0||b===0||a===6||b===6||(a>=2&&a<=4&&b>=2&&b<=4);})():((hash=Math.imul(hash,1664525)+1013904223>>>0)&4);if(on){const r=document.createElementNS('http://www.w3.org/2000/svg','rect');r.setAttribute('x',x);r.setAttribute('y',y);r.setAttribute('width',1);r.setAttribute('height',1);group.append(r);}}}
    async function load(){
        state=await request(base+id);const b=state.booking,p=state.payment,s=b.summary;
        const tone=v=>['success','confirmed','completed'].includes(v)?'success':['failed','expired'].includes(v)?'danger':v==='pending'||String(v).includes('reconciliation')?'warning':'neutral';
        const paymentDisplayStatus=p?.display_status||p?.payment_status;
        const nightly=Array.isArray(b.nightly_rates)?b.nightly_rates:s.lines.flatMap(line=>line.nightly_rates||[]);
        $('demo-summary').innerHTML=`<dt>Khách sạn</dt><dd>${esc(s.hotel)}</dd><dt>Lưu trú</dt><dd>${esc(b.check_in).slice(0,10)} → ${esc(b.check_out).slice(0,10)}</dd>`+s.lines.map(l=>`<dt>Loại phòng</dt><dd>${esc(l.name)} × ${Number(l.quantity)} phòng × ${Number(l.nights)} đêm<br>${nightly.length?'Xem giá từng đêm đã lưu bên dưới':money(l.unit_price)+'/phòng/đêm tại thời điểm đặt'}</dd>`).join('')+`<dt>Khách</dt><dd>${Number(b.num_guests)}</dd><dt>Đặt phòng</dt><dd><span class="demo-state" data-tone="${tone(b.status)}">${esc(labels[b.status]||b.status)}</span></dd><dt>Thanh toán</dt><dd><span class="demo-state" data-tone="${tone(paymentDisplayStatus)}">${esc(labels[paymentDisplayStatus]||'Chờ thanh toán')}</span></dd>`;
        $('demo-nightly').hidden=!nightly.length;
        $('demo-nightly-rows').innerHTML=nightly.map(night=>{const quantity=Number(night.quantity||s.lines[0]?.quantity||1);const parts=night.base_price!=null&&night.surcharge!=null?`<small style="display:block">Giá cơ bản ${money(night.base_price)}<br>Phụ thu +${money(night.surcharge)}</small>`:'';return `<tr><td>${esc(night.date||night.night_date)}</td><td>${parts}${money(night.unit_price)} × ${quantity} phòng<br><strong>${money(Math.round(Number(night.unit_price)*100)*quantity/100)}</strong></td><td>${quantity}</td></tr>`;}).join('');
        $('demo-details').hidden=false;$('demo-amount').textContent=money(b.total_price);$('demo-transaction').textContent=reference(p.transaction_id);qr(p.transaction_id);
        syncActions();
        if($('demo-retry'))$('demo-retry').hidden=b.status!=='pending'||p.payment_status!=='failed';
        if($('demo-invoice'))$('demo-invoice').hidden=!state.invoice;
        $('demo-delivery').textContent=state.invoice?'Hóa đơn đã sẵn sàng. Bạn có thể xem lịch sử đặt phòng hoặc tải hóa đơn.':'Hóa đơn được tạo sau khi hệ thống xử lý thanh toán thành công.';
        const emailStatus=state.confirmation_email?.status;
        if(emailStatus==='sent')$('demo-delivery').textContent+=' Email xác nhận kèm hóa đơn đã được gửi.';
        else if(admin&&['uncertain','preparation_failed'].includes(emailStatus))$('demo-delivery').textContent+=' Email chưa được xác nhận gửi thành công; cần kiểm tra trước khi gửi lại.';
        else if(admin&&emailStatus==='sending')$('demo-delivery').textContent+=' Đang gửi email xác nhận.';
        $('demo-history').innerHTML=state.transactions.length?state.transactions.map(t=>`<tr><td>${esc(reference(t.merchant_ref))}</td><td><span class="demo-state" data-tone="${tone(t.outcome)}">${esc(labels[t.outcome]||t.outcome)}</span></td><td>${money(t.amount)}</td></tr>`).join(''):'<tr><td colspan="3">Chưa có kết quả thanh toán.</td></tr>';
        feedback(b.status==='confirmed'?'Đặt phòng đã được xác nhận.':b.status==='completed'?'Kỳ lưu trú đã hoàn tất.':b.status==='cancelled'?'Đặt phòng đã hủy; phòng đã được giải phóng.':b.status==='expired'?'Đặt phòng đã hết hạn; phòng đã được giải phóng.':p.payment_status==='failed'?'Thanh toán thất bại. Có thể thử lại trong thời hạn giữ phòng.':'Đang chờ xác nhận thanh toán.',false,['cancelled','expired'].includes(b.status)?tone(b.status):tone(p.payment_status));
        if(state.transactions.some(event=>String(event.outcome).includes('reconciliation')))feedback('Có giao dịch được ghi nhận để đối soát. Đơn giữ nguyên trạng thái; hãy liên hệ hỗ trợ để kiểm tra kết quả xử lý.',false,'warning');
        tick();
    }
    async function action(value){if(busy||(localPayment&&!admin))return;busy=true;document.querySelectorAll('[data-demo-action]').forEach(b=>b.disabled=true);try{await request(base+id+'/simulate',{method:'POST',body:JSON.stringify({action:value,payment_id:state?.payment?.id})});await load();}catch(e){feedback(e.message,true);try{await load();feedback(e.message,true);}catch{}}finally{busy=false;if(value==='expired'&&state?.booking.status==='pending')expiredPosted=false;syncActions();}}
    function tick(){if(!state)return;const seconds=Math.max(0,Math.ceil((Date.parse(state.expires_at_utc)-Date.now())/1000));$('demo-deadline').textContent=state.booking.status==='pending'?'Giữ phòng còn '+Math.floor(seconds/60)+':'+String(seconds%60).padStart(2,'0'):'Đã kết thúc lượt thanh toán';if(seconds===0&&state.booking.status==='pending'&&!busy&&!expiredPosted){expiredPosted=true;if(localPayment){load().catch(e=>{expiredPosted=false;feedback(e.message,true);});}else action('expired');}}
    document.querySelectorAll('[data-demo-action]').forEach(b=>b.addEventListener('click',()=>action(b.dataset.demoAction)));
    $('demo-refresh')?.addEventListener('click',()=>load().catch(e=>feedback(e.message,true)));
    if(!admin)window.addEventListener('aurel:notification',event=>{if(Number(event.detail?.data?.booking_id)===Number(id))load().catch(e=>feedback(e.message,true));});
    $('demo-retry')?.addEventListener('click',async()=>{if(busy)return;busy=true;try{await request(base+id+'/retry',{method:'POST',body:'{}'});await load();}catch(e){feedback(e.message,true);}finally{busy=false;await load().catch(()=>{});}});
    $('demo-invoice')?.addEventListener('click',async()=>{try{const token=localStorage.getItem('token');const r=await fetch('/api/invoices/'+id+'/pdf',{headers:{Authorization:'Bearer '+token}});if(!r.ok)throw new Error('Chưa tải được hóa đơn.');const url=URL.createObjectURL(await r.blob());const a=document.createElement('a');a.href=url;a.download='AUREL-hoa-don-'+id+'.pdf';a.click();setTimeout(()=>URL.revokeObjectURL(url),10000);}catch(e){feedback(e.message,true);}});
    load().catch(e=>feedback(e.message,true));setInterval(tick,1000);setInterval(()=>{if(!busy&&!document.hidden)load().catch(()=>{});},10000);
})();
</script>
