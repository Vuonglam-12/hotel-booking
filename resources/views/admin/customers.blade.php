{{-- resources/views/admin/customers.blade.php --}}
@extends('admin.layouts.admin')
@section('title', 'Quản lý khách hàng')

@push('styles')
<style>
    /* ── Layout ── */
    .page-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:20px; }
    .page-title  { font-family:Arial,sans-serif; font-size:22px; font-weight:700; color:var(--dark); }
    .page-sub    { font-size:13px; color:var(--gray); margin-top:3px; }

    .main-cols   { display:grid; grid-template-columns:1fr 300px; gap:20px; align-items:start; }

    /* ── Stats ── */
    .stats-grid  { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:20px; }
    .stat-card   { background:#fff; border:1px solid var(--border); border-radius:16px; padding:18px 20px; }
    .stat-label  { font-size:12px; color:var(--gray); font-weight:500; margin-bottom:6px; }
    .stat-value  { font-size:26px; font-weight:700; color:var(--dark); line-height:1; }

    /* ── Toolbar ── */
    .toolbar { background:#fff; border:1px solid var(--border); border-radius:14px; padding:14px 18px; display:flex; align-items:center; gap:12px; margin-bottom:16px; flex-wrap:wrap; }
    .toolbar-search { flex:1; min-width:200px; position:relative; }
    .toolbar-search input { width:100%; padding:8px 12px 8px 34px; border:1px solid var(--border); border-radius:10px; font-size:13px; background:var(--bg); color:var(--dark); outline:none; transition:all .2s; box-sizing:border-box; }
    .toolbar-search input:focus { border-color:var(--blue); background:#fff; box-shadow:0 0 0 3px rgba(37,99,235,.1); }
    .toolbar-search-icon { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--gray); pointer-events:none; }

    /* ── Table ── */
    .table-card      { background:#fff; border:1px solid var(--border); border-radius:16px; overflow:hidden; }
    .table-head-row  { padding:16px 20px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; }
    .table-title     { font-size:14px; font-weight:600; color:var(--dark); }
    .table-count     { font-size:12px; color:var(--gray); background:var(--bg); padding:3px 10px; border-radius:99px; }

    .cust-table { width:100%; border-collapse:collapse; }
    .cust-table th { font-size:11px; font-weight:600; color:var(--gray); text-transform:uppercase; letter-spacing:.4px; padding:10px 16px; text-align:left; border-bottom:1px solid var(--border); background:var(--bg); white-space:nowrap; }
    .cust-table td { font-size:13px; padding:13px 16px; border-bottom:1px solid rgba(226,232,240,.5); vertical-align:middle; }
    .cust-table tr:last-child td { border-bottom:none; }
    .cust-table tbody tr { cursor:pointer; transition:background .12s; }
    .cust-table tbody tr:hover td { background:rgba(37,99,235,.02); }

    /* ── Avatar initials ── */
    .cust-avatar { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,#2563EB,#7C3AED); color:#fff; font-size:11px; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0; }

    /* ── Buttons ── */
    .btn-action  { padding:5px 11px; border-radius:8px; font-size:11px; font-weight:600; cursor:pointer; border:1px solid transparent; transition:all .15s; white-space:nowrap; }
    .btn-detail  { background:rgba(37,99,235,.08); color:var(--blue); border-color:rgba(37,99,235,.15); }
    .btn-detail:hover { background:rgba(37,99,235,.15); }

    /* ── Pagination ── */
    .pagination      { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-top:1px solid var(--border); }
    .pagination-info { font-size:13px; color:var(--gray); }
    .pagination-btns { display:flex; gap:6px; }
    .pg-btn { width:32px; height:32px; border-radius:8px; border:1px solid var(--border); background:#fff; color:var(--dark); font-size:13px; cursor:pointer; transition:all .15s; display:flex; align-items:center; justify-content:center; }
    .pg-btn:hover   { background:var(--bg); }
    .pg-btn.active  { background:var(--blue); border-color:var(--blue); color:#fff; font-weight:700; }
    .pg-btn:disabled { opacity:.35; cursor:default; }

    /* ── Sidebar ── */
    .sidebar-card { background:#fff; border:1px solid var(--border); border-radius:16px; padding:18px 20px; margin-bottom:16px; }
    .sidebar-title { font-size:14px; font-weight:600; color:var(--dark); margin-bottom:14px; }

    /* Mini bar chart */
    .mini-chart { display:flex; align-items:flex-end; gap:6px; height:80px; }
    .mini-bar-wrap { flex:1; display:flex; flex-direction:column; align-items:center; gap:4px; height:100%; justify-content:flex-end; }
    .mini-bar { width:100%; border-radius:4px 4px 0 0; background:var(--blue); opacity:.8; min-height:4px; transition:height .3s; }
    .mini-bar-label { font-size:10px; color:var(--gray); }

    /* Top customers list */
    .top-cust-item { display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid rgba(226,232,240,.4); }
    .top-cust-item:last-child { border-bottom:none; }
    .top-cust-rank { width:20px; font-size:11px; font-weight:700; color:var(--gray); text-align:center; flex-shrink:0; }
    .top-cust-info { flex:1; min-width:0; }
    .top-cust-name { font-size:13px; font-weight:600; color:var(--dark); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .top-cust-sub  { font-size:11px; color:var(--gray); }
    .top-cust-spent { font-size:12px; font-weight:700; color:var(--blue); white-space:nowrap; }

    /* ── Modal ── */
    .modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.35); backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center; z-index:1000; opacity:0; pointer-events:none; transition:opacity .2s; }
    .modal-backdrop.open { opacity:1; pointer-events:all; }
    .modal-box { background:#fff; border-radius:20px; width:540px; max-width:95vw; max-height:90vh; overflow-y:auto; box-shadow:0 20px 40px rgba(0,0,0,.15); }
    .modal-header { display:flex; align-items:center; justify-content:space-between; padding:20px 24px; border-bottom:1px solid var(--border); position:sticky; top:0; background:#fff; z-index:1; }
    .modal-header h3 { font-size:16px; font-weight:700; color:var(--dark); }
    .modal-close { width:32px; height:32px; border-radius:8px; border:1px solid var(--border); background:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center; color:var(--gray); transition:all .15s; }
    .modal-close:hover { background:var(--bg); }
    .modal-body { padding:24px; }

    /* Modal detail */
    .cust-detail-head   { display:flex; align-items:center; gap:16px; padding-bottom:20px; border-bottom:1px solid var(--border); margin-bottom:20px; }
    .cust-detail-avatar { width:52px; height:52px; border-radius:14px; background:linear-gradient(135deg,#2563EB,#7C3AED); color:#fff; font-size:18px; font-weight:800; display:flex; align-items:center; justify-content:center; flex-shrink:0; overflow:hidden; }
    .cust-detail-name   { font-size:16px; font-weight:700; color:var(--dark); }
    .cust-detail-sub    { font-size:12px; color:var(--gray); margin-top:3px; }
    .cust-stats-row     { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; margin-bottom:20px; }
    .cust-stat-box  { background:var(--bg); border-radius:12px; padding:12px; text-align:center; }
    .cust-stat-num  { font-size:22px; font-weight:700; color:var(--blue); }
    .cust-stat-lbl  { font-size:11px; color:var(--gray); margin-top:4px; }

    .modal-actions-row { display:flex; gap:8px; margin-bottom:20px; }
    .btn-modal-action { flex:1; padding:9px 14px; border-radius:10px; font-size:13px; font-weight:600; cursor:pointer; border:1px solid transparent; transition:all .15s; text-align:center; }
    .btn-booking { background:rgba(37,99,235,.08); color:var(--blue); border-color:rgba(37,99,235,.2); }
    .btn-booking:hover { background:rgba(37,99,235,.15); }
    .btn-email   { background:rgba(16,185,129,.08); color:#059669; border-color:rgba(16,185,129,.2); }
    .btn-email:hover { background:rgba(16,185,129,.15); }

    .booking-history-item { display:flex; align-items:center; justify-content:space-between; padding:10px 12px; background:var(--bg); border-radius:10px; margin-bottom:8px; }
    .booking-history-hotel { font-size:13px; font-weight:600; color:var(--dark); }
    .booking-history-date  { font-size:11px; color:var(--gray); margin-top:2px; }
    .booking-history-price { font-size:13px; font-weight:700; color:var(--dark); text-align:right; }

    .badge            { display:inline-flex; align-items:center; padding:2px 8px; border-radius:99px; font-size:10px; font-weight:600; }
    .badge-confirmed  { background:#DCFCE7; color:#15803D; }
    .badge-pending    { background:#FEF9C3; color:#A16207; }
    .badge-cancelled  { background:#FEE2E2; color:#B91C1C; }
    .badge-completed  { background:#EFF6FF; color:#2563EB; }
    .badge-expired    { background:#F3F4F6; color:#6B7280; }

    .section-label { font-size:13px; font-weight:600; color:var(--dark); margin-bottom:10px; }
    .empty-state   { text-align:center; padding:40px 20px; color:var(--gray); font-size:13px; }
</style>
@endpush

@section('content')

{{-- Header --}}
<div class="page-header">
    <div>
        <div class="page-title">Quản lý khách hàng</div>
        <div class="page-sub">Xem và theo dõi thông tin khách hàng trong hệ thống</div>
    </div>
</div>

{{-- Stats --}}
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Tổng khách hàng</div>
        <div class="stat-value" id="kpi-total">—</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Khách mới tháng này</div>
        <div class="stat-value" id="kpi-new">—</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Tổng booking</div>
        <div class="stat-value" id="kpi-booking">—</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Tổng doanh thu</div>
        <div class="stat-value" id="kpi-revenue">—</div>
    </div>
</div>

{{-- Main cols --}}
<div class="main-cols">

    {{-- LEFT: Table --}}
    <div>
        <div class="toolbar">
            <div class="toolbar-search">
                <span class="toolbar-search-icon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </span>
                <input type="text" id="customerSearch" placeholder="Tìm tên, email...">
            </div>
        </div>

        <div class="table-card">
            <div class="table-head-row">
                <span class="table-title">Danh sách khách hàng</span>
                <span class="table-count" id="tableCount">—</span>
            </div>
            <table class="cust-table">
                <thead>
                    <tr>
                        <th>Khách hàng</th>
                        <th>Liên hệ</th>
                        <th>Booking</th>
                        <th>Đánh giá</th>
                        <th>Chi tiêu</th>
                        <th>Tham gia</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="customerTbody">
                    <tr><td colspan="7" class="empty-state">Đang tải...</td></tr>
                </tbody>
            </table>
            <div class="pagination" id="paginationWrap" style="display:none;">
                <span class="pagination-info" id="paginationInfo"></span>
                <div class="pagination-btns" id="paginationBtns"></div>
            </div>
        </div>
    </div>

    {{-- RIGHT: Sidebar --}}
    <div>
        {{-- Doanh thu theo tháng --}}
        <div class="sidebar-card">
            <div class="sidebar-title">Doanh thu theo tháng</div>
            <div class="mini-chart" id="miniChart">
                <div class="empty-state" style="padding:10px;width:100%;">Đang tải...</div>
            </div>
        </div>

        {{-- Top 5 khách chi tiêu nhiều nhất --}}
        <div class="sidebar-card">
            <div class="sidebar-title">Top khách hàng</div>
            <div id="topCustomersList">
                <div class="empty-state" style="padding:10px;">Đang tải...</div>
            </div>
        </div>
    </div>
</div>

{{-- Modal --}}
<div class="modal-backdrop" id="customerModal" onclick="if(event.target===this)closeModal()">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Chi tiết khách hàng</h3>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <div class="modal-body" id="modalBody">
            <div class="empty-state">Đang tải...</div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    let currentPage   = 1;
    let currentSearch = '';
    let currentCustomerId = null;

    document.addEventListener('DOMContentLoaded', function () {
        loadCustomers();
        loadSidebar();

        let t;
        document.getElementById('customerSearch').addEventListener('input', function () {
            clearTimeout(t);
            t = setTimeout(() => { currentSearch = this.value; currentPage = 1; loadCustomers(); }, 400);
        });
    });

    // ── Table ──────────────────────────────────────────────

    async function loadCustomers() {
        try {
            const params = new URLSearchParams({ page: currentPage, per_page: 15 });
            if (currentSearch) params.append('search', currentSearch);
            const res  = await adminApi(`/customers?${params}`);
            const data = await res.json();
            renderKPI(data.stats);
            renderTable(data.customers);
            renderPagination(data.customers);
        } catch (e) {
            document.getElementById('customerTbody').innerHTML =
                `<tr><td colspan="7" class="empty-state">Không thể tải dữ liệu</td></tr>`;
        }
    }

    function renderKPI(s) {
        setEl('kpi-total',   s.total);
        setEl('kpi-new',     s.new_month);
        setEl('kpi-booking', s.total_booking);
        setEl('kpi-revenue', fmtMoney(s.total_revenue));
    }

    function renderTable(data) {
        const tbody = document.getElementById('customerTbody');
        const rows  = data.data ?? [];
        setEl('tableCount', `${data.total ?? 0} khách hàng`);

        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="7" class="empty-state">Không tìm thấy khách hàng</td></tr>`;
            return;
        }

        tbody.innerHTML = rows.map(c => {
            const spent    = c.total_spent ? fmtMoney(c.total_spent) : '—';
            const joined   = fmtDate(c.created_at);
            return `
            <tr onclick="openDetail(${c.id})">
                <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        ${renderAvatar(c.name, c.avatar_url)}
                        <div>
                            <div style="font-weight:600;color:var(--dark);">${escHtml(c.name)}</div>
                            <div style="font-size:11px;color:var(--gray);">${escHtml(c.email ?? '—')}</div>
                        </div>
                    </div>
                </td>
                <td style="color:var(--gray);">${escHtml(c.phone ?? '—')}</td>
                <td style="text-align:center;font-weight:600;">${c.bookings_count}</td>
                <td style="text-align:center;color:var(--gray);">${c.reviews_count}</td>
                <td style="font-weight:600;color:var(--blue);">${spent}</td>
                <td style="color:var(--gray);white-space:nowrap;">${joined}</td>
                <td>
                    <button class="btn-action btn-detail" onclick="event.stopPropagation();openDetail(${c.id})">Chi tiết</button>
                </td>
            </tr>`;
        }).join('');
    }

    function renderPagination(data) {
        const wrap = document.getElementById('paginationWrap');
        const last = data.last_page ?? 1;
        if (last <= 1) { wrap.style.display = 'none'; return; }
        wrap.style.display = 'flex';
        setEl('paginationInfo', `Trang ${currentPage}/${last} — ${data.total} khách hàng`);

        const btns = [];
        btns.push(`<button class="pg-btn" onclick="changePage(${currentPage-1})" ${currentPage===1?'disabled':''}>‹</button>`);
        for (let i = Math.max(1, currentPage-2); i <= Math.min(last, currentPage+2); i++) {
            btns.push(`<button class="pg-btn ${i===currentPage?'active':''}" onclick="changePage(${i})">${i}</button>`);
        }
        btns.push(`<button class="pg-btn" onclick="changePage(${currentPage+1})" ${currentPage===last?'disabled':''}>›</button>`);
        document.getElementById('paginationBtns').innerHTML = btns.join('');
    }

    function changePage(p) {
        if (p < 1) return;
        currentPage = p;
        loadCustomers();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // ── Sidebar ────────────────────────────────────────────

    async function loadSidebar() {
        try {
            // Dùng /revenue cho chart tháng + top customers từ /customers?per_page=5&sort=spent
            const [revRes, custRes] = await Promise.all([
                adminApi('/revenue'),
                adminApi('/customers?per_page=5&sort=spent'),
            ]);
            const revData  = await revRes.json();
            const custData = await custRes.json();
            renderMiniChart(revData.monthly_revenue ?? []);
            renderTopCustomers(custData.customers?.data ?? []);
        } catch (e) {}
    }

    function renderMiniChart(monthly) {
        const chart = document.getElementById('miniChart');
        if (!monthly.length) { chart.innerHTML = '<div class="empty-state" style="padding:10px;width:100%;">Chưa có dữ liệu</div>'; return; }

        const max = Math.max(...monthly.map(m => +m.total), 1);
        const monthNames = ['T1','T2','T3','T4','T5','T6','T7','T8','T9','T10','T11','T12'];

        chart.innerHTML = monthly.map(m => {
            const pct = Math.round((+m.total / max) * 100);
            return `
            <div class="mini-bar-wrap" title="${monthNames[(m.month-1)??0]}: ${fmtMoney(m.total)}">
                <div class="mini-bar" style="height:${pct}%;"></div>
                <div class="mini-bar-label">${monthNames[(m.month-1)??0]}</div>
            </div>`;
        }).join('');
    }

    function renderTopCustomers(customers) {
        const el = document.getElementById('topCustomersList');
        if (!customers.length) { el.innerHTML = '<div class="empty-state" style="padding:10px;">Chưa có dữ liệu</div>'; return; }

        // Sắp xếp theo chi tiêu giảm dần
        const sorted = [...customers].sort((a,b) => (+b.total_spent||0) - (+a.total_spent||0));
        el.innerHTML = sorted.map((c, i) => `
            <div class="top-cust-item" onclick="openDetail(${c.id})" style="cursor:pointer;">
                <div class="top-cust-rank">${i+1}</div>
                ${renderAvatar(c.name, c.avatar_url, 28)}
                <div class="top-cust-info">
                    <div class="top-cust-name">${escHtml(c.name)}</div>
                    <div class="top-cust-sub">${c.bookings_count} booking</div>
                </div>
                <div class="top-cust-spent">${c.total_spent ? fmtMoney(c.total_spent) : '—'}</div>
            </div>
        `).join('');
    }

    // ── Modal ──────────────────────────────────────────────

    async function openDetail(id) {
        currentCustomerId = id;
        document.getElementById('customerModal').classList.add('open');
        document.getElementById('modalBody').innerHTML =
            `<div class="empty-state">Đang tải...</div>`;

        try {
            const res  = await adminApi(`/customers/${id}`);
            const data = await res.json();
            const c    = data.customer;

            document.getElementById('modalBody').innerHTML = `
                <div class="cust-detail-head">
                    ${renderAvatar(c.name, c.avatar_url, 52)}
                    <div>
                        <div class="cust-detail-name">${escHtml(c.name)}</div>
                        <div class="cust-detail-sub">${escHtml(c.email ?? '—')}</div>
                        <div class="cust-detail-sub">${escHtml(c.phone ?? '—')}</div>
                    </div>
                </div>

                <div class="cust-stats-row">
                    <div class="cust-stat-box">
                        <div class="cust-stat-num">${c.bookings_count}</div>
                        <div class="cust-stat-lbl">Tổng booking</div>
                    </div>
                    <div class="cust-stat-box">
                        <div class="cust-stat-num">${c.reviews_count}</div>
                        <div class="cust-stat-lbl">Đánh giá</div>
                    </div>
                    <div class="cust-stat-box">
                        <div class="cust-stat-num" style="font-size:14px;">${c.total_spent ? fmtMoney(c.total_spent) : '0đ'}</div>
                        <div class="cust-stat-lbl">Tổng chi tiêu</div>
                    </div>
                </div>

                <div class="modal-actions-row">
                    <button class="btn-modal-action btn-booking" onclick="goToBookings(${id})">
                        📋 Xem booking
                    </button>
                    <button class="btn-modal-action btn-email" onclick="sendEmail('${escHtml(c.email ?? '')}')">
                        ✉️ Liên hệ qua Email
                    </button>
                </div>

                <div class="section-label">Lịch sử đặt phòng (10 gần nhất)</div>
                <div style="max-height:280px;overflow-y:auto;">
                    ${data.bookings.length ? data.bookings.map(b => `
                        <div class="booking-history-item">
                            <div>
                                <div class="booking-history-hotel">${b.room_numbers ? 'Phòng ' + escHtml(b.room_numbers) : '#BK-' + b.id} · ${escHtml(b.hotel ?? '—')}</div>
                                <div class="booking-history-date">${b.check_in} → ${b.check_out}</div>
                            </div>
                            <div class="booking-history-price">
                                ${Number(b.total_price).toLocaleString('vi-VN')}đ
                                <div style="margin-top:3px;">
                                    <span class="badge badge-${b.status}">${statusLabel(b.status)}</span>
                                </div>
                            </div>
                        </div>
                    `).join('') : '<div class="empty-state">Chưa có booking nào</div>'}
                </div>
                <div style="font-size:11px;color:var(--gray);margin-top:16px;">Tham gia: ${fmtDate(c.created_at)}</div>
            `;
        } catch(e) {
            document.getElementById('modalBody').innerHTML =
                `<div class="empty-state" style="color:#ef4444;">Không tải được dữ liệu</div>`;
        }
    }

    function closeModal() {
        document.getElementById('customerModal').classList.remove('open');
        currentCustomerId = null;
    }

    // Chuyển sang trang bookings filter theo khách hàng
    function goToBookings(customerId) {
        closeModal();
        // Nếu admin bookings page có hỗ trợ query param customer_id thì dùng
        window.location.href = `{{ route('admin.bookings') }}?customer_id=${customerId}`;
    }

    function sendEmail(email) {
        const gmailUrl = `https://mail.google.com/mail/?view=cm&fs=1&to=${encodeURIComponent(email)}`;
        window.open(gmailUrl, '_blank');
    }

    // ── Helpers ────────────────────────────────────────────

    function initials2(name) {
        return (name ?? 'KH').split(' ').map(w => w[0]).slice(-2).join('').toUpperCase();
    }

    function fmtMoney(n) {
        n = +n;
        if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'M đ';
        return n.toLocaleString('vi-VN') + 'đ';
    }

    function fmtDate(str) {
        if (!str) return '—';
        return new Date(str).toLocaleDateString('vi-VN', { day:'2-digit', month:'2-digit', year:'numeric' });
    }

    function statusLabel(s) {
        return { confirmed:'Đã xác nhận', pending:'Chờ duyệt', cancelled:'Đã huỷ', completed:'Hoàn thành', expired:'Hết hạn' }[s] ?? s;
    }

    function escHtml(s) {
        if (!s) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function setEl(id, val) {
        const el = document.getElementById(id);
        if (el) el.textContent = val ?? '—';
    }

    function renderAvatar(name, avatarUrl, size = 34) {
        if (avatarUrl) {
            return `<img src="${avatarUrl}" 
                        style="width:${size}px;height:${size}px;border-radius:50%;object-fit:cover;flex-shrink:0;"
                        onerror="this.outerHTML='<div class=\\'cust-avatar\\'>${initials2(name)}</div>'">`;
        }
        return `<div class="cust-avatar" style="width:${size}px;height:${size}px;">${initials2(name)}</div>`;
    }
</script>
@endpush