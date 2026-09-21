<style>
    .voucher-card { background: linear-gradient(135deg, #1E3A5F, #0F3460); border-radius: 16px; padding: 20px 24px; display: flex; align-items: center; justify-content: space-between; position: relative; overflow: hidden; }
    .voucher-card::before { content: ''; position: absolute; right: -30px; top: 50%; transform: translateY(-50%); width: 80px; height: 80px; border-radius: 50%; background: rgba(255,255,255,0.05); }
    .voucher-dashed { border-left: 2px dashed rgba(255,255,255,0.2); padding-left: 20px; margin-left: 20px; }
</style>

<script>
function copyVoucher(code) {
    navigator.clipboard.writeText(code).then(() => {
        showToast('✅ Đã sao chép mã: ' + code);
    }).catch(() => {
        showToast('Mã: ' + code);
    });
}
</script>