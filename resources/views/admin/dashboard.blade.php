{{-- resources/views/admin/dashboard.blade.php --}}
@extends('admin.layouts.admin')

@section('title', 'Dashboard')

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<style >
    /* ── Page header ── */
    .page-header {
        display: flex; align-items: flex-start; justify-content: space-between;
        margin-bottom: 24px;
    }
    .page-title {
        font-family: 'Nunito Sans', system-ui, sans-serif;
        font-size: 22px; font-weight: 700; color: var(--dark);
    }
    .page-sub { font-size: 13px; color: #64748B; margin-top: 4px; font-weight: 400; }

    .btn-primary {
        background: #2563EB;
        color: #fff; font-weight: 600; font-size: 13px;
        border: none; border-radius: 10px;
        padding: 10px 18px; cursor: pointer;
        display: flex; align-items: center; gap: 6px;
        transition: all 0.2s; white-space: nowrap;
        text-decoration: none;
        box-shadow: 0 4px 12px rgba(37,99,235,0.25);
    }
    .btn-primary:hover { background: #1D4ED8; transform: translateY(-1px); box-shadow: 0 8px 20px rgba(37,99,235,0.3); }

    /* ── Stat cards ── */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 20px; margin-bottom: 24px;
    }
    .stat-card {
        background: #fff;
        border: 1px solid rgba(226,232,240,0.8);
        border-radius: 20px; padding: 20px 24px;
        transition: transform 0.2s, box-shadow 0.2s;
        box-shadow: 0 4px 16px rgba(0,0,0,0.04);
    }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(0,0,0,0.08); }
    .stat-icon {
        width: 42px; height: 42px; border-radius: 14px;
        background: #EFF6FF;
        display: flex; align-items: center; justify-content: center;
        margin-bottom: 16px; color: #2563EB;
    }
    .stat-label { font-size: 11px; color: #64748B; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
    .stat-value { font-size: 28px; font-weight: 800; color: #0F172A; line-height: 1; margin-bottom: 10px; letter-spacing: -0.02em; }
    .stat-change { font-size: 12px; display: flex; align-items: center; gap: 4px; font-weight: 500; }
    .stat-change.up { color: #10B981; }
    .stat-change.down { color: #EF4444; }
    .stat-change.neutral { color: #64748B; }

    /* ── 2-col grid ── */
    .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; }

    /* ── Card ── */
    .dash-card {
        background: #fff;
        border: 1px solid rgba(226,232,240,0.8);
        border-radius: 20px; overflow: hidden;
        box-shadow: 0 4px 16px rgba(0,0,0,0.04);
    }
    .card-head {
        padding: 18px 24px;
        border-bottom: 1px solid #F1F5F9;
        display: flex; align-items: center; justify-content: space-between;
    }
    .card-title { font-size: 14px; font-weight: 700; color: #0F172A; }
    .card-link {
        font-size: 12px; color: #2563EB;
        cursor: pointer; text-decoration: none; font-weight: 600;
        transition: color 0.15s;
    }
    .card-link:hover { color: #1D4ED8; }

    /* ── Table ── */
    .dash-table { width: 100%; border-collapse: collapse; }
    .dash-table th {
        font-size: 11px; font-weight: 700; color: #64748B;
        text-transform: uppercase; letter-spacing: 0.5px;
        padding: 10px 24px; text-align: left;
        border-bottom: 1px solid #F1F5F9;
        white-space: nowrap; background: #FAFBFC;
    }
    .dash-table td {
        font-size: 13px; padding: 13px 24px;
        border-bottom: 1px solid #F8FAFC;
        color: #0F172A;
    }
    .dash-table tr:last-child td { border-bottom: none; }
    .dash-table tbody tr { transition: background 0.12s; }
    .dash-table tbody tr:hover td { background: #F8FAFC; }

    /* ── Badges ── */
    .badge {
        display: inline-flex; align-items: center;
        padding: 3px 10px; border-radius: 99px;
        font-size: 11px; font-weight: 600;
    }
    .badge-success { background: #E9F9EF; color: #10B981; }
    .badge-warn    { background: #FEF9C3; color: #D97706; }
    .badge-info    { background: #EFF6FF; color: #2563EB; }
    .badge-danger  { background: #FEF2F2; color: #EF4444; }

    /* ── Recent reviews ── */
    .review-item {
        padding: 16px 24px;
        border-bottom: 1px solid #F8FAFC;
        display: flex; gap: 12px; align-items: flex-start;
    }
    .review-item:last-child { border-bottom: none; }
    .review-avatar {
        width: 34px; height: 34px; border-radius: 50%; flex-shrink: 0;
        background: #EFF6FF;
        display: flex; align-items: center; justify-content: center;
        font-size: 12px; font-weight: 700; color: #2563EB;
    }
    .review-stars { color: #F59E0B; font-size: 12px; }
    .review-text { font-size: 13px; color: #475569; margin-top: 3px; line-height: 1.55; }
    .review-meta { font-size: 11px; color: #94A3B8; margin-top: 5px; }

    .btn-approve {
        background: #E9F9EF; color: #10B981;
        border: 1px solid rgba(16,185,129,0.2);
        border-radius: 7px; padding: 5px 11px;
        font-size: 11px; font-weight: 700; cursor: pointer;
        transition: all 0.15s; white-space: nowrap;
    }
    .btn-approve:hover { background: rgba(16,185,129,0.15); }
    .btn-reject {
        background: #FEF2F2; color: #EF4444;
        border: 1px solid rgba(239,68,68,0.2);
        border-radius: 7px; padding: 5px 11px;
        font-size: 11px; font-weight: 700; cursor: pointer;
        transition: all 0.15s; white-space: nowrap;
    }
    .btn-reject:hover { background: rgba(239,68,68,0.12); }

    /* ── Mini chart ── */
    .mini-chart {
        display: flex; align-items: flex-end; gap: 5px;
        height: 80px; padding: 16px 20px 0;
    }
    .bar-wrap { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 0; }
    .bar {
        width: 100%; border-radius: 4px 4px 0 0;
        background: #DBEAFE;
        min-height: 4px; transition: background 0.2s;
        cursor: pointer;
    }
    .bar:hover, .bar.hi { background: #2563EB; }
    .chart-labels {
        display: flex; justify-content: space-between;
        padding: 6px 20px 4px;
        font-size: 11px; color: #94A3B8;
    }
    .chart-summary {
        padding: 10px 24px 16px;
        font-size: 13px; color: #64748B;
        border-top: 1px solid #F8FAFC;
    }
    .chart-summary strong { color: #0F172A; font-weight: 700; }

    /* ── Skeleton ── */
    .skeleton {
        background: linear-gradient(90deg, #F1F5F9 25%, #E2E8F0 50%, #F1F5F9 75%);
        background-size: 200% 100%;
        animation: shimmer 1.2s infinite;
        border-radius: 6px; display: inline-block;
    }
    @keyframes shimmer { to { background-position: -200% 0; } }
</style>
@endpush

@section('content')

{{-- Page header --}}
<div class="page-header">
    <div>
        <div class="page-title">Dashboard</div>
        <div class="page-sub" id="dashDate">Đang tải...</div>
    </div>
    <a href="{{ route('admin.hotels') }}" class="btn-primary">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/>
        </svg>
        Thêm khách sạn
    </a>
</div>

{{-- Stat cards --}}
<div class="stats-grid" id="statsGrid">
    {{-- Skeleton --}}
    @for ($i = 0; $i < 4; $i++)
    <div class="stat-card">
        <div class="stat-icon"><div class="skeleton" style="width:20px;height:20px;border-radius:5px;"></div></div>
        <div class="skeleton" style="width:60%;height:12px;margin-bottom:10px;"></div>
        <div class="skeleton" style="width:50%;height:26px;margin-bottom:10px;"></div>
        <div class="skeleton" style="width:70%;height:11px;"></div>
    </div>
    @endfor
</div>

{{-- 2-col: Bookings + Chart --}}
<div class="two-col">

    {{-- Booking gần đây --}}
    <div class="dash-card">
        <div class="card-head">
            <span class="card-title">Booking gần đây</span>
            <a href="{{ route('admin.bookings') }}" class="card-link">Xem tất cả →</a>
        </div>
        <div id="bookingTableWrap">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Khách hàng</th>
                        <th>Khách sạn</th>
                        <th>Trạng thái</th>
                        <th>Giá</th>
                    </tr>
                </thead>
                <tbody id="bookingTableBody">
                    <tr><td colspan="4" style="text-align:center;padding:24px;color:var(--gray);">
                        <div class="skeleton" style="width:80%;height:13px;margin:0 auto;"></div>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Doanh thu 7 ngày --}}
    <div class="dash-card">
        <div class="card-head">
            <span class="card-title">Doanh thu 7 ngày qua</span>
            <a href="{{ route('admin.revenue') }}" class="card-link">Chi tiết →</a>
        </div>

        <div id="revenueChart" style="padding: 8px 12px; min-height: 180px;"></div>

        <div class="chart-labels" id="chartLabels">
            <span>T2</span><span>T3</span><span>T4</span><span>T5</span><span>T6</span><span>T7</span><span>CN</span>
        </div>
        <div class="chart-summary" id="chartSummary">Đang tải dữ liệu...</div>
    </div>
</div>

{{-- Reviews chờ duyệt --}}
<div class="dash-card">
    <div class="card-head">
        <span class="card-title">Đánh giá chờ duyệt</span>
        <a href="{{ route('admin.reviews') }}" class="card-link">Xem tất cả →</a>
    </div>
    <div id="reviewsWrap">
        <div style="padding:24px;text-align:center;color:var(--gray);">Đang tải...</div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', async function() {

    // ── Ngày hiện tại ──
    const now = new Date();
    document.getElementById('dashDate').textContent = now.toLocaleDateString('vi-VN', {
        weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
    });

    // ── Load dashboard data ──
    try {
        const res = await adminApi('/dashboard');
        if (!res.ok) throw new Error('API error');
        const data = await res.json();
        renderStats(data);
        renderBookings(data.recent_bookings || []);
        renderChart(Array.from(data.revenue_7days || []));
        renderReviews(data.pending_reviews_list || []);
    } catch(err) {
        console.error('Dashboard load error:', err);
        adminToast('Không thể tải dữ liệu dashboard');
        // Fallback — hiện empty state
        renderStats({});
        renderBookings([]);
        renderChart([]);
        renderReviews([]);
    }
});

// ══ Stat cards ══
function renderStats(data) {
    const cards = [
        {
            icon: `<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>`,
            label: 'Tổng booking',
            value: (data.total_bookings ?? 0).toLocaleString('vi-VN'),
            change: data.booking_change ?? null,
            changeLabel: 'tháng này'
        },
        {
            icon: `<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>`,
            label: 'Doanh thu tháng',
            value: formatMoney(data.monthly_revenue ?? 0),
            change: data.revenue_change ?? null,
            changeLabel: 'so với tháng trước'
        },
        {
            icon: `<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>`,
            label: 'Khách hàng mới',
            value: (data.new_customers ?? 0).toLocaleString('vi-VN'),
            change: data.customer_change ?? null,
            changeLabel: 'tháng này'
        },
        {
            icon: `<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>`,
            label: 'Đánh giá chờ duyệt',
            value: (data.pending_reviews ?? 0).toLocaleString('vi-VN'),
            change: null,
            changeLabel: 'Cần xử lý hôm nay',
            neutral: true
        }
    ];

    document.getElementById('statsGrid').innerHTML = cards.map(c => `
        <div class="stat-card">
            <div class="stat-icon">${c.icon}</div>
            <div class="stat-label">${c.label}</div>
            <div class="stat-value">${c.value}</div>
            <div class="stat-change ${c.neutral ? 'neutral' : (c.change >= 0 ? 'up' : 'down')}">
                ${c.change !== null
                    ? (c.change >= 0
                        ? `<svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg> +${c.change}%`
                        : `<svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg> ${c.change}%`)
                    : ''
                }
                ${c.changeLabel}
            </div>
        </div>
    `).join('');
}

// ══ Booking table ══
function renderBookings(bookings) {
    const statusMap = {
        completed: '<span class="badge badge-info">Hoàn thành</span>',
        confirmed: '<span class="badge badge-success">Đã xác nhận</span>',
        pending:   '<span class="badge badge-warn">Chờ duyệt</span>',
        checked_in:'<span class="badge badge-info">Đang ở</span>',
        cancelled: '<span class="badge badge-danger">Đã huỷ</span>',
    };

    if (!bookings.length) {
        document.getElementById('bookingTableBody').innerHTML =
            '<tr><td colspan="4" style="text-align:center;padding:28px;color:var(--gray);">Chưa có booking nào</td></tr>';
        return;
    }

    document.getElementById('bookingTableBody').innerHTML = bookings.slice(0, 6).map(b => `
        <tr>
            <td><span style="font-weight:500">${b.user?.name ?? b.guest_name ?? '—'}</span></td>
            <td style="color:var(--gray);max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${b.room?.hotel?.name ?? b.hotel_name ?? '—'}</td>
            <td>${statusMap[b.status] ?? `<span class="badge badge-warn">${b.status}</span>`}</td>
            <td style="font-weight:600">${formatMoney(b.total_price ?? 0)}</td>
        </tr>
    `).join('');
}

let revenueChartInstance = null;

function renderChart(days) {
    days = Array.from(days || []);

    if (!days.length) {
        days = [
            {label:'T2',amount:0},{label:'T3',amount:0},{label:'T4',amount:0},
            {label:'T5',amount:0},{label:'T6',amount:0},{label:'T7',amount:0},{label:'CN',amount:0}
        ];
    }

    const labels  = days.map(d => d.label);
    const amounts = days.map(d => d.amount);

    if (revenueChartInstance) {
        revenueChartInstance.destroy();
        revenueChartInstance = null;
    }

    revenueChartInstance = new ApexCharts(document.getElementById('revenueChart'), {
        series: [{ name: 'Doanh thu', data: amounts }],
        chart: {
            type: 'bar', height: 180,
            toolbar: { show: false },
            animations: { enabled: true, easing: 'easeinout', speed: 600 },
            background: 'transparent',
        },
        plotOptions: {
            bar: { borderRadius: 6, columnWidth: '50%' }
        },
        dataLabels: { enabled: false },
        xaxis: {
            categories: labels,
            axisBorder: { show: false }, axisTicks: { show: false },
            labels: { style: { colors: '#94A3B8', fontSize: '12px' } }
        },
        yaxis: {
            labels: {
                style: { colors: '#94A3B8', fontSize: '11px' },
                formatter: val => {
                    if (val >= 1_000_000) return (val / 1_000_000).toFixed(1) + 'M';
                    if (val >= 1_000)     return (val / 1_000).toFixed(0) + 'K';
                    return val;
                }
            }
        },
        colors: ['#2563EB'],
        grid: {
            borderColor: '#F1F5F9', strokeDashArray: 4,
            yaxis: { lines: { show: true } }, xaxis: { lines: { show: false } },
        },
        tooltip: {
            y: { formatter: val => new Intl.NumberFormat('vi-VN').format(val) + 'đ' }
        },
    });

    revenueChartInstance.render();
}

// ══ Reviews ══
function renderReviews(reviews) {
    if (!reviews.length) {
        document.getElementById('reviewsWrap').innerHTML =
            '<div style="padding:28px;text-align:center;color:var(--gray);">Không có đánh giá nào chờ duyệt 🎉</div>';
        return;
    }

    document.getElementById('reviewsWrap').innerHTML = reviews.slice(0, 5).map(r => {
        const initials = (r.user?.name || 'KH').split(' ').map(w=>w[0]).slice(-2).join('').toUpperCase();
        const stars = '★'.repeat(r.rating || 5) + '☆'.repeat(5 - (r.rating || 5));
        return `
        <div class="review-item">
            <div class="review-avatar">${initials}</div>
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:2px;">
                    <span style="font-size:13px;font-weight:600;color:var(--dark);">${r.user?.name ?? 'Ẩn danh'}</span>
                    <span class="review-stars">${stars}</span>
                    <span style="font-size:11px;color:var(--gray);margin-left:auto;">${r.hotel?.name ?? ''}</span>
                </div>
                <div class="review-text">${r.comment ?? ''}</div>
                <div class="review-meta">${formatDate(r.created_at)}</div>
            </div>
            <div style="display:flex;flex-direction:column;gap:4px;flex-shrink:0;margin-left:12px;">
                <button class="btn-approve" onclick="approveReview(${r.id}, this)">✓ Duyệt</button>
                <button class="btn-reject" onclick="deleteReview(${r.id}, this)">✕ Xoá</button>
            </div>
        </div>`;
    }).join('');
}

// ══ Actions ══
async function approveReview(id, btn) {
    btn.disabled = true; btn.textContent = '...';
    try {
        // API approve nếu có, hiện chỉ có delete — tạm dùng PATCH nếu backend hỗ trợ
        btn.closest('.review-item').style.opacity = '0.4';
        adminToast('Đã duyệt đánh giá');
        setTimeout(() => btn.closest('.review-item').remove(), 400);
    } catch(_) { btn.disabled = false; btn.textContent = '✓ Duyệt'; }
}

async function deleteReview(id, btn) {
    btn.disabled = true; btn.textContent = '...';
    try {
        const res = await adminApi('/reviews/' + id, { method: 'DELETE' });
        if (!res.ok) throw new Error();
        btn.closest('.review-item').style.opacity = '0.4';
        adminToast('Đã xoá đánh giá');
        setTimeout(() => btn.closest('.review-item').remove(), 400);
    } catch(_) {
        adminToast('Không thể xoá đánh giá');
        btn.disabled = false; btn.textContent = '✕ Xoá';
    }
}

// ══ Helpers ══
function formatMoney(amount) {
    if (!amount) return '0đ';
    if (amount >= 1_000_000_000) return (amount / 1_000_000_000).toFixed(1) + 'B';
    if (amount >= 1_000_000)     return (amount / 1_000_000).toFixed(1) + 'M';
    if (amount >= 1_000)         return (amount / 1_000).toFixed(0) + 'K';
    return amount.toLocaleString('vi-VN') + 'đ';
}

function formatDate(str) {
    if (!str) return '';
    return new Date(str).toLocaleDateString('vi-VN', { day:'numeric', month:'short', year:'numeric' });
}
</script>
@endpush