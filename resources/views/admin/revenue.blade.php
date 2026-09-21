{{-- resources/views/admin/revenue.blade.php --}}
@extends('admin.layouts.admin')

@section('title', 'Báo cáo doanh thu')

@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    /* Revenue Dashboard - Main Content Only */
    .revenue-dashboard {
        font-family: 'Nunito Sans', system-ui, -apple-system, sans-serif;
    }

    .page-title {
    font-family: 'Nunito Sans', sans-serif;
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
</style>
@endpush

@section('content')
<div class="revenue-dashboard">
    {{-- HEADER + bộ lọc --}}
    <div class="dashboard-header">
        <div class="dashboard-title">
            <div class="page-title">Báo Cáo Doanh Thu</div>
            <p>Theo dõi và phân tích doanh thu hệ thống</p>
        </div>
        <div class="filter-group">
            <button class="filter-btn active">Hôm nay</button>
            <button class="filter-btn">7 ngày</button>
            <button class="filter-btn">30 ngày</button>
            <button class="filter-btn">Tháng này</button>
            <button class="filter-btn">Năm nay</button>
            <button class="filter-btn custom">📅 Tùy chỉnh</button>
        </div>
    </div>

    {{-- KPI cards - đã thêm id cho 4 .kpi-value --}}
    <div class="kpi-grid">
        {{-- Tổng doanh thu --}}
        <div class="kpi-card">
            <div class="kpi-header">
                <span class="kpi-icon"><i class="fas fa-dollar-sign"></i></span>
                <span class="trend-badge"><i class="fas fa-arrow-up"></i> +18,6%</span>
            </div>
            <div class="kpi-value" id="kpi-total">—</div>
            <div class="kpi-label">TỔNG DOANH THU</div>
            <div class="kpi-sub">so với tháng trước</div>
        </div>

        {{-- Doanh thu tháng này --}}
        <div class="kpi-card">
            <div class="kpi-header">
                <span class="kpi-icon"><i class="fas fa-chart-line"></i></span>
                <span class="trend-badge"><i class="fas fa-arrow-up"></i> +12,4%</span>
            </div>
            <div class="kpi-value" id="kpi-monthly">—</div>
            <div class="kpi-label">DOANH THU THÁNG NÀY</div>
            <div class="kpi-sub">so với tháng trước</div>
        </div>

        {{-- Tổng giao dịch --}}
        <div class="kpi-card">
            <div class="kpi-header">
                <span class="kpi-icon"><i class="fas fa-calendar-check"></i></span>
                <span class="trend-badge"><i class="fas fa-arrow-up"></i> +10,2%</span>
            </div>
            <div class="kpi-value" id="kpi-tx">—</div>
            <div class="kpi-label">TỔNG GIAO DỊCH</div>
            <div class="kpi-sub">so với tháng trước</div>
        </div>

        {{-- Số khách sạn --}}
        <div class="kpi-card">
            <div class="kpi-header">
                <span class="kpi-icon"><i class="fas fa-building"></i></span>
                <span class="trend-badge"><i class="fas fa-arrow-up"></i> +5,3%</span>
            </div>
            <div class="kpi-value" id="kpi-hotels">—</div>
            <div class="kpi-label">KHÁCH SẠN ĐANG HOẠT ĐỘNG</div>
            <div class="kpi-sub">so với tháng trước</div>
        </div>
    </div>

    {{-- BIỂU ĐỒ: LINE + BAR (2 cột) --}}
    <div class="two-columns">
        {{-- Line chart: Doanh thu theo thời gian -- thay canvas bằng div --}}
        <div class="dashboard-card">
            <div class="card-title">
                <span>📈 Doanh thu theo thời gian</span>
                <span style="font-size:0.7rem; background:#F1F5F9; padding:4px 10px; border-radius:20px;">Tháng này vs cùng kỳ</span>
            </div>
            <div id="revenueLineChart" style="width:100%; height:280px;"></div>
        </div>
        {{-- Bar chart: Doanh thu theo khách sạn -- thêm id wrapper --}}
        <div class="dashboard-card">
            <div class="card-title">🏨 Doanh thu theo khách sạn (Top 5)</div>
            <div id="topHotelsList">
                {{-- Sẽ được JS render --}}
                <div class="hotel-list-item">
                    <div class="hotel-name">Đang tải...</div>
                    <div class="bar-wrapper"><div class="bar-fill"></div></div>
                    <div class="hotel-amount">---</div>
                </div>
            </div>
            <div class="mt-3" style="font-size:0.7rem; color:#64748B; margin-top:12px;">* Dữ liệu 30 ngày gần nhất</div>
        </div>
    </div>

    {{-- HÀNG 3 KHỐI: Doanh thu loại phòng + Dịch vụ thêm + Phương thức thanh toán (thêm id cho từng card) --}}
    <div class="three-grid">
        {{-- Doanh thu theo loại phòng --}}
        <div class="dashboard-card" id="roomTypeCard">
            <div class="card-title">🛏️ Doanh thu theo loại phòng</div>
            <div class="room-type-row">
                <span>Deluxe Ocean</span>
                <span>42%</span>
            </div>
            <div class="progress-bg"><div class="progress-fill" style="width:42%"></div></div>
            <div class="room-type-row">
                <span>Suite President</span>
                <span>28%</span>
            </div>
            <div class="progress-bg"><div class="progress-fill" style="width:28%"></div></div>
            <div class="room-type-row">
                <span>Family Connecting</span>
                <span>18%</span>
            </div>
            <div class="progress-bg"><div class="progress-fill" style="width:18%"></div></div>
            <div class="room-type-row">
                <span>Standard City</span>
                <span>12%</span>
            </div>
            <div class="progress-bg"><div class="progress-fill" style="width:12%"></div></div>
            <div class="small-text mt-2" style="margin-top:12px; font-size:0.7rem; color:#64748B;">Tổng: 1.84 tỷ</div>
        </div>

        {{-- Doanh thu dịch vụ thêm --}}
        <div class="dashboard-card" id="upsellCard">
            <div class="card-title">🧳 Dịch vụ thêm (Upsell)</div>
            <div class="service-row">
                <span>✈️ Đưa đón sân bay</span>
                <span><strong>128M ₫</strong></span>
            </div>
            <div class="service-row">
                <span>🍽️ Gói ẩm thực</span>
                <span><strong>94M ₫</strong></span>
            </div>
            <div class="service-row">
                <span>🧘 Spa & massage</span>
                <span><strong>67M ₫</strong></span>
            </div>
            <div class="service-row">
                <span>🚗 Thuê xe du lịch</span>
                <span><strong>43M ₫</strong></span>
            </div>
            <div class="service-row">
                <span>🎟️ Tour trải nghiệm</span>
                <span><strong>31M ₫</strong></span>
            </div>
            <hr style="margin:12px 0">
            <div class="service-row" style="font-weight:700;">
                <span>Tổng dịch vụ</span>
                <span>363M ₫</span>
            </div>
        </div>

        {{-- Phương thức thanh toán --}}
        <div class="dashboard-card" id="paymentCard">
            <div class="card-title">💳 Phương thức thanh toán</div>
            <div class="donut-container">
                <canvas id="paymentDonut" width="180" height="150" style="max-width:170px; max-height:150px;"></canvas>
            </div>
            <div style="display:flex; justify-content:center; gap:16px; margin-top:10px; flex-wrap:wrap;">
                <span><span style="background:#3B82F6; display:inline-block; width:10px; height:10px; border-radius:50%;"></span> Chuyển khoản 54%</span>
                <span><span style="background:#F59E0B; display:inline-block; width:10px; height:10px; border-radius:50%;"></span> Thẻ tín dụng 28%</span>
                <span><span style="background:#10B981; display:inline-block; width:10px; height:10px; border-radius:50%;"></span> Ví điện tử 18%</span>
            </div>
        </div>
    </div>

    {{-- BẢNG GIAO DỊCH GẦN ĐÂY --}}
    <div class="dashboard-card" style="margin-bottom:28px;">
        <div class="card-title">📋 Giao dịch gần đây</div>
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
    </div>

    {{-- TỔNG KẾT DOANH THU THEO THÁNG (grid) -- thêm id --}}
    <div class="dashboard-card monthly-summary">
        <div class="card-title">📆 Tổng kết doanh thu theo tháng (2025)</div>
        <div class="month-grid" id="monthSummaryGrid">
            {{-- JS sẽ render --}}
            <div class="month-card"><div class="month-name">Đang tải...</div><div class="month-revenue">---</div></div>
        </div>
        <div style="margin-top: 16px; background: #F8FAFC; border-radius: 16px; padding: 10px; text-align: center; font-size:0.8rem;">
            📊 Dự kiến doanh thu Q2 tăng 14% so với cùng kỳ
        </div>
    </div>
</div>
@endsection

@push('scripts')

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
    document.addEventListener('DOMContentLoaded', async function() {

        // ══ Load data thật từ API ══
        let apiData = { monthly_revenue: [], top_hotels: [], total_revenue: 0 };
        try {
            const res  = await adminApi('/revenue');
            if (res.ok) apiData = await res.json();
        } catch(e) {
            console.error('Revenue API error:', e);
        }

        renderKPI(apiData);
        renderMonthlyChart(apiData.monthly_revenue || []);
        renderTopHotels(apiData.top_hotels || []);
        renderTransactions(apiData);
        renderMonthSummary(apiData.monthly_revenue || []);

        // ── Các phần chưa có API ──
        markNoData('roomTypeCard');
        markNoData('upsellCard');
        markNoData('paymentCard');
    });

    // ══ KPI Cards ══
    function renderKPI(data) {
        const total    = data.total_revenue   ?? 0;
        const monthly  = (data.monthly_revenue ?? []);
        const currentMonth = new Date().getMonth() + 1;
        const thisMonth = monthly.find(m => m.month == currentMonth)?.total ?? 0;
        const totalTx  = monthly.reduce((s, m) => s + (m.count ?? 0), 0);
        const hotelsCount = (data.top_hotels ?? []).length;

        document.getElementById('kpi-total').textContent    = formatMoney(total);
        document.getElementById('kpi-monthly').textContent  = formatMoney(thisMonth);
        document.getElementById('kpi-tx').textContent       = totalTx.toLocaleString('vi-VN');
        document.getElementById('kpi-hotels').textContent   = hotelsCount;
    }

    // ══ Line chart doanh thu theo tháng ══
    function renderMonthlyChart(monthly) {
        const MONTH_NAMES = ['T1','T2','T3','T4','T5','T6','T7','T8','T9','T10','T11','T12'];

        // Tạo mảng 12 tháng, tháng nào không có data thì = 0
        const amounts = Array.from({length: 12}, (_, i) => {
            const found = monthly.find(m => m.month == i + 1);
            return found ? Math.round(found.total / 1_000_000) : 0; // đổi sang triệu
        });

        if (window._revenueLineChart) {
            window._revenueLineChart.destroy();
        }

        window._revenueLineChart = new ApexCharts(
            document.getElementById('revenueLineChart'), {
            series: [{ name: 'Doanh thu (triệu ₫)', data: amounts }],
            chart: {
                type: 'area', height: 280,
                toolbar: { show: false },
                animations: { enabled: true, speed: 700 },
                background: 'transparent',
            },
            stroke: { curve: 'smooth', width: 2.5 },
            fill: {
                type: 'gradient',
                gradient: { shadeIntensity: 1, opacityFrom: 0.25, opacityTo: 0.01 }
            },
            dataLabels: { enabled: false },
            xaxis: {
                categories: MONTH_NAMES,
                axisBorder: { show: false }, axisTicks: { show: false },
                labels: { style: { colors: '#94A3B8', fontSize: '12px' } }
            },
            yaxis: {
                labels: {
                    style: { colors: '#94A3B8', fontSize: '11px' },
                    formatter: val => val + 'M'
                }
            },
            colors: ['#2563EB'],
            grid: {
                borderColor: 'rgba(226,232,240,0.6)', strokeDashArray: 4,
                yaxis: { lines: { show: true } }, xaxis: { lines: { show: false } },
            },
            tooltip: {
                y: { formatter: val => val + ' triệu ₫' }
            },
        });
        window._revenueLineChart.render();
    }

    // ══ Top hotels bar list ══
    function renderTopHotels(hotels) {
        const wrap = document.getElementById('topHotelsList');
        if (!hotels.length) {
            wrap.innerHTML = '<div style="color:#94A3B8;font-size:13px;text-align:center;padding:20px 0;">Chưa có dữ liệu</div>';
            return;
        }
        const max = Math.max(...hotels.map(h => h.revenue));
        wrap.innerHTML = hotels.map(h => `
            <div class="hotel-list-item">
                <div class="hotel-name" title="${escHtml(h.name)}">${escHtml(h.name.length > 14 ? h.name.substring(0,14)+'…' : h.name)}</div>
                <div class="bar-wrapper">
                    <div class="bar-fill" style="width:${Math.round((h.revenue/max)*100)}%;transition:width 0.8s ease;"></div>
                </div>
                <div class="hotel-amount">${formatMoney(h.revenue)}</div>
            </div>
        `).join('');
    }

    // ══ Bảng giao dịch gần đây ══
    function renderTransactions(data) {
        const wrap = document.getElementById('transactionsTbody');
        if (!data.top_hotels || !data.top_hotels.length) {
            wrap.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:24px;color:#94A3B8;">Chưa có dữ liệu giao dịch</td></tr>';
            return;
        }
        // Hiển thị dạng demo từ top_hotels (có thể thay bằng API giao dịch thực tế)
        wrap.innerHTML = data.top_hotels.map(h => `
            <tr>
                <td style="font-weight:600;color:#2563EB;">${escHtml(h.name)}</td>
                <td>${h.transactions ?? '—'} giao dịch</td>
                <td style="font-weight:700;">${formatMoney(h.revenue)}</td>
                <td>—</td>
                <td><span class="badge badge-success">Hoàn thành</span></td>
                <td>—</td>
            </tr>
        `).join('');
    }

    // ══ Tổng kết tháng ══
    function renderMonthSummary(monthly) {
        const MONTH_NAMES = ['Thg 1','Thg 2','Thg 3','Thg 4','Thg 5','Thg 6',
                            'Thg 7','Thg 8','Thg 9','Thg 10','Thg 11','Thg 12'];
        const wrap = document.getElementById('monthSummaryGrid');

        if (!monthly.length) {
            wrap.innerHTML = '<div style="color:#94A3B8;font-size:13px;text-align:center;padding:20px 0;grid-column:1/-1;">Chưa có dữ liệu</div>';
            return;
        }

        // Hiện đủ 12 tháng, tháng nào chưa có thì hiện "—"
        wrap.innerHTML = Array.from({length: 12}, (_, i) => {
            const found = monthly.find(m => m.month == i + 1);
            const isFuture = (i + 1) > new Date().getMonth() + 1;
            return `
                <div class="month-card" style="${isFuture ? 'opacity:0.4' : ''}">
                    <div class="month-name">${MONTH_NAMES[i]}</div>
                    <div class="month-revenue">${found ? formatMoney(found.total) : '—'}</div>
                </div>
            `;
        }).join('');
    }

    // ══ Mark "Chưa có dữ liệu" cho các card chưa có API ══
    function markNoData(cardId) {
        const card = document.getElementById(cardId);
        if (!card) return;
        // Giữ nguyên title, chỉ thay nội dung bên trong
        const title = card.querySelector('.card-title');
        const titleHTML = title ? title.outerHTML : '';
        const body = card.querySelectorAll(':scope > *:not(.card-title)');
        body.forEach(el => el.style.display = 'none');

        const notice = document.createElement('div');
        notice.style.cssText = 'text-align:center;padding:28px 0;color:#94A3B8;font-size:13px;';
        notice.innerHTML = `
            <svg width="32" height="32" fill="none" stroke="#CBD5E1" viewBox="0 0 24 24" style="margin:0 auto 8px;display:block;">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            Chưa có dữ liệu
        `;
        card.appendChild(notice);
    }

    // ══ Helpers ══
    function formatMoney(amount) {
        if (!amount) return '0đ';
        if (amount >= 1_000_000_000) return (amount / 1_000_000_000).toFixed(1) + 'B';
        if (amount >= 1_000_000)     return (amount / 1_000_000).toFixed(1) + 'M';
        if (amount >= 1_000)         return (amount / 1_000).toFixed(0) + 'K';
        return Number(amount).toLocaleString('vi-VN') + 'đ';
    }

    function escHtml(s) {
        if (!s) return '';
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
</script>
@endpush