{{-- ============================================================
    bookings-section.blade.php
    Nâng cấp Booking History: 2 cột, detail panel, review modal
    ============================================================ --}}

<style>
    /* ============================================================
    BOOKING HISTORY - ENHANCED
    ============================================================ */
    :root {
        --font-heading: 'Inter', 'Segoe UI', sans-serif;
        --font-body: 'Inter', system-ui, sans-serif;
        --primary: #3b82f6;
        --primary-dark: #2563eb;
        --primary-light: #93c5fd;
        --accent: #f59e0b;
        --success: #22c55e;
        --danger: #ef4444;
        --warning: #eab308;
        --text-primary: #1e293b;
        --text-secondary: #475569;
        --text-muted: #94a3b8;
        --border: #e2e8f0;
        --border-light: #f1f5f9;
        --radius: 12px;
        --radius-sm: 8px;
        --transition: all 0.2s ease;
    }

    .bookings-main-container {
        display: flex;
        gap: 24px;
        align-items: flex-start;
        position: relative;
    }

    .booking-list-panel {
        flex: 1;
        min-width: 0;
        transition: all 0.3s ease;
    }

    .booking-detail-panel {
        width: 380px;
        flex-shrink: 0;
        background: #fff;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        box-shadow: -4px 0 24px rgba(0,0,0,.06);
        position: sticky;
        top: 100px;
        max-height: calc(100vh - 120px);
        overflow-y: auto;
        display: none; /* hidden by default */
        transform: translateX(1rem);
        opacity: 0;
        transition: opacity 0.3s, transform 0.3s;
    }

    .booking-detail-panel.show {
        display: block;
        transform: translateX(0);
        opacity: 1;
    }

    /* Header */
    .bookings-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }
    .bookings-header h1 {
        font-family: var(--font-heading);
        font-size: 24px;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
    }
    .btn-main {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: #fff;
        border-radius: var(--radius-sm);
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        transition: var(--transition);
        box-shadow: 0 2px 8px rgba(59,130,246,.25);
    }
    .btn-main:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 14px rgba(59,130,246,.35);
    }

    /* Tabs */
    .tabs-bar {
        display: flex;
        gap: 8px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }
    .tab-btn {
        position: relative;
        padding: 9px 20px;
        border-radius: 30px;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-secondary);
        background: #fff;
        border: 1px solid var(--border);
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .tab-btn:hover {
        border-color: var(--primary-light);
        color: var(--primary);
    }
    .tab-btn.active {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
    }
    .tab-btn .badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 22px;
        height: 22px;
        border-radius: 50%;
        background: rgba(255,255,255,.2);
        color: inherit;
        font-size: 11px;
        font-weight: 700;
        padding: 0 6px;
    }
    .tab-btn.active .badge {
        background: rgba(255,255,255,.3);
    }

    /* Booking card (list) */
    .booking-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .booking-card {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 16px;
        display: flex;
        align-items: center;
        gap: 16px;
        transition: var(--transition);
        cursor: pointer;
    }
    .booking-card:hover {
        border-color: var(--primary-light);
        box-shadow: 0 4px 12px rgba(0,0,0,.04);
    }
    .booking-card.selected {
        border-color: var(--primary);
        box-shadow: 0 0 0 2px rgba(59,130,246,.2);
    }
    .booking-card-img {
        width: 72px;
        height: 72px;
        border-radius: var(--radius-sm);
        object-fit: cover;
        flex-shrink: 0;
        background: var(--border-light);
    }
    .booking-card-content {
        flex: 1;
        min-width: 0;
    }
    .booking-card-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 6px;
    }
    .booking-card-code {
        font-size: 12px;
        font-weight: 700;
        color: var(--primary);
        text-transform: uppercase;
    }
    .booking-card-name {
        font-size: 16px;
        font-weight: 700;
        color: var(--text-primary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 4px;
    }
    .booking-card-location {
        font-size: 12.5px;
        color: var(--text-muted);
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .booking-card-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        font-size: 13px;
        color: var(--text-secondary);
        margin-bottom: 10px;
    }
    .booking-card-meta span {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .booking-card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .booking-price {
        font-weight: 700;
        color: var(--text-primary);
        font-size: 15px;
    }
    .booking-actions {
        display: flex;
        gap: 8px;
    }

    /* Status badges */
    .status-badge {
        display: inline-block;
        font-size: 11px;
        font-weight: 700;
        padding: 3px 10px;
        border-radius: 20px;
        text-transform: uppercase;
    }
    .status-pending {
        background: #fff7ed;
        color: #ea580c;
    }
    .status-confirmed {
        background: #e0f2fe;
        color: #0284c7;
    }
    .status-completed {
        background: #dcfce7;
        color: #16a34a;
    }
    .status-cancelled {
        background: #f1f5f9;
        color: #64748b;
    }
    .status-reviewed {
        background: #fef9c3;
        color: #a16207;
    }

    /* Action buttons */
    .btn-action {
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        border: 1px solid transparent;
        cursor: pointer;
        transition: var(--transition);
        background: #fff;
        color: var(--text-secondary);
        border-color: var(--border);
    }
    .btn-action:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    .btn-action.danger {
        color: var(--danger);
        border-color: #fecaca;
    }
    .btn-action.danger:hover {
        background: var(--danger);
        color: #fff;
        border-color: var(--danger);
    }
    .btn-action.primary {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
    }
    .btn-action.primary:hover {
        background: var(--primary-dark);
    }
    .btn-action.review {
        color: var(--accent);
        border-color: #fde68a;
    }
    .btn-action.review:hover {
        background: var(--accent);
        color: #fff;
        border-color: var(--accent);
    }

    .btn-action.invoice {
    color: #0891b2;
    border-color: #bae6fd;
    }
    
    .btn-action.invoice:hover {
        background: #0891b2;
        color: #fff;
        border-color: #0891b2;
    }

    /* Review modal - hotel header */
    .review-modal-hotel {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        background: #f8fafc;
        border-radius: 8px;
        margin-bottom: 16px;
    }
    .review-modal-hotel img {
        width: 48px;
        height: 48px;
        border-radius: 8px;
        object-fit: cover;
    }
    .review-modal-hotel-name {
        font-weight: 700;
        font-size: 14px;
        color: var(--text-primary);
    }
    .review-modal-hotel-code {
        font-size: 12px;
        color: var(--text-muted);
        margin-top: 2px;
    }
    .review-stars-label {
        font-size: 12px;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: .5px;
        margin-bottom: 8px;
    }
    .review-char-count {
        text-align: right;
        font-size: 11px;
        color: var(--text-muted);
        margin-top: -10px;
        margin-bottom: 12px;
    }

    /* Detail panel content */
    .detail-header {
        padding: 20px 24px 16px;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .detail-header h3 {
        font-family: var(--font-heading);
        font-size: 18px;
        font-weight: 700;
        margin: 0;
        color: var(--text-primary);
    }
    .btn-close-detail {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        border: 1px solid var(--border);
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        color: var(--text-muted);
        transition: var(--transition);
    }
    .btn-close-detail:hover {
        background: var(--border-light);
        color: var(--text-primary);
    }
    .detail-body {
        padding: 20px 24px;
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    .detail-img {
        width: 100%;
        height: 180px;
        border-radius: var(--radius-sm);
        object-fit: cover;
        background: var(--border-light);
    }
    .detail-info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    .detail-item {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .detail-label {
        font-size: 12px;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: .5px;
    }
    .detail-value {
        font-weight: 600;
        color: var(--text-primary);
        font-size: 14px;
    }
    .detail-divider {
        border: none;
        border-top: 1px solid var(--border-light);
        margin: 8px 0;
    }
    .detail-total {
        font-weight: 800;
        font-size: 18px;
        color: var(--primary);
        text-align: right;
    }

    /* Review modal overlay */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15,23,42,.6);
        backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        animation: fadeIn .2s ease;
    }
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    .modal-content {
        background: #fff;
        border-radius: var(--radius);
        width: 90%;
        max-width: 480px;
        padding: 28px;
        box-shadow: 0 20px 40px rgba(0,0,0,.15);
        position: relative;
    }
    .modal-title {
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 20px;
        color: var(--text-primary);
    }
    .review-stars {
        display: flex;
        gap: 8px;
        margin-bottom: 16px;
    }
    .review-stars button {
        background: none;
        border: none;
        font-size: 28px;
        color: #d1d5db;
        cursor: pointer;
        transition: var(--transition);
        line-height: 1;
    }
    .review-stars button.active {
        color: var(--accent);
    }
    textarea.review-text {
        width: 100%;
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 12px;
        font-size: 14px;
        font-family: var(--font-body);
        resize: vertical;
        min-height: 100px;
        outline: none;
        margin-bottom: 16px;
    }
    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }
    .btn-modal {
        padding: 8px 20px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
        border: 1px solid var(--border);
        cursor: pointer;
        background: #fff;
        transition: var(--transition);
    }
    .btn-modal.primary {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
    }

    /* Pagination */
    .pagination-wrapper {
        margin-top: 32px;
        display: flex;
        justify-content: center;
    }
    .pagination {
        display: flex;
        gap: 4px;
        list-style: none;
    }
    .page-item {
        border-radius: 8px;
        overflow: hidden;
    }
    .page-link {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 36px;
        height: 36px;
        padding: 0 8px;
        font-size: 13px;
        font-weight: 600;
        border: 1px solid var(--border);
        background: #fff;
        color: var(--text-secondary);
        cursor: pointer;
        transition: var(--transition);
    }
    .page-link:hover {
        background: var(--border-light);
    }
    .page-item.active .page-link {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
    }
    .page-item.disabled .page-link {
        opacity: .4;
        pointer-events: none;
    }

    /* Empty state */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-muted);
    }
    .empty-state i {
        font-size: 40px;
        margin-bottom: 16px;
        display: block;
        color: var(--border);
    }
    .empty-state p {
        margin: 8px 0;
        font-size: 15px;
    }
    .empty-state .btn-main {
        display: inline-flex;
        margin-top: 16px;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .bookings-main-container {
            flex-direction: column;
        }
        .booking-detail-panel {
            width: 100%;
            position: relative;
            top: auto;
            max-height: none;
            box-shadow: 0 -4px 12px rgba(0,0,0,.06);
            border-radius: var(--radius) var(--radius) 0 0;
            margin-top: 24px;
        }
    }
    
</style>

<div class="bookings-main-container" id="bookingHistoryApp">
    {{-- LEFT: List --}}
    <div class="booking-list-panel">
        <div class="bookings-header">
            <h1>Lịch sử đặt phòng</h1>
            <a href="{{ route('home') }}" class="btn-main">
                <i class="fa-solid fa-plus"></i> Đặt phòng mới
            </a>
        </div>

        <div class="tabs-bar" id="tabsContainer">
            <!-- Dynamically rendered by JS -->
        </div>

        <div class="booking-list" id="bookingList">
            <!-- Dynamically rendered -->
        </div>

        <div class="pagination-wrapper" id="paginationContainer"></div>
    </div>

    {{-- RIGHT: Detail Panel --}}
    <div class="booking-detail-panel" id="detailPanel">
        <div class="detail-header">
            <h3>Chi tiết đặt phòng</h3>
            <button class="btn-close-detail" id="btnCloseDetail"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="detail-body" id="detailContent">
            <!-- Populated by JS -->
        </div>
    </div>

    {{-- Review Modal (hidden) --}}
    <div id="reviewModal" style="display: none;"></div>
</div>