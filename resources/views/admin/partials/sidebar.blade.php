{{-- The existing route names, account hooks and badge API are preserved. --}}
@php
    $groups = [
        ['label' => 'Tổng quan', 'items' => [
            ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'layout-dashboard'],
            ['label' => 'Báo cáo doanh thu', 'route' => 'admin.revenue', 'icon' => 'chart-no-axes-column-increasing'],
        ]],
        ['label' => 'Vận hành', 'items' => [
            ['label' => 'Khách sạn', 'route' => 'admin.hotels', 'icon' => 'hotel'],
            ['label' => 'Khách hàng', 'route' => 'admin.customers', 'icon' => 'users-round'],
            ['label' => 'Đánh giá', 'route' => 'admin.reviews', 'icon' => 'star', 'badge' => 'js-review-badge'],
            ['label' => 'Đặt phòng', 'route' => 'admin.bookings', 'icon' => 'calendar-days', 'badge' => 'js-booking-badge'],
        ]],
        ['label' => 'Marketing', 'items' => [
            ['label' => 'Trải nghiệm', 'route' => 'admin.experiences', 'icon' => 'compass'],
            ['label' => 'Ưu đãi & Deal', 'icon' => 'tag'],
            ['label' => 'Cẩm nang', 'route' => 'admin.guides', 'icon' => 'notebook-pen'],
        ]],
        ['label' => 'Hệ thống', 'items' => [
            ['label' => 'Cài đặt', 'icon' => 'settings-2'],
        ]],
    ];
@endphp
<aside class="admin-sidebar" id="adminSidebar" aria-label="Điều hướng quản trị">
    <div class="sb-brand-area">
        <a href="{{ route('home') }}" class="sb-brand" aria-label="Angel — về website">
            <span class="sb-logo-full">@include('partials.brand-logo')</span>
            <span class="sb-logo-mark">
                <img src="{{ asset(config('brand.logo_url')) }}" alt="Angel" width="2172" height="724" decoding="async">
            </span>
        </a>
        <button type="button" class="admin-icon-button sb-mobile-close" id="sidebarClose" aria-label="Đóng menu điều hướng">
            @include('admin.partials.icon', ['name' => 'x'])
        </button>
    </div>
    <button type="button" class="sb-edge-toggle" id="sidebarCollapse" aria-controls="adminSidebar" aria-expanded="true" aria-label="Thu gọn menu" data-sidebar-tooltip="Thu gọn menu">
        @include('admin.partials.icon', ['name' => 'panel-left-close', 'iconClass' => 'sb-collapse-icon'])
        @include('admin.partials.icon', ['name' => 'panel-left-open', 'iconClass' => 'sb-expand-icon'])
    </button>
    <nav class="sb-nav" aria-label="Chức năng Admin">
        @foreach ($groups as $group)
        <div class="sb-group">
            <span class="sb-group-label">{{ $group['label'] }}</span>
            @foreach ($group['items'] as $item)
            @php $active = isset($item['route']) && request()->routeIs($item['route']); @endphp
            <a href="{{ isset($item['route']) ? route($item['route']) : '#' }}"
               class="sb-link {{ $active ? 'active' : '' }}"
               aria-label="{{ $item['label'] }}" data-sidebar-tooltip="{{ $item['label'] }}"
               @if ($active) aria-current="page" @endif
               @unless (isset($item['route'])) aria-disabled="true" tabindex="-1" @endunless>
                <span class="sb-icon-wrap">@include('admin.partials.icon', ['name' => $item['icon']])</span>
                <span class="sb-label">{{ $item['label'] }}</span>
                @isset ($item['badge'])
                <span class="sb-badge {{ $item['badge'] }}" style="display:none"></span>
                @endisset
                @unless (isset($item['route']))
                <span class="sb-soon">Sắp có</span>
                @endunless
            </a>
            @endforeach
        </div>
        @endforeach
    </nav>
    <button type="button" class="sb-user" onclick="adminLogout()" aria-label="Đăng xuất Admin" data-sidebar-tooltip="Đăng xuất">
        <span class="sb-user-avatar js-avatar-initials">AD</span>
        <span class="sb-user-info">
            <span class="sb-user-name js-admin-name" style="display:block">Đang tải...</span>
            <span class="sb-user-role js-admin-role" style="display:block">—</span>
        </span>
        @include('admin.partials.icon', ['name' => 'log-out', 'iconClass' => 'sb-user-logout'])
    </button>
</aside>

<script>
// Load pending booking count + review count cho badge sidebar
document.addEventListener('DOMContentLoaded', async function() {
    try {
        const res = await adminApi('/dashboard');
        if (!res.ok) return;
        const data = await res.json();

        // Booking pending badge
        const pending = data.pending_bookings ?? 0;
        const bookingBadge = document.querySelector('.js-booking-badge');
        if (bookingBadge && pending > 0) {
            bookingBadge.textContent = pending;
            bookingBadge.style.display = 'inline-flex';
        }

        // Review pending badge
        const reviewPending = data.pending_reviews ?? 0;
        const reviewBadge = document.querySelector('.js-review-badge');
        if (reviewBadge && reviewPending > 0) {
            reviewBadge.textContent = reviewPending;
            reviewBadge.style.display = 'inline-flex';
        }
    } catch(_) {}
});
</script>
