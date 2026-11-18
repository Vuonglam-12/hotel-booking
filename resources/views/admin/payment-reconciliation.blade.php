@extends('admin.layouts.admin')
@section('title','Đối soát thanh toán')
@section('content')
<h1 style="font-size:24px;margin-bottom:8px">Đối soát thanh toán</h1>
<p style="margin-bottom:20px">Ghi nhận tiền đến muộn hoặc sai số tiền. Xử lý đối soát không khôi phục đơn hay thay đổi tồn phòng.</p>
<p style="margin-bottom:16px"><a href="/admin/bookings">← Danh sách đặt phòng</a></p>
<div style="display:flex;gap:12px;margin-bottom:16px;align-items:center;flex-wrap:wrap">
    <label>Trạng thái <select id="reconciliation-state"><option value="">Tất cả</option><option value="open">Chờ xử lý</option><option value="reviewed">Đã đối soát</option><option value="refund_verified">Hoàn tiền có bằng chứng</option></select></label>
    <button class="btn-primary" id="reconciliation-refresh" type="button">Làm mới</button>
</div>
<p id="reconciliation-message" role="status" style="margin-bottom:12px"></p>
<div class="admin-table-scroll" style="background:#fff;border-radius:12px;padding:16px">
<table style="width:100%;border-collapse:collapse;text-align:left"><thead><tr><th>Đơn / giao dịch</th><th>Lý do</th><th>Số tiền</th><th>Xử lý</th></tr></thead><tbody id="reconciliation-rows"></tbody></table>
</div>
<div style="display:flex;gap:12px;margin-top:16px"><button type="button" id="reconciliation-prev">Trước</button><span id="reconciliation-page"></span><button type="button" id="reconciliation-next">Sau</button></div>
<dialog id="reconciliation-dialog" style="border:0;border-radius:14px;padding:24px;width:540px;max-width:94vw">
<form id="reconciliation-form">
    <h2 style="margin-bottom:16px">Kết quả đối soát <span id="reconciliation-selected"></span></h2>
    <label style="display:block;margin-bottom:12px">Trạng thái<br><select name="state" required><option value="open">Chờ xử lý</option><option value="reviewed">Đã đối soát</option><option value="refund_verified">Hoàn tiền có bằng chứng</option></select></label>
    <label style="display:block;margin-bottom:12px">Ghi chú xử lý<br><textarea name="note" required minlength="5" maxlength="4000" rows="4" style="width:100%"></textarea></label>
    <label style="display:block;margin-bottom:12px">Mã giao dịch / tham chiếu bằng chứng<br><input name="evidence_reference" maxlength="100" style="width:100%"></label>
    <label style="display:block;margin-bottom:12px">Bằng chứng (PDF, JPG hoặc PNG, tối đa 5 MB)<br><input name="evidence" type="file" accept="application/pdf,image/jpeg,image/png"></label>
    <p style="margin-bottom:16px">Chỉ ghi nhận hoàn tiền khi có mã tham chiếu và tệp bằng chứng. Tệp được lưu riêng tư; lịch sử ghi chú được giữ lại.</p>
    <p id="reconciliation-form-message" role="alert"></p>
    <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:16px"><button type="button" id="reconciliation-close">Đóng</button><button type="submit" class="btn-primary">Lưu kết quả</button></div>
</form></dialog>
@endsection
@push('scripts')
<script>
(()=>{
    const $=id=>document.getElementById(id),esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const money=v=>new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND',maximumFractionDigits:2}).format(Number(v));
    const labels={open:'Chờ xử lý',reviewed:'Đã đối soát',refund_verified:'Hoàn tiền có bằng chứng',late_payment:'Tiền đến sau hủy/hết hạn',amount_mismatch:'Sai số tiền'};
    let page=1,last=1,rows=[],selected=null,busy=false;
    async function api(url,options={}){const credential=localStorage.getItem('admin_token');if(!credential){location.assign('/admin/login');throw new Error('Vui lòng đăng nhập Admin.');}const response=await fetch(url,{...options,headers:{Accept:'application/json',Authorization:'Bearer '+credential,...options.headers}});const data=await response.json();if(!response.ok)throw new Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Không thể xử lý đối soát.');return data;}
    async function load(){try{const query=new URLSearchParams({page:String(page),state:$('reconciliation-state').value});const data=await api('/api/admin/demo/reconciliation?'+query);rows=data.data;last=data.last_page;$('reconciliation-rows').innerHTML=rows.length?rows.map(r=>`<tr><td style="padding:12px"><a href="/demo/admin/payments/${Number(r.booking_id)}">Đơn #${Number(r.booking_id)}</a><br>${esc(r.hotel)}<br><small>${esc(String(r.transaction_id).replace(/^DEMO_LOCAL_/,'AUREL-PAY-'))}</small><br>${esc(r.booking_status)}</td><td style="padding:12px">${esc(labels[r.reason]||r.reason)}</td><td style="padding:12px">Theo đơn: ${money(r.expected_amount)}<br>Đã nhận: ${money(r.received_amount)}</td><td style="padding:12px">${esc(labels[r.state]||r.state)}<br>${esc(r.note)}${r.evidence_reference?`<br>Bằng chứng: ${esc(r.evidence_reference)}`:''}<details><summary>Lịch sử xử lý (${r.reviews.length})</summary>${r.reviews.map(v=>`<p>${esc(v.created_at)} · ${esc(labels[v.state]||v.state)}<br>${esc(v.note)}${v.evidence_reference?`<br>${esc(v.evidence_reference)}`:''}${v.evidence_sha256?`<br><small>SHA-256: ${esc(v.evidence_sha256)}</small>`:''}</p>`).join('')}</details><button type="button" data-reconciliation="${Number(r.id)}">Ghi kết quả</button></td></tr>`).join(''):'<tr><td colspan="4" style="padding:24px">Chưa có giao dịch cần đối soát.</td></tr>';$('reconciliation-page').textContent=`Trang ${page}/${last}`;$('reconciliation-prev').disabled=page<=1;$('reconciliation-next').disabled=page>=last;$('reconciliation-message').textContent='';}catch(error){$('reconciliation-message').textContent=error.message;}}
    $('reconciliation-rows').addEventListener('click',event=>{const button=event.target.closest('[data-reconciliation]');if(!button)return;selected=rows.find(r=>Number(r.id)===Number(button.dataset.reconciliation));const form=$('reconciliation-form');form.reset();form.elements.state.value=selected.state;form.elements.note.value=selected.note||'';form.elements.evidence_reference.value=selected.evidence_reference||'';$('reconciliation-selected').textContent='#'+selected.booking_id;$('reconciliation-form-message').textContent='';$('reconciliation-dialog').showModal();});
    $('reconciliation-form').addEventListener('submit',async event=>{event.preventDefault();if(busy||!selected)return;busy=true;const button=event.submitter;button.disabled=true;try{await api('/api/admin/demo/reconciliation/'+selected.id+'/review',{method:'POST',body:new FormData(event.target)});$('reconciliation-dialog').close();await load();}catch(error){$('reconciliation-form-message').textContent=error.message;}finally{busy=false;button.disabled=false;}});
    $('reconciliation-close').addEventListener('click',()=>{if(!busy)$('reconciliation-dialog').close();});
    $('reconciliation-refresh').addEventListener('click',load);$('reconciliation-state').addEventListener('change',()=>{page=1;load();});$('reconciliation-prev').addEventListener('click',()=>{if(page>1){page--;load();}});$('reconciliation-next').addEventListener('click',()=>{if(page<last){page++;load();}});load();
})();
</script>
@endpush
