{{-- resources/views/admin/bookings.blade.php --}}
@extends('admin.layouts.admin')

@section('title', 'Quản lý Bookings')

@push('styles')
<style>
    /* ── Page header ── */
    .page-header {
        display: flex; align-items: flex-start; justify-content: space-between;
        margin-bottom: 20px;
    }
    .page-title {
        font-family: Arial, sans-serif;
        font-size: 22px; font-weight: 700; color: var(--dark);
    }
    .page-sub { font-size: 13px; color: var(--gray); margin-top: 3px; }

    /* ── Stat cards ── */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px; margin-bottom: 20px;
    }
    .stat-card {
        background: #fff;
        border: 1px solid rgba(135,206,250,0.2);
        border-radius: 16px; padding: 18px 20px;
        transition: all 0.2s; position: relative; overflow: hidden;
        cursor: pointer;
    }
    .stat-card::before {
        content: ''; position: absolute;
        top: 0; left: 0; right: 0; height: 3px;
        background: linear-gradient(90deg, var(--sky), var(--sky-dark));
        opacity: 0; transition: opacity 0.2s;
    }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 12px 24px -8px rgba(135,206,250,0.25); }
    .stat-card:hover::before { opacity: 1; }
    .stat-card.active::before { opacity: 1; }
    .stat-card.active { box-shadow: 0 8px 20px -6px rgba(135,206,250,0.3); }
    .stat-icon {
        width: 36px; height: 36px; border-radius: 10px;
        background: rgba(135,206,250,0.12);
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 14px; color: var(--sky-dark);
    }
    .stat-label { font-size: 12px; color: var(--gray); font-weight: 500; margin-bottom: 6px; }
    .stat-value { font-size: 26px; font-weight: 700; color: var(--dark); line-height: 1; margin-bottom: 4px; }
    .stat-sub { font-size: 12px; color: var(--gray); }

    /* ── Toolbar ── */
    .toolbar {
        background: #fff;
        border: 1px solid rgba(135,206,250,0.2);
        border-radius: 14px;
        padding: 14px 18px;
        display: flex; align-items: center; gap: 12px;
        margin-bottom: 16px; flex-wrap: wrap;
    }
    .toolbar-search {
        flex: 1; min-width: 200px;
        position: relative;
    }
    .toolbar-search input {
        width: 100%; padding: 8px 12px 8px 34px;
        border: 1px solid rgba(135,206,250,0.3);
        border-radius: 10px; font-size: 13px;
        background: var(--bg); color: var(--dark);
        outline: none; transition: all 0.2s;
        box-sizing: border-box;
    }
    .toolbar-search input:focus {
        border-color: var(--sky); background: #fff;
        box-shadow: 0 0 0 3px rgba(135,206,250,0.15);
    }
    .toolbar-search-icon {
        position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
        color: var(--gray); pointer-events: none;
    }
    .toolbar select {
        padding: 8px 12px; border: 1px solid rgba(135,206,250,0.3);
        border-radius: 10px; font-size: 13px;
        background: var(--bg); color: var(--dark);
        outline: none; cursor: pointer; transition: all 0.2s;
    }
    .toolbar select:focus { border-color: var(--sky); background: #fff; }
    .toolbar-btn {
        padding: 8px 14px;
        background: rgba(135,206,250,0.12);
        border: 1px solid rgba(135,206,250,0.25);
        border-radius: 10px; font-size: 13px;
        color: var(--sky-dark); font-weight: 500;
        cursor: pointer; transition: all 0.15s;
        white-space: nowrap;
    }
    .toolbar-btn:hover { background: rgba(135,206,250,0.22); }
    .toolbar-btn.active {
        background: var(--sky-dark); color: var(--dark);
        border-color: var(--sky-dark);
    }

    /* ── Table card ── */
    .table-card {
        background: #fff;
        border: 1px solid rgba(135,206,250,0.2);
        border-radius: 16px; overflow: hidden;
    }
    .table-head-row {
        padding: 16px 20px;
        border-bottom: 1px solid rgba(135,206,250,0.12);
        display: flex; align-items: center; justify-content: space-between;
    }
    .table-title { font-size: 14px; font-weight: 600; color: var(--dark); }
    .table-count {
        font-size: 12px; color: var(--gray);
        background: var(--bg); padding: 3px 10px; border-radius: 99px;
    }

    .bk-table { width: 100%; border-collapse: collapse; }
    .bk-table th {
        font-size: 11px; font-weight: 600; color: var(--gray);
        text-transform: uppercase; letter-spacing: 0.4px;
        padding: 10px 16px; text-align: left;
        border-bottom: 1px solid rgba(135,206,250,0.12);
        white-space: nowrap; background: var(--bg);
    }
    .bk-table td {
        font-size: 13px; padding: 13px 16px;
        border-bottom: 1px solid rgba(135,206,250,0.07);
        color: var(--dark); vertical-align: middle;
    }
    .bk-table tr:last-child td { border-bottom: none; }
    .bk-table tbody tr { transition: background 0.12s; cursor: pointer; }
    .bk-table tbody tr:hover td { background: rgba(135,206,250,0.04); }

    /* ── Badges ── */
    .badge {
        display: inline-flex; align-items: center;
        padding: 3px 9px; border-radius: 99px;
        font-size: 11px; font-weight: 600; white-space: nowrap;
    }
    .badge-confirmed { background: #DCFCE7; color: #15803D; }
    .badge-pending   { background: #FEF9C3; color: #A16207; }
    .badge-cancelled { background: #FEE2E2; color: #B91C1C; }
    .badge-completed { background: rgba(135,206,250,0.15); color: var(--sky-dark); }

    /* ── Action buttons ── */
    .btn-action {
        padding: 5px 11px; border-radius: 8px;
        font-size: 11px; font-weight: 600; cursor: pointer;
        border: 1px solid transparent; transition: all 0.15s;
        white-space: nowrap;
    }
    .btn-confirm  { background: rgba(22,163,74,0.1);  color: #16A34A; border-color: rgba(22,163,74,0.2); }
    .btn-confirm:hover  { background: rgba(22,163,74,0.2); }
    .btn-complete { background: rgba(135,206,250,0.15); color: var(--sky-dark); border-color: rgba(135,206,250,0.3); }
    .btn-complete:hover { background: rgba(135,206,250,0.28); }
    .btn-cancel   { background: rgba(220,38,38,0.08); color: #DC2626; border-color: rgba(220,38,38,0.15); }
    .btn-cancel:hover   { background: rgba(220,38,38,0.15); }

    /* ── Pagination ── */
    .pagination {
        display: flex; align-items: center; justify-content: space-between;
        padding: 14px 18px;
        border-top: 1px solid rgba(135,206,250,0.1);
    }
    .pagination-info { font-size: 13px; color: var(--gray); }
    .pagination-btns { display: flex; gap: 6px; }
    .pg-btn {
        width: 32px; height: 32px; border-radius: 8px;
        border: 1px solid rgba(135,206,250,0.25);
        background: #fff; color: var(--dark); font-size: 13px;
        cursor: pointer; transition: all 0.15s;
        display: flex; align-items: center; justify-content: center;
    }
    .pg-btn:hover { background: rgba(135,206,250,0.12); border-color: var(--sky); }
    .pg-btn.active { background: var(--sky-dark); border-color: var(--sky-dark); color: var(--dark); font-weight: 700; }
    .pg-btn:disabled { opacity: 0.35; cursor: default; }

    /* ── Modal ── */
    .modal-overlay {
        position: fixed; inset: 0;
        background: rgba(0,0,0,0.35); backdrop-filter: blur(4px);
        display: flex; align-items: center; justify-content: center;
        z-index: 1000; opacity: 0; pointer-events: none;
        transition: opacity 0.2s;
    }
    .modal-overlay.open { opacity: 1; pointer-events: all; }
    .modal {
        background: #fff; border-radius: 20px;
        width: 560px; max-width: 95vw; max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 32px 64px -12px rgba(0,0,0,0.18);
        transform: translateY(12px); transition: transform 0.2s;
    }
    .modal-overlay.open .modal { transform: translateY(0); }
    .modal-head {
        padding: 20px 24px 16px;
        border-bottom: 1px solid rgba(135,206,250,0.15);
        display: flex; align-items: center; justify-content: space-between;
    }
    .modal-title { font-family: Arial, sans-serif; font-size: 17px; font-weight: 700; color: var(--dark); }
    .modal-close {
        width: 28px; height: 28px; border-radius: 8px;
        background: var(--bg); border: none; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        color: var(--gray); transition: all 0.15s;
    }
    .modal-close:hover { background: #fee2e2; color: #dc2626; }
    .modal-body { padding: 20px 24px; }
    .modal-footer {
        padding: 16px 24px;
        border-top: 1px solid rgba(135,206,250,0.12);
        display: flex; gap: 8px; justify-content: flex-end;
    }

    /* ── Detail rows ── */
    .detail-section { margin-bottom: 18px; }
    .detail-section-title {
        font-size: 11px; font-weight: 600; color: var(--gray);
        text-transform: uppercase; letter-spacing: 0.5px;
        margin-bottom: 10px;
    }
    .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .detail-item { }
    .detail-label { font-size: 11px; color: var(--gray); margin-bottom: 2px; }
    .detail-value { font-size: 13px; color: var(--dark); font-weight: 500; }

    /* ── Status selector in modal ── */
    .status-btns { display: flex; gap: 8px; flex-wrap: wrap; }
    .status-opt {
        padding: 7px 14px; border-radius: 10px;
        font-size: 12px; font-weight: 600; cursor: pointer;
        border: 2px solid transparent; transition: all 0.15s;
    }
    .status-opt[data-s="pending"]   { background: #FEF9C3; color: #A16207; }
    .status-opt[data-s="confirmed"] { background: #DCFCE7; color: #15803D; }
    .status-opt[data-s="completed"] { background: rgba(135,206,250,0.15); color: var(--sky-dark); }
    .status-opt[data-s="cancelled"] { background: #FEE2E2; color: #B91C1C; }
    .status-opt.selected { border-color: currentColor; transform: scale(1.04); }

    /* ── Loading / empty ── */
    .skeleton {
        background: linear-gradient(90deg, rgba(135,206,250,0.1) 25%, rgba(135,206,250,0.2) 50%, rgba(135,206,250,0.1) 75%);
        background-size: 200% 100%;
        animation: shimmer 1.2s infinite;
        border-radius: 6px;
    }
    @keyframes shimmer { to { background-position: -200% 0; } }
    .empty-state {
        padding: 48px 20px; text-align: center; color: var(--gray);
    }
    .empty-icon { font-size: 36px; margin-bottom: 10px; }
    .empty-text { font-size: 14px; }

    /* ── Toast ── */
    .bk-toast {
        position: fixed; bottom: 24px; right: 24px;
        background: var(--dark); color: #fff;
        padding: 11px 18px; border-radius: 12px;
        font-size: 13px; font-weight: 500;
        transform: translateY(20px); opacity: 0;
        transition: all 0.3s; z-index: 9999;
        box-shadow: 0 8px 24px -4px rgba(0,0,0,0.25);
    }
    .bk-toast.show { transform: translateY(0); opacity: 1; }

    /* ── Guest info inline ── */
    .guest-info { display: flex; flex-direction: column; gap: 1px; }
    .guest-name { font-weight: 600; color: var(--dark); }
    .guest-email { font-size: 11px; color: var(--gray); }
</style>
@endpush

@section('content')

{{-- Page header --}}
<div class="page-header">
    <div>
        <div class="page-title">Quản lý Bookings</div>
        <div class="page-sub">Xem và cập nhật trạng thái đặt phòng</div>
    </div>
    <button class="btn-primary" onclick="loadBookings()">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.2">
            <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 11-2.12-9.36L23 10"/>
        </svg>
        Làm mới
    </button>
</div>

{{-- Stat cards --}}
<div class="stats-grid" id="statCards">
    @foreach([['all','Tất cả','📋'],['pending','Chờ duyệt','⏳'],['confirmed','Đã xác nhận','✅'],['cancelled','Đã huỷ','❌']] as [$s,$l,$ic])
    <div class="stat-card {{ $s === 'all' ? 'active' : '' }}" onclick="filterByStatus('{{ $s }}')" data-filter="{{ $s }}">
        <div class="stat-icon" style="font-size:18px">{{ $ic }}</div>
        <div class="stat-label">{{ $l }}</div>
        <div class="stat-value js-count-{{ $s }}">—</div>
        <div class="stat-sub">bookings</div>
    </div>
    @endforeach
</div>

{{-- Toolbar --}}
<div class="toolbar">
    <div class="toolbar-search">
        <span class="toolbar-search-icon">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
            </svg>
        </span>
        <input type="text" id="searchInput" placeholder="Tìm tên khách, email..." oninput="debounceSearch()">
    </div>

    <select id="statusFilter" onchange="loadBookings()">
        <option value="">Tất cả trạng thái</option>
        <option value="pending">Chờ duyệt</option>
        <option value="confirmed">Đã xác nhận</option>
        <option value="completed">Hoàn thành</option>
        <option value="cancelled">Đã huỷ</option>
    </select>

    <input type="date" id="dateFrom" onchange="loadBookings()"
        style="padding:8px 12px;border:1px solid rgba(135,206,250,0.3);border-radius:10px;font-size:13px;background:var(--bg);color:var(--dark);outline:none;">
    <input type="date" id="dateTo" onchange="loadBookings()"
        style="padding:8px 12px;border:1px solid rgba(135,206,250,0.3);border-radius:10px;font-size:13px;background:var(--bg);color:var(--dark);outline:none;">

    <button class="toolbar-btn" onclick="clearFilters()">Xoá bộ lọc</button>
</div>

{{-- Table --}}
<div class="table-card">
    <div class="table-head-row">
        <div class="table-title">Danh sách đặt phòng</div>
        <span class="table-count" id="tableCount">Đang tải...</span>
    </div>

    <div style="overflow-x:auto;">
        <table class="bk-table">
            <thead>
                <tr>
                    <th>#ID</th>
                    <th>Khách hàng</th>
                    <th>Khách sạn</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Tổng tiền</th>
                    <th>Trạng thái</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody id="bookingTableBody">
                <tr><td colspan="8"><div style="padding:28px">
                    <div class="skeleton" style="height:14px;width:100%;margin-bottom:12px"></div>
                    <div class="skeleton" style="height:14px;width:80%"></div>
                </div></td></tr>
            </tbody>
        </table>
    </div>

    <div class="pagination" id="paginationWrap" style="display:none">
        <div class="pagination-info" id="paginationInfo"></div>
        <div class="pagination-btns" id="paginationBtns"></div>
    </div>
</div>

{{-- Detail / Edit Modal --}}
<div class="modal-overlay" id="bookingModal" onclick="closeModal(event)">
    <div class="modal">
        <div class="modal-head">
            <div class="modal-title" id="modalTitle">Chi tiết Booking</div>
            <button class="modal-close" onclick="closeModalDirect()">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body" id="modalBody">
            {{-- filled by JS --}}
        </div>
        <div class="modal-footer">
            <button class="btn-action btn-cancel" onclick="closeModalDirect()">Đóng</button>
            <button class="btn-action btn-confirm" id="modalSaveBtn" onclick="saveStatus()">Lưu thay đổi</button>
        </div>
    </div>
</div>

{{-- Toast --}}
<div class="bk-toast" id="bkToast"></div>

@endsection

@push('scripts')
<script>
    // ═══════════════════════════════════════════
    // STATE
    // ═══════════════════════════════════════════
    let currentPage    = 1;
    let currentBooking = null;
    let selectedStatus = null;
    let searchTimer    = null;
    let lastMeta       = null;

    // ═══════════════════════════════════════════
    // BOOT
    // ═══════════════════════════════════════════
    document.addEventListener('DOMContentLoaded', () => {
        loadBookings();
        loadCounts();
    });

    // ═══════════════════════════════════════════
    // LOAD COUNTS cho stat cards
    // ═══════════════════════════════════════════
    async function loadCounts() {
        const statuses = ['all', 'pending', 'confirmed', 'cancelled'];
        for (const s of statuses) {
            try {
                const params = s === 'all' ? '' : `?status=${s}&per_page=1`;
                const res = await adminApi('/bookings' + params);
                if (!res.ok) continue;
                const data = await res.json();
                const el = document.querySelector(`.js-count-${s}`);
                if (el) el.textContent = (data.total ?? data.meta?.total ?? '—').toLocaleString('vi-VN');
            } catch(_) {}
        }
    }

    // ═══════════════════════════════════════════
    // LOAD BOOKINGS
    // ═══════════════════════════════════════════
    async function loadBookings(page = 1) {
        currentPage = page;
        showTableLoading();

        const params = new URLSearchParams();
        params.set('per_page', 15);
        params.set('page', page);

        const search  = document.getElementById('searchInput').value.trim();
        const status  = document.getElementById('statusFilter').value;
        const dateFrom= document.getElementById('dateFrom').value;
        const dateTo  = document.getElementById('dateTo').value;

        if (search)   params.set('search', search);
        if (status)   params.set('status', status);
        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo)   params.set('date_to', dateTo);

        try {
            const res = await adminApi('/bookings?' + params.toString());
            if (!res.ok) throw new Error('API error');
            const data = await res.json();

            const items = data.data ?? [];
            lastMeta    = data;

            renderTable(items);
            renderPagination(data);
            updateTableCount(data);
        } catch(e) {
            renderError();
        }
    }

    // ═══════════════════════════════════════════
    // RENDER TABLE
    // ═══════════════════════════════════════════
    function renderTable(bookings) {
        const tbody = document.getElementById('bookingTableBody');

        if (!bookings.length) {
            tbody.innerHTML = `
                <tr><td colspan="8">
                    <div class="empty-state">
                        <div class="empty-icon">📋</div>
                        <div class="empty-text">Không có booking nào phù hợp</div>
                    </div>
                </td></tr>`;
            return;
        }

        tbody.innerHTML = bookings.map(b => `
            <tr onclick="openModal(${JSON.stringify(b).replace(/"/g, '&quot;')})">
                <td><span style="font-family:monospace;font-size:12px;color:var(--gray)">#${b.id}</span></td>
                <td>
                    <div class="guest-info">
                        <span class="guest-name">${escHtml(b.customer?.name ?? b.guest_name ?? '—')}</span>
                        <span class="guest-email">${escHtml(b.customer?.email ?? '')}</span>
                    </div>
                </td>
                <td style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                    ${escHtml(b.hotel?.name ?? '—')}
                </td>
                <td style="white-space:nowrap">${formatDate(b.check_in)}</td>
                <td style="white-space:nowrap">${formatDate(b.check_out)}</td>
                <td style="font-weight:600">${formatMoney(b.total_price)}</td>
                <td>${badgeHtml(b.status)}</td>
                <td onclick="event.stopPropagation()">
                    ${actionBtns(b)}
                </td>
            </tr>
        `).join('');
    }

    function badgeHtml(status) {
        const map = {
            confirmed: ['badge-confirmed','Đã xác nhận'],
            pending:   ['badge-pending',  'Chờ duyệt'],
            cancelled: ['badge-cancelled','Đã huỷ'],
            completed: ['badge-completed','Hoàn thành'],
        };
        const [cls, label] = map[status] ?? ['badge-pending', status];
        return `<span class="badge ${cls}">${label}</span>`;
    }

    function actionBtns(b) {
        const btns = [];
        if (b.status === 'pending')
            btns.push(`<button class="btn-action btn-confirm" onclick="quickStatus(${b.id},'confirmed',this)">Xác nhận</button>`);
        if (b.status === 'confirmed')
            btns.push(`<button class="btn-action btn-complete" onclick="quickStatus(${b.id},'completed',this)">Hoàn thành</button>`);
        if (['pending','confirmed'].includes(b.status))
            btns.push(`<button class="btn-action btn-cancel" onclick="quickStatus(${b.id},'cancelled',this)">Huỷ</button>`);
        return `<div style="display:flex;gap:5px;flex-wrap:wrap">${btns.join('')}</div>`;
    }

    // ═══════════════════════════════════════════
    // QUICK STATUS UPDATE (từ bảng)
    // ═══════════════════════════════════════════
    async function quickStatus(id, status, btn) {
        btn.disabled = true;
        btn.textContent = '...';
        try {
            const res = await adminApi(`/bookings/${id}/status`, {
                method: 'PUT',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ status }),
            });
            if (!res.ok) throw new Error();
            showToast(`✓ Đã cập nhật booking #${id} → ${statusLabel(status)}`);
            loadBookings(currentPage);
            loadCounts();
        } catch(_) {
            showToast('❌ Cập nhật thất bại');
            btn.disabled = false;
            btn.textContent = btn.dataset.label ?? 'Thử lại';
        }
    }

    // ═══════════════════════════════════════════
    // MODAL — xem chi tiết + đổi trạng thái
    // ═══════════════════════════════════════════
    function openModal(booking) {
        currentBooking = booking;
        document.getElementById('modalTitle').textContent =
            `Booking ${booking.room_numbers ? 'Phòng ' + booking.room_numbers : '#' + booking.id}`;

        const pay = booking.payment ?? {};
        const nights = booking.check_in && booking.check_out
            ? Math.round((new Date(booking.check_out) - new Date(booking.check_in)) / 86400000)
            : '—';

        document.getElementById('modalBody').innerHTML = `
            <div class="detail-section">
                <div class="detail-section-title">Thông tin khách hàng</div>
                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="detail-label">Tên khách</div>
                        <div class="detail-value">${escHtml(booking.customer?.name ?? booking.guest_name ?? '—')}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Email</div>
                        <div class="detail-value">${escHtml(booking.customer?.email ?? '—')}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Số điện thoại</div>
                        <div class="detail-value">${escHtml(booking.customer?.phone ?? '—')}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Đặt lúc</div>
                        <div class="detail-value">${formatDateTime(booking.created_at)}</div>
                    </div>
                </div>
            </div>

            <div class="detail-section">
                <div class="detail-section-title">Thông tin đặt phòng</div>
                <div class="detail-grid">
                    <div class="detail-item">
                        <div class="detail-label">Khách sạn</div>
                        <div class="detail-value">${escHtml(booking.hotel?.name ?? '—')}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Số đêm</div>
                        <div class="detail-value">${nights} đêm</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Check-in</div>
                        <div class="detail-value">${formatDate(booking.check_in)}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Check-out</div>
                        <div class="detail-value">${formatDate(booking.check_out)}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Tổng tiền</div>
                        <div class="detail-value" style="font-weight:700;color:var(--sky-dark)">${formatMoney(booking.total_price)}</div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Thanh toán</div>
                        <div class="detail-value">${pay.payment_method ?? '—'} · ${pay.payment_status ?? '—'}</div>
                    </div>
                </div>
            </div>

            <div class="detail-section">
                <div class="detail-section-title">Cập nhật trạng thái</div>
                <div class="status-btns" id="statusBtns">
                    ${['pending','confirmed','completed','cancelled'].map(s => `
                        <div class="status-opt ${s === booking.status ? 'selected' : ''}"
                            data-s="${s}" onclick="selectStatus('${s}')">
                            ${statusLabel(s)}
                        </div>
                    `).join('')}
                </div>
            </div>
        `;

        selectedStatus = booking.status;
        document.getElementById('bookingModal').classList.add('open');
    }

    function selectStatus(s) {
        selectedStatus = s;
        document.querySelectorAll('.status-opt').forEach(el => {
            el.classList.toggle('selected', el.dataset.s === s);
        });
    }

    async function saveStatus() {
        if (!currentBooking || selectedStatus === currentBooking.status) {
            closeModalDirect();
            return;
        }
        const btn = document.getElementById('modalSaveBtn');
        btn.disabled = true; btn.textContent = 'Đang lưu...';
        try {
            const res = await adminApi(`/bookings/${currentBooking.id}/status`, {
                method: 'PUT',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ status: selectedStatus }),
            });
            if (!res.ok) throw new Error();
            showToast(`✓ Booking #${currentBooking.id} → ${statusLabel(selectedStatus)}`);
            closeModalDirect();
            loadBookings(currentPage);
            loadCounts();
        } catch(_) {
            showToast('❌ Cập nhật thất bại');
        } finally {
            btn.disabled = false; btn.textContent = 'Lưu thay đổi';
        }
    }

    function closeModal(e) {
        if (e.target === document.getElementById('bookingModal')) closeModalDirect();
    }
    function closeModalDirect() {
        document.getElementById('bookingModal').classList.remove('open');
        currentBooking = null;
    }

    // ═══════════════════════════════════════════
    // FILTER BY STAT CARD
    // ═══════════════════════════════════════════
    function filterByStatus(status) {
        document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active'));
        document.querySelector(`[data-filter="${status}"]`)?.classList.add('active');

        const sel = document.getElementById('statusFilter');
        sel.value = status === 'all' ? '' : status;
        loadBookings(1);
    }

    // ═══════════════════════════════════════════
    // PAGINATION
    // ═══════════════════════════════════════════
    function renderPagination(data) {
        const wrap = document.getElementById('paginationWrap');
        const total      = data.total ?? 0;
        const perPage    = data.per_page ?? 15;
        const lastPage   = data.last_page ?? Math.ceil(total / perPage);
        const current    = data.current_page ?? currentPage;
        const from       = data.from ?? 0;
        const to         = data.to ?? 0;

        if (total <= perPage) { wrap.style.display = 'none'; return; }
        wrap.style.display = 'flex';

        document.getElementById('paginationInfo').textContent =
            `Hiển thị ${from}–${to} / ${total} booking`;

        const btns = [];
        btns.push(`<button class="pg-btn" onclick="loadBookings(${current-1})" ${current===1?'disabled':''}>‹</button>`);
        const range = pageRange(current, lastPage);
        range.forEach(p => {
            if (p === '...') btns.push(`<button class="pg-btn" disabled>…</button>`);
            else btns.push(`<button class="pg-btn ${p===current?'active':''}" onclick="loadBookings(${p})">${p}</button>`);
        });
        btns.push(`<button class="pg-btn" onclick="loadBookings(${current+1})" ${current===lastPage?'disabled':''}>›</button>`);
        document.getElementById('paginationBtns').innerHTML = btns.join('');
    }

    function pageRange(current, last) {
        if (last <= 7) return Array.from({length:last},(_,i)=>i+1);
        const pages = [1];
        if (current > 3) pages.push('...');
        for (let p = Math.max(2,current-1); p <= Math.min(last-1,current+1); p++) pages.push(p);
        if (current < last - 2) pages.push('...');
        pages.push(last);
        return pages;
    }

    // ═══════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════
    function updateTableCount(data) {
        const total = data.total ?? (data.data?.length ?? 0);
        document.getElementById('tableCount').textContent = `${total.toLocaleString('vi-VN')} booking`;
    }

    function showTableLoading() {
        document.getElementById('bookingTableBody').innerHTML = `
            <tr><td colspan="8"><div style="padding:20px">
                ${[1,2,3,4,5].map(()=>`
                    <div class="skeleton" style="height:13px;width:100%;margin-bottom:10px"></div>
                `).join('')}
            </div></td></tr>`;
        document.getElementById('paginationWrap').style.display = 'none';
    }

    function renderError() {
        document.getElementById('bookingTableBody').innerHTML = `
            <tr><td colspan="8">
                <div class="empty-state">
                    <div class="empty-icon">⚠️</div>
                    <div class="empty-text">Không thể tải dữ liệu. <a href="#" onclick="loadBookings()">Thử lại</a></div>
                </div>
            </td></tr>`;
    }

    function clearFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('statusFilter').value = '';
        document.getElementById('dateFrom').value = '';
        document.getElementById('dateTo').value = '';
        filterByStatus('all');
    }

    function debounceSearch() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadBookings(1), 420);
    }

    function statusLabel(s) {
        return {pending:'Chờ duyệt',confirmed:'Đã xác nhận',completed:'Hoàn thành',cancelled:'Đã huỷ'}[s] ?? s;
    }

    function formatDate(str) {
        if (!str) return '—';
        return new Date(str).toLocaleDateString('vi-VN', {day:'2-digit',month:'2-digit',year:'numeric'});
    }

    function formatDateTime(str) {
        if (!str) return '—';
        return new Date(str).toLocaleString('vi-VN', {day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});
    }

    function formatMoney(amount) {
        if (!amount) return '0đ';
        if (amount >= 1_000_000_000) return (amount/1_000_000_000).toFixed(1)+'B';
        if (amount >= 1_000_000)     return (amount/1_000_000).toFixed(1)+'M';
        if (amount >= 1_000)         return (amount/1_000).toFixed(0)+'K';
        return Number(amount).toLocaleString('vi-VN')+'đ';
    }

    function escHtml(s) {
        if (!s) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function showToast(msg) {
        if (typeof adminToast === 'function') { adminToast(msg); return; }
        const el = document.getElementById('bkToast');
        el.textContent = msg; el.classList.add('show');
        setTimeout(() => el.classList.remove('show'), 2800);
    }
</script>
@endpush