@extends('admin.layouts.admin')
@section('title', 'Dashboard')

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<style>
    .dashboard-page { max-width: 1440px; margin-inline: auto; }
    .dashboard-page p, .dashboard-page h1, .dashboard-page h2 { margin: 0; }
    .dashboard-page .page-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; }
    .dashboard-page .page-title { font-size: 24px; line-height: 1.35; font-weight: 600; letter-spacing: -.025em; }
    .dashboard-page .page-sub { margin-top: 6px; font-size: 12px; color: var(--gray); }
    .dashboard-page .btn-primary { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 40px; padding: 9px 14px; border: 1px solid var(--blue); border-radius: 8px; background: var(--blue); color: white; font-size: 12px; text-decoration: none; transition: background-color var(--admin-duration); }
    .dashboard-page .btn-primary:hover { background: var(--blue-dark); }
    .dashboard-page .btn-primary .admin-icon { width: 17px; height: 17px; }
    .dashboard-page .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 24px; }
    .dashboard-page .stat-card { min-width: 0; padding: 18px; border: 1px solid var(--border); border-radius: var(--admin-radius); background: white; }
    .dashboard-page .stat-heading { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
    .dashboard-page .stat-icon { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; flex-shrink: 0; background: var(--blue-soft); border-radius: 8px; color: var(--blue); }
    .dashboard-page .stat-icon .admin-icon { width: 17px; height: 17px; }
    .dashboard-page .stat-label { color: var(--gray); font-size: 11px; line-height: 1.5; }
    .dashboard-page .stat-value { margin-bottom: 8px; color: var(--dark); font-size: clamp(22px, 2vw, 28px); font-weight: 600; line-height: 1.2; letter-spacing: -.025em; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
    .dashboard-page .stat-change { display: flex; align-items: center; flex-wrap: wrap; gap: 4px; font-size: 11px; color: var(--gray); min-height: 17px; }
    .dashboard-page .stat-change .admin-icon { width: 14px; height: 14px; }
    .dashboard-page .stat-change.up { color: #15803D; }
    .dashboard-page .stat-change.down { color: #DC2626; }
    .dashboard-page .stat-change-label { color: var(--gray); }
    .dashboard-page .two-col { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr); align-items: start; gap: 20px; margin-bottom: 20px; }
    .dashboard-page .dash-card { min-width: 0; border: 1px solid var(--border); border-radius: var(--admin-radius); overflow: hidden; background: white; }
    .dashboard-page .card-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; padding: 18px 20px; border-bottom: 1px solid var(--border); }
    .dashboard-page .card-title { font-size: 14px; line-height: 1.5; font-weight: 600; }
    .dashboard-page .card-subtitle { margin-top: 4px; color: var(--gray); font-size: 11px; }
    .dashboard-page .card-link { display: inline-flex; align-items: center; gap: 5px; color: var(--blue); font-size: 11px; white-space: nowrap; text-decoration: none; }
    .dashboard-page .card-link:hover { color: var(--blue-dark); }
    .dashboard-page .card-link .admin-icon { width: 14px; height: 14px; }
    .dashboard-page .table-scroll { max-width: 100%; overflow-x: auto; }
    .dashboard-page .dash-table { width: 100%; border-collapse: collapse; }
    .dashboard-page .dash-table th { padding: 12px 16px; background: #F8F8F8; color: var(--gray); font-size: 10px; font-weight: 500; text-align: left; white-space: nowrap; border-bottom: 1px solid var(--border); }
    .dashboard-page .dash-table td { padding: 14px 16px; color: var(--dark); font-size: 12px; line-height: 1.55; border-bottom: 1px solid var(--border); }
    .dashboard-page .dash-table tr:last-child td { border-bottom: 0; }
    .dashboard-page .dash-table tbody tr:hover { background: #FAFAFA; }
    .dashboard-page .dash-table .money-cell, .dashboard-page .dash-table .money-heading { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .dashboard-page .hotel-cell { color: var(--gray) !important; min-width: 120px; overflow-wrap: anywhere; }
    .dashboard-page .badge { display: inline-flex; align-items: center; padding: 3px 8px; border-radius: 5px; font-size: 10px; white-space: nowrap; }
    .dashboard-page .badge-info { background: var(--blue-soft); color: var(--blue); }
    .dashboard-page .badge-success { background: #F0FDF4; color: #15803D; }
    .dashboard-page .badge-warn { background: #FFFBEB; color: #B45309; }
    .dashboard-page .badge-danger { background: #FEF2F2; color: #DC2626; }
    .dashboard-page .empty-cell { padding: 0 !important; }
    .dashboard-page .dash-empty { min-height: 208px; padding: 32px 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; color: var(--gray); text-align: center; }
    .dashboard-page .dash-empty-icon { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; margin-bottom: 4px; border-radius: 10px; background: #F8F8F8; color: var(--gray); }
    .dashboard-page .dash-empty strong { font-size: 12px; font-weight: 500; color: var(--dark); }
    .dashboard-page .dash-empty p { max-width: 260px; font-size: 11px; line-height: 1.6; }
    .dashboard-page .chart-wrap { padding: 10px 12px 0; min-height: 220px; }
    .dashboard-page .chart-wrap .dash-empty { min-height: 208px; padding-block: 20px; }
    .dashboard-page .chart-summary { padding: 12px 20px; border-top: 1px solid var(--border); color: var(--gray); font-size: 10px; }
    .dashboard-page .review-item { display: flex; align-items: flex-start; gap: 12px; padding: 18px 20px; border-bottom: 1px solid var(--border); }
    .dashboard-page .review-item:last-child { border-bottom: 0; }
    .dashboard-page .review-avatar { width: 32px; height: 32px; flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; background: var(--blue-soft); color: var(--blue); font-size: 10px; }
    .dashboard-page .review-content { flex: 1; min-width: 0; }
    .dashboard-page .review-heading { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 12px; }
    .dashboard-page .review-stars { display: inline-flex; gap: 2px; color: #B45309; }
    .dashboard-page .review-stars .admin-icon { width: 12px; height: 12px; }
    .dashboard-page .review-stars .filled .admin-icon { fill: currentColor; }
    .dashboard-page .review-stars .unfilled { color: #D1D5DB; }
    .dashboard-page .review-hotel, .dashboard-page .review-meta { color: var(--gray); font-size: 10px; }
    .dashboard-page .review-hotel { margin-left: auto; }
    .dashboard-page .review-text { margin-top: 6px; color: var(--gray); font-size: 12px; line-height: 1.6; overflow-wrap: anywhere; }
    .dashboard-page .review-meta { margin-top: 6px; }
    .dashboard-page .review-actions { display: flex; gap: 6px; margin-left: 4px; }
    .dashboard-page .btn-approve, .dashboard-page .btn-reject { display: inline-flex; align-items: center; justify-content: center; gap: 5px; min-height: 32px; padding: 6px 10px; border: 1px solid var(--border); border-radius: 7px; background: white; color: var(--gray); font-size: 11px; white-space: nowrap; }
    .dashboard-page .btn-approve:hover { color: var(--blue); background: var(--blue-soft); }
    .dashboard-page .btn-reject:hover { color: #DC2626; background: #FEF2F2; }
    .dashboard-page .review-actions .admin-icon { width: 13px; height: 13px; }
    .dashboard-page .skeleton { display: inline-block; max-width: 100%; background: #F3F4F6; border-radius: 5px; animation: admin-skeleton 1.4s ease-in-out infinite alternate; }
    @keyframes admin-skeleton { to { opacity: .45; } }
    .dashboard-page .dash-error { margin-bottom: 20px; padding: 12px 16px; border: 1px solid var(--border); border-radius: 8px; color: var(--gray); font-size: 12px; background: #F8F8F8; }
    @media (max-width: 1279px) {
        .dashboard-page .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .dashboard-page .two-col { grid-template-columns: minmax(0, 1fr); }
    }
    @media (max-width: 599px) {
        .dashboard-page .page-title { font-size: 22px; }
        .dashboard-page .card-head { padding: 16px; }
        .dashboard-page .review-item { flex-wrap: wrap; padding: 16px; }
        .dashboard-page .review-actions { margin-left: 44px; }
        .dashboard-page .review-hotel { margin-left: 0; }
    }
    @media (max-width: 479px) {
        .dashboard-page .stats-grid { grid-template-columns: minmax(0, 1fr); gap: 12px; }
        .dashboard-page .stat-card { padding: 16px; }
    }
</style>
@endpush

@section('content')
<section class="dashboard-page" aria-labelledby="dashboardTitle">
    <div class="page-header">
        <div>
            <h1 class="page-title" id="dashboardTitle">Dashboard</h1>
            <p class="page-sub" id="dashDate">Đang tải...</p>
        </div>
        <a href="{{ route('admin.hotels') }}" class="btn-primary">
            @include('admin.partials.icon', ['name' => 'plus'])
            Thêm khách sạn
        </a>
    </div>
    <div class="dash-error" id="dashLoadError" role="alert" hidden>Không thể tải dữ liệu Dashboard. Vui lòng tải lại trang để thử lại.</div>
    <div class="stats-grid" id="statsGrid" aria-busy="true" aria-label="Số liệu tổng quan">
        @for ($i = 0; $i < 4; $i++)
        <div class="stat-card" aria-hidden="true">
            <div class="stat-heading">
                <span class="stat-icon"><span class="skeleton" style="width:17px;height:17px"></span></span>
                <span class="skeleton" style="width:60%;height:12px"></span>
            </div>
            <div class="skeleton" style="width:50%;height:28px;margin-bottom:8px"></div>
            <div class="skeleton" style="width:65%;height:11px"></div>
        </div>
        @endfor
    </div>
    <div class="two-col">
        <section class="dash-card" aria-labelledby="recentBookingsTitle">
            <div class="card-head">
                <h2 class="card-title" id="recentBookingsTitle">Đặt phòng gần đây</h2>
                <a href="{{ route('admin.bookings') }}" class="card-link">Xem tất cả @include('admin.partials.icon', ['name' => 'arrow-right'])</a>
            </div>
            <div class="table-scroll" id="bookingTableWrap">
                <table class="dash-table" aria-label="Các đặt phòng gần đây">
                    <thead><tr><th scope="col">Khách hàng</th><th scope="col">Khách sạn</th><th scope="col">Trạng thái</th><th scope="col" class="money-heading">Tổng tiền</th></tr></thead>
                    <tbody id="bookingTableBody" aria-busy="true">
                        <tr><td colspan="4" class="empty-cell"><div class="dash-empty"><span class="skeleton" style="width:70%;height:12px"></span><p>Đang tải đặt phòng...</p></div></td></tr>
                    </tbody>
                </table>
            </div>
        </section>
        <section class="dash-card" aria-labelledby="revenueTitle">
            <div class="card-head">
                <div><h2 class="card-title" id="revenueTitle">Doanh thu 7 ngày</h2><p class="card-subtitle">Thanh toán thành công · VND</p></div>
                <a href="{{ route('admin.revenue') }}" class="card-link">Chi tiết @include('admin.partials.icon', ['name' => 'arrow-right'])</a>
            </div>
            <div class="chart-wrap" id="revenueChart" aria-busy="true" role="img" aria-label="Biểu đồ doanh thu 7 ngày">
                <div class="dash-empty"><span class="skeleton" style="width:70%;height:80px"></span><p>Đang tải doanh thu...</p></div>
            </div>
            <div class="chart-summary" id="chartSummary">Đơn vị: VND · Dữ liệu từ các thanh toán thành công</div>
        </section>
    </div>
    <section class="dash-card" aria-labelledby="pendingReviewsTitle">
        <div class="card-head">
            <h2 class="card-title" id="pendingReviewsTitle">Đánh giá chờ duyệt</h2>
            <a href="{{ route('admin.reviews') }}" class="card-link">Xem tất cả @include('admin.partials.icon', ['name' => 'arrow-right'])</a>
        </div>
        <div id="reviewsWrap" aria-busy="true"><div class="dash-empty"><p>Đang tải đánh giá...</p></div></div>
    </section>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', async function() {
    document.getElementById('dashDate').textContent = new Date().toLocaleDateString('vi-VN', {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
    });
    try {
        const res = await adminApi('/dashboard');
        if (!res.ok) throw new Error('API error');
        const data = await res.json();
        renderStats(data);
        renderBookings(data.recent_bookings || []);
        renderChart(Array.from(data.revenue_7days || []));
        renderReviews(data.pending_reviews_list || []);
    } catch (err) {
        console.error('Dashboard load error:', err);
        document.getElementById('dashLoadError').hidden = false;
        renderStats({}, true);
        renderBookings([], true);
        renderChart([], true);
        renderReviews([], true);
    } finally {
        ['statsGrid', 'bookingTableBody', 'revenueChart', 'reviewsWrap'].forEach(id => document.getElementById(id).setAttribute('aria-busy', 'false'));
    }
});

function renderStats(data, unavailable = false) {
    const cards = [
        { icon: 'calendar-check', label: 'Tổng booking', value: data.total_bookings ?? 0, change: data.booking_change ?? null, changeLabel: 'tháng này' },
        { icon: 'wallet', label: 'Doanh thu tháng', value: data.monthly_revenue ?? 0, money: true, change: data.revenue_change ?? null, changeLabel: 'so với tháng trước' },
        { icon: 'user-round-plus', label: 'Khách hàng mới', value: data.new_customers ?? 0, change: data.customer_change ?? null, changeLabel: 'tháng này' },
        { icon: 'message-square-text', label: 'Đánh giá chờ duyệt', value: data.pending_reviews ?? 0, change: null, changeLabel: 'Cần xử lý hôm nay' },
    ];
    document.getElementById('statsGrid').innerHTML = cards.map(card => {
        const hasChange = !unavailable && card.change !== null;
        const direction = hasChange ? (card.change >= 0 ? 'up' : 'down') : 'neutral';
        const value = unavailable ? '—' : (card.money ? formatMoney(card.value, true) : Number(card.value).toLocaleString('vi-VN'));
        return `<div class="stat-card">
            <div class="stat-heading"><span class="stat-icon">${adminIcon(card.icon)}</span><span class="stat-label">${card.label}</span></div>
            <div class="stat-value" title="${unavailable ? 'Chưa tải được dữ liệu' : (card.money ? formatMoney(card.value) : value)}">${value}</div>
            <div class="stat-change ${direction}">
                ${hasChange ? adminIcon(card.change >= 0 ? 'arrow-up-right' : 'arrow-down-right') + (card.change >= 0 ? '+' : '') + escapeHtml(card.change) + '%' : ''}
                <span class="stat-change-label">${unavailable ? 'Không thể tải dữ liệu' : card.changeLabel}</span>
            </div>
        </div>`;
    }).join('');
}

function emptyState(icon, title, description) {
    return `<div class="dash-empty"><span class="dash-empty-icon">${adminIcon(icon)}</span><strong>${escapeHtml(title)}</strong><p>${escapeHtml(description)}</p></div>`;
}

function renderBookings(bookings, unavailable = false) {
    const body = document.getElementById('bookingTableBody');
    if (!bookings.length) {
        body.innerHTML = '<tr><td colspan="4" class="empty-cell">' + (unavailable
            ? emptyState('circle-alert', 'Chưa tải được đặt phòng', 'Vui lòng tải lại trang để thử lại.')
            : emptyState('calendar-days', 'Chưa có đặt phòng', 'Các đặt phòng mới sẽ xuất hiện tại đây.')) + '</td></tr>';
        return;
    }
    const statuses = { completed: ['info', 'Hoàn thành'], confirmed: ['success', 'Đã xác nhận'], pending: ['warn', 'Chờ duyệt'], checked_in: ['info', 'Đang ở'], cancelled: ['danger', 'Đã huỷ'] };
    body.innerHTML = bookings.slice(0, 6).map(booking => {
        const status = statuses[booking.status] ?? ['warn', booking.status || '—'];
        return `<tr>
            <td>${escapeHtml(booking.user?.name ?? booking.guest_name ?? '—')}</td>
            <td class="hotel-cell">${escapeHtml(booking.room?.hotel?.name ?? booking.hotel_name ?? '—')}</td>
            <td><span class="badge badge-${status[0]}">${escapeHtml(status[1])}</span></td>
            <td class="money-cell">${formatMoney(booking.total_price ?? 0)}</td>
        </tr>`;
    }).join('');
}

let revenueChartInstance = null;
function renderChart(days, unavailable = false) {
    days = Array.from(days || []);
    const target = document.getElementById('revenueChart');
    if (revenueChartInstance) { revenueChartInstance.destroy(); revenueChartInstance = null; }
    const amounts = days.map(day => Number(day.amount) || 0);
    if (unavailable || !days.length || !amounts.some(amount => amount !== 0)) {
        target.innerHTML = unavailable
            ? emptyState('circle-alert', 'Chưa tải được doanh thu', 'Vui lòng tải lại trang để thử lại.')
            : emptyState('chart-no-axes-column-increasing', 'Chưa có doanh thu', 'Chưa ghi nhận thanh toán thành công trong 7 ngày qua.');
        target.setAttribute('aria-label', unavailable ? 'Chưa tải được doanh thu' : 'Doanh thu 7 ngày: chưa có doanh thu');
        return;
    }
    if (typeof ApexCharts === 'undefined') {
        target.innerHTML = emptyState('circle-alert', 'Chưa hiển thị được biểu đồ', 'Vui lòng tải lại trang để thử lại.');
        return;
    }
    target.innerHTML = '';
    target.setAttribute('aria-label', days.map(day => day.label + ': ' + formatMoney(day.amount)).join('; '));
    revenueChartInstance = new ApexCharts(target, {
        series: [{ name: 'Doanh thu', data: amounts }],
        chart: {
            type: 'bar', height: 208, fontFamily: 'Be Vietnam Pro, sans-serif',
            toolbar: { show: false }, background: 'transparent',
            animations: { enabled: !window.matchMedia('(prefers-reduced-motion: reduce)').matches, easing: 'easeinout', speed: 200 },
        },
        plotOptions: { bar: { borderRadius: 4, columnWidth: '38%' } },
        dataLabels: { enabled: false },
        xaxis: {
            categories: days.map(day => day.label),
            axisBorder: { show: false }, axisTicks: { show: false },
            labels: { style: { colors: '#6B7280', fontSize: '10px' } },
        },
        yaxis: {
            min: 0, tickAmount: 3,
            labels: { style: { colors: '#6B7280', fontSize: '10px' }, formatter: value => formatMoney(value, true) },
        },
        colors: ['#2563EB'],
        grid: { borderColor: '#E5E7EB', strokeDashArray: 4, padding: { left: 0, right: 4 }, yaxis: { lines: { show: true } }, xaxis: { lines: { show: false } } },
        tooltip: { y: { formatter: value => formatMoney(value) } },
        legend: { show: false },
    });
    revenueChartInstance.render();
}

function renderReviews(reviews, unavailable = false) {
    if (!reviews.length) {
        document.getElementById('reviewsWrap').innerHTML = unavailable
            ? emptyState('circle-alert', 'Chưa tải được đánh giá', 'Vui lòng tải lại trang để thử lại.')
            : emptyState('message-square-text', 'Không có đánh giá chờ duyệt', 'Các đánh giá cần xử lý sẽ xuất hiện tại đây.');
        return;
    }
    document.getElementById('reviewsWrap').innerHTML = reviews.slice(0, 5).map(review => {
        const initials = (review.user?.name || 'KH').split(' ').map(word => word[0]).slice(-2).join('').toUpperCase();
        const rating = Math.max(0, Math.min(5, Number(review.rating) || 0));
        const stars = Array.from({length: 5}, (_, index) => `<span class="${index < rating ? 'filled' : 'unfilled'}">${adminIcon('star')}</span>`).join('');
        return `<div class="review-item">
            <span class="review-avatar">${escapeHtml(initials)}</span>
            <div class="review-content">
                <div class="review-heading">
                    <span>${escapeHtml(review.user?.name ?? 'Ẩn danh')}</span>
                    <span class="review-stars" aria-label="${rating} trên 5 sao">${stars}</span>
                    <span class="review-hotel">${escapeHtml(review.hotel?.name ?? '')}</span>
                </div>
                <div class="review-text">${escapeHtml(review.comment ?? '')}</div>
                <div class="review-meta">${formatDate(review.created_at)}</div>
            </div>
            <div class="review-actions">
                <button type="button" class="btn-approve" disabled title="API hiện chưa hỗ trợ duyệt đánh giá">${adminIcon('check')} Duyệt</button>
                <button type="button" class="btn-reject" onclick="deleteReview(${Number(review.id)}, this)">${adminIcon('trash')} Xoá</button>
            </div>
        </div>`;
    }).join('');
}

// ══ Actions ══
async function approveReview(id, btn) {
    adminToast('Chức năng duyệt đánh giá chưa được API hỗ trợ.','warning');
}

async function deleteReview(id, btn) {
    if (!await adminConfirm({title:'Xóa đánh giá?',message:'Đánh giá này sẽ bị xóa. Thao tác không thể hoàn tác từ danh sách.',confirmText:'Xóa đánh giá'})) return;
    btn.disabled = true; btn.textContent = '...';
    try {
        const res = await adminApi('/reviews/' + id, { method: 'DELETE' });
        if (!res.ok) throw new Error();
        btn.closest('.review-item').style.opacity = '0.4';
        adminToast('Đã xoá đánh giá');
        setTimeout(() => btn.closest('.review-item').remove(), 400);
    } catch(_) {
        adminToast('Không thể xoá đánh giá');
        btn.disabled = false; btn.innerHTML = adminIcon('trash') + ' Xoá';
    }
}


function formatMoney(amount, compact = false) {
    const value = Number(amount) || 0;
    if (compact && Math.abs(value) >= 1_000_000) {
        return new Intl.NumberFormat('vi-VN', { notation: 'compact', maximumFractionDigits: 1 }).format(value) + ' ₫';
    }
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(value);
}
function formatDate(value) {
    return value ? new Date(value).toLocaleDateString('vi-VN', { day: 'numeric', month: 'short', year: 'numeric' }) : '';
}
function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
}
</script>
@endpush
