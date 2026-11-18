<dialog class="hv-detail-dialog hv-room-dialog" id="room-detail-modal" aria-labelledby="room-modal-title">
    <div class="hv-detail-dialog-heading">
        <h2 id="room-modal-title">Chi tiết loại phòng</h2>
        <button type="button" class="hv-detail-dialog-close" data-detail-dialog-close aria-label="Đóng chi tiết phòng">×</button>
    </div>
    <div class="hv-room-modal-body">
        <div class="hv-modal-photo-gallery">
            <div class="hv-modal-photo-stage">
                <img id="room-modal-photo" alt="Ảnh loại phòng">
                <button type="button" class="hv-photo-arrow hv-photo-arrow--prev" id="room-photo-prev" aria-label="Ảnh phòng trước">‹</button>
                <button type="button" class="hv-photo-arrow hv-photo-arrow--next" id="room-photo-next" aria-label="Ảnh phòng tiếp theo">›</button>
            </div>
            <p class="hv-photo-counter" id="room-photo-counter" aria-live="polite"></p>
            <div class="hv-photo-thumbnails" id="room-photo-thumbnails" aria-label="Chọn ảnh phòng"></div>
        </div>
        <div class="hv-room-modal-info">
            <p id="room-modal-description"></p>
            <dl class="hv-room-modal-facts" id="room-modal-facts"></dl>
            <div class="hv-room-modal-note"><h3>Tiện nghi riêng</h3><p id="room-modal-amenities">Chưa có thông tin tiện nghi riêng theo loại phòng.</p></div>
            <div class="hv-room-modal-note"><h3>Chính sách</h3><p>Chính sách trẻ em và các yêu cầu đặc biệt cần được khách sạn xác nhận.</p></div>
            <p class="hv-room-availability" id="room-modal-availability" role="status"></p>
        </div>
    </div>
    <div class="hv-room-modal-footer">
        <div><p class="hv-room-price"><strong id="room-modal-price"></strong> <span id="room-modal-price-label">/ đêm</span></p><p class="hv-booking-help" id="room-modal-stay-total" hidden></p></div>
        <button type="button" class="hv-button hv-button--primary" id="room-modal-select">Chọn phòng này</button>
    </div>
</dialog>
<dialog class="hv-detail-dialog hv-hotel-photo-dialog" id="hotel-photos-modal" aria-labelledby="hotel-photos-title">
    <div class="hv-detail-dialog-heading">
        <h2 id="hotel-photos-title">Ảnh {{ $hotel->name }}</h2>
        <button type="button" class="hv-detail-dialog-close" data-detail-dialog-close aria-label="Đóng ảnh khách sạn">×</button>
    </div>
    <div class="hv-modal-photo-stage">
        <img id="hotel-modal-photo" alt="Ảnh khách sạn">
        <button type="button" class="hv-photo-arrow hv-photo-arrow--prev" id="hotel-photo-prev" aria-label="Ảnh khách sạn trước">‹</button>
        <button type="button" class="hv-photo-arrow hv-photo-arrow--next" id="hotel-photo-next" aria-label="Ảnh khách sạn tiếp theo">›</button>
    </div>
    <p class="hv-photo-counter" id="hotel-photo-counter" aria-live="polite"></p>
    <div class="hv-photo-thumbnails" id="hotel-photo-thumbnails" aria-label="Chọn ảnh khách sạn"></div>
</dialog>
