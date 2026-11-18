<script>
(function() {
    const token = localStorage.getItem('token');
    if (!token) { window.location.href = '/login'; return; }
    try { if (!JSON.parse(localStorage.getItem('user') || '{}').id) window.location.href = '/login'; }
    catch { window.location.href = '/login'; }
})();
    function api(path, opts = {}) {
        return fetch('/api' + path, {
            ...opts,
            headers: {
                'Content-Type':  'application/json',
                'Authorization': 'Bearer ' + localStorage.getItem('token'),
                'Accept':        'application/json',
                ...(opts.headers || {})
            }
        });
    }

    let logoutPending = false;
    async function doLogout() {
        if (logoutPending) return;
        const token = localStorage.getItem('token');
        if (!token) {
            showToast('Không tìm thấy phiên đăng nhập để xác nhận thu hồi. Vui lòng đăng nhập lại.', 'error');
            return;
        }
        logoutPending = true;
        const button = document.querySelector('.sidebar-logout');
        if (button) button.disabled = true;
        try {
            const response = await api('/logout', { method: 'POST', headers: { Authorization: 'Bearer ' + token }, signal: AbortSignal.timeout(15000) });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.revoked !== true) throw new Error(data.message || 'Máy chủ chưa xác nhận thu hồi phiên đăng nhập.');
            if (localStorage.getItem('token') === token) {
                ['token', 'user', 'activities', 'notif_settings'].forEach(key => localStorage.removeItem(key));
                window.location.href = '/login';
            }
        } catch (error) {
            showToast('Chưa xác nhận đăng xuất. ' + (error.name === 'TimeoutError' || error.name === 'TypeError' ? 'Vui lòng kiểm tra kết nối và thử lại.' : error.message), 'error');
        } finally {
            logoutPending = false;
            if (button) button.disabled = false;
        }
    }


function accountEscape(value) { return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
const accountIconNames = new Set('user-round calendar-check calendar-days route lock settings-2 log-out menu x arrow-right plus wallet star check trash circle-alert camera eye download clock mail phone chevron-left chevron-right refresh spinner moon door pin shield sliders search info document motion message-square-text hotel'.split(' '));
function accountIcon(name) {
    const icon = accountIconNames.has(name) ? name : 'info';
    return '<svg class="account-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="' + accountEscape(document.body.dataset.iconUrl) + '#' + icon + '"></use></svg>';
}
function accountLoading(message) { return '<div class="loading-state" role="status">' + accountIcon('spinner') + '<span>' + accountEscape(message) + '</span></div>'; }
function accountErrorMessage(data, fallback) { return Object.values(data.errors || {}).flat().find(v => typeof v === 'string') || data.message || fallback; }
function loadUserUI() {
    let user = {}; try { user = JSON.parse(localStorage.getItem('user') || '{}'); } catch {}
    document.getElementById('sidebarUserName').textContent = user.name || 'Tài khoản của bạn';
    const initial = document.getElementById('sidebarAvatarInitial'), img = document.getElementById('sidebarAvatarImg');
    initial.textContent = (user.name || '?').charAt(0).toUpperCase();
    img.hidden = !user.avatar_url; initial.hidden = !!user.avatar_url;
    if (user.avatar_url) img.src = user.avatar_url;
    img.onerror = () => { img.hidden = true; initial.hidden = false; };
}
function setActiveNav() {
    const path = ('/' + location.pathname.split('/').filter(Boolean).join('/')).replace(/\/$/, '') || '/';
    const key = {'/profile':'profile','/bookings':'bookings','/itineraries':'itineraries','/password':'password','/settings':'settings'}[path] || (/^\/payments\/bookings\/\d+$/.test(path) ? 'bookings' : null);
    document.querySelectorAll('.sidebar-nav-item[data-nav]').forEach(el => { el.classList.toggle('active', el.dataset.nav === key); if (el.dataset.nav === key) el.setAttribute('aria-current','page'); else el.removeAttribute('aria-current'); });
}
const accountMobile = matchMedia('(max-width: 1023px)');
let accountSidebarReturn = null;
function syncSidebar() {
    const sidebar = document.getElementById('sidebar');
    const open = accountMobile.matches && sidebar.classList.contains('open');
    document.getElementById('sidebarOverlay').classList.toggle('open', open);
    document.getElementById('accountMenuToggle').setAttribute('aria-expanded', String(open));
    document.body.classList.toggle('sidebar-open', open);
    sidebar.inert = accountMobile.matches && !open;
    if (accountMobile.matches) sidebar.setAttribute('aria-hidden', String(!open)); else sidebar.removeAttribute('aria-hidden');
}
function toggleSidebar() {
    if (!accountMobile.matches) return;
    const sidebar = document.getElementById('sidebar');
    accountSidebarReturn = document.activeElement;
    sidebar.classList.toggle('open'); syncSidebar();
    if (sidebar.classList.contains('open')) document.getElementById('accountMenuClose').focus();
}
function closeSidebar() {
    const wasOpen = document.getElementById('sidebar').classList.contains('open');
    document.getElementById('sidebar').classList.remove('open'); syncSidebar();
    if (wasOpen && accountSidebarReturn?.isConnected) accountSidebarReturn.focus();
}
accountMobile.addEventListener('change', () => {
    document.getElementById('sidebar').classList.remove('open');
    syncSidebar();
    accountSidebarReturn = null;
});
document.querySelectorAll('.sidebar-nav-item').forEach(link => link.addEventListener('click', () => {
    if (accountMobile.matches) closeSidebar();
}));
function _showAlert(prefix, type, message) {
    _clearAlerts(prefix);
    const el = document.getElementById(prefix + (type === 'success' ? 'Success' : 'Error'));
    const label = document.getElementById(prefix + (type === 'success' ? 'SuccessMsg' : 'ErrorMsg'));
    if (label) label.textContent = String(message ?? '');
    if (el) { el.classList.add('show'); el.setAttribute('role', type === 'error' ? 'alert' : 'status'); }
}
function _clearAlerts(prefix) { ['Success','Error'].forEach(type => document.getElementById(prefix + type)?.classList.remove('show')); }
function showToast(message, type = 'info', subtitle = '') {
    const container = document.getElementById('toastContainer');
    while (container.children.length > 2) container.firstElementChild.remove();
    const toast = document.createElement('div'); toast.className = 'toast toast-' + (['success','error','warning','info'].includes(type) ? type : 'info');
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
    toast.innerHTML = accountIcon(type === 'success' ? 'check' : type === 'error' ? 'circle-alert' : 'info') + '<div class="toast-body"><div class="toast-title"></div><div class="toast-msg"></div></div><button type="button" class="icon-button toast-close" aria-label="Đóng thông báo">' + accountIcon('x') + '</button>';
    toast.querySelector('.toast-title').textContent = String(message ?? ''); toast.querySelector('.toast-msg').textContent = String(subtitle ?? '');
    toast.querySelector('button').onclick = () => toast.remove(); container.appendChild(toast);
    setTimeout(() => toast.remove(), 6000);
}
let _confirmCallback = null, accountConfirmBusy = false, accountModalReturn = null;
function showConfirmModal(title, desc, onConfirm, options = {}) {
    document.getElementById('confirmModalTitle').textContent = title; document.getElementById('confirmModalDesc').textContent = desc;
    const button = document.getElementById('confirmModalOkBtn'); button.textContent = options.confirmText || 'Xác nhận';
    button.classList.toggle('danger', options.type !== 'warning'); _confirmCallback = onConfirm;
    accountModalReturn = document.activeElement; document.getElementById('confirmModal').classList.add('open'); document.body.classList.add('modal-open');
    document.getElementById('confirmModalCancelBtn').focus();
}
function closeConfirmModal() {
    if (accountConfirmBusy) return;
    document.getElementById('confirmModal').classList.remove('open'); document.body.classList.remove('modal-open'); _confirmCallback = null;
    if (accountModalReturn?.isConnected) accountModalReturn.focus();
}
document.getElementById('confirmModalOkBtn').addEventListener('click', async () => {
    if (accountConfirmBusy || !_confirmCallback) return;
    accountConfirmBusy = true; const button = document.getElementById('confirmModalOkBtn'), cancel = document.getElementById('confirmModalCancelBtn'), label = button.textContent;
    button.disabled = cancel.disabled = true; button.textContent = 'Đang xử lý…'; let close = false;
    try { close = (await _confirmCallback()) !== false; } catch { showToast('Chưa thể thực hiện. Vui lòng thử lại.', 'error'); }
    finally { accountConfirmBusy = false; button.disabled = cancel.disabled = false; button.textContent = label; if (close) closeConfirmModal(); }
});
document.getElementById('confirmModal').addEventListener('click', event => { if (event.target === event.currentTarget) closeConfirmModal(); });
function confirmLogout() { showConfirmModal('Đăng xuất khỏi Angel?', 'Bạn có thể đăng nhập lại để tiếp tục quản lý đặt phòng và lịch trình.', async () => { await doLogout(); return false; }, {confirmText:'Đăng xuất',type:'warning'}); }
function accountTrapFocus(event, element) {
    if (event.key !== 'Tab') return;
    const items = [...element.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex="0"]')].filter(el => el.getClientRects().length);
    if (!items.length) { event.preventDefault(); return; }
    const first = items[0], last = items.at(-1);
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
}
document.addEventListener('keydown', event => {
    const modal = document.getElementById('confirmModal');
    if (modal.classList.contains('open')) { if (event.key === 'Escape') closeConfirmModal(); accountTrapFocus(event, modal); }
    else if (document.body.classList.contains('sidebar-open')) { if (event.key === 'Escape') closeSidebar(); accountTrapFocus(event, document.getElementById('sidebar')); }
});
function accountLegacyNavigation() { if (location.pathname.replace(/\/$/,'') === '/profile' && location.hash === '#password') location.replace('/password'); }
function applyAccountMotion(value) { document.documentElement.dataset.accountMotion = value === 'reduced' ? 'reduced' : 'system'; }
window.addEventListener('hashchange', accountLegacyNavigation);
document.addEventListener('DOMContentLoaded', () => { loadUserUI(); setActiveNav(); syncSidebar(); accountLegacyNavigation(); });
</script>
