<script>
function mountBankingVerification(booking) {
    const root=document.createElement('div'); root.className='detail-section';
    const payments=(booking.payments||[]).filter(p=>p.payment_method==='banking');
    root.innerHTML=payments.map(p=>`<div class="detail-section-title">QR Banking #${p.id}: ${escHtml(p.display_status||p.payment_status)}</div>
        ${p.payment_status==='success' ? `<p>${escHtml(p.verification_outcome||'Đã ghi nhận')}</p>` : `
        <p>Chỉ xác nhận sau khi kiểm tra tiền thực nhận trên sao kê ngân hàng. Ảnh khách gửi không phải bằng chứng xác nhận.</p>
        <div class="detail-grid">
            <label class="detail-item">Reference giao dịch<input id="bank-reference-${p.id}" type="text" maxlength="100" autocomplete="off"></label>
            <label class="detail-item">Số tiền nhận<input id="bank-amount-${p.id}" type="text" inputmode="decimal" value="${escHtml(p.amount)}"></label>
            <label class="detail-item">Thời gian nhận tiền<input id="bank-time-${p.id}" type="datetime-local" step="1"></label>
        </div>
        <label><input id="bank-attest-${p.id}" type="checkbox"> Tôi đã kiểm tra tiền thực nhận trong tài khoản ngân hàng.</label>
        <button type="button" class="btn-primary" id="bank-submit-${p.id}" onclick="verifyBankingPayment(${booking.id},${p.id})">Xác nhận giao dịch ngân hàng</button>`}`).join('');
    document.getElementById('modalBody').append(root);
    if (booking.banking_policy) root.insertAdjacentHTML('beforeend',`<p>${escHtml(booking.banking_policy.warning)}</p><a href="/admin/reconciliation">Kiểm tra đối soát và email</a>`);
    const states=(booking.effect_delivery||[]).map(e=>`#${e.id}: ${escHtml(e.display_state||e.state)}`).join(', ');
    if (states) root.insertAdjacentHTML('beforeend',`<p>Gửi xác nhận: ${states}</p>`);
}
async function verifyBankingPayment(bookingId,paymentId) {
    const reference=document.getElementById('bank-reference-'+paymentId).value.trim();
    const amount=document.getElementById('bank-amount-'+paymentId).value.trim();
    const time=document.getElementById('bank-time-'+paymentId).value;
    const received=document.getElementById('bank-attest-'+paymentId).checked;
    if (!reference || !amount || !time || !received) { showToast('Điền đủ dữ liệu sao kê và xác nhận đã kiểm tra tiền nhận.'); return; }
    const button=document.getElementById('bank-submit-'+paymentId); button.disabled=true;
    try {
        const res=await adminApi(`/bookings/${bookingId}/payments/${paymentId}/verify-banking`,{method:'POST',body:JSON.stringify({
            reference,amount,received_at:new Date(time).toISOString().replace(/\.\d{3}Z$/,'+00:00'),funds_received:true,evidence_source:'bank_statement'})});
        const data=await res.json();
        if (!res.ok) { showToast(data.message||'Không xác minh được giao dịch.'); return; }
        showToast(data.outcome==='success'?'Đã xác nhận đặt phòng.':'Tiền đã ghi nhận; cần đối soát, không khôi phục đặt phòng.');
        closeModalDirect(); loadBookings();
    } catch (e) { showToast('Chưa xác nhận kết quả; kiểm tra lại trước khi gửi lại.'); }
    finally { button.disabled=false; }
}
</script>
