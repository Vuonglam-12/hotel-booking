<footer class="hv-footer hv-ui">
    <div class="hv-shell-container">
        <div class="hv-footer-content">
            <div class="hv-footer-brand">
                @include('partials.layout.brand')
                <p class="hv-footer-description">Tìm khách sạn phù hợp, khám phá trải nghiệm và chuẩn bị hành trình cùng Angel.</p>
            </div>
            <div>
                <h2>Khám phá</h2>
                <ul>
                    <li><a href="{{ route('home') }}">Khách sạn</a></li>
                    <li><a href="{{ route('home', ['city' => 'Hà Nội']) }}">Hà Nội</a></li>
                    <li><a href="{{ route('home', ['city' => 'Đà Nẵng']) }}">Đà Nẵng</a></li>
                    <li><a href="{{ route('deals') }}">Ưu đãi</a></li>
                </ul>
            </div>
            <div>
                <h2>Về Angel</h2>
                <ul>
                    <li><a href="/itineraries">Lịch trình AI</a></li>
                    <li><a href="{{ \Illuminate\Support\Facades\Route::has('guides.index') ? route('guides.index') : route('blog') }}">Cẩm nang du lịch</a></li>
                </ul>
            </div>
        </div>
        <div class="hv-footer-bottom">
            <p>© {{ date('Y') }} Angel. Built by Nguyễn Trần Quốc Anh.</p>
        </div>
    </div>
</footer>
