<script>

    async function applyVoucher() {

        const code = document.getElementById('voucher-input').value.trim().toUpperCase();

        const statusEl = document.getElementById('voucher-status');

       

        if (!code) return;

       

        await new Promise(r => setTimeout(r, 400));

       

        const voucher = MOCK_VOUCHERS[code];

       

        if (!voucher) {

            statusEl.className = 'mt-2 text-xs rounded-lg px-3 py-2 bg-red-50 text-red-600 border border-red-200';

            statusEl.textContent = '❌ Mã voucher không hợp lệ hoặc đã hết hạn';

            statusEl.classList.remove('hidden');

            appliedVoucher = null;

            calcPrice();

            return;

        }

       

        if (baseTotal > 0 && baseTotal < voucher.min) {

            statusEl.className = 'mt-2 text-xs rounded-lg px-3 py-2 bg-amber-50 text-amber-700 border border-amber-200';

            statusEl.textContent = `⚠️ Đơn tối thiểu ${new Intl.NumberFormat('vi-VN').format(voucher.min)}đ để dùng mã này`;

            statusEl.classList.remove('hidden');

            appliedVoucher = null;

            calcPrice();

            return;

        }

       

        appliedVoucher = { code, ...voucher };

        statusEl.className = 'mt-2 text-xs rounded-lg px-3 py-2 bg-green-50 text-green-700 border border-green-200';

        statusEl.textContent = `✅ ${voucher.label} — đã áp dụng!`;

        statusEl.classList.remove('hidden');

        calcPrice();

    }



    // HÀM RESET TẤT CẢ KHI ĐỔI NGÀY

    function resetSelectedRoom() {

        // Xóa giá trị ID phòng thật

        const hiddenRoomInput = document.getElementById('selected-room-id');

        if (hiddenRoomInput) hiddenRoomInput.value = '';



        // Reset text hiển thị trên giao diện (Đập nát cú lừa Superior đi)

        const nameInput = document.getElementById('room-select-name');

        if (nameInput) nameInput.value = '';

       

        // Nếu mày dùng thẻ <div> chứa <span> như cũ

        const displayText = document.getElementById('room-type-display-text');

        if (displayText) {

            displayText.textContent = '← Vui lòng chọn lại phòng';

            displayText.classList.add('italic', 'text-[#C9D3DD]');

            displayText.classList.remove('font-semibold', 'text-[#3A4A5A]');

        }

       

        // Nếu mày dùng thẻ <input> (như trong hình)

        const displayInput = document.getElementById('room-type-display');

        if (displayInput && displayInput.tagName === 'INPUT') {

            displayInput.value = '';

            displayInput.placeholder = '← Vui lòng chọn phòng';

        }

    }



    function calcPrice() {

        const checkIn = document.getElementById('check-in').value;

        const checkOut = document.getElementById('check-out').value;

        const price = parseFloat(document.getElementById('room-select-price').value) || 0;

        const estimateEl = document.getElementById('price-estimate');

        const submitBtn = document.getElementById('submit-booking-btn'); // Nút Đặt ngay

       

        // 🔥 CHỐT CHẶN: Chưa có Số Phòng (VD: 101) là dẹp hết!

        const selectedRoomId = document.getElementById('selected-room-id')?.value;

       

        // Nếu thiếu 1 trong 4 yếu tố này thì tắt bảng tính tiền và khóa nút Đặt

        if (!checkIn || !checkOut || !price || !selectedRoomId || selectedRoomId === "") {

            estimateEl.classList.add('hidden'); // Giấu bảng tính tiền

            submitBtn.classList.add('opacity-50', 'cursor-not-allowed'); // Làm mờ nút

            return;

        }

       

        const nights = Math.ceil((new Date(checkOut) - new Date(checkIn)) / 86400000);

        if (nights <= 0) {

            estimateEl.classList.add('hidden');

            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');

            return;

        }

       

        // Nếu đã đủ điều kiện thì mở khóa nút Đặt phòng

        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');



        baseTotal = nights * price;

        currentTotal = baseTotal;

       

        const fmt = n => new Intl.NumberFormat('vi-VN').format(n) + 'đ';

       

        document.getElementById('price-label-nights').textContent = `${fmt(price)} × ${nights} đêm`;

        document.getElementById('price-subtotal').textContent = fmt(baseTotal);

       

        const discountRow = document.getElementById('voucher-discount-row');

        if (appliedVoucher) {

            let discount = 0;

            if (appliedVoucher.type === 'percent') {

                discount = Math.round(baseTotal * appliedVoucher.value / 100);

            } else {

                discount = appliedVoucher.value;

            }

            discount = Math.min(discount, baseTotal);

            currentTotal = baseTotal - discount;

           

            document.getElementById('voucher-discount-label').textContent = `Voucher ${appliedVoucher.code}`;

            document.getElementById('voucher-discount-amount').textContent = `-${fmt(discount)}`;

            discountRow.classList.remove('hidden');

        } else {

            discountRow.classList.add('hidden');

        }

       

        document.getElementById('total-price').textContent = fmt(currentTotal);

        estimateEl.classList.remove('hidden');

    }



    async function toggleWishlistDetail(hotelId, btn) {

        const token = localStorage.getItem('token');

        if (!token) { window.location.href = '/login'; return; }

        const res = await api(`/wishlist/${hotelId}/toggle`, { method: 'POST' });

        const data = await res.json();

        btn.innerHTML = data.liked ?

            '<svg class="w-4 h-4" fill="currentColor" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg> Đã lưu yêu thích' :

            '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg> Lưu yêu thích';

        btn.style.color = data.liked ? '#ef4444' : '';

        showToast(data.message);

    }



    // Lắng nghe sự kiện đổi ngày, số lượng khách

    document.getElementById('check-in').addEventListener('change', () => {
        resetSelectedRoom();
        calcPrice();
    });
    document.getElementById('check-out').addEventListener('change', () => {
        resetSelectedRoom();
        calcPrice();
    });
    document.getElementById('guests').addEventListener('change', calcPrice);

    document.getElementById('voucher-input').addEventListener('keydown', e => { if (e.key === 'Enter') applyVoucher(); });



    // Set min date cho input check-in, check-out

    const today = new Date().toISOString().split('T')[0];

    document.getElementById('check-in').min = today;

    document.getElementById('check-out').min = today;

</script>