@if(\App\Support\RoomTypeV2CheckoutGate::staging())
<script>
(() => {
    const modal=document.getElementById('v2-checkout');
    const button=document.getElementById('submit-booking-btn');
    const feedback=document.getElementById('v2-checkout-error');
    document.getElementById('booking-pending-note').textContent='Thanh toán và xác minh trong 15 phút. Môi trường nghiệm thu: không chuyển tiền thật.';
    let request=null; let busy=false;
    const text=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    button.addEventListener('click',event=>{
        event.preventDefault(); event.stopImmediatePropagation();
        if (busy || button.disabled) return;
        if (!localStorage.getItem('token')) { window.location.assign('/login'); return; }
        const type=HolidayVietDetailRooms.types.get(Number(document.getElementById('room-select').value));
        if (!type) return;
        request={hotel_id:HOTEL_ID,room_type_id:type.id,quantity:Number(document.getElementById('room-quantity').value),
            num_guests:Number(document.getElementById('guests').value),check_in:document.getElementById('check-in').value,check_out:document.getElementById('check-out').value};
        document.getElementById('v2-checkout-summary').innerHTML=`<p>${text(type.name)} × ${request.quantity} phòng</p><p>${text(request.check_in)} → ${text(request.check_out)} · ${request.num_guests} khách</p><strong>${text(document.getElementById('total-price').textContent)}</strong><p>Giá và tồn được kiểm tra lại tại server.</p>`;
        feedback.textContent=''; modal.showModal();
    },true);
    document.getElementById('v2-checkout-close').addEventListener('click',()=>modal.close());
    document.getElementById('v2-checkout-confirm').addEventListener('click',async()=>{
        if (!request || busy) return; busy=true; const confirm=document.getElementById('v2-checkout-confirm'); confirm.disabled=true;
        try {
            const response=await _layoutApi('/bookings',{method:'POST',body:JSON.stringify({...request,payment_method:document.getElementById('v2-payment-method').value})});
            const data=await response.json();
            if (!response.ok) { feedback.textContent=getApiErrorMessage(data); return; }
            if (!Number.isSafeInteger(data.booking?.id)) throw new Error('Missing Booking');
            window.location.assign('/payments/bookings/'+data.booking.id);
        } catch(e) { feedback.textContent='Chưa xác nhận được kết quả. Kiểm tra Lịch sử đặt phòng trước khi thử lại.'; }
        finally { busy=false; confirm.disabled=false; }
    });
})();
</script>
@endif
