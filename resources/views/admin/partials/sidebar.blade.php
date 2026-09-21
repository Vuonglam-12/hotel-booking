{{-- resources/views/admin/partials/sidebar.blade.php --}}
<aside class="admin-sidebar">

    <nav class="sb-nav">

        {{-- ── Tổng quan ── --}}
        <div class="sb-group">
            <span class="sb-group-label">Tổng quan</span>

            <a href="{{ route('admin.dashboard') }}" class="sb-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <span class="sb-icon-wrap">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                        <rect x="14" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="14" width="7" height="7" rx="1"/>
                        <rect x="14" y="14" width="7" height="7" rx="1"/>
                    </svg>
                </span>
                Dashboard
            </a>

            <a href="{{ route('admin.revenue') }}" class="sb-link {{ request()->routeIs('admin.revenue') ? 'active' : '' }}">
                <span class="sb-icon-wrap">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <line x1="18" y1="20" x2="18" y2="10"/>
                        <line x1="12" y1="20" x2="12" y2="4"/>
                        <line x1="6" y1="20" x2="6" y2="14"/>
                    </svg>
                </span>
                Báo cáo doanh thu
            </a>
        </div>

        <div class="sb-separator"></div>

        {{-- ── Vận hành ── --}}
        <div class="sb-group">
            <span class="sb-group-label">Vận hành</span>

            <a href="{{ route('admin.hotels') }}" class="sb-link {{ request()->routeIs('admin.hotels') ? 'active' : '' }}">
                <span class="sb-icon-wrap">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                </span>
                Khách sạn
            </a>

            <a href="{{ route('admin.customers') }}" class="sb-link {{ request()->routeIs('admin.customers') ? 'active' : '' }}">
                <span class="sb-icon-wrap">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                    </svg>
                </span>
                Khách hàng
            </a>

            <a href="{{ route('admin.reviews') }}" class="sb-link {{ request()->routeIs('admin.reviews') ? 'active' : '' }}">
                <span class="sb-icon-wrap">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                </span>
                Đánh giá
                <span class="sb-badge sb-badge-red js-review-badge" style="display:none"></span>
            </a>

            <a href="{{ route('admin.bookings') }}" class="sb-link {{ request()->routeIs('admin.bookings') ? 'active' : '' }}">
                <span class="sb-icon-wrap">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <rect x="2" y="5" width="20" height="14" rx="2"/>
                        <line x1="2" y1="10" x2="22" y2="10"/>
                    </svg>
                </span>
                Đặt phòng
                <span class="sb-badge sb-badge-red js-booking-badge" style="display:none"></span>
            </a>
        </div>

        <div class="sb-separator"></div>

        {{-- ── Marketing ── --}}
        <div class="sb-group">
            <span class="sb-group-label">Marketing</span>

            <a href="#" class="sb-link sb-link-dim">
                <span class="sb-icon-wrap">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/>
                        <line x1="7" y1="7" x2="7.01" y2="7"/>
                    </svg>
                </span>
                Ưu đãi & Deal
                <span class="sb-soon">Soon</span>
            </a>

            <a href="#" class="sb-link sb-link-dim">
                <span class="sb-icon-wrap">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M4 4h16v16H4z"/>
                        <line x1="8" y1="8" x2="16" y2="8"/>
                        <line x1="8" y1="12" x2="16" y2="12"/>
                        <line x1="8" y1="16" x2="12" y2="16"/>
                    </svg>
                </span>
                Blog
                <span class="sb-soon">Soon</span>
            </a>
        </div>

        <div class="sb-separator"></div>

        {{-- ── Hệ thống ── --}}
        <div class="sb-group">
            <span class="sb-group-label">Hệ thống</span>

            <a href="#" class="sb-link sb-link-dim">
                <span class="sb-icon-wrap">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>
                    </svg>
                </span>
                Nhân viên
                <span class="sb-soon">Soon</span>
            </a>

            <a href="#" class="sb-link sb-link-dim">
                <span class="sb-icon-wrap">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/>
                    </svg>
                </span>
                Cài đặt
                <span class="sb-soon">Soon</span>
            </a>
        </div>

    </nav>

    {{-- ── User footer ── --}}
    <div class="sb-user" onclick="adminLogout()">
        <div class="sb-user-avatar js-avatar-initials">AD</div>
        <div class="sb-user-info">
            <div class="sb-user-name js-staff-name">Đang tải...</div>
            <div class="sb-user-role js-staff-role">—</div>
        </div>
        <svg class="sb-user-logout" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/>
        </svg>
    </div>
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