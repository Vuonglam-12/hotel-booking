{{-- resources/views/admin/revenue.blade.php --}}
@extends('admin.layouts.admin')

@section('title', 'Báo cáo doanh thu')

@push('styles')
<style>
    /* Revenue Dashboard - Main Content Only */
    .revenue-dashboard {
        font-family: 'Be Vietnam Pro', system-ui, -apple-system, sans-serif;
    }

    .page-title {
    font-family: 'Be Vietnam Pro', sans-serif;
    font-size: 22px; font-weight: 700; color: var(--dark);
    }

    .dashboard-header {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 28px;
    }
    .dashboard-title h1 {
        font-size: 1.8rem;
        font-weight: 700;
        color: #0F172A;
        letter-spacing: -0.01em;
        margin-bottom: 6px;
    }
    .dashboard-title p {
        color: #64748B;
        font-size: 0.9rem;
        margin: 0;
    }
    .filter-group {
        background: #FFFFFF;
        padding: 6px;
        border-radius: 60px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    .filter-btn {
        background: transparent;
        border: none;
        padding: 8px 20px;
        font-size: 0.85rem;
        font-weight: 500;
        border-radius: 40px;
        color: #1E293B;
        cursor: pointer;
        transition: all 0.2s;
        font-family: inherit;
    }
    .filter-btn.active {
        background: #2563EB;
        color: white;
        box-shadow: 0 3px 10px rgba(37,99,235,0.2);
    }
    .filter-btn.custom {
        border: 1px solid #E2E8F0;
        background: white;
    }
    .filter-btn.custom:hover {
        background: #F8FAFC;
    }

    /* KPI Cards */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 28px;
    }
    .kpi-card {
        background: #FFFFFF;
        border-radius: 20px;
        padding: 1.25rem 1.5rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        transition: transform 0.2s;
        border: 1px solid rgba(226,232,240,0.6);
    }
    .kpi-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }
    .kpi-icon {
        background: #EFF6FF;
        border-radius: 14px;
        width: 42px;
        height: 42px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #2563EB;
        font-size: 1.3rem;
    }
    .trend-badge {
        font-size: 0.7rem;
        font-weight: 600;
        padding: 4px 8px;
        border-radius: 30px;
        background: #E9F9EF;
        color: #10B981;
    }
    .trend-badge.down {
        background: #FEF2F2;
        color: #EF4444;
    }
    .kpi-value {
        font-size: 2rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        color: #0F172A;
        margin-bottom: 4px;
    }
    .kpi-label {
        font-size: 0.75rem;
        font-weight: 500;
        color: #64748B;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .kpi-sub {
        font-size: 0.7rem;
        color: #94A3B8;
        margin-top: 6px;
    }

    /* Two columns */
    .two-columns {
        display: grid;
        grid-template-columns: 1.6fr 1fr;
        gap: 24px;
        margin-bottom: 28px;
    }
    .dashboard-card {
        background: white;
        border-radius: 20px;
        padding: 1.5rem;
        box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        border: 1px solid rgba(226,232,240,0.6);
    }
    .card-title {
        font-weight: 600;
        font-size: 1rem;
        color: #0F172A;
        margin-bottom: 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    /* Hotel bar list */
    .hotel-list-item {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 18px;
    }
    .hotel-name {
        flex: 0 0 110px;
        font-weight: 500;
        font-size: 0.85rem;
    }
    .bar-wrapper {
        flex: 1;
        background: #E2E8F0;
        border-radius: 20px;
        height: 8px;
        overflow: hidden;
    }
    .bar-fill {
        background: #2563EB;
        height: 100%;
        width: 0%;
        border-radius: 20px;
    }
    .hotel-amount {
        font-weight: 700;
        font-size: 0.85rem;
        min-width: 75px;
        text-align: right;
    }

    /* Three grid */
    .three-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 24px;
        margin-bottom: 28px;
    }
    .room-type-row, .service-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        font-size: 0.85rem;
    }
    .progress-bg {
        background: #E2E8F0;
        border-radius: 20px;
        height: 6px;
        width: 100%;
        margin-top: 4px;
    }
    .progress-fill {
        background: #2563EB;
        height: 6px;
        border-radius: 20px;
        width: 0%;
    }
    .donut-container {
        display: flex;
        justify-content: center;
        align-items: center;
        height: 180px;
    }
    canvas#paymentDonut {
        max-height: 150px;
    }

    /* Bảng giao dịch */
    .table-wrapper {
        overflow-x: auto;
        border-radius: 20px;
    }
    .transaction-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    .transaction-table th {
        text-align: left;
        padding: 14px 12px;
        background: #F8FAFC;
        font-weight: 600;
        color: #1E293B;
    }
    .transaction-table td {
        padding: 14px 12px;
        border-bottom: 1px solid #ECF3FA;
    }
    .transaction-table tr:hover td {
        background: #F8FAFE;
    }
    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 30px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .badge-success {
        background: #E0F2FE;
        color: #0369A1;
    }
    .badge-warning {
        background: #FEF9C3;
        color: #854D0E;
    }
    .badge-paid {
        background: #DCFCE7;
        color: #166534;
    }
    .monthly-summary {
        margin-top: 28px;
    }
    .month-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
        gap: 12px;
        margin-top: 16px;
    }
    .month-card {
        background: white;
        border-radius: 16px;
        padding: 12px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        border: 1px solid #F0F2F5;
    }
    .month-name {
        font-size: 0.75rem;
        font-weight: 600;
        color: #475569;
    }
    .month-revenue {
        font-weight: 800;
        font-size: 1rem;
        margin-top: 6px;
        color: #0F172A;
    }
    hr {
        margin: 20px 0;
        border-color: #ECF3FA;
    }
    @media (max-width: 1000px) {
        .two-columns, .three-grid { grid-template-columns: 1fr; }
        .dashboard-header { flex-direction: column; align-items: start; gap: 15px; }
    }
    .revenue-dashboard [hidden]{display:none!important}
    .revenue-dashboard .report-controls{display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin:-10px 0 16px}
    .revenue-dashboard .report-controls label{font-size:13px;color:#475569}
    .revenue-dashboard .report-controls select,.revenue-dashboard .report-dates input{border:1px solid #e2e8f0;border-radius:10px;padding:9px 12px;background:#fff;color:#142b49;font:inherit;font-size:13px;max-width:100%}
    .revenue-dashboard .report-dates{display:flex;align-items:end;flex-wrap:wrap;gap:12px;margin:0 0 16px;padding:16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px}
    .revenue-dashboard .report-dates label{display:grid;gap:6px;font-size:12px;color:#475569}
    .revenue-dashboard .report-dates p{flex-basis:100%;margin:0;font-size:12px;line-height:1.6;color:#64748b}
    .revenue-dashboard .report-dates #reportDateError{color:#b91c1c}.revenue-dashboard .report-dates input[aria-invalid="true"]{border-color:#dc2626}
    .revenue-dashboard .report-range{margin:0 0 6px;font-size:13px;font-weight:600;color:#142b49}
    .revenue-dashboard .report-policy{margin:0 0 22px;font-size:12px;line-height:1.7;color:#64748b}
    .revenue-dashboard .report-message{margin:0 0 16px;padding:12px 14px;border:1px solid #dbeafe;border-radius:10px;background:#eff6ff;color:#1e40af;font-size:13px;line-height:1.7}
    .revenue-dashboard .report-message[data-error="true"]{border-color:#fecaca;background:#fef2f2;color:#b91c1c}
    .revenue-dashboard .report-button{padding:9px 14px;background:#fff;border:1px solid #cbd5e1;border-radius:10px;color:#142b49;cursor:pointer;font:inherit;font-size:12px}
    .revenue-dashboard .report-button:disabled{opacity:.5;cursor:not-allowed}
    .revenue-dashboard .kpi-value{font-size:clamp(20px,2vw,30px);overflow-wrap:anywhere;font-variant-numeric:tabular-nums}
    .revenue-dashboard .dashboard-card{min-width:0}.revenue-dashboard .hotel-name{flex:1;min-width:0;overflow-wrap:anywhere}.revenue-dashboard .bar-wrapper{flex:1;min-width:24px}.revenue-dashboard .hotel-amount{min-width:0;white-space:nowrap;font-variant-numeric:tabular-nums}
    .revenue-dashboard .report-row{display:flex;justify-content:space-between;gap:12px;align-items:start;margin:12px 0;font-size:13px;line-height:1.6}
    .revenue-dashboard .report-row>span{min-width:0;overflow-wrap:anywhere}.revenue-dashboard .report-row strong{white-space:nowrap;font-variant-numeric:tabular-nums}
    .revenue-dashboard .report-row small,.revenue-dashboard .transaction-table small{display:block;margin-top:3px;color:#64748b;font-size:11px}
    .revenue-dashboard .report-note{margin:12px 0 0;font-size:11px;line-height:1.7;color:#64748b}
    .revenue-dashboard .report-pages{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-top:16px;font-size:12px;color:#64748b}
    .revenue-dashboard .report-pages>div{display:flex;align-items:center;gap:10px}
    .revenue-dashboard .month-revenue{font-size:13px;overflow-wrap:anywhere;font-variant-numeric:tabular-nums}
    .revenue-dashboard .month-grid{grid-template-columns:repeat(auto-fit,minmax(145px,1fr))}
    .revenue-dashboard .transaction-table td:nth-child(3){white-space:nowrap;font-variant-numeric:tabular-nums}
    .revenue-dashboard .report-series-data{max-height:240px;overflow:auto;font-size:12px}.revenue-dashboard .report-series-data table{width:100%;border-collapse:collapse}.revenue-dashboard .report-series-data td{padding:8px;border-bottom:1px solid #e2e8f0}.revenue-dashboard .report-series-data td:last-child{text-align:right}
    @media(max-width:600px){.revenue-dashboard .filter-group{border-radius:14px}.revenue-dashboard .filter-btn{padding:8px 12px}.revenue-dashboard .dashboard-card{padding:16px}.revenue-dashboard .card-title{flex-wrap:wrap;gap:6px}.revenue-dashboard .hotel-list-item{gap:8px}.revenue-dashboard .hotel-amount{font-size:11px}.revenue-dashboard .report-row{font-size:12px}}
</style>
@endpush

@section('content')
<div class="revenue-dashboard" id="revenueDashboard" aria-busy="true">
    {{-- HEADER + bộ lọc --}}
    <div class="dashboard-header">
        <div class="dashboard-title">
            <h1 class="page-title">Báo Cáo Doanh Thu</h1>
            <p>Theo dõi và phân tích doanh thu hệ thống</p>
        </div>
        <div class="filter-group" aria-label="Chọn kỳ báo cáo">
            <button type="button" class="filter-btn active" data-period="today" aria-pressed="true">Hôm nay</button>
            <button type="button" class="filter-btn" data-period="7days" aria-pressed="false">7 ngày</button>
            <button type="button" class="filter-btn" data-period="30days" aria-pressed="false">30 ngày</button>
            <button type="button" class="filter-btn" data-period="month" aria-pressed="false">Tháng này</button>
            <button type="button" class="filter-btn" data-period="year" aria-pressed="false">Năm nay</button>
            <button type="button" class="filter-btn custom" id="reportCustomToggle" aria-expanded="false" aria-controls="reportDateForm">@include('admin.partials.icon', ['name' => 'calendar-days']) Tùy chỉnh</button>
        </div>
    </div>
    <div class="report-controls"><label for="revenueScope">Dữ liệu thanh toán</label><select id="revenueScope"><option value="real">Thanh toán thực tế</option><option value="demo">Thanh toán DEMO</option></select><button type="button" class="report-button" id="revenueReload">Cập nhật báo cáo</button></div>
    <form id="reportDateForm" class="report-dates" hidden novalidate>
        <label for="reportDateFrom">Từ ngày<input type="date" id="reportDateFrom" required aria-describedby="reportDateError"></label>
        <label for="reportDateTo">Đến hết ngày<input type="date" id="reportDateTo" required aria-describedby="reportDateError"></label>
        <button type="submit" class="report-button">Áp dụng</button>
        <p>Tính cả ngày bắt đầu và ngày kết thúc, tối đa 366 ngày mỗi báo cáo.</p><p id="reportDateError" role="alert" hidden></p>
    </form>
    <p id="revenueRange" class="report-range">Đang tải kỳ báo cáo…</p>
    <p class="report-policy">Theo ngày thanh toán, giờ Việt Nam · VND. Chỉ tính thanh toán thành công, đúng số tiền của đơn đã xác nhận hoặc hoàn thành; mỗi đơn tính một lần. Giao dịch chờ, thất bại, đơn hủy và tiền cần đối soát không được cộng.</p>
    <div id="revenueStatus" class="report-message" role="status" aria-live="polite" hidden></div>

    {{-- KPI cards - đã thêm id cho 4 .kpi-value --}}
    <div class="kpi-grid">
        {{-- Tổng doanh thu --}}
        <div class="kpi-card">
            <div class="kpi-header">
                <span class="kpi-icon">@include('admin.partials.icon', ['name' => 'wallet'])</span>
            </div>
            <div class="kpi-value" id="kpi-total">—</div>
            <div class="kpi-label">Tổng doanh thu trong kỳ</div>

        </div>

        {{-- Doanh thu tháng này --}}
        <div class="kpi-card">
            <div class="kpi-header">
                <span class="kpi-icon">@include('admin.partials.icon', ['name' => 'chart-no-axes-column-increasing'])</span>
            </div>
            <div class="kpi-value" id="kpi-monthly">—</div>
            <div class="kpi-label" id="kpi-month-label">Doanh thu tháng cuối kỳ</div>
            <div class="kpi-sub">Chỉ tính phần tháng nằm trong kỳ đã chọn</div>

        </div>

        {{-- Tổng giao dịch --}}
        <div class="kpi-card">
            <div class="kpi-header">
                <span class="kpi-icon">@include('admin.partials.icon', ['name' => 'calendar-check'])</span>
            </div>
            <div class="kpi-value" id="kpi-tx">—</div>
            <div class="kpi-label">Tổng giao dịch</div>

        </div>

        {{-- Số khách sạn --}}
        <div class="kpi-card">
            <div class="kpi-header">
                <span class="kpi-icon">@include('admin.partials.icon', ['name' => 'hotel'])</span>
            </div>
            <div class="kpi-value" id="kpi-hotels">—</div>
            <div class="kpi-label">Khách sạn trong báo cáo</div>

        </div>
    </div>

    {{-- BIỂU ĐỒ: LINE + BAR (2 cột) --}}
    <div class="two-columns">
        {{-- Line chart: Doanh thu theo thời gian -- thay canvas bằng div --}}
        <div class="dashboard-card">
            <div class="card-title">
                <span>@include('admin.partials.icon', ['name' => 'chart-no-axes-column-increasing']) Doanh thu theo thời gian</span>
                <span class="card-subtitle" id="revenueChartUnit">Đơn vị: VND</span>
            </div>
            <div id="revenueLineChart" style="width:100%; min-height:190px;"></div>
        </div>
        {{-- Bar chart: Doanh thu theo khách sạn -- thêm id wrapper --}}
        <div class="dashboard-card">
            <div class="card-title">@include('admin.partials.icon', ['name' => 'hotel']) Doanh thu theo khách sạn (Top 5)</div>
            <div id="topHotelsList">
                {{-- Sẽ được JS render --}}
                <div class="hotel-list-item">
                    <div class="hotel-name">Đang tải...</div>
                    <div class="bar-wrapper"><div class="bar-fill"></div></div>
                    <div class="hotel-amount">---</div>
                </div>
            </div>
            <div class="report-note" id="topHotelsPeriod">Trong kỳ báo cáo đã chọn</div>
        </div>
    </div>

    {{-- HÀNG 3 KHỐI: Doanh thu loại phòng + Dịch vụ thêm + Phương thức thanh toán (thêm id cho từng card) --}}
    <div class="three-grid">
        {{-- Doanh thu theo loại phòng --}}
        <div class="dashboard-card" id="roomTypeCard">
            <div class="card-title">@include('admin.partials.icon', ['name' => 'hotel']) Doanh thu theo loại phòng</div>
            <div id="roomTypeList" class="report-breakdown"><div class="empty-state">Đang tải dữ liệu...</div></div>
            <p class="report-note">Top 8 theo tiền phòng đã lưu trong đơn, gồm phụ thu; chưa phân bổ giảm giá và thuế.</p>
        </div>

        {{-- Doanh thu dịch vụ thêm --}}
        <div class="dashboard-card" id="upsellCard">
            <div class="card-title">@include('admin.partials.icon', ['name' => 'tag']) Dịch vụ thêm (Upsell)</div>
            <div id="upsellList" class="report-breakdown"><div class="empty-state">Đang tải dữ liệu...</div></div>
        </div>

        {{-- Phương thức thanh toán --}}
        <div class="dashboard-card" id="paymentCard">
            <div class="card-title">@include('admin.partials.icon', ['name' => 'wallet']) Phương thức thanh toán</div>
            <div id="paymentDonutChart"></div><div id="paymentMethodsList"><div class="empty-state">Đang tải dữ liệu...</div></div>
        </div>
    </div>

    {{-- BẢNG GIAO DỊCH GẦN ĐÂY --}}
    <div class="dashboard-card" style="margin-bottom:28px;">
        <div class="card-title">@include('admin.partials.icon', ['name' => 'calendar-days']) Giao dịch gần đây</div>
        <div class="table-wrapper">
            <table class="transaction-table">
                <thead>
                    <tr><th>Mã booking</th><th>Khách hàng</th><th>Số tiền</th><th>Phương thức</th><th>Trạng thái</th><th>Thời gian</th></tr>
                </thead>
                <tbody id="transactionsTbody">
                    {{-- JS sẽ fill dữ liệu --}}
                    <tr><td colspan="6" style="text-align:center;padding:24px;">Đang tải...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="report-pages"><span id="transactionsCount">Đang tải giao dịch…</span><div><button type="button" id="transactionsPrev" class="report-button" disabled>Trang trước</button><span id="transactionsPage"></span><button type="button" id="transactionsNext" class="report-button" disabled>Trang sau</button></div></div>
    </div>

    {{-- TỔNG KẾT DOANH THU THEO THÁNG (grid) -- thêm id --}}
    <div class="dashboard-card monthly-summary">
        <div class="card-title">@include('admin.partials.icon', ['name' => 'calendar-days']) Tổng kết doanh thu theo tháng <span class="card-subtitle">Trong kỳ đã chọn</span></div>
        <div class="month-grid" id="monthSummaryGrid">
            {{-- JS sẽ render --}}
            <div class="month-card"><div class="month-name">Đang tải...</div><div class="month-revenue">---</div></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
@include('admin.partials.revenue-script')
@endpush
