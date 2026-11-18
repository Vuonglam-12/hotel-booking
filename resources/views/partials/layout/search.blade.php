<div class="hv-shell-container">
    <form class="hv-search-form" id="heroSearchBar" method="GET" action="{{ route('home') }}" autocomplete="off" aria-label="Tìm khách sạn" aria-describedby="hotel-search-note">
        <div class="hv-search-field hv-search-destination">
            <label for="searchInput">Địa điểm</label>
            <input type="text" id="searchInput" name="search" maxlength="100" value="{{ request('city', request('search', '')) }}" placeholder="Tìm địa phương hoặc khách sạn" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="searchDropdown">
            <input type="hidden" id="searchCity" name="city" value="{{ request('city', '') }}" disabled>
            <input type="hidden" id="searchLocationId" name="location_id" value="{{ request('location_id', '') }}" @disabled(!request('location_id'))>
            <input type="hidden" id="searchHotelId" name="hotel_id" value="{{ request('hotel_id', '') }}" @disabled(!request('hotel_id'))>
        </div>
        <div class="hv-search-field hv-search-dates">
            <button type="button" class="hv-search-trigger" id="search-dates-toggle" aria-expanded="false" aria-controls="search-dates-panel" aria-haspopup="dialog">
                <span class="hv-search-label">Ngày lưu trú</span>
                <span class="hv-search-value" id="search-dates-value">Thêm ngày</span>
            </button>
        </div>
        <div class="hv-search-field hv-search-guests">
            <button type="button" class="hv-search-trigger" id="search-guests-toggle" aria-expanded="false" aria-controls="search-guests-panel" aria-haspopup="dialog">
                <span class="hv-search-label">Số khách</span>
                <span class="hv-search-value" id="search-guests-value">{{ request('guests', 2) }} khách</span>
            </button>
            <button type="submit" class="hv-search-submit" aria-label="Tìm kiếm khách sạn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-width="2" stroke-linecap="round" d="m21 21-4.5-4.5M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z"/></svg>
                <span>Tìm kiếm</span>
            </button>
        </div>
        <div id="searchDropdown" class="hv-search-dropdown hidden" role="listbox" aria-label="Gợi ý địa điểm và khách sạn"></div>
        {{-- Keep the existing GET fields and native validation behind the pickers. --}}
        <input class="hv-search-native" type="date" id="checkInInput" name="check_in" min="{{ \Carbon\CarbonImmutable::today('Asia/Ho_Chi_Minh')->toDateString() }}" value="{{ request('check_in', '') }}" tabindex="-1" aria-label="Ngày nhận phòng">
        <input class="hv-search-native" type="date" id="checkOutInput" name="check_out" min="{{ \Carbon\CarbonImmutable::today('Asia/Ho_Chi_Minh')->toDateString() }}" value="{{ request('check_out', '') }}" tabindex="-1" aria-label="Ngày trả phòng">
        <input class="hv-search-native" type="number" id="guestsInput" name="guests" min="1" max="20" step="1" required value="{{ request('guests', 2) }}" tabindex="-1" aria-label="Tổng số khách">
        <input type="hidden" id="searchAdults" name="adults" value="{{ request('adults', request('guests', 2)) }}">
        <input type="hidden" id="searchChildren" name="children" value="{{ request('children', 0) }}">
        <input type="hidden" id="searchInfants" name="infants" value="{{ request('infants', 0) }}">
        @if(request('star'))
            <input type="hidden" name="star" value="{{ request('star') }}">
        @endif

        <section class="hv-search-popover hv-date-picker" id="search-dates-panel" role="dialog" aria-labelledby="search-calendar-title" hidden>
            <div class="hv-picker-heading">
                <h2 id="search-calendar-title">Chọn ngày lưu trú</h2>
                <button type="button" class="hv-picker-close" data-search-picker-close aria-label="Đóng lịch">×</button>
            </div>
            <p class="hv-search-error" id="search-picker-error" role="alert" hidden></p>
            <p class="hv-calendar-hint" id="search-calendar-hint" aria-live="polite">Chọn ngày nhận phòng, sau đó chọn ngày trả phòng.</p>
            <div class="hv-calendar-navigation">
                <button type="button" class="hv-calendar-arrow" id="search-calendar-prev" aria-label="Tháng trước">‹</button>
                <button type="button" class="hv-calendar-arrow" id="search-calendar-next" aria-label="Tháng sau">›</button>
            </div>
            <div class="hv-calendar-months" id="search-calendar-months"></div>
            <div class="hv-calendar-selection">
                <button type="button" data-date-boundary="check_in"><span>Nhận phòng</span><strong id="search-checkin-value">Chọn ngày</strong></button>
                <button type="button" data-date-boundary="check_out"><span>Trả phòng</span><strong id="search-checkout-value">Chọn ngày</strong></button>
            </div>
            <div class="hv-picker-actions">
                <button type="button" class="hv-picker-clear" id="search-dates-clear">Xóa ngày</button>
                <button type="button" class="hv-button hv-button--primary" id="search-dates-done">Xong</button>
            </div>
        </section>

        <section class="hv-search-popover hv-guest-picker" id="search-guests-panel" role="dialog" aria-labelledby="search-guests-title" aria-describedby="search-guests-note" hidden>
            <div class="hv-picker-heading">
                <h2 id="search-guests-title">Khách lưu trú</h2>
                <button type="button" class="hv-picker-close" data-search-picker-close aria-label="Đóng số khách">×</button>
            </div>
            @foreach(['adults' => ['Người lớn', 'Khách lưu trú'], 'children' => ['Trẻ em', 'Đi cùng gia đình'], 'infants' => ['Em bé', 'Đi cùng gia đình']] as $kind => $labels)
                <div class="hv-guest-row">
                    <div><h3>{{ $labels[0] }}</h3><p>{{ $labels[1] }}</p></div>
                    <div class="hv-guest-counter" role="group" aria-label="Số {{ mb_strtolower($labels[0]) }}">
                        <button type="button" data-guest-kind="{{ $kind }}" data-guest-step="-1" aria-label="Giảm số {{ mb_strtolower($labels[0]) }}">−</button>
                        <output data-guest-count="{{ $kind }}" aria-live="polite">{{ $kind === 'adults' ? request('guests', 2) : 0 }}</output>
                        <button type="button" data-guest-kind="{{ $kind }}" data-guest-step="1" aria-label="Tăng số {{ mb_strtolower($labels[0]) }}">+</button>
                    </div>
                </div>
            @endforeach
            <p class="hv-guest-note" id="search-guests-note">Tổng số khách gồm người lớn, trẻ em và em bé. Chính sách độ tuổi và thú cưng tùy từng khách sạn.</p>
            <div class="hv-picker-actions"><button type="button" class="hv-button hv-button--primary" data-search-picker-close>Xong</button></div>
        </section>
    </form>
    <p class="hv-search-note" id="hotel-search-note">Chọn ngày và số khách để tìm khách sạn còn phòng phù hợp.</p>
</div>
