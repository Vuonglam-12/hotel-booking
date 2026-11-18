@extends('user.layouts.user')
@section('title','Thông tin đặt phòng và thanh toán')
@section('content')
<div class="card" style="padding:24px" data-prepared-payment="{{ $bookingId }}">
    <h1>Thông tin đặt phòng</h1>
    <p id="payment-feedback" role="status">Đang kiểm tra đặt phòng…</p>
    <dl id="booking-summary"></dl>
    <div id="banking-instructions"></div>
    <p id="banking-policy" role="status"></p>
    <p id="delivery-status" role="status">Invoice và email sẽ được xử lý sau khi thanh toán được xác minh.</p>
    <button id="banking-ack" type="button" disabled>Tôi đã chuyển khoản</button>
    <button id="vnpay-start" type="button" disabled>Thanh toán qua VNPay</button>
    <button id="invoice-download" type="button" hidden>Tải hóa đơn PDF</button>
    <button id="payment-refresh" type="button">Cập nhật trạng thái</button>
    <p>Đặt phòng chỉ được xác nhận sau khi Admin kiểm tra tiền nhận, hoặc IPN VNPay hợp lệ. Thời hạn giữ phòng là 15 phút.</p>
</div>
@endsection
@section('scripts')
<script>
(() => {
    const id=@json($bookingId);
    const feedback=document.getElementById('payment-feedback');
    const button=document.getElementById('banking-ack');
    const vnpay=document.getElementById('vnpay-start');
    const invoice=document.getElementById('invoice-download');
    const refresh=document.getElementById('payment-refresh');
    const escape=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    let loading=false;
    let bookingExpiresAt=0;
    async function load() {
        if (loading) return;
        loading=true;
        refresh.disabled=true;
        try {
            const response=await api('/payments/'+id);
            const data=await response.json();
            if (!response.ok) { feedback.textContent=data.message||'Không truy cập được đặt phòng.'; return; }
            const b=data.booking; const s=b.summary; const payment=data.payment;
            document.getElementById('booking-summary').innerHTML=`<dt>Khách sạn</dt><dd>${escape(s.hotel)}</dd>`+
                s.lines.map(l=>`<dt>Loại phòng</dt><dd>${escape(l.name)} × ${l.quantity} phòng × ${l.nights} đêm; ${escape(l.unit_price)} VND/phòng/đêm</dd>`).join('')+
                `<dt>Tổng tiền</dt><dd>${escape(s.total)} VND</dd><dt>Phương thức</dt><dd>${escape(s.payment_method)}</dd><dt>Đặt phòng</dt><dd>${escape(s.status)}</dd><dt>Thanh toán</dt><dd>${escape(payment?.display_status||'pending')}</dd>`;
            const bank=data.banking_instructions;
            document.getElementById('banking-policy').textContent=data.banking_policy?.warning||'';
            document.getElementById('banking-instructions').innerHTML=bank?
                `<p>${escape(bank.bank_name)} · ${escape(bank.account_name)} · ${escape(bank.account_number)}</p>`+
                `<p>Số tiền: ${escape(payment?.amount||s.total)} VND</p><p>Nội dung chuyển khoản: ${escape(payment?.transaction_id)}</p>`+
                (bank.qr_image_url?`<img src="${escape(bank.qr_image_url)}" alt="QR ngân hàng đã cấu hình" width="240">`:
                    bank.qr_payload?`<p>Chưa có ảnh QR. Nội dung cấu hình để tạo QR:</p><pre style="white-space:pre-wrap;overflow-wrap:anywhere">${escape(bank.qr_payload)}</pre>`:''):
                '<p>Chưa có thông tin ngân hàng được xác minh. Vui lòng liên hệ hỗ trợ; không chuyển khoản theo thông tin suy đoán.</p>';
            const expiry=Date.parse(String(b.expires_at||'').replace(' ','T'));
            bookingExpiresAt=Number.isFinite(expiry)?expiry:0;
            const live=b.status==='pending'&&bookingExpiresAt>Date.now();
            button.disabled=!live||!bank||s.payment_method!=='banking';
            vnpay.disabled=!live||!data.vnpay_ready||s.payment_method!=='vnpay';
            const effect=(b.effect_delivery||[]).find(item=>item.state!=='blocked')||(b.effect_delivery||[])[0];
            const delivery=document.getElementById('delivery-status');
            if (!effect) delivery.textContent='Invoice và email đang chờ hiệu ứng thanh toán đã xác minh.';
            else if (effect.state==='delivered') delivery.textContent='Invoice đã phát hành; email đã được transport chấp nhận.';
            else if (effect.state==='uncertain') delivery.textContent='Invoice đã được tạo; trạng thái email chưa chắc chắn và cần được kiểm tra.';
            else if (effect.state==='failed'||effect.state==='blocked') delivery.textContent='Invoice/email cần Admin xử lý; không tự gửi lại.';
            else delivery.textContent='Thanh toán đã được xác minh; invoice/email đang chờ xử lý.';
            invoice.hidden=!effect?.invoice_id;
            feedback.textContent=payment?.display_status==='reconciliation'?'Giao dịch cần đối soát.':
                b.status==='completed'?'Đặt phòng đã hoàn tất.':b.status==='confirmed'?'Đặt phòng đã xác nhận.':
                ['cancelled','expired'].includes(b.status)?'Đặt phòng đã hủy hoặc hết hạn.':!live?'Đã hết thời hạn giữ phòng.':'Đang chờ xác minh thanh toán.';
        } catch(e) {
            feedback.textContent='Chưa tải được trạng thái; vui lòng thử lại.';
        } finally {
            loading=false;
            refresh.disabled=false;
        }
    }
    button.addEventListener('click',async()=>{
        button.disabled=true;
        try {
            const response=await api('/payments/manual',{method:'POST',body:JSON.stringify({booking_id:id,payment_method:'banking'})});
            const data=await response.json();
            if (!response.ok) feedback.textContent=data.message||'Chưa ghi nhận được yêu cầu.';
            else feedback.textContent='Đã ghi nhận xác nhận của bạn; thanh toán vẫn chờ hệ thống xác minh.';
            await load();
        } catch(e) { feedback.textContent='Chưa gửi được yêu cầu; kiểm tra lại trạng thái.'; }
    });
    refresh.addEventListener('click',load);
    load().catch(()=>{feedback.textContent='Chưa tải được trạng thái; vui lòng thử lại.';});
    invoice.addEventListener('click',async()=>{
        invoice.disabled=true;
        try {
            const response=await api('/invoices/'+id+'/pdf');
            if (!response.ok) throw new Error('Invoice PDF unavailable');
            const url=URL.createObjectURL(await response.blob());
            const link=document.createElement('a');
            link.href=url; link.download='aurel-invoice-'+id+'.pdf'; link.click();
            setTimeout(()=>URL.revokeObjectURL(url),60000);
        } catch(e) {
            feedback.textContent='Chưa tải được hóa đơn; vui lòng thử lại.';
        } finally {
            invoice.disabled=false;
        }
    });
    window.setInterval(()=>{
        if (!document.hidden && (bookingExpiresAt===0||Date.now()<=bookingExpiresAt+300000)) load();
    },10000);
    vnpay.addEventListener('click',async()=>{
        vnpay.disabled=true;
        try {
            const response=await api('/payments/create',{method:'POST',body:JSON.stringify({booking_id:id})}); const data=await response.json();
            if (!response.ok) { feedback.textContent=data.message||'Chưa tạo được yêu cầu VNPay.'; return; }
            const target=new URL(data.payment_url);
            if(target.origin!=='https://sandbox.vnpayment.vn'||target.pathname!=='/paymentv2/vpcpay.html') throw new Error('Unverified payment target');
            window.location.assign(target.href);
        } catch(e) { feedback.textContent='Chưa tạo được yêu cầu VNPay; vui lòng kiểm tra lại trạng thái.'; }
    });
})();
</script>
@endsection
