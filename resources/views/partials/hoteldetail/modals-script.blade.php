<script>
    // ==========================================
    // LOGIC MODAL CHỌN PHÒNG CỤ THỂ
    // ==========================================
    function openRoomModal(hotelId, roomTypeId, roomTypeName, price) {

        // ✅ GUARD: Bắt buộc chọn ngày trước khi xem phòng
        const ci = document.getElementById('check-in').value;
        const co = document.getElementById('check-out').value;
        if (!ci || !co) {
            showToast('⚠️ Vui lòng chọn ngày nhận và trả phòng trước!');
            smoothScrollToElement('check-in');
            return;
        }


        modalCurrentHotelId = hotelId;
        modalCurrentRoomTypeId = roomTypeId;
        modalCurrentPrice = price;
        modalSelectedRoom = null;
        
        document.getElementById('modal-room-type-name').textContent = `Chọn phòng — ${roomTypeName}`;
        
        if (ci && co) {
        } else {
            document.getElementById('modal-room-checkin-info').textContent = 'Vui lòng chọn ngày ở sidebar để thấy giá';
        }
        
        document.getElementById('modal-selected-panel').classList.add('hidden');
        const confirmBtn = document.getElementById('modal-confirm-btn');
        confirmBtn.disabled = true;
        confirmBtn.classList.add('opacity-40', 'cursor-not-allowed');
        
        const modal = document.getElementById('roomModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        loadRoomsForType(hotelId, roomTypeId);
    }

    function closeRoomModal() {
        document.getElementById('roomModal').classList.add('hidden');
        document.getElementById('roomModal').classList.remove('flex');
        modalSelectedRoom = null;
    }

    async function loadRoomsForType(hotelId, roomTypeId) {
        const container = document.getElementById('modal-floors-container');
        container.innerHTML = '<p class="text-sm text-[#C9D3DD] text-center py-8">Đang tải...</p>';

        const ci = document.getElementById('check-in').value;
        const co = document.getElementById('check-out').value;

        const params = new URLSearchParams({ room_type_id: roomTypeId });
        if (ci) params.append('check_in', ci);
        if (co) params.append('check_out', co);

        try {
            const res = await api(`/api/hotels/${hotelId}/rooms?${params}`);
            const rooms = await res.json();
            renderRoomFloors(rooms);
        } catch (e) {
            container.innerHTML = '<p class="text-sm text-red-400 text-center py-8">Không tải được danh sách phòng.</p>';
        }
    }

    function renderRoomFloors(rooms) {
        const container = document.getElementById('modal-floors-container');
        
        if (!rooms.length) {
            container.innerHTML = '<p class="text-sm text-[#C9D3DD] text-center py-8">Không có phòng nào cho loại này.</p>';
            return;
        }
        
        const byFloor = {};
        rooms.forEach(r => {
            const floor = r.floor;
            if (!byFloor[floor]) byFloor[floor] = [];
            byFloor[floor].push(r);
        });
        
        const avail = rooms.filter(r => r.status === 'available').length;
        const locked = rooms.filter(r => r.status === 'locked').length;
        const booked = rooms.filter(r => r.status === 'booked').length;
        document.getElementById('modal-stat-avail').textContent = avail;
        document.getElementById('modal-stat-locked').textContent = locked;
        document.getElementById('modal-stat-booked').textContent = booked;
        
        const viewLabel = { sea: 'View biển', city: 'View thành phố', garden: 'View vườn', pool: 'View hồ bơi', mountain: 'View núi' };
        
        // ĐÂY LÀ ĐOẠN NÃY TAO LÀM LỖI, GIỜ ĐÃ FIX CHUẨN 100%
        container.innerHTML = Object.entries(byFloor).sort(([a], [b]) => a - b).map(([floor, floorRooms]) => `
            <div>
                <p class="text-xs font-semibold text-[#3A4A5A] mb-2">Tầng ${floor}</p>
                <div class="flex flex-wrap gap-2">
                    ${floorRooms.map(r => {
                        const isAvail = r.status === 'available';
                        const isLocked = r.status === 'locked';
                        const isBooked = r.status === 'booked';
                        
                        const bg = isAvail ? 'bg-green-50 border-green-400 cursor-pointer hover:border-green-600'
                            : isLocked ? 'bg-amber-50 border-amber-300 cursor-not-allowed opacity-80'
                            : 'bg-[#EAF3FF] border-[#C9D3DD] cursor-not-allowed opacity-50';
                        
                        const dot = isLocked ? '<div class="absolute top-1 right-1 w-2 h-2 rounded-full bg-amber-400"></div>' : '';
                        const icon = isBooked ? '<span class="text-xs text-[#C9D3DD]">✕</span>'
                            : isLocked ? '<span class="text-xs text-amber-500">⏳</span>'
                            : '';
                        
                        const clickFn = isAvail ? `selectModalRoom(${JSON.stringify(r).replace(/"/g, '&quot;')})` : '';
                        
                        return `
                            <button class="relative w-16 h-14 rounded-xl border flex flex-col items-center justify-center gap-0.5 transition-all ${bg}"
                                onclick="${clickFn}" id="room-btn-${r.id}" title="${viewLabel[r.view_type] || ''} · ${r.area_sqm || ''}m²">
                                ${dot} <span class="text-xs font-semibold text-[#3A4A5A]">${r.room_number}</span> ${icon}
                            </button>
                        `;
                    }).join('')}
                </div>
            </div>
        `).join('');
    }

    function selectModalRoom(room) {
        document.querySelectorAll('[id^="room-btn-"]').forEach(btn => {
            if (btn.id === `room-btn-${room.id}`) return;
            btn.classList.remove('border-[#87CEFA]', 'border-2', 'bg-[#EAF3FF]');
            if (!btn.classList.contains('cursor-not-allowed')) {
                btn.classList.add('bg-green-50', 'border-green-400');
            }
        });
        
        const btn = document.getElementById(`room-btn-${room.id}`);
        if (btn) {
            btn.classList.remove('bg-green-50', 'border-green-400', 'border');
            btn.classList.add('bg-[#EAF3FF]', 'border-[#87CEFA]', 'border-2');
        }
        
        modalSelectedRoom = room;
        
        const ci = document.getElementById('check-in').value;
        const co = document.getElementById('check-out').value;
        const nights = (ci && co) ? Math.ceil((new Date(co) - new Date(ci)) / 86400000) : 1;
        const fmt = n => new Intl.NumberFormat('vi-VN').format(n) + 'đ';
        const viewLabel = { sea: 'View biển', city: 'View thành phố', garden: 'View vườn', pool: 'View hồ bơi' };
        
        document.getElementById('modal-sel-name').textContent = `Phòng ${room.room_number}`;
        document.getElementById('modal-sel-meta').textContent = [viewLabel[room.view_type], room.area_sqm ? `${room.area_sqm}m²` : null].filter(Boolean).join(' · ');
        document.getElementById('modal-sel-price').textContent = `${fmt(modalCurrentPrice)}/đêm`;
        document.getElementById('modal-sel-total').textContent = nights > 1 ? `${nights} đêm = ${fmt(modalCurrentPrice * nights)}` : '';
        
        document.getElementById('modal-selected-panel').classList.remove('hidden');
        
        const confirmBtn = document.getElementById('modal-confirm-btn');
        confirmBtn.disabled = false;
        confirmBtn.classList.remove('opacity-40', 'cursor-not-allowed');
        
    }

    function confirmRoomSelection() {
        if (!modalSelectedRoom) return;

        // ==========================================
        // KHỐI 1: CẬP NHẬT DỮ LIỆU VÀO FORM ẨN 
        // Gán dữ liệu vào các thẻ input hidden ở sidebar để chuẩn bị submit
        // ==========================================
        document.getElementById('room-select').value = modalCurrentRoomTypeId;
        document.getElementById('room-select-price').value = modalCurrentPrice;
        
        // Gán trực tiếp ID phòng vào input có sẵn trên sidebar, tạo mới nếu chưa có
        let hiddenRoomInput = document.getElementById('selected-room-id');
        if (!hiddenRoomInput) {
            hiddenRoomInput = document.createElement('input');
            hiddenRoomInput.type = 'hidden';
            hiddenRoomInput.id = 'selected-room-id';
            document.body.appendChild(hiddenRoomInput);
        }
        hiddenRoomInput.value = modalSelectedRoom.id;


        // ==========================================
        // KHỐI 2: CẬP NHẬT GIAO DIỆN HIỂN THỊ
        // Lấy tên loại phòng và số phòng ghép lại để show ra cho đẹp
        // ==========================================
        const typeName = document.getElementById('modal-room-type-name').textContent.replace('Chọn phòng — ', '');
        const roomNumber = modalSelectedRoom.room_number;
        const fullRoomName = `${typeName} - Phòng ${roomNumber}`;

        document.getElementById('room-select-name').value = fullRoomName;

        const displayText = document.getElementById('room-type-display-text');
        if (displayText) {
            displayText.textContent = fullRoomName;
            displayText.classList.remove('italic', 'text-[#C9D3DD]');
            displayText.classList.add('font-semibold', 'text-[#3A4A5A]');
        }


        // ==========================================
        // KHỐI 3: TÍNH LẠI TIỀN VÀ ĐÓNG MODAL
        // ==========================================
        calcPrice();
        closeRoomModal();


        // ==========================================
        // KHỐI 4: LÀM MỚI LẠI BADGE LOẠI PHÒNG Ở PHÍA BÊN PHẢI (nếu có)
        // Vì có thể mày chọn 1 phòng rồi nhưng vẫn còn phòng khác cùng loại
        // Nên sau khi chọn xong thì refresh lại badge để nó cập nhật số lượng phòng còn trống
        // ==========================================
        refreshRoomTypeBadge(modalCurrentHotelId, modalCurrentRoomTypeId);

    }

    // ==========================================
    // LOGIC MODAL ĐẶT PHÒNG / THANH TOÁN
    // ==========================================
    async function openBookingPreview(hotelId) {
        const token = localStorage.getItem('token');
        if (!token) { window.location.href = '/login'; return; }
        
        const checkIn = document.getElementById('check-in').value;
        const checkOut = document.getElementById('check-out').value;
        const guests = document.getElementById('guests').value;
        const roomId = document.getElementById('room-select').value;
        const roomName = document.getElementById('room-select-name').value;
        const price = document.getElementById('room-select-price').value;
        const errDiv = document.getElementById('booking-error');
        
        errDiv.classList.add('hidden');

        if (!roomId) {
            errDiv.textContent = 'Vui lòng chọn loại phòng trước khi đặt!';
            errDiv.classList.remove('hidden');
            return;
        }
        
        if (!checkIn || !checkOut) {
            errDiv.textContent = 'Vui lòng chọn ngày nhận và trả phòng!';
            errDiv.classList.remove('hidden');
            return;
        }
        if (new Date(checkOut) <= new Date(checkIn)) {
            errDiv.textContent = 'Ngày trả phòng phải sau ngày nhận phòng!';
            errDiv.classList.remove('hidden');
            return;
        }
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (new Date(checkIn) < today) {
            errDiv.textContent = 'Ngày nhận phòng không được trong quá khứ!';
            errDiv.classList.remove('hidden');
            return;
        }
        
        const nights = Math.ceil((new Date(checkOut) - new Date(checkIn)) / 86400000);
        const total = currentTotal > 0 ? currentTotal : nights * parseInt(price);
        currentTotal = total;
        
        const selectedRoomId = document.getElementById('selected-room-id')?.value || null;

        if (!selectedRoomId || selectedRoomId === "") {
            errDiv.textContent = 'Vui lòng chọn phòng (VD: Phòng 101) trước khi đặt!';
            errDiv.classList.remove('hidden');
            return;
        }
        
        pendingBookingData = {
            hotel_id: hotelId,
            room_type_id: parseInt(roomId),
            room_id: selectedRoomId && !isNaN(parseInt(selectedRoomId)) ? parseInt(selectedRoomId) : null,
            check_in: checkIn,
            check_out: checkOut,
            num_guests: parseInt(guests),
            quantity: 1,
            total_price: total,
            voucher_code: appliedVoucher?.code || null,
            discount_amount: appliedVoucher ? (baseTotal - total) : 0,
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
        document.getElementById('co-confirm-btn').innerHTML = `<span>🏨</span> Xác nhận — Thanh toán tại quầy`;
        stopQrCountdown();
        
        document.getElementById('payment-overlay').style.display = 'none';
        document.getElementById('overlay-loading').style.display = 'flex';
        document.getElementById('overlay-success').style.display = 'none';
        
        document.getElementById('checkoutModal').classList.remove('hidden');
        document.getElementById('checkoutModal').classList.add('flex');
    }

    function closeCheckout() {
        document.getElementById('checkoutModal').classList.add('hidden');
        document.getElementById('checkoutModal').classList.remove('flex');
        document.getElementById('payment-overlay').style.display = 'none';
        document.getElementById('overlay-loading').style.display = 'flex';
        document.getElementById('overlay-success').style.display = 'none';
        stopQrCountdown();
        isConfirming = false;
    }

    async function confirmPayment() {
        if (isConfirming) return;
        if (!pendingBookingData) {
            document.getElementById('co-error').textContent = 'Có lỗi xảy ra, vui lòng thử lại!';
            document.getElementById('co-error').classList.remove('hidden');
            return;
        }
        
        const method = document.querySelector('input[name="payment_method"]:checked').value;
        const errDiv = document.getElementById('co-error');
        errDiv.classList.add('hidden');
        isConfirming = true;
        
        document.getElementById('payment-overlay').style.display = 'flex';
        document.getElementById('overlay-loading').style.display = 'flex';
        document.getElementById('overlay-success').style.display = 'none';
        
        const token = localStorage.getItem('token');
        if (!token) { window.location.href = '/login'; return; }
        
        try {
            const bookingRes = await api('/api/bookings', {
                method: 'POST',
                body: JSON.stringify({ ...pendingBookingData, payment_method: method })
            });
            const bookingData = await bookingRes.json();
            
            if (!bookingRes.ok) {
                document.getElementById('payment-overlay').style.display = 'none';
                errDiv.textContent = getApiErrorMessage(bookingData) || 'Đặt phòng thất bại!';
                errDiv.classList.remove('hidden');
                isConfirming = false;
                return;
            }
            
            currentBookingId = bookingData.booking.id;
            
            if (method === 'cash') {
                stopQrCountdown();
                document.getElementById('overlay-loading').style.display = 'none';
                document.getElementById('overlay-success').style.display = 'flex';
                document.getElementById('overlay-success-msg').textContent = `Booking #${currentBookingId} đã được giữ chỗ. Vui lòng thanh toán tại quầy.`;
                
                setTimeout(() => {
                    closeCheckout();
                    pendingBookingData = null;
                    const succDiv = document.getElementById('booking-success');
                    succDiv.textContent = `✅ Đặt phòng thành công! Mã booking #${currentBookingId} — Chờ xác nhận.`;
                    succDiv.classList.remove('hidden');
                    setTimeout(() => smoothScrollToElement('booking-success'), 200);
                }, 800);
            } else if (method === 'banking') {
                document.getElementById('payment-overlay').style.display = 'none';
                isConfirming = false;
                generateQR(currentTotal, currentBookingId);
                document.getElementById('qr-section').classList.remove('hidden');
                document.getElementById('cash-section').classList.add('hidden');
                startQrCountdown(currentBookingId);
                
                setTimeout(() => {
                    isConfirming = true;
                    document.getElementById('payment-overlay').style.display = 'flex';
                    document.getElementById('overlay-loading').style.display = 'none';
                    document.getElementById('overlay-success').style.display = 'flex';
                    document.getElementById('overlay-success-msg').textContent = `Thanh toán QR thành công! Mã booking: #${currentBookingId}`;
                    
                    setTimeout(() => {
                        closeCheckout();
                        pendingBookingData = null;
                        const succDiv = document.getElementById('booking-success');
                        succDiv.textContent = `✅ Thanh toán QR thành công! Mã booking #${currentBookingId}`;
                        succDiv.classList.remove('hidden');
                        setTimeout(() => smoothScrollToElement('booking-success'), 200);
                    }, 2500);
                }, 500);
            }
        } catch (error) {
            document.getElementById('payment-overlay').style.display = 'none';
            errDiv.textContent = 'Đã có lỗi mạng. Vui lòng thử lại.';
            errDiv.classList.remove('hidden');
            isConfirming = false;
        }
    }

    function generateQR(amount, bookingId) {
        const bank = 'VCB';
        const stk = '1033816978';
        const content = `BOOKING${bookingId}`;
        const qrUrl = `https://img.vietqr.io/image/${bank}-${stk}-compact2.png?amount=${amount}&addInfo=${content}&accountName=HOTEL%20BOOKING`;
        
        document.getElementById('qr-image').src = qrUrl;
        document.getElementById('qr-amount').textContent = new Intl.NumberFormat('vi-VN').format(amount) + 'đ';
        document.getElementById('qr-content').textContent = `Nội dung: ${content}`;
    }

    function startQrCountdown(bookingId) {
        stopQrCountdown();
        _qrSeconds = 10 * 60;
        updateCountdownDisplay(_qrSeconds);
        
        _qrTimer = setInterval(async () => {
            _qrSeconds--;
            updateCountdownDisplay(_qrSeconds);
            if (_qrSeconds <= 0) {
                stopQrCountdown();
                await autoExpireBooking(bookingId);
            }
        }, 1000);
    }

    function stopQrCountdown() {
        if (_qrTimer) { clearInterval(_qrTimer); _qrTimer = null; }
    }

    function updateCountdownDisplay(seconds) {
        const m = String(Math.floor(seconds / 60)).padStart(2, '0');
        const s = String(seconds % 60).padStart(2, '0');
        const el = document.getElementById('qr-countdown');
        if (!el) return;
        el.textContent = `${m}:${s}`;
        el.style.color = seconds < 120 ? '#ef4444' : '';
    }

    async function autoExpireBooking(bookingId) {
        try { await api(`/api/bookings/${bookingId}/cancel`, { method: 'POST' }); } catch(e) {}
        document.getElementById('qr-section').classList.add('hidden');
        document.getElementById('qr-expired-section').classList.remove('hidden');
        const btn = document.getElementById('co-confirm-btn');
        btn.disabled = true;
        btn.innerHTML = '⏰ Đã hết hạn — Vui lòng thử lại';
        btn.classList.add('opacity-50', 'cursor-not-allowed');
        pendingBookingData = null;
        currentBookingId = null;
        isConfirming = false;
    }

    // EVENT LISTENERS CỦA MODALS
    document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
        radio.addEventListener('change', function() {
            document.getElementById('qr-section').classList.add('hidden');
            document.getElementById('qr-expired-section').classList.add('hidden');
            document.getElementById('cash-section').classList.add('hidden');
            stopQrCountdown();
            
            const btn = document.getElementById('co-confirm-btn');
            btn.disabled = false;
            btn.classList.remove('opacity-50', 'cursor-not-allowed');
            
            if (this.value === 'cash') {
                document.getElementById('cash-section').classList.remove('hidden');
                btn.innerHTML = `<span>🏨</span> Xác nhận — Thanh toán tại quầy`;
            }
            if (this.value === 'banking') {
                generateQR(currentTotal, 'PREVIEW');
                document.getElementById('qr-section').classList.remove('hidden');
                btn.innerHTML = `<span>🏦</span> Xác nhận & Hiển thị QR chuyển khoản`;
            }
        });
    });

    document.getElementById('roomModal').addEventListener('click', function(e) {
        if (e.target === this) closeRoomModal();
    });

    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeCheckout(); });


    async function refreshRoomTypeBadge(hotelId, roomTypeId) {
    try {
        const res = await api(`/api/hotels/${hotelId}/rooms?room_type_id=${roomTypeId}`);
        const rooms = await res.json();
        const avail = rooms.filter(r => r.status === 'available').length;
        const total = rooms.length;

        // Cập nhật text "Tổng X | Trống X"
        const card = document.querySelector(`[data-room-type-id="${roomTypeId}"]`);
        if (!card) return;
        card.querySelector('.room-avail-badge-' + roomTypeId).textContent = avail;

        // Cập nhật badge "Còn X phòng"
        const badge = card.querySelector('.room-status-badge-' + roomTypeId);
        if (avail > 0) {
            badge.className = `room-status-badge-${roomTypeId} text-xs bg-green-50 text-green-700 border border-green-200 rounded-full px-2 py-0.5`;
            badge.textContent = `Còn ${avail} phòng`;
        } else {
            badge.className = `room-status-badge-${roomTypeId} text-xs bg-red-50 text-red-600 border border-red-200 rounded-full px-2 py-0.5`;
            badge.textContent = 'Hết phòng';
        }
    } catch(e) {}
}
</script>