<script>
    // ============================================================
    // Quantity-based room selection for customer UI only
    // ============================================================

    function openBookingPreview(hotelId) {
        const token = localStorage.getItem('token');
        if (!token) { window.location.href = '/login'; return; }

        const checkIn = document.getElementById('check-in').value;
        const checkOut = document.getElementById('check-out').value;
        const guests = document.getElementById('guests').value;
        const roomId = document.getElementById('room-select').value;
        const roomName = document.getElementById('room-select-name').value;
        const price = document.getElementById('room-select-price').value;
        const errDiv = document.getElementById('booking-error');

        errDiv.hidden = true;
        errDiv.classList.remove('hidden');

        function bookingError(text) {
            errDiv.textContent = text;
            errDiv.hidden = false;
            errDiv.scrollIntoView({block:'center',behavior:'smooth'});
        }

        if (!roomId) {
            bookingError('Bạn chưa chọn loại phòng. Bấm “Chọn loại phòng” trong Lựa chọn lưu trú.');
            return;
        }

        if (!checkIn || !checkOut) {
            bookingError(!checkIn&&!checkOut?'Bạn chưa chọn ngày nhận phòng và ngày trả phòng.':!checkIn?'Bạn chưa chọn ngày nhận phòng.':'Bạn chưa chọn ngày trả phòng.');
            return;
        }

        if (new Date(checkOut) <= new Date(checkIn)) {
            bookingError('Ngày trả phòng phải sau ngày nhận phòng ít nhất 1 ngày.');
            return;
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (new Date(checkIn) < today) {
            bookingError('Ngày nhận phòng đã qua. Vui lòng chọn từ hôm nay trở đi.');
            return;
        }

        const nights = Math.ceil((new Date(checkOut) - new Date(checkIn)) / 86400000);
        const selectedQty = (() => {
            const type = selectedRoomTypes && selectedRoomTypes[parseInt(roomId)];
            return type ? type.quantity : 1;
        })();

        const quote = window.HolidayVietDetailPricing?.getQuote(Number(roomId));
        if (!quote || quote.quantity !== selectedQty || quote.nights !== nights) {
            bookingError('Đang lấy giá từng đêm cho lựa chọn này. Vui lòng chờ; nếu có lỗi, bấm “Tải lại giá và phòng trống”.');
            return;
        }
        const total = Number(quote.total_price);
        currentTotal = total;

        pendingBookingData = {
            hotel_id: hotelId,
            room_type_id: parseInt(roomId),
            room_id: null,
            check_in: checkIn,
            check_out: checkOut,
            num_guests: parseInt(guests),
            quantity: selectedQty,
            total_price: total,
            quote_hash: quote.quote_hash,
        };

        document.getElementById('co-hotel').textContent = document.querySelector('h1').textContent.trim();
        document.getElementById('co-room').textContent = roomName?.split('—')[0]?.trim();
        document.getElementById('co-checkin').textContent = checkIn;
        document.getElementById('co-checkout').textContent = checkOut;
        document.getElementById('co-nights').textContent = nights + ' đêm';
        document.getElementById('co-total').textContent = new Intl.NumberFormat('vi-VN').format(total) + 'đ';

        document.querySelector('input[value="cash"]').checked = true;
        document.getElementById('qr-section').classList.add('hidden');
        document.getElementById('qr-expired-section').classList.add('hidden');
        document.getElementById('cash-section').classList.remove('hidden');
        document.getElementById('co-error').classList.add('hidden');
        document.getElementById('co-confirm-btn').disabled = false;
        document.getElementById('co-confirm-btn').innerHTML = '<span>🏨</span> Xác nhận — Thanh toán tại quầy';
        stopQrCountdown();

        document.getElementById('payment-overlay').style.display = 'none';
        document.getElementById('overlay-loading').style.display = 'flex';
        document.getElementById('overlay-success').style.display = 'none';

        document.getElementById('checkoutModal').classList.remove('hidden');
        document.getElementById('checkoutModal').classList.add('flex');
    }

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !document.getElementById('checkoutModal').classList.contains('hidden')
            && typeof closeCheckout === 'function') closeCheckout();
    });
</script>
