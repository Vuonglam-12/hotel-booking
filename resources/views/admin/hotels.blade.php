{{-- resources/views/admin/hotels.blade.php --}}
@extends('admin.layouts.admin')

@section('title', 'Quản lý Khách sạn')

@push('styles')
<style>
    .page-header {
        display: flex; align-items: flex-start; justify-content: space-between;
        margin-bottom: 20px;
    }
    .page-title {
        font-family: 'Nunito Sans', system-ui, sans-serif;
        font-size: 22px; font-weight: 700; color: var(--dark);
    }
    .page-sub { font-size: 13px; color: var(--gray); margin-top: 3px; }

    .btn-primary {
        background: linear-gradient(135deg, var(--sky), var(--sky-dark));
        color: var(--dark); font-weight: 700; font-size: 13px;
        border: none; border-radius: 10px;
        padding: 9px 16px; cursor: pointer;
        display: flex; align-items: center; gap: 6px;
        transition: all 0.2s; white-space: nowrap; text-decoration: none;
    }
    .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 8px 16px -4px rgba(135,206,250,0.45); }

    /* ── Stats ── */
    .stats-grid {
        display: grid; grid-template-columns: repeat(4, minmax(0,1fr));
        gap: 14px; margin-bottom: 20px;
    }
    .stat-card {
        background: #fff; border: 1px solid rgba(135,206,250,0.2);
        border-radius: 16px; padding: 18px 20px;
        transition: all 0.2s; position: relative; overflow: hidden;
    }
    .stat-card::before {
        content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
        background: linear-gradient(90deg, var(--sky), var(--sky-dark));
        opacity: 0; transition: opacity 0.2s;
    }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 12px 24px -8px rgba(135,206,250,0.25); }
    .stat-card:hover::before { opacity: 1; }
    .stat-icon {
        width: 36px; height: 36px; border-radius: 10px;
        background: rgba(135,206,250,0.12);
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 14px; color: var(--sky-dark); font-size: 18px;
    }
    .stat-label { font-size: 12px; color: var(--gray); font-weight: 500; margin-bottom: 6px; }
    .stat-value { font-size: 26px; font-weight: 700; color: var(--dark); line-height: 1; }

    /* ── Toolbar ── */
    .toolbar {
        background: #fff; border: 1px solid rgba(135,206,250,0.2);
        border-radius: 14px; padding: 14px 18px;
        display: flex; align-items: center; gap: 12px;
        margin-bottom: 16px; flex-wrap: wrap;
    }
    .toolbar-search { flex: 1; min-width: 200px; position: relative; }
    .toolbar-search input {
        width: 100%; padding: 8px 12px 8px 34px;
        border: 1px solid rgba(135,206,250,0.3); border-radius: 10px;
        font-size: 13px; background: var(--bg); color: var(--dark);
        outline: none; transition: all 0.2s; box-sizing: border-box;
    }
    .toolbar-search input:focus { border-color: var(--sky); background: #fff; box-shadow: 0 0 0 3px rgba(135,206,250,0.15); }
    .search-icon-abs { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--gray); pointer-events: none; }
    .toolbar select, .toolbar input[type=date] {
        padding: 8px 12px; border: 1px solid rgba(135,206,250,0.3);
        border-radius: 10px; font-size: 13px; background: var(--bg);
        color: var(--dark); outline: none; cursor: pointer;
    }
    .toolbar select:focus { border-color: var(--sky); }
    .toolbar-btn {
        padding: 8px 14px; background: rgba(135,206,250,0.12);
        border: 1px solid rgba(135,206,250,0.25); border-radius: 10px;
        font-size: 13px; color: var(--sky-dark); font-weight: 500;
        cursor: pointer; transition: all 0.15s; white-space: nowrap;
    }
    .toolbar-btn:hover { background: rgba(135,206,250,0.22); }

    /* ── Table ── */
    .table-card {
        background: #fff; border: 1px solid rgba(135,206,250,0.2);
        border-radius: 16px; overflow: hidden;
    }
    .table-head-row {
        padding: 16px 20px; border-bottom: 1px solid rgba(135,206,250,0.12);
        display: flex; align-items: center; justify-content: space-between;
    }
    .table-title { font-size: 14px; font-weight: 600; color: var(--dark); }
    .table-count { font-size: 12px; color: var(--gray); background: var(--bg); padding: 3px 10px; border-radius: 99px; }

    .ht-table { width: 100%; border-collapse: collapse; }
    .ht-table th {
        font-size: 11px; font-weight: 600; color: var(--gray);
        text-transform: uppercase; letter-spacing: 0.4px;
        padding: 10px 16px; text-align: left;
        border-bottom: 1px solid rgba(135,206,250,0.12);
        white-space: nowrap; background: var(--bg);
    }
    .ht-table td {
        font-size: 13px; padding: 13px 16px;
        border-bottom: 1px solid rgba(135,206,250,0.07);
        color: var(--dark); vertical-align: middle;
    }
    .ht-table tr:last-child td { border-bottom: none; }
    .ht-table tbody tr { transition: background 0.12s; }
    .ht-table tbody tr:hover td { background: rgba(135,206,250,0.04); }

    /* Hotel thumb */
    .hotel-thumb {
        width: 48px; height: 36px; border-radius: 8px;
        object-fit: cover; display: block;
        background: rgba(135,206,250,0.15);
    }
    .hotel-info { display: flex; align-items: center; gap: 12px; }
    .hotel-name { font-weight: 600; color: var(--dark); }
    .hotel-addr { font-size: 11px; color: var(--gray); margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px; }

    /* Stars */
    .stars { color: #F59E0B; font-size: 12px; letter-spacing: 1px; }

    /* Badges */
    .badge { display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 99px; font-size: 11px; font-weight: 600; }
    .badge-active   { background: #DCFCE7; color: #15803D; }
    .badge-inactive { background: #FEF9C3; color: #A16207; }
    .badge-closed   { background: #FEE2E2; color: #B91C1C; }

    /* Action buttons */
    .btn-act {
        padding: 5px 11px; border-radius: 8px; font-size: 11px; font-weight: 600;
        cursor: pointer; border: 1px solid transparent; transition: all 0.15s; white-space: nowrap;
    }
    .btn-edit    { background: rgba(135,206,250,0.15); color: var(--sky-dark); border-color: rgba(135,206,250,0.3); }
    .btn-edit:hover { background: rgba(135,206,250,0.28); }
    .btn-toggle-on  { background: rgba(220,38,38,0.08); color: #DC2626; border-color: rgba(220,38,38,0.15); }
    .btn-toggle-on:hover  { background: rgba(220,38,38,0.15); }
    .btn-toggle-off { background: rgba(22,163,74,0.1); color: #16A34A; border-color: rgba(22,163,74,0.2); }
    .btn-toggle-off:hover { background: rgba(22,163,74,0.2); }

    /* Room button */
    .btn-room {
        background: rgba(16,185,129,0.1); color: #059669; border-color: rgba(16,185,129,0.25);
    }
    .btn-room:hover { background: rgba(16,185,129,0.2); }

    /* Pagination */
    .pagination {
        display: flex; align-items: center; justify-content: space-between;
        padding: 14px 18px; border-top: 1px solid rgba(135,206,250,0.1);
    }
    .pagination-info { font-size: 13px; color: var(--gray); }
    .pagination-btns { display: flex; gap: 6px; }
    .pg-btn {
        width: 32px; height: 32px; border-radius: 8px;
        border: 1px solid rgba(135,206,250,0.25); background: #fff;
        color: var(--dark); font-size: 13px; cursor: pointer;
        transition: all 0.15s; display: flex; align-items: center; justify-content: center;
    }
    .pg-btn:hover { background: rgba(135,206,250,0.12); border-color: var(--sky); }
    .pg-btn.active { background: var(--sky-dark); border-color: var(--sky-dark); font-weight: 700; }
    .pg-btn:disabled { opacity: 0.35; cursor: default; }

    /* ── Modal ── */
    .modal-overlay {
        position: fixed; inset: 0;
        background: rgba(0,0,0,0.4); backdrop-filter: blur(4px);
        display: flex; align-items: center; justify-content: center;
        z-index: 1000; opacity: 0; pointer-events: none; transition: opacity 0.2s;
    }
    .modal-overlay.open { opacity: 1; pointer-events: all; }
    .modal {
        background: #fff; border-radius: 20px;
        width: 680px; max-width: 96vw; max-height: 92vh; overflow-y: auto;
        box-shadow: 0 32px 64px -12px rgba(0,0,0,0.2);
        transform: translateY(14px); transition: transform 0.22s;
    }
    .modal-overlay.open .modal { transform: translateY(0); }
    .modal-head {
        padding: 20px 24px 16px; border-bottom: 1px solid rgba(135,206,250,0.15);
        display: flex; align-items: center; justify-content: space-between;
        position: sticky; top: 0; background: #fff; z-index: 2;
    }
    .modal-title { font-family: 'Nunito Sans', system-ui, sans-serif; font-size: 17px; font-weight: 700; color: var(--dark); }
    .modal-close {
        width: 28px; height: 28px; border-radius: 8px; background: var(--bg);
        border: none; cursor: pointer; display: flex; align-items: center;
        justify-content: center; color: var(--gray); transition: all 0.15s;
    }
    .modal-close:hover { background: #fee2e2; color: #dc2626; }
    .modal-body { padding: 22px 24px; }
    .modal-footer {
        padding: 16px 24px; border-top: 1px solid rgba(135,206,250,0.12);
        display: flex; gap: 8px; justify-content: flex-end;
        position: sticky; bottom: 0; background: #fff;
    }
    .btn-cancel-modal {
        padding: 9px 18px; border-radius: 10px; font-size: 13px; font-weight: 600;
        background: var(--bg); border: 1px solid rgba(135,206,250,0.25);
        color: var(--gray); cursor: pointer; transition: all 0.15s;
    }
    .btn-cancel-modal:hover { background: #fee2e2; color: #dc2626; }
    .btn-save {
        padding: 9px 20px; border-radius: 10px; font-size: 13px; font-weight: 700;
        background: linear-gradient(135deg, var(--sky), var(--sky-dark));
        border: none; color: var(--dark); cursor: pointer; transition: all 0.2s;
    }
    .btn-save:hover { transform: translateY(-1px); box-shadow: 0 8px 16px -4px rgba(135,206,250,0.4); }
    .btn-save:disabled { opacity: 0.55; transform: none; cursor: default; }

    /* ── Form fields ── */
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .form-group { display: flex; flex-direction: column; gap: 5px; }
    .form-group.full { grid-column: 1 / -1; }
    .form-label { font-size: 12px; font-weight: 600; color: var(--dark); }
    .form-label span { color: #dc2626; margin-left: 2px; }
    .form-input, .form-select, .form-textarea {
        padding: 9px 12px; border: 1px solid rgba(135,206,250,0.3);
        border-radius: 10px; font-size: 13px; color: var(--dark);
        background: var(--bg); outline: none; transition: all 0.2s;
        width: 100%; box-sizing: border-box;
    }
    .form-input:focus, .form-select:focus, .form-textarea:focus {
        border-color: var(--sky); background: #fff;
        box-shadow: 0 0 0 3px rgba(135,206,250,0.15);
    }
    .form-textarea { resize: vertical; min-height: 80px; }
    .form-error { font-size: 11px; color: #dc2626; display: none; }
    .form-group.has-error .form-input,
    .form-group.has-error .form-select { border-color: #dc2626; }
    .form-group.has-error .form-error { display: block; }

    /* Section divider */
    .form-section { margin-bottom: 18px; }
    .form-section-title {
        font-size: 11px; font-weight: 600; color: var(--gray);
        text-transform: uppercase; letter-spacing: 0.5px;
        margin-bottom: 12px; padding-bottom: 8px;
        border-bottom: 1px solid rgba(135,206,250,0.12);
    }

    /* Amenities checkboxes */
    .amenity-grid {
        display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;
    }
    .amenity-item {
        display: flex; align-items: center; gap: 7px;
        padding: 7px 10px; border: 1px solid rgba(135,206,250,0.25);
        border-radius: 9px; cursor: pointer; transition: all 0.15s;
        font-size: 12px; color: var(--dark);
    }
    .amenity-item:hover { border-color: var(--sky); background: rgba(135,206,250,0.06); }
    .amenity-item input[type=checkbox] { accent-color: var(--sky-dark); width: 14px; height: 14px; }
    .amenity-item.checked { border-color: var(--sky-dark); background: rgba(135,206,250,0.1); font-weight: 500; }

    /* Hotel preview img */
    .preview-img {
        width: 100%; height: 140px; object-fit: cover;
        border-radius: 10px; border: 1px solid rgba(135,206,250,0.2);
        display: none; margin-top: 8px;
    }

    /* Skeleton */
    .skeleton {
        background: linear-gradient(90deg, rgba(135,206,250,0.1) 25%, rgba(135,206,250,0.2) 50%, rgba(135,206,250,0.1) 75%);
        background-size: 200% 100%; animation: shimmer 1.2s infinite; border-radius: 6px;
    }
    @keyframes shimmer { to { background-position: -200% 0; } }
    .empty-state { padding: 48px 20px; text-align: center; color: var(--gray); }
    .empty-icon { font-size: 36px; margin-bottom: 10px; }

    /* Toast */
    .ht-toast {
        position: fixed; bottom: 24px; right: 24px;
        background: var(--dark); color: #fff;
        padding: 11px 18px; border-radius: 12px; font-size: 13px; font-weight: 500;
        transform: translateY(20px); opacity: 0; transition: all 0.3s; z-index: 9999;
        box-shadow: 0 8px 24px -4px rgba(0,0,0,0.25);
    }
    .ht-toast.show { transform: translateY(0); opacity: 1; }

    /* ── Room Type Modal Styles ── */
    .rt-modal-overlay {
        position: fixed; inset: 0;
        background: rgba(0,0,0,0.45); backdrop-filter: blur(4px);
        display: none; align-items: center; justify-content: center;
        z-index: 1100;
    }
    .rt-modal-overlay.open { display: flex; }
    .rt-modal {
        background: #fff; border-radius: 20px;
        width: 520px; max-width: 96vw; max-height: 90vh; overflow-y: auto;
        box-shadow: 0 32px 64px -12px rgba(0,0,0,0.2);
    }
    .rt-modal .modal-header {
        padding: 18px 24px;
        border-bottom: 1px solid rgba(135,206,250,0.15);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .rt-modal .modal-header h3 {
        font-family: 'Nunito Sans', system-ui, sans-serif;
        font-size: 17px;
        font-weight: 700;
        color: var(--dark);
        margin: 0;
    }
    .rt-modal .modal-header button {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        background: var(--bg);
        border: none;
        cursor: pointer;
        font-size: 18px;
        transition: all 0.15s;
    }
    .rt-modal .modal-header button:hover {
        background: #fee2e2;
        color: #dc2626;
    }
    .rt-modal .modal-body {
        padding: 22px 24px;
    }
    .form-row {
        margin-bottom: 16px;
    }
    .form-row label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: var(--dark);
        margin-bottom: 5px;
    }
    .form-row input, .form-row select, .form-row textarea {
        width: 100%;
        padding: 9px 12px;
        border: 1px solid rgba(135,206,250,0.3);
        border-radius: 10px;
        font-size: 13px;
        background: var(--bg);
        transition: all 0.2s;
        box-sizing: border-box;
    }
    .form-row input:focus, .form-row select:focus, .form-row textarea:focus {
        border-color: var(--sky);
        background: #fff;
        box-shadow: 0 0 0 3px rgba(135,206,250,0.15);
        outline: none;
    }
    .form-row.two-col {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    .rt-modal .modal-footer {
        padding: 16px 24px;
        border-top: 1px solid rgba(135,206,250,0.12);
        display: flex;
        gap: 8px;
        justify-content: flex-end;
        background: #fff;
        border-radius: 0 0 20px 20px;
    }
    .btn-secondary {
        padding: 9px 18px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 600;
        background: var(--bg);
        border: 1px solid rgba(135,206,250,0.25);
        color: var(--gray);
        cursor: pointer;
        transition: all 0.15s;
    }
    .btn-secondary:hover {
        background: #fee2e2;
        color: #dc2626;
    }
    .rt-modal .btn-primary {
        background: linear-gradient(135deg, var(--sky), var(--sky-dark));
        padding: 9px 20px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 700;
        border: none;
        color: var(--dark);
        cursor: pointer;
    }
    .rt-modal .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 8px 16px -4px rgba(135,206,250,0.4);
    }
    .rt-modal .btn-primary:disabled {
        opacity: 0.55;
        transform: none;
        cursor: default;
    }
</style>
@endpush

@section('content')

{{-- Page header --}}
<div class="page-header">
    <div>
        <div class="page-title">Quản lý Khách sạn</div>
        <div class="page-sub">Thêm, sửa và quản lý trạng thái khách sạn</div>
    </div>
    <button class="btn-primary" onclick="openAddModal()">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Thêm khách sạn
    </button>
</div>

{{-- Stat cards --}}
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">🏨</div>
        <div class="stat-label">Tổng khách sạn</div>
        <div class="stat-value js-stat-total">—</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">✅</div>
        <div class="stat-label">Đang hoạt động</div>
        <div class="stat-value js-stat-active">—</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⏸️</div>
        <div class="stat-label">Tạm ngưng</div>
        <div class="stat-value js-stat-inactive">—</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⭐</div>
        <div class="stat-label">Đánh giá TB</div>
        <div class="stat-value js-stat-rating">—</div>
    </div>
</div>

{{-- Toolbar --}}
<div class="toolbar">
    <div class="toolbar-search">
        <span class="search-icon-abs">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/>
            </svg>
        </span>
        <input type="text" id="searchInput" placeholder="Tìm tên khách sạn, địa chỉ..." oninput="debounceSearch()">
    </div>
    <select id="statusFilter" onchange="loadHotels()">
        <option value="">Tất cả trạng thái</option>
        <option value="active">Đang hoạt động</option>
        <option value="inactive">Tạm ngưng</option>
        <option value="closed">Đóng cửa</option>
    </select>
    <select id="starFilter" onchange="loadHotels()">
        <option value="">Tất cả sao</option>
        <option value="5">5 Sao</option>
        <option value="4">4 Sao</option>
        <option value="3">3 Sao</option>
        <option value="2">2 Sao</option>
    </select>
    <button class="toolbar-btn" onclick="clearFilters()">Xoá bộ lọc</button>
</div>

{{-- Table --}}
<div class="table-card">
    <div class="table-head-row">
        <div class="table-title">Danh sách khách sạn</div>
        <span class="table-count" id="tableCount">Đang tải...</span>
    </div>
    <div style="overflow-x:auto">
        <table class="ht-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Khách sạn</th>
                    <th>Địa chỉ</th>
                    <th>Sao</th>
                    <th>Đánh giá</th>
                    <th>Phòng</th>
                    <th>Trạng thái</th>
                    <th>Hành động</th>
                </tr>
            </thead>
            <tbody id="hotelTableBody">
                <tr><td colspan="8"><div style="padding:20px">
                    <div class="skeleton" style="height:13px;width:100%;margin-bottom:10px"></div>
                    <div class="skeleton" style="height:13px;width:80%;margin-bottom:10px"></div>
                    <div class="skeleton" style="height:13px;width:90%"></div>
                </div></td></tr>
            </tbody>
        </table>
    </div>
    <div class="pagination" id="paginationWrap" style="display:none">
        <div class="pagination-info" id="paginationInfo"></div>
        <div class="pagination-btns" id="paginationBtns"></div>
    </div>
</div>

{{-- ══════════════════════════════════════════════
     MODAL: Thêm / Sửa Khách sạn
══════════════════════════════════════════════ --}}
<div class="modal-overlay" id="hotelModal" onclick="closeModalBg(event)">
    <div class="modal">
        <div class="modal-head">
            <div class="modal-title" id="modalTitle">Thêm khách sạn mới</div>
            <button class="modal-close" onclick="closeModal()">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        <div class="modal-body">

            {{-- 1. Thông tin cơ bản --}}
            <div class="form-section">
                <div class="form-section-title">Thông tin cơ bản</div>
                <div class="form-grid">
                    <div class="form-group full" id="fg-name">
                        <label class="form-label">Tên khách sạn <span>*</span></label>
                        <input type="text" class="form-input" id="f-name" placeholder="VD: Sofitel Legend Metropole">
                        <div class="form-error" id="err-name">Vui lòng nhập tên khách sạn</div>
                    </div>
                    <div class="form-group" id="fg-location">
                        <label class="form-label">Tỉnh / Thành phố <span>*</span></label>
                        <select class="form-select" id="f-location">
                            <option value="">— Chọn địa điểm —</option>
                        </select>
                        <div class="form-error" id="err-location">Vui lòng chọn địa điểm</div>
                    </div>
                    <div class="form-group" id="fg-star">
                        <label class="form-label">Số sao <span>*</span></label>
                        <select class="form-select" id="f-star">
                            <option value="">— Chọn hạng sao —</option>
                            <option value="1">1 Sao</option>
                            <option value="2">2 Sao</option>
                            <option value="3">3 Sao</option>
                            <option value="4">4 Sao</option>
                            <option value="5">5 Sao</option>
                        </select>
                        <div class="form-error" id="err-star">Vui lòng chọn hạng sao</div>
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Địa chỉ đầy đủ <span>*</span></label>
                        <input type="text" class="form-input" id="f-address" placeholder="Số nhà, đường, quận/huyện...">
                        <div class="form-error" id="err-address">Vui lòng nhập địa chỉ</div>
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Mô tả</label>
                        <textarea class="form-textarea" id="f-description" placeholder="Mô tả ngắn về khách sạn..."></textarea>
                    </div>
                </div>
            </div>

            {{-- 2. Liên hệ --}}
            <div class="form-section">
                <div class="form-section-title">Thông tin liên hệ</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Số điện thoại</label>
                        <input type="text" class="form-input" id="f-phone" placeholder="028 xxxx xxxx">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-input" id="f-email" placeholder="hotel@example.com">
                    </div>
                </div>
            </div>

            {{-- 3. Thời gian check-in/out --}}
            <div class="form-section">
                <div class="form-section-title">Giờ check-in / check-out</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Check-in từ</label>
                        <input type="time" class="form-input" id="f-checkin" value="14:00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Check-out trước</label>
                        <input type="time" class="form-input" id="f-checkout" value="12:00">
                    </div>
                </div>
            </div>

            {{-- 4. Toạ độ --}}
            <div class="form-section">
                <div class="form-section-title">Toạ độ bản đồ <span style="font-weight:400;color:var(--gray);font-size:11px">(tuỳ chọn)</span></div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Latitude</label>
                        <input type="number" step="any" class="form-input" id="f-lat" placeholder="21.0245">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Longitude</label>
                        <input type="number" step="any" class="form-input" id="f-lng" placeholder="105.8562">
                    </div>
                </div>
            </div>

            {{-- 5. Tiện ích --}}
            <div class="form-section">
                <div class="form-section-title">Tiện ích khách sạn</div>
                <div class="amenity-grid" id="amenityGrid">
                    <!-- filled by JS -->
                    <div class="skeleton" style="height:34px;border-radius:9px"></div>
                    <div class="skeleton" style="height:34px;border-radius:9px"></div>
                    <div class="skeleton" style="height:34px;border-radius:9px"></div>
                </div>
            </div>

            {{-- 6. Trạng thái --}}
            <div class="form-section">
                <div class="form-section-title">Trạng thái</div>
                <select class="form-select" id="f-status" style="max-width:240px">
                    <option value="active">Đang hoạt động</option>
                    <option value="inactive">Tạm ngưng</option>
                    <option value="closed">Đóng cửa</option>
                </select>
            </div>

            {{-- Preview ảnh --}}
            <div class="form-section" id="previewSection" style="display:none">
                <div class="form-section-title">Ảnh đại diện (tự động)</div>
                <img id="previewImg" class="preview-img" alt="Preview">
                <div style="font-size:11px;color:var(--gray);margin-top:6px">
                    Ảnh được lấy tự động theo ID khách sạn sau khi tạo
                </div>
            </div>

        </div>

        <div class="modal-footer">
            <button class="btn-cancel-modal" onclick="closeModal()">Huỷ</button>
            <button class="btn-save" id="saveBtn" onclick="saveHotel()">
                <span id="saveBtnText">Tạo khách sạn</span>
            </button>
        </div>
    </div>
</div>

<!-- Modal thêm loại phòng (Room Type Modal) -->
<div id="roomTypeModal" class="rt-modal-overlay" onclick="closeRoomTypeModalOnBg(event)">
    <div class="rt-modal">
        <div class="modal-header">
            <h3 id="roomTypeModalTitle">Thêm loại phòng</h3>
            <button onclick="closeRoomTypeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="rtHotelId">
            <div class="form-row">
                <label>Tên loại phòng *</label>
                <input type="text" id="rtName" placeholder="VD: Deluxe, Superior...">
            </div>
            <div class="form-row two-col">
                <div>
                    <label>Giá/đêm (VNĐ) *</label>
                    <input type="number" id="rtPrice" placeholder="500000" min="0">
                </div>
                <div>
                    <label>Sức chứa *</label>
                    <input type="number" id="rtCapacity" value="2" min="1" max="10">
                </div>
            </div>
            <div class="form-row two-col">
                <div>
                    <label>Loại giường</label>
                    <select id="rtBedType">
                        <option value="">-- Chọn --</option>
                        <option value="single">Single</option>
                        <option value="double">Double</option>
                        <option value="twin">Twin</option>
                        <option value="king">King</option>
                        <option value="queen">Queen</option>
                    </select>
                </div>
                <div>
                    <label>Tầng bắt đầu</label>
                    <input type="number" id="rtFloor" value="1" min="1" max="99">
                </div>
            </div>
            <div class="form-row">
                <label>Số lượng phòng tạo *</label>
                <input type="number" id="rtCount" value="5" min="1" max="100">
                <small style="color:#999; display:block; margin-top:4px;">Hệ thống tự tạo số phòng (101, 102...)</small>
            </div>
            <div class="form-row">
                <label>Mô tả</label>
                <textarea id="rtDesc" rows="2" placeholder="Mô tả ngắn..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button onclick="closeRoomTypeModal()" class="btn-secondary">Huỷ</button>
            <button onclick="saveRoomType()" class="btn-primary" id="rtSaveBtn">
                Tạo loại phòng
            </button>
        </div>
    </div>
</div>

<div class="ht-toast" id="htToast"></div>

@endsection

@push('scripts')
<script>
// ═══════════════════════════════════════════
// STATE
// ═══════════════════════════════════════════
let currentPage  = 1;
let editingId    = null;    // null = thêm mới, số = đang sửa
let searchTimer  = null;
let allLocations = [];

const AMENITY_LIST = [
    { key: 'wifi',        label: '📶 WiFi miễn phí' },
    { key: 'pool',        label: '🏊 Hồ bơi' },
    { key: 'gym',         label: '🏋️ Phòng gym' },
    { key: 'spa',         label: '💆 Spa & Massage' },
    { key: 'restaurant',  label: '🍽️ Nhà hàng' },
    { key: 'bar',         label: '🍸 Bar / Lounge' },
    { key: 'parking',     label: '🅿️ Bãi đỗ xe' },
    { key: 'airport',     label: '✈️ Đưa đón sân bay' },
    { key: 'laundry',     label: '👕 Giặt ủi' },
    { key: 'breakfast',   label: '🥐 Bữa sáng' },
    { key: 'meeting',     label: '💼 Phòng họp' },
    { key: 'childcare',   label: '👶 Trông trẻ' },
];

// ═══════════════════════════════════════════
// BOOT
// ═══════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    loadHotels();
    loadLocations();
    renderAmenityGrid([]);
});

// ═══════════════════════════════════════════
// LOAD LOCATIONS (cho select)
// ═══════════════════════════════════════════
async function loadLocations() {
    try {
        const res  = await fetch('/api/locations');
        const data = await res.json();
        allLocations = Array.isArray(data) ? data : (data.data ?? []);

        const sel = document.getElementById('f-location');
        sel.innerHTML = '<option value="">— Chọn địa điểm —</option>' +
            allLocations.map(l => `<option value="${l.id}">${escHtml(l.name)}</option>`).join('');
    } catch(_) {}
}

// ═══════════════════════════════════════════
// LOAD HOTELS
// ═══════════════════════════════════════════
async function loadHotels(page = 1) {
    currentPage = page;
    showLoading();

    const params = new URLSearchParams();
    params.set('per_page', 15);
    params.set('page', page);

    const search = document.getElementById('searchInput').value.trim();
    const status = document.getElementById('statusFilter').value;
    const star   = document.getElementById('starFilter').value;

    if (search) params.set('search', search);
    if (status) params.set('status', status);
    if (star)   params.set('star', star);

    try {
        const res  = await adminApi('/hotels?' + params.toString());
        if (!res.ok) throw new Error();
        const data = await res.json();
        const items = data.data ?? [];

        renderTable(items);
        renderPagination(data);
        updateStats(items, data.total ?? items.length);
        document.getElementById('tableCount').textContent =
            `${(data.total ?? items.length).toLocaleString('vi-VN')} khách sạn`;
    } catch(_) {
        document.getElementById('hotelTableBody').innerHTML = `
            <tr><td colspan="8">
                <div class="empty-state">
                    <div class="empty-icon">⚠️</div>
                    <div style="font-size:14px">Không thể tải dữ liệu. <a href="#" onclick="loadHotels()">Thử lại</a></div>
                </div>
            </td></tr>`;
    }
}

// ═══════════════════════════════════════════
// RENDER TABLE
// ═══════════════════════════════════════════
function renderTable(hotels) {
    const tbody = document.getElementById('hotelTableBody');
    if (!hotels.length) {
        tbody.innerHTML = `<tr><td colspan="8">
            <div class="empty-state">
                <div class="empty-icon">🏨</div>
                <div style="font-size:14px">Không tìm thấy khách sạn nào</div>
            </div></td></tr>`;
        return;
    }

    tbody.innerHTML = hotels.map(h => {
        const statusBadge = {
            active:   '<span class="badge badge-active">Hoạt động</span>',
            inactive: '<span class="badge badge-inactive">Tạm ngưng</span>',
            closed:   '<span class="badge badge-closed">Đóng cửa</span>',
        }[h.status] ?? `<span class="badge badge-inactive">${h.status}</span>`;

        const stars = '★'.repeat(h.star_rating ?? 0) + '☆'.repeat(5 - (h.star_rating ?? 0));
        const roomCount = h.rooms_count ?? h.rooms?.length ?? '—';

        return `<tr>
            <td style="color:var(--gray);font-size:12px;font-family:monospace">#${h.id}</td>
            <td>
                <div class="hotel-info">
                    <img class="hotel-thumb"
                         src="https://picsum.photos/seed/${h.id}/120/90"
                         alt="${escHtml(h.name)}"
                         onerror="this.style.background='rgba(135,206,250,0.2)'">
                    <div>
                        <div class="hotel-name">${escHtml(h.name)}</div>
                        <div class="hotel-addr">${escHtml(h.location?.name ?? '—')}</div>
                    </div>
                </div>
            </td>
            <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--gray);font-size:12px">
                ${escHtml(h.address ?? '—')}
            </td>
            <td><span class="stars">${stars}</span></td>
            <td>
                <span style="font-weight:600">${h.avg_rating ?? '—'}</span>
                <span style="font-size:11px;color:var(--gray)"> /5</span>
            </td>
            <td style="text-align:center">${roomCount}</td>
            <td>${statusBadge}</td>
            <td>
                <div style="display:flex;gap:5px">
                    <button class="btn-act btn-room" onclick="openRoomTypeModal(${h.id})" title="Thêm phòng">🛏 Phòng</button>
                    <button class="btn-act btn-edit" onclick="openEditModal(${JSON.stringify(h).replace(/"/g,'&quot;')})">Sửa</button>
                    ${h.status === 'active' 
                        ? `<button class="btn-act btn-toggle-on" onclick="toggleStatus(${h.id},'inactive',this)">Tắt</button>`
                        : `<button class="btn-act btn-toggle-off" onclick="toggleStatus(${h.id},'active',this)">Bật</button>`
                    }
                </div>
            </td>
        </tr>`;
    }).join('');
}

// ═══════════════════════════════════════════
// STATS (từ danh sách hiện tại)
// ═══════════════════════════════════════════
function updateStats(hotels, total) {
    document.querySelector('.js-stat-total').textContent = total;
    const active   = hotels.filter(h => h.status === 'active').length;
    const inactive = hotels.filter(h => h.status !== 'active').length;
    const avgRating = hotels.length
        ? (hotels.reduce((s,h) => s + parseFloat(h.avg_rating||0), 0) / hotels.length).toFixed(1)
        : '—';
    document.querySelector('.js-stat-active').textContent   = active;
    document.querySelector('.js-stat-inactive').textContent = inactive;
    document.querySelector('.js-stat-rating').textContent   = avgRating;
}

// ═══════════════════════════════════════════
// TOGGLE STATUS
// ═══════════════════════════════════════════
async function toggleStatus(id, newStatus, btn) {
    btn.disabled = true; btn.textContent = '...';
    try {
        const res = await adminApi(`/hotels/${id}/status`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status: newStatus }),
        });
        if (!res.ok) throw new Error();
        showToast(newStatus === 'active' ? '✅ Đã bật khách sạn' : '⏸️ Đã tạm ngưng khách sạn');
        loadHotels(currentPage);
    } catch(_) {
        showToast('❌ Cập nhật thất bại');
        btn.disabled = false;
        btn.textContent = newStatus === 'active' ? 'Bật' : 'Tắt';
    }
}

// ═══════════════════════════════════════════
// AMENITY GRID
// ═══════════════════════════════════════════
function renderAmenityGrid(checked = []) {
    const grid = document.getElementById('amenityGrid');
    grid.innerHTML = AMENITY_LIST.map(a => `
        <label class="amenity-item ${checked.includes(a.key) ? 'checked' : ''}" onclick="toggleAmenityLabel(this)">
            <input type="checkbox" value="${a.key}" ${checked.includes(a.key) ? 'checked' : ''}>
            ${a.label}
        </label>
    `).join('');
}

function toggleAmenityLabel(el) {
    setTimeout(() => {
        const cb = el.querySelector('input[type=checkbox]');
        el.classList.toggle('checked', cb.checked);
    }, 0);
}

function getSelectedAmenities() {
    return [...document.querySelectorAll('#amenityGrid input[type=checkbox]:checked')]
        .map(cb => cb.value);
}

// ═══════════════════════════════════════════
// MODAL — MỞ THÊM MỚI
// ═══════════════════════════════════════════
function openAddModal() {
    editingId = null;
    document.getElementById('modalTitle').textContent = 'Thêm khách sạn mới';
    document.getElementById('saveBtnText').textContent = 'Tạo khách sạn';
    resetForm();
    renderAmenityGrid([]);
    document.getElementById('hotelModal').classList.add('open');
}

// ═══════════════════════════════════════════
// MODAL — MỞ SỬA
// ═══════════════════════════════════════════
function openEditModal(hotel) {
    editingId = hotel.id;
    document.getElementById('modalTitle').textContent = `Sửa: ${hotel.name}`;
    document.getElementById('saveBtnText').textContent = 'Lưu thay đổi';
    resetForm();

    document.getElementById('f-name').value        = hotel.name ?? '';
    document.getElementById('f-location').value    = hotel.location_id ?? '';
    document.getElementById('f-star').value        = hotel.star_rating ?? '';
    document.getElementById('f-address').value     = hotel.address ?? '';
    document.getElementById('f-description').value = hotel.description ?? '';
    document.getElementById('f-phone').value       = hotel.phone ?? '';
    document.getElementById('f-email').value       = hotel.email ?? '';
    document.getElementById('f-checkin').value     = hotel.check_in_time  ? hotel.check_in_time.slice(0,5)  : '14:00';
    document.getElementById('f-checkout').value    = hotel.check_out_time ? hotel.check_out_time.slice(0,5) : '12:00';
    document.getElementById('f-lat').value         = hotel.latitude  ?? '';
    document.getElementById('f-lng').value         = hotel.longitude ?? '';
    document.getElementById('f-status').value      = hotel.status ?? 'active';

    // Amenities
    const existingAmenities = (hotel.amenities ?? []).map(a => a.amenity ?? a.key ?? a);
    renderAmenityGrid(existingAmenities);

    document.getElementById('hotelModal').classList.add('open');
}

// ═══════════════════════════════════════════
// SAVE (thêm mới hoặc sửa)
// ═══════════════════════════════════════════
async function saveHotel() {
    if (!validateForm()) return;

    const btn = document.getElementById('saveBtn');
    const txt = document.getElementById('saveBtnText');
    btn.disabled = true; txt.textContent = 'Đang lưu...';

    const payload = {
        name:           document.getElementById('f-name').value.trim(),
        location_id:    document.getElementById('f-location').value,
        star_rating:    document.getElementById('f-star').value,
        address:        document.getElementById('f-address').value.trim(),
        description:    document.getElementById('f-description').value.trim(),
        phone:          document.getElementById('f-phone').value.trim(),
        email:          document.getElementById('f-email').value.trim(),
        check_in_time:  document.getElementById('f-checkin').value,
        check_out_time: document.getElementById('f-checkout').value,
        latitude:       document.getElementById('f-lat').value  || null,
        longitude:      document.getElementById('f-lng').value  || null,
        status:         document.getElementById('f-status').value,
        amenities:      getSelectedAmenities(),
    };

    try {
        let res;
        let data;
        if (editingId) {
            // Sửa
            res = await adminApi(`/hotels/${editingId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            data = await res.json();
        } else {
            // Thêm mới
            res = await adminApi('/hotels', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            data = await res.json();
        }

        if (!res.ok) {
            throw new Error(data.message ?? 'Lỗi server');
        }

        showToast(editingId ? '✅ Đã cập nhật khách sạn' : '✅ Đã thêm khách sạn mới');
        closeModal();
        loadHotels(editingId ? currentPage : 1);

        // Tự động mở modal thêm phòng nếu vừa tạo mới
        if (!editingId && data.hotel?.id) {
            setTimeout(() => openRoomTypeModal(data.hotel.id), 500);
        }
    } catch(e) {
        showToast('❌ ' + (e.message || 'Lưu thất bại'));
    } finally {
        btn.disabled = false;
        txt.textContent = editingId ? 'Lưu thay đổi' : 'Tạo khách sạn';
    }
}

// ═══════════════════════════════════════════
// ROOM TYPE MODAL
// ═══════════════════════════════════════════
let _currentRoomHotelId = null;

function openRoomTypeModal(hotelId) {
    _currentRoomHotelId = hotelId;
    document.getElementById('rtHotelId').value = hotelId;
    document.getElementById('rtName').value    = '';
    document.getElementById('rtPrice').value   = '';
    document.getElementById('rtCapacity').value= '2';
    document.getElementById('rtBedType').value = '';
    document.getElementById('rtFloor').value   = '1';
    document.getElementById('rtCount').value   = '5';
    document.getElementById('rtDesc').value    = '';
    document.getElementById('roomTypeModalTitle').textContent = 'Thêm loại phòng';
    document.getElementById('roomTypeModal').classList.add('open');
}

function closeRoomTypeModal() {
    document.getElementById('roomTypeModal').classList.remove('open');
}

function closeRoomTypeModalOnBg(event) {
    if (event.target === document.getElementById('roomTypeModal')) {
        closeRoomTypeModal();
    }
}

async function saveRoomType() {
    const hotelId = document.getElementById('rtHotelId').value;
    const name    = document.getElementById('rtName').value.trim();
    const price   = document.getElementById('rtPrice').value;
    const capacity= document.getElementById('rtCapacity').value;
    const bedType = document.getElementById('rtBedType').value;
    const floor   = document.getElementById('rtFloor').value;
    const count   = document.getElementById('rtCount').value;
    const desc    = document.getElementById('rtDesc').value;

    if (!name || !price || !count) {
        showToast('Vui lòng điền đầy đủ thông tin bắt buộc!');
        return;
    }

    const btn = document.getElementById('rtSaveBtn');
    btn.disabled = true;
    btn.textContent = 'Đang tạo...';

    try {
        const res = await adminApi(`/hotels/${hotelId}/room-types`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name,
                base_price:  parseFloat(price),
                capacity:    parseInt(capacity),
                bed_type:    bedType,
                floor_start: parseInt(floor),
                room_count:  parseInt(count),
                description: desc,
            }),
        });
        const data = await res.json();

        if (res.ok) {
            showToast(data.message || 'Tạo phòng thành công!');
            closeRoomTypeModal();
            loadHotels(currentPage);
        } else {
            showToast(data.message || 'Lỗi tạo phòng');
        }
    } catch(e) {
        showToast('Lỗi kết nối');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Tạo loại phòng';
    }
}

// ═══════════════════════════════════════════
// VALIDATE
// ═══════════════════════════════════════════
function validateForm() {
    let ok = true;
    const rules = [
        { id: 'f-name',     fg: 'fg-name',     err: 'err-name',     msg: 'Vui lòng nhập tên' },
        { id: 'f-location', fg: 'fg-location',  err: 'err-location', msg: 'Vui lòng chọn địa điểm' },
        { id: 'f-star',     fg: 'fg-star',      err: 'err-star',     msg: 'Vui lòng chọn hạng sao' },
        { id: 'f-address',  fg: null,           err: 'err-address',  msg: 'Vui lòng nhập địa chỉ' },
    ];
    rules.forEach(r => {
        const val = document.getElementById(r.id)?.value?.trim();
        const hasErr = !val;
        if (r.fg) document.getElementById(r.fg)?.classList.toggle('has-error', hasErr);
        if (hasErr) { ok = false; }
    });
    return ok;
}

// ═══════════════════════════════════════════
// MODAL HELPERS
// ═══════════════════════════════════════════
function resetForm() {
    ['f-name','f-location','f-star','f-address','f-description',
     'f-phone','f-email','f-lat','f-lng'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    document.getElementById('f-checkin').value  = '14:00';
    document.getElementById('f-checkout').value = '12:00';
    document.getElementById('f-status').value   = 'active';
    ['fg-name','fg-location','fg-star'].forEach(id =>
        document.getElementById(id)?.classList.remove('has-error'));
}

function closeModal() { document.getElementById('hotelModal').classList.remove('open'); }
function closeModalBg(e) { if (e.target === document.getElementById('hotelModal')) closeModal(); }

// ═══════════════════════════════════════════
// PAGINATION
// ═══════════════════════════════════════════
function renderPagination(data) {
    const wrap    = document.getElementById('paginationWrap');
    const total   = data.total ?? 0;
    const perPage = data.per_page ?? 15;
    const last    = data.last_page ?? Math.ceil(total / perPage);
    const cur     = data.current_page ?? currentPage;
    const from    = data.from ?? 0;
    const to      = data.to ?? 0;

    if (total <= perPage) { wrap.style.display = 'none'; return; }
    wrap.style.display = 'flex';

    document.getElementById('paginationInfo').textContent = `Hiển thị ${from}–${to} / ${total}`;

    const btns = [];
    btns.push(`<button class="pg-btn" onclick="loadHotels(${cur-1})" ${cur===1?'disabled':''}>‹</button>`);
    pageRange(cur, last).forEach(p => {
        if (p === '...') btns.push(`<button class="pg-btn" disabled>…</button>`);
        else btns.push(`<button class="pg-btn ${p===cur?'active':''}" onclick="loadHotels(${p})">${p}</button>`);
    });
    btns.push(`<button class="pg-btn" onclick="loadHotels(${cur+1})" ${cur===last?'disabled':''}>›</button>`);
    document.getElementById('paginationBtns').innerHTML = btns.join('');
}

function pageRange(cur, last) {
    if (last <= 7) return Array.from({length:last},(_,i)=>i+1);
    const p = [1];
    if (cur > 3) p.push('...');
    for (let i = Math.max(2,cur-1); i <= Math.min(last-1,cur+1); i++) p.push(i);
    if (cur < last-2) p.push('...');
    p.push(last);
    return p;
}

// ═══════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════
function showLoading() {
    document.getElementById('hotelTableBody').innerHTML = `
        <tr><td colspan="8"><div style="padding:20px">
            ${[1,2,3,4,5].map(()=>`<div class="skeleton" style="height:13px;width:100%;margin-bottom:10px"></div>`).join('')}
        </div></td></tr>`;
    document.getElementById('paginationWrap').style.display = 'none';
}

function clearFilters() {
    document.getElementById('searchInput').value  = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('starFilter').value   = '';
    loadHotels(1);
}

function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadHotels(1), 420);
}

function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showToast(msg) {
    if (typeof adminToast === 'function') { adminToast(msg); return; }
    const el = document.getElementById('htToast');
    el.textContent = msg; el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 2800);
}

</script>
@endpush