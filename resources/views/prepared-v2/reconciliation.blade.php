@extends('admin.layouts.admin')
@section('title','Đối soát thanh toán và email')
@section('page-title','Đối soát')
@section('content')
<div class="page-header"><div><h1>Đối soát thanh toán và email</h1><p>Tiền nhận muộn không tự khôi phục phòng. Email chưa chắc chắn cần kiểm tra transport trước khi gửi lại.</p></div><button type="button" class="btn-primary" id="reconciliation-refresh">Tải lại</button></div>
<p role="status" id="reconciliation-feedback"></p>
<div class="card" style="padding:20px"><h2>Giao dịch cần đối soát</h2><div style="overflow:auto"><table><thead><tr><th>Nguồn</th><th>Booking</th><th>Payment</th><th>Mã giao dịch / tham chiếu</th><th>Số tiền nhận</th><th>Kết quả</th><th>Đặt phòng</th></tr></thead><tbody id="reconciliation-funds"></tbody></table></div></div>
<div class="card" style="padding:20px;margin-top:20px"><h2>VNPay chưa khớp Payment</h2><p>Callback VNPay có chữ ký hợp lệ nhưng không ánh xạ được Payment; cần đối soát thủ công.</p><div style="overflow:auto"><table><thead><tr><th>Gateway transaction</th><th>Mã tham chiếu</th><th>Số tiền</th><th>Mã phản hồi / trạng thái</th><th>Kết quả</th></tr></thead><tbody id="reconciliation-vnpay-unmatched"></tbody></table></div></div>
<div class="card" style="padding:20px;margin-top:20px"><h2>SePay chưa khớp Payment</h2><p>Các giao dịch đã xác thực nhưng chưa khớp Payment cần kiểm tra thủ công với sao kê ngân hàng.</p><div style="overflow:auto"><table><thead><tr><th>SePay transaction</th><th>Mã tham chiếu</th><th>Số tiền</th><th>Kết quả</th></tr></thead><tbody id="reconciliation-sepay"></tbody></table></div></div>
<div class="card" style="padding:20px;margin-top:20px"><h2>Email cần kiểm tra</h2><div style="overflow:auto"><table><thead><tr><th>Booking / Event</th><th>Trạng thái</th><th>Số lần</th><th>Thao tác</th></tr></thead><tbody id="reconciliation-delivery"></tbody></table></div></div>
<dialog id="delivery-review-dialog" aria-labelledby="delivery-review-title" style="width:min(520px,calc(100% - 32px));padding:24px;border:1px solid #E5E7EB;border-radius:20px">
<h2 id="delivery-review-title">Kiểm tra email</h2><p>Xác nhận đã đối chiếu kết quả từ máy chủ email. Gửi lại có thể tạo email trùng.</p>
<label>Lý do / bằng chứng đối chiếu<textarea id="delivery-review-reason" maxlength="500" style="width:100%"></textarea></label>
<label style="display:block"><input type="checkbox" id="delivery-provider-checked"> Đã kiểm tra máy chủ email</label>
<label style="display:block"><input type="checkbox" id="delivery-duplicate-risk"> Chấp nhận nguy cơ gửi trùng nếu retry</label>
<button type="button" class="btn-primary" id="delivery-review-save">Xác nhận đối soát</button><button type="button" id="delivery-review-close">Đóng</button>
</dialog>
@endsection
@push('scripts')
<script>
(() => {
    const feedback=document.getElementById('reconciliation-feedback'); const dialog=document.getElementById('delivery-review-dialog'); let selected=null;
    const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    async function request(path,options={}) {
        const response=await adminApi(path,options);
        const data=await response.json(); if (!response.ok) throw new Error(data.message||'Không tải được dữ liệu.'); return data;
    }
    async function load() {
        try { const data=await request('/reconciliation');
            document.getElementById('reconciliation-funds').innerHTML=[...data.manual,...data.gateway].map(r=>`<tr><td>${esc(r.provider||'Banking thủ công')}</td><td>${r.booking_id}</td><td>${r.payment_id}</td><td>${esc(r.provider_transaction_id||r.bank_reference||r.merchant_ref||'—')}</td><td>${esc(r.received_amount||r.amount)}</td><td>${esc(r.outcome)}</td><td>${esc(r.status)}</td></tr>`).join('')||'<tr><td colspan="7">Chưa có giao dịch cần đối soát.</td></tr>';
            document.getElementById('reconciliation-vnpay-unmatched').innerHTML=data.unmatched_gateway.map(e=>`<tr><td>${esc(e.provider_transaction_id||'—')}</td><td>${esc(e.merchant_ref)}</td><td>${esc(e.amount)}</td><td>${esc(e.response_code)} / ${esc(e.transaction_status)}</td><td>${esc(e.outcome)}</td></tr>`).join('')||'<tr><td colspan="5">Chưa có callback VNPay chưa khớp.</td></tr>';
            document.getElementById('reconciliation-sepay').innerHTML=data.sepay_events.map(e=>`<tr><td>${esc(e.provider_transaction_id)}</td><td>${esc(e.merchant_ref||'—')}</td><td>${esc(e.amount)}</td><td>${esc(e.outcome)}</td></tr>`).join('')||'<tr><td colspan="4">Chưa có giao dịch SePay chưa khớp.</td></tr>';
            document.getElementById('reconciliation-delivery').innerHTML=data.delivery.map(e=>`<tr><td>${e.booking_id} / #${e.id}</td><td>${esc(e.state)}</td><td>${e.attempts}</td><td>${['uncertain','failed'].includes(e.state)?`<button data-effect="${e.id}" data-action="retry">Retry có kiểm soát</button> <button data-effect="${e.id}" data-action="confirm_delivered">Transport đã nhận</button>`:esc(e.last_error)}</td></tr>`).join('')||'<tr><td colspan="4">Chưa có email cần kiểm tra.</td></tr>'; feedback.textContent='Hiển thị tối đa 100 mục mỗi nhóm; không tự hoàn tiền hay khôi phục đặt phòng.';
        } catch(e) { feedback.textContent=e.message; }
    }
    document.getElementById('reconciliation-delivery').addEventListener('click',e=>{ const button=e.target.closest('[data-effect]'); if(!button)return; selected={effect:Number(button.dataset.effect),action:button.dataset.action};document.getElementById('delivery-review-reason').value='';document.getElementById('delivery-provider-checked').checked=false;document.getElementById('delivery-duplicate-risk').checked=false;dialog.showModal(); });
    document.getElementById('delivery-review-save').addEventListener('click',async()=>{
        if(!selected)return; const button=document.getElementById('delivery-review-save');button.disabled=true;
        try { await request('/delivery/'+selected.effect+'/review',{method:'POST',body:JSON.stringify({action:selected.action,reason:document.getElementById('delivery-review-reason').value,provider_checked:document.getElementById('delivery-provider-checked').checked,duplicate_risk_accepted:document.getElementById('delivery-duplicate-risk').checked})});dialog.close();await load(); } catch(e){feedback.textContent=e.message;}finally{button.disabled=false;}
    });
    document.getElementById('delivery-review-close').addEventListener('click',()=>dialog.close());document.getElementById('reconciliation-refresh').addEventListener('click',load);load();
})();
</script>
@endpush
