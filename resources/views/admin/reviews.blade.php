@extends('admin.layouts.admin')
@section('title', 'Quản lý đánh giá')

@push('styles')
<style>
    .page-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:20px; }
    .page-title { font-family:'Be Vietnam Pro', system-ui, sans-serif; font-size:22px; font-weight:700; color:var(--dark); }
    .page-sub { font-size:13px; color:var(--gray); margin-top:3px; }

    .stats-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; margin-bottom:20px; }
    .stat-card { background:#fff; border:1px solid var(--border); border-radius:16px; padding:18px 20px; }
    .stat-label { font-size:12px; color:var(--gray); font-weight:500; margin-bottom:6px; }
    .stat-value { font-size:26px; font-weight:700; color:var(--dark); }

    .toolbar { background:#fff; border:1px solid var(--border); border-radius:14px; padding:14px 18px; display:flex; align-items:center; gap:12px; margin-bottom:16px; flex-wrap:wrap; }
    .toolbar-search { flex:1; min-width:200px; position:relative; }
    .toolbar-search input { width:100%; padding:8px 12px 8px 34px; border:1px solid var(--border); border-radius:10px; font-size:13px; background:var(--bg); outline:none; }
    .toolbar-search input:focus { border-color:var(--blue); background:#fff; }
    .toolbar-search-icon { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--gray); pointer-events:none; }
    .toolbar select { padding:8px 12px; border:1px solid var(--border); border-radius:10px; font-size:13px; background:var(--bg); outline:none; cursor:pointer; }

    .table-card { background:#fff; border:1px solid var(--border); border-radius:16px; overflow:hidden; }
    .table-head-row { padding:16px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
    .table-title { font-size:14px; font-weight:600; color:var(--dark); }
    .table-count { font-size:12px; color:var(--gray); background:var(--bg); padding:3px 10px; border-radius:99px; }

    .rv-table { width:100%; border-collapse:collapse; }
    .rv-table th { font-size:11px; font-weight:600; color:var(--gray); text-transform:uppercase; letter-spacing:.4px; padding:10px 16px; text-align:left; border-bottom:1px solid var(--border); background:var(--bg); white-space:nowrap; }
    .rv-table td { font-size:13px; padding:13px 16px; border-bottom:1px solid rgba(226,232,240,.5); vertical-align:middle; }
    .rv-table tr:last-child td { border-bottom:none; }
    .rv-table tbody tr:hover td { background:rgba(37,99,235,.02); }

    .stars { color:#EAB308; font-size:13px; letter-spacing:1px; }
    .comment-cell { max-width:320px; }
    .comment-text { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:300px; color:var(--gray); font-size:12px; margin-top:3px; }

    .badge { display:inline-flex; align-items:center; padding:3px 9px; border-radius:99px; font-size:11px; font-weight:600; }
    .badge-pending  { background:#FEF9C3; color:#A16207; }
    .badge-approved { background:#DCFCE7; color:#15803D; }
    .badge-rejected { background:#FEE2E2; color:#B91C1C; }

    .btn-action { padding:5px 11px; border-radius:8px; font-size:11px; font-weight:600; cursor:pointer; border:1px solid transparent; transition:all .15s; white-space:nowrap; }
    .btn-delete { background:rgba(220,38,38,.08); color:#DC2626; border-color:rgba(220,38,38,.15); }
    .btn-delete:hover { background:rgba(220,38,38,.15); }
    .btn-approve { color:#15803D;background:#F0FDF4;border-color:#86EFAC; }
    .btn-hide { color:#475569;background:#F1F5F9;border-color:#CBD5E1; }
    .review-actions { display:flex;flex-wrap:wrap;gap:6px; }
    .review-full summary { color:#2563EB;cursor:pointer;font-size:12px;margin-top:6px; }
    .review-full p { white-space:pre-wrap;overflow-wrap:anywhere;font-size:12px;line-height:1.7;margin-top:8px; }
    @media(max-width:639px){.stats-grid{grid-template-columns:1fr}.toolbar-search{min-width:0;width:100%;flex-basis:100%}.table-card{overflow-x:auto}}

    .empty-state { text-align:center; padding:60px 20px; color:var(--gray); }
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <h1 class="page-title">Quản lý đánh giá</h1>
        <div class="page-sub">Xem, duyệt và quản lý đánh giá từ khách đã thanh toán thành công</div>
    </div>
</div>

{{-- Stats --}}
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Tổng đánh giá</div>
        <div class="stat-value" id="statTotal">—</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Điểm trung bình đã công khai</div>
        <div class="stat-value" id="statAvg">—</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Đánh giá hôm nay</div>
        <div class="stat-value" id="statToday">—</div>
    </div>
</div>

{{-- Toolbar --}}
<div class="toolbar">
    <div class="toolbar-search">
        <span class="toolbar-search-icon">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </span>
        <input type="text" id="searchInput" placeholder="Tìm tên khách, khách sạn..." oninput="debounceSearch()">
    </div>
    <select id="ratingFilter" onchange="loadReviews(1)">
        <option value="">Tất cả sao</option>
        <option value="1">1 sao</option>
        <option value="2">2 sao</option>
        <option value="3">3 sao</option>
        <option value="4">4 sao</option>
        <option value="5">5 sao</option>
    </select>
    <select id="reviewStatusFilter" aria-label="Trạng thái đánh giá" onchange="loadReviews(1)"><option value="">Tất cả trạng thái</option><option value="pending">Chờ duyệt</option><option value="approved">Đã duyệt</option><option value="hidden">Tạm ẩn</option></select>
    <button class="btn-action" style="background:var(--bg);border-color:var(--border);color:var(--gray);" onclick="clearFilters()">Xoá bộ lọc</button>
</div>

{{-- Table --}}
<div class="table-card">
    <div class="table-head-row">
        <span class="table-title">Danh sách đánh giá</span>
        <span class="table-count" id="tableCount">—</span>
    </div>
    <table class="rv-table">
        <thead>
            <tr>
                <th>#ID</th>
                <th>Khách hàng</th>
                <th>Khách sạn</th>
                <th>Sao</th>
                <th>Trạng thái</th>
                <th>Nhận xét</th>
                <th>Ngày</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody id="reviewTableBody">
            <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--gray);">Đang tải...</td></tr>
        </tbody>
    </table>
    <div id="paginationWrap" style="display:none; padding:14px 18px; border-top:1px solid var(--border); display:flex; align-items:center; justify-content:space-between;">
        <span class="table-count" id="paginationInfo"></span>
        <div style="display:flex;gap:6px;" id="paginationBtns"></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let currentPage = 1;
    let searchTimer = null;
    let reviewRequestSequence = 0;

    async function loadReviews(page = 1) {
        const sequence=++reviewRequestSequence;
        currentPage = page;
        const search = document.getElementById('searchInput').value;
        const rating = document.getElementById('ratingFilter').value;

        const params = new URLSearchParams({ per_page: 20, page });
        if (rating) params.append('rating', rating);
        if(search.trim())params.append('search',search.trim());
        const status=document.getElementById('reviewStatusFilter').value;if(status)params.append('status',status);

        showLoading();

        try {
            const res = await adminApi(`/reviews?${params}`);
            if (!res.ok) throw new Error();
            const data = await res.json();
            if(sequence!==reviewRequestSequence)return;
            renderTable(data);
            renderPagination(data);
            updateCount(data);
            renderStats(data.stats);
        } catch(_) {
            if(sequence!==reviewRequestSequence)return;
            document.getElementById('reviewTableBody').innerHTML =
                `<tr><td colspan="8" class="empty-state">Không thể tải dữ liệu</td></tr>`;
        }
    }

    function renderTable(data) {
        const rows = data.data || [];
        if (!rows.length) {
            document.getElementById('reviewTableBody').innerHTML =
                `<tr><td colspan="8" class="empty-state">Không có đánh giá nào</td></tr>`;
            return;
        }

        document.getElementById('reviewTableBody').innerHTML = rows.map(r => `
            <tr data-review-id="${Number(r.id)}">
                <td style="font-weight:700;color:var(--blue);">#${r.id}</td>
                <td>${escHtml(r.customer?.name ?? '—')}</td>
                <td>${escHtml(r.hotel?.name ?? '—')}</td>
                <td><span class="stars">${'★'.repeat(r.rating)}${'☆'.repeat(5 - r.rating)}</span></td>
                <td><span class="badge ${r.status==='approved'?'badge-approved':r.status==='pending'?'badge-pending':'badge-rejected'}">${({pending:'Chờ duyệt',approved:'Đã duyệt',hidden:'Tạm ẩn'})[r.status]||'Chưa xác định'}</span></td>
                <td class="comment-cell">
                    <div class="comment-text" title="${escHtml(r.comment ?? '')}">${escHtml(r.comment ?? '—')}</div>
                    ${(r.comment||'').length>150?`<details class="review-full"><summary>Xem đầy đủ</summary><p>${escHtml(r.comment)}</p></details>`:''}
                </td>
                <td style="color:var(--gray);white-space:nowrap;">${formatDate(r.created_at)}</td>
                <td>
                    <div class="review-actions">${r.status!=='approved'?`<button class="btn-action btn-approve" data-review-action="approve" onclick="moderateReview(${Number(r.id)},'approved',this)">Duyệt</button>`:''}${r.status!=='hidden'?`<button class="btn-action btn-hide" data-review-action="hide" onclick="moderateReview(${Number(r.id)},'hidden',this)">Ẩn</button>`:''}<button class="btn-action btn-delete" data-review-action="delete" onclick="deleteReview(${Number(r.id)})">Xóa</button></div>
                </td>
            </tr>
        `).join('');
    }

    async function moderateReview(id,status,button) {
        button.disabled=true;
        try {
            const res=await adminApi('/reviews/'+id+'/status',{method:'PATCH',headers:{'Content-Type':'application/json'},body:JSON.stringify({status})});
            const data=await res.json();if(!res.ok)throw new Error(data.message||'Chưa cập nhật được đánh giá.');
            adminToast(status==='approved'?'Đã duyệt đánh giá.':'Đã ẩn đánh giá.');await loadReviews(currentPage);
        }catch(error){adminToast(error.message||'Chưa cập nhật được đánh giá.');}finally{button.disabled=false;}
    }

    async function deleteReview(id) {
        if (!await adminConfirm({title:'Xóa đánh giá?',message:'Đánh giá này sẽ bị xóa. Hãy kiểm tra đúng bản ghi trước khi xác nhận.',confirmText:'Xóa đánh giá'})) return;
        try {
            const res = await adminApi(`/reviews/${id}`, { method: 'DELETE' });
            if (!res.ok) throw new Error();
            adminToast('✓ Đã xoá đánh giá');
            loadReviews(currentPage);
        } catch(_) {
            adminToast('❌ Xoá thất bại');
        }
    }

    function renderStats(stats = {}) {
        document.getElementById('statTotal').textContent = stats.total ?? '—';
        document.getElementById('statAvg').textContent = Number(stats.avg_rating || 0).toFixed(1) + ' ★';
        document.getElementById('statToday').textContent = stats.today ?? '—';
    }

    function renderPagination(data) {
        const wrap = document.getElementById('paginationWrap');
        const total    = data.total ?? 0;
        const perPage  = data.per_page ?? 20;
        const lastPage = data.last_page ?? Math.ceil(total / perPage);
        const current  = data.current_page ?? currentPage;

        if (total <= perPage) { wrap.style.display = 'none'; return; }
        wrap.style.display = 'flex';

        document.getElementById('paginationInfo').textContent =
            `Hiển thị ${data.from ?? 0}–${data.to ?? 0} / ${total} đánh giá`;

        const btns = [];
        btns.push(`<button class="btn-action" onclick="loadReviews(${current-1})" ${current===1?'disabled':''} style="padding:5px 10px;">‹</button>`);
        for (let p = Math.max(1, current-2); p <= Math.min(lastPage, current+2); p++) {
            btns.push(`<button class="btn-action ${p===current?'active':''} onclick="loadReviews(${p})" style="padding:5px 10px;${p===current?'background:var(--blue);color:#fff;border-color:var(--blue);':''}">${p}</button>`);
        }
        btns.push(`<button class="btn-action" onclick="loadReviews(${current+1})" ${current===lastPage?'disabled':''} style="padding:5px 10px;">›</button>`);
        document.getElementById('paginationBtns').innerHTML = btns.join('');
    }

    function updateCount(data) {
        document.getElementById('tableCount').textContent = `${data.total ?? 0} đánh giá`;
    }

    function showLoading() {
        document.getElementById('reviewTableBody').innerHTML =
            `<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--gray);">Đang tải...</td></tr>`;
    }

    function clearFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('ratingFilter').value = '';
        document.getElementById('reviewStatusFilter').value = '';
        loadReviews(1);
    }

    function debounceSearch() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadReviews(1), 400);
    }

    function formatDate(str) {
        if (!str) return '—';
        return new Date(str).toLocaleDateString('vi-VN', { day:'2-digit', month:'2-digit', year:'numeric' });
    }

    function escHtml(s) {
        if (!s) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    document.addEventListener('DOMContentLoaded', () => loadReviews(1));
</script>
@endpush
