<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Tài khoản') — Angel</title>
    <link rel="stylesheet" href="{{ asset('fonts/admin/be-vietnam-pro.css') }}">
    <script>try { document.documentElement.dataset.accountMotion = localStorage.getItem('aurel_account_motion') === 'reduced' ? 'reduced' : 'system'; } catch {}</script>
    <link rel="stylesheet" href="{{ asset('css/aurel-account.css') }}?v={{ filemtime(public_path('css/aurel-account.css')) }}">
    @include('partials.accommodation-images')
    @yield('styles')
</head>
<body class="account-ui @yield('body-class')" data-icon-url="{{ asset('vendor/aurel-account/icons.svg') }}">
<a class="skip-link" href="#accountMain">Đến nội dung chính</a>
@php
    $accountNavigation = [
        ['key' => 'profile', 'href' => '/profile', 'label' => 'Thông tin cá nhân', 'icon' => 'user-round', 'active' => request()->is('profile')],
        ['key' => 'bookings', 'href' => '/bookings', 'label' => 'Lịch sử đặt phòng', 'icon' => 'calendar-check', 'active' => request()->is('bookings') || request()->is('payments/bookings/*')],
        ['key' => 'itineraries', 'href' => '/itineraries', 'label' => 'Lịch trình AI', 'icon' => 'route', 'active' => request()->is('itineraries')],
        ['key' => 'password', 'href' => '/password', 'label' => 'Bảo mật tài khoản', 'icon' => 'shield', 'active' => request()->is('password')],
        ['key' => 'settings', 'href' => '/settings', 'label' => 'Cài đặt', 'icon' => 'sliders', 'active' => request()->is('settings')],
    ];
@endphp
<aside class="sidebar" id="sidebar" aria-label="Tài khoản khách hàng">
    <div class="sidebar-brand">
        <a href="/" aria-label="Angel — Trang chủ">@include('partials.brand-logo')</a>
        <button type="button" class="icon-button sidebar-close" id="accountMenuClose" onclick="closeSidebar()" aria-label="Đóng menu">@include('user.partials.icon', ['name'=>'x'])</button>
    </div>
    <div class="sidebar-caption">TÀI KHOẢN CỦA BẠN</div>
    <nav class="sidebar-nav" aria-label="Điều hướng tài khoản">
        @foreach ($accountNavigation as $item)
        <a href="{{ $item['href'] }}" data-nav="{{ $item['key'] }}" class="sidebar-nav-item {{ $item['active'] ? 'active' : '' }}" @if($item['active']) aria-current="page" @endif>
            @include('user.partials.icon', ['name' => $item['icon']])
            <span>{{ $item['label'] }}</span>
        </a>
        @endforeach
    </nav>
    <div class="sidebar-bottom">
        <div class="sidebar-user"><div class="sidebar-avatar"><img id="sidebarAvatarImg" alt="" hidden><span id="sidebarAvatarInitial">?</span></div><div class="sidebar-user-info"><strong id="sidebarUserName">Tài khoản của bạn</strong></div></div>
        <button type="button" class="sidebar-logout" onclick="confirmLogout()">@include('user.partials.icon', ['name'=>'log-out']) <span>Đăng xuất</span></button>
    </div>
</aside>
<button type="button" id="sidebarOverlay" class="sidebar-overlay" onclick="closeSidebar()" aria-label="Đóng menu tài khoản" tabindex="-1"></button>
<div class="account-workspace">
    <header class="topbar"><div class="topbar-start"><button type="button" id="accountMenuToggle" class="icon-button menu-toggle" onclick="toggleSidebar()" aria-label="Mở menu tài khoản" aria-controls="sidebar" aria-expanded="false">@include('user.partials.icon', ['name'=>'menu'])</button><span>Không gian của bạn</span></div><div class="topbar-actions"><a class="home-link" href="/">Khám phá khách sạn @include('user.partials.icon', ['name'=>'arrow-up-right'])</a>
        <div class="account-notifications" id="notif-bell-wrap">
            <button type="button" id="notif-bell" class="icon-button" onclick="toggleNotifDropdown(event)" aria-label="Thông báo" aria-haspopup="true" aria-expanded="false" aria-controls="notif-dropdown"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg><span id="notif-badge" class="notif-badge"></span></button>
            <div id="notif-dropdown" class="notif-dropdown" role="region" aria-label="Thông báo"><div class="account-notif-heading"><strong>Thông báo</strong><button type="button" onclick="markAllRead()">Đánh dấu đã đọc</button></div><div id="notif-list"><p class="account-notif-empty">Đang tải thông báo…</p></div><div id="notif-footer"></div></div>
        </div>
    </div></header>
    <main class="main-content" id="accountMain" tabindex="-1"><div class="page-shell">@yield('content')</div></main>
    <footer class="account-footer"><span>Angel</span><span>Mỗi chuyến đi, một trải nghiệm riêng.</span></footer>
</div>
<div id="toastContainer" class="toast-container" aria-live="polite" aria-relevant="additions"></div>
<div id="confirmModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="confirmModalTitle" aria-describedby="confirmModalDesc">
    <div class="modal-box"><div class="modal-icon" id="confirmModalIcon">@include('user.partials.icon', ['name'=>'circle-alert'])</div><h2 id="confirmModalTitle"></h2><p id="confirmModalDesc"></p><div class="modal-actions"><button type="button" id="confirmModalCancelBtn" class="button secondary" onclick="closeConfirmModal()">Quay lại</button><button type="button" id="confirmModalOkBtn" class="button">Xác nhận</button></div></div>
</div>
@include('user.layouts.account-script')
@include('components.notification-script')
@yield('scripts')
</body>
</html>
