<section class="hv-room-section" id="hotel-room-types" aria-labelledby="room-types-heading">
    <div class="hv-detail-section-heading">
        <h2 id="room-types-heading">Các loại phòng có sẵn tại {{ $hotel->name }}</h2>
        <p>Sức chứa là tổng số khách mỗi phòng. Chọn ngày lưu trú để kiểm tra số phòng trống.</p>
    </div>
    <div class="hv-room-list">
        @forelse($detailRoomTypes as $type)
            <article class="hv-room-card" data-room-type-id="{{ $type['id'] }}" data-room-selected="false">
                <button type="button" class="hv-room-cover" data-open-room="{{ $type['id'] }}" aria-label="Xem chi tiết phòng {{ $type['name'] }}">
                    <img src="{{ $type['cover'] }}" data-accommodation-image alt="Loại phòng {{ $type['name'] }}" width="480" height="360" loading="lazy">
                </button>
                <div class="hv-room-card-content">
                    <div class="hv-room-card-heading">
                        <h3><button type="button" data-open-room="{{ $type['id'] }}">{{ $type['name'] }}</button></h3>
                        <span class="hv-room-selected-label" data-room-selection-label hidden>Đã chọn</span>
                    </div>
                    <ul class="hv-room-facts">
                        @if($type['capacity'])<li>Tối đa {{ $type['capacity'] }} khách/phòng</li>@endif
                        @if($type['bed_type'])<li>{{ ['Double' => 'Giường đôi', 'King' => 'Giường king', 'Twin' => 'Giường twin'][$type['bed_type']] ?? $type['bed_type'] }}</li>@endif
                    </ul>
                    @if($type['description'])<p class="hv-room-description">{{ $type['description'] }}</p>@endif
                    <p class="hv-room-availability" data-room-availability role="status">Chọn ngày để kiểm tra phòng trống</p>
                    <div class="hv-room-card-bottom">
                        <p class="hv-room-price" data-room-price>
                            @if($type['price'] > 0)<strong>{{ number_format($type['price'], 0, ',', '.') }} ₫</strong> <span>/ đêm</span>
                            @else<span>Chưa có thông tin giá</span>@endif
                        </p>
                        <p class="hv-booking-help" data-room-stay-total hidden></p>
                        <button type="button" class="hv-button" data-open-room="{{ $type['id'] }}">Xem chi tiết</button>
                    </div>
                </div>
            </article>
        @empty<p class="hv-detail-empty">Chưa có loại phòng để hiển thị.</p>@endforelse
    </div>
</section>
