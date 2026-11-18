@php
    $navigationCities = isset($locations) ? $locations->pluck('name')->all()
        : ['Hà Nội', 'Hồ Chí Minh', 'Đà Nẵng', 'Phú Quốc', 'Nha Trang', 'Hội An', 'Đà Lạt', 'Huế'];
    // Switch these links only when the corresponding module has a registered page.
    $experiencesUrl = \Illuminate\Support\Facades\Route::has('experiences.index') ? route('experiences.index') : route('home');
    $guideUrl = \Illuminate\Support\Facades\Route::has('guides.index') ? route('guides.index') : route('blog');
    $searchContext = request()->only(['check_in', 'check_out', 'guests']);
    $headerSearchEnabled = trim($__env->yieldContent('header-search', 'enabled')) !== 'hidden';
@endphp
<nav class="hv-header hv-header--explore hv-ui navbar-glass fixed top-0 left-0 right-0 z-50" aria-label="Điều hướng chính" data-search-enabled="{{ $headerSearchEnabled ? 'true' : 'false' }}">
    <div class="hv-shell-container hv-header-row">
        @include('partials.layout.brand')
        <button type="button" id="compact-search-toggle" class="hv-compact-search" aria-controls="header-search-panel" aria-expanded="{{ $headerSearchEnabled ? 'true' : 'false' }}" @if(!$headerSearchEnabled) hidden @endif>
            <span class="hv-compact-place">
                <svg class="hv-compact-hotel-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 21V5l8-2 8 2v16M2 21h20M9 21v-5h6v5M8 7h1m6 0h1M8 10h1m6 0h1M8 13h1m6 0h1"/></svg>
                <span data-search-summary-place>Địa điểm bất kỳ</span>
            </span>
            <span data-search-summary-dates>Thêm ngày</span>
            <span data-search-summary-guests>2 khách</span>
            <svg class="hv-compact-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linecap="round" d="m21 21-4.5-4.5M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/></svg>
        </button>
        <div id="nav-auth">
            <button type="button" class="hv-auth-link hv-auth-link--primary" data-customer-login-open aria-haspopup="dialog" aria-controls="customer-login-dialog">Đăng nhập</button>
        </div>
        <button type="button" id="mobile-menu-toggle" onclick="toggleMobileMenu()" class="hv-icon-button hv-mobile-toggle" aria-label="Mở menu điều hướng" aria-haspopup="true" aria-expanded="false" aria-controls="mobile-menu">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>

    <div class="hv-header-experiences" id="header-experiences">
        <a href="{{ route('home') }}" class="nav-link" @if(request()->routeIs('home')) aria-current="page" @endif>Khách sạn</a>
        <div class="nav-dropdown">
            <a href="{{ $experiencesUrl }}" class="nav-link">Trải nghiệm</a>
            <button type="button" class="hv-destinations-toggle" data-shell-menu-toggle aria-expanded="false" aria-controls="header-destinations" aria-label="Mở điểm đến">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" aria-hidden="true"><path d="m5 7.5 5 5 5-5" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
        </div>
        <a href="{{ $guideUrl }}" class="nav-link" @if(request()->routeIs('blog', 'blog.detail', 'guides.*')) aria-current="page" @endif>Cẩm nang</a>
        <a href="/itineraries" class="nav-link" @if(request()->is('itineraries')) aria-current="page" @endif>Lịch trình AI</a>
    </div>

    <div id="header-destinations" class="hv-navigation-panel hv-shell-container" aria-label="Khách sạn theo điểm đến" hidden>
        <p class="hv-destinations-heading">Khách sạn theo điểm đến</p>
        <div class="hv-destinations-grid">
            @foreach($navigationCities as $city)
                <a href="{{ route('home', array_merge($searchContext, ['city' => $city])) }}" class="dropdown-item">{{ $city }}</a>
            @endforeach
        </div>
    </div>

    <div id="mobile-menu">
        <a href="{{ route('home') }}" class="mobile-nav-link">Khách sạn</a>
        <div class="hv-mobile-experience-row">
            <a href="{{ $experiencesUrl }}" class="mobile-nav-link">Trải nghiệm</a>
            <button type="button" class="hv-destinations-toggle" data-shell-menu-toggle aria-expanded="false" aria-controls="mobile-destinations" aria-label="Mở điểm đến">
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" aria-hidden="true"><path d="m5 7.5 5 5 5-5" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
        </div>
        <div id="mobile-destinations" class="hv-navigation-panel" aria-label="Khách sạn theo điểm đến" hidden>
            <p class="hv-destinations-heading">Khách sạn theo điểm đến</p>
            <div class="hv-destinations-grid">
                @foreach($navigationCities as $city)
                    <a href="{{ route('home', array_merge($searchContext, ['city' => $city])) }}" class="dropdown-item">{{ $city }}</a>
                @endforeach
            </div>
        </div>
        <a href="{{ $guideUrl }}" class="mobile-nav-link" @if(request()->routeIs('blog', 'blog.detail', 'guides.*')) aria-current="page" @endif>Cẩm nang</a>
        <a href="/itineraries" class="mobile-nav-link" @if(request()->is('itineraries')) aria-current="page" @endif>Lịch trình AI</a>
        <div data-guest-menu-links>
            <button type="button" class="mobile-nav-link hv-mobile-auth-button" data-customer-login-open aria-haspopup="dialog" aria-controls="customer-login-dialog">Đăng nhập</button>
        </div>
    </div>

    <div class="hv-search-panel" id="header-search-panel" @if(!$headerSearchEnabled) hidden inert @endif>
        @include('partials.layout.search')
    </div>
</nav>

<dialog id="customer-login-dialog" class="hv-login-dialog" aria-label="Tài khoản Angel">
    <div class="hv-login-dialog-panel">
        <button type="button" class="hv-login-dialog-close" data-customer-login-close aria-label="Đóng cửa sổ đăng nhập">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" stroke-width="1.8" stroke-linecap="round"/></svg>
        </button>
        <iframe id="customer-login-frame" title="Tài khoản Angel" src="about:blank"></iframe>
    </div>
</dialog>
