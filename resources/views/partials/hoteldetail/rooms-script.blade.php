<script>
    function selectRoomTypeFromCard(roomTypeId, roomTypeName, price, cardEl) {
        document.getElementById('room-select').value = roomTypeId;
        document.getElementById('room-select-price').value = price;
        document.getElementById('room-select-name').value = roomTypeName;

        const displayText = document.getElementById('room-type-display-text');
        // displayText.textContent = roomTypeName + ' — ' + new Intl.NumberFormat('vi-VN').format(price) + 'đ/đêm';
        displayText.classList.remove('italic');
        displayText.classList.add('font-semibold');

        document.querySelectorAll('.room-item').forEach(el => el.classList.remove('border-[#87CEFA]', 'bg-[#EAF3FF]'));
        if (cardEl) cardEl.classList.add('border-[#87CEFA]', 'bg-[#EAF3FF]');

        calcPrice();
    }

    function viewRoom(roomTypeId, roomTypeName, price, hotelId, cardEl) {
        selectRoomTypeFromCard(roomTypeId, roomTypeName, price, cardEl);
        openRoomModal(hotelId, roomTypeId, roomTypeName, price);
    }
</script>