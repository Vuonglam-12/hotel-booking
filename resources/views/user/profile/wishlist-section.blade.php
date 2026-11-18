<section class="card profile-wishlist" aria-labelledby="profileWishlistTitle">
    <div class="card-header">
        <h2 id="profileWishlistTitle" tabindex="-1">
            <svg class="profile-wishlist-heart" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/></svg>
            Khách sạn yêu thích
        </h2>
        <span id="profileWishlistCount" class="profile-wishlist-count" hidden></span>
    </div>
    <div class="card-body" id="profileWishlistBody" aria-busy="true">
        <div id="profileWishlistStatus" role="status" aria-live="polite">
            <div class="loading-state">@include('user.partials.icon', ['name' => 'spinner']) Đang tải khách sạn yêu thích…</div>
        </div>
        <div id="profileWishlistList" class="profile-wishlist-grid" hidden></div>
        <div class="profile-wishlist-footer" id="profileWishlistFooter" hidden>
            <button type="button" class="button secondary" id="profileWishlistMore" aria-controls="profileWishlistList" aria-expanded="false">Xem thêm</button>
        </div>
    </div>
</section>
