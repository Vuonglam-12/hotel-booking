@extends('admin.layouts.admin')
@section('title','Giao dịch đặt phòng')
@section('content')
<div class="page-header"><div><h1 class="page-title">Giao dịch đặt phòng</h1><p class="page-sub">Theo dõi trạng thái và xử lý thanh toán đặt phòng.</p></div><button class="btn-primary" id="demo-list-refresh" type="button">Cập nhật</button></div>
<p id="demo-list-feedback" role="status"></p>
<div class="table-card admin-table-scroll"><table class="bk-table"><thead><tr><th>Đặt phòng</th><th>Khách sạn</th><th>Tổng tiền</th><th>Trạng thái</th><th>Thanh toán</th><th>Thao tác</th></tr></thead><tbody id="demo-list"></tbody></table></div>
<div class="demo-controls" style="margin:16px 0"><button class="btn-secondary" id="demo-list-prev" disabled>Trước</button><span id="demo-list-page"></span><button class="btn-secondary" id="demo-list-next" disabled>Tiếp</button></div>

@endsection
@push('scripts')
<script>
(()=>{const $=id=>document.getElementById(id),esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));let page=1;async function load(){try{const r=await adminApi('/demo/bookings?page='+page);if(!r.ok)throw new Error('Chưa tải được giao dịch.');const d=await r.json();$('demo-list').innerHTML=d.data.map(b=>`<tr><td>#${b.id}</td><td>${esc(b.hotel)}</td><td>${new Intl.NumberFormat('vi-VN').format(b.total_price)} ₫</td><td><span class="badge" data-admin-tone="${b.status==='confirmed'?'success':b.status==='pending'?'warning':'neutral'}">${({confirmed:'Đã xác nhận',pending:'Chờ thanh toán',cancelled:'Đã hủy / hết hạn'})[b.status]||esc(b.status)}</span></td><td>${esc(b.payment_status)}<br><small>${esc(String(b.transaction_id||'').replace(/^DEMO_LOCAL_/,'AUREL-PAY-').replace(/^DEMO_/,'AUREL-PAY-'))}</small></td><td><a class="btn-action" data-admin-action="view" href="/demo/admin/payments/${b.id}">Xem giao dịch</a></td></tr>`).join('')||'<tr><td colspan="6">Chưa có đặt phòng. Hãy tạo từ trang khách sạn.</td></tr>';$('demo-list-prev').disabled=page<=1;$('demo-list-next').disabled=page>=d.last_page;$('demo-list-page').textContent=page+' / '+d.last_page;$('demo-list-feedback').textContent='Danh sách giao dịch đặt phòng.';}catch(e){$('demo-list-feedback').textContent=e.message;}}$('demo-list-refresh').onclick=load;$('demo-list-prev').onclick=()=>{page--;load();};$('demo-list-next').onclick=()=>{page++;load();};load();})();
</script>
@endpush
