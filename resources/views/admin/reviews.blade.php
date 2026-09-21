@extends('admin.layouts.admin')
@section('title', 'Quản lý đánh giá')

@push('styles')
<style>
    .page-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:20px; }
    .page-title { font-family:Arial, sans-serif; font-size:22px; font-weight:700; color:var(--dark); }
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

    .stars { color:#F59E0B; font-size:13px; letter-spacing:1px; }
    .comment-cell { max-width:320px; }
    .comment-text { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:300px; color:var(--gray); font-size:12px; margin-top:3px; }

    .badge { display:inline-flex; align-items:center; padding:3px 9px; border-radius:99px; font-size:11px; font-weight:600; }
    .badge-pending  { background:#FEF9C3; color:#A16207; }
    .badge-approved { background:#DCFCE7; color:#15803D; }
    .badge-rejected { background:#FEE2E2; color:#B91C1C; }

    .btn-action { padding:5px 11px; border-radius:8px; font-size:11px; font-weight:600; cursor:pointer; border:1px solid transparent; transition:all .15s; white-space:nowrap; }
    .btn-delete { background:rgba(220,38,38,.08); color:#DC2626; border-color:rgba(220,38,38,.15); }
    .btn-delete:hover { background:rgba(220,38,38,.15); }

    .empty-state { text-align:center; padding:60px 20px; color:var(--gray); }
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <div class="page-title">Quản lý đánh giá</div>
        <div class="page-sub">Xem và xoá đánh giá của khách hàng</div>
    </div>
</div>

{{-- Stats --}}
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Tổng đánh giá</div>
        <div class="stat-value" id="statTotal">—</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Điểm trung bình</div>
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
                <th>Nhận xét</th>
                <th>Ngày</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody id="reviewTableBody">
            <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--gray);">Đang tải...</td></tr>
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

    async function loadReviews(page = 1) {
        currentPage = page;
        const search = document.getElementById('searchInput').value;
        const rating = document.getElementById('ratingFilter').value;

        const params = new URLSearchParams({ per_page: 20, page });
        if (rating) params.append('max_rating', rating);

        showLoading();

        try {
            const res = await adminApi(`/reviews?${params}`);
            if (!res.ok) throw new Error();
            const data = await res.json();
            renderTable(data);
            renderPagination(data);
            updateCount(data);
            loadStats();
        } catch(_) {
            document.getElementById('reviewTableBody').innerHTML =
                `<tr><td colspan="7" class="empty-state">Không thể tải dữ liệu</td></tr>`;
        }
    }

    function renderTable(data) {
        const rows = data.data || [];
        if (!rows.length) {
            document.getElementById('reviewTableBody').innerHTML =
                `<tr><td colspan="7" class="empty-state">Không có đánh giá nào</td></tr>`;
            return;
        }

        // Client-side filter by search (vì API chưa hỗ trợ search review)
        const search = document.getElementById('searchInput').value.toLowerCase();
        const filtered = search
            ? rows.filter(r =>
                r.customer?.name?.toLowerCase().includes(search) ||
                r.hotel?.name?.toLowerCase().includes(search))
            : rows;

        document.getElementById('reviewTableBody').innerHTML = filtered.map(r => `
            <tr>
                <td style="font-weight:700;color:var(--blue);">#${r.id}</td>
                <td>${escHtml(r.customer?.name ?? '—')}</td>
                <td>${escHtml(r.hotel?.name ?? '—')}</td>
                <td><span class="stars">${'★'.repeat(r.rating)}${'☆'.repeat(5 - r.rating)}</span></td>
                <td class="comment-cell">
                    <div class="comment-text" title="${escHtml(r.comment ?? '')}">${escHtml(r.comment ?? '—')}</div>
                </td>
                <td style="color:var(--gray);white-space:nowrap;">${formatDate(r.created_at)}</td>
                <td>
                    <button class="btn-action btn-delete" onclick="deleteReview(${r.id})">Xoá</button>
                </td>
            </tr>
        `).join('');
    }

    async function deleteReview(id) {
        if (!confirm('Xoá đánh giá này?')) return;
        try {
            const res = await adminApi(`/reviews/${id}`, { method: 'DELETE' });
            if (!res.ok) throw new Error();
            adminToast('✓ Đã xoá đánh giá');
            loadReviews(currentPage);
        } catch(_) {
            adminToast('❌ Xoá thất bại');
        }
    }

    async function loadStats() {
        try {
            const res = await adminApi('/reviews?per_page=1');
            const data = await res.json();
            document.getElementById('statTotal').textContent = data.total ?? '—';

            // Tính avg từ tất cả review — gọi thêm endpoint lấy hết
            const allRes = await adminApi('/reviews?per_page=9999');
            const allData = await allRes.json();
            const reviews = allData.data || [];
            const avg = reviews.length
                ? (reviews.reduce((s, r) => s + r.rating, 0) / reviews.length).toFixed(1)
                : '—';
            document.getElementById('statAvg').textContent = avg ? `${avg} ★` : '—';

            const today = new Date().toDateString();
            const todayCount = reviews.filter(r => new Date(r.created_at).toDateString() === today).length;
            document.getElementById('statToday').textContent = todayCount;
        } catch(_) {}
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
            btns.push(`<button class="btn-action ${p===current?'':''}\" onclick="loadReviews(${p})" style="padding:5px 10px;${p===current?'background:var(--blue);color:#fff;border-color:var(--blue);':''}">${p}</button>`);
        }
        btns.push(`<button class="btn-action" onclick="loadReviews(${current+1})" ${current===lastPage?'disabled':''} style="padding:5px 10px;">›</button>`);
        document.getElementById('paginationBtns').innerHTML = btns.join('');
    }

    function updateCount(data) {
        document.getElementById('tableCount').textContent = `${data.total ?? 0} đánh giá`;
    }

    function showLoading() {
        document.getElementById('reviewTableBody').innerHTML =
            `<tr><td colspan="7" style="text-align:center;padding:30px;color:var(--gray);">Đang tải...</td></tr>`;
    }

    function clearFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('ratingFilter').value = '';
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