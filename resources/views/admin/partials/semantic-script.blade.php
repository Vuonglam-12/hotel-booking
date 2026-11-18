<script>
(() => {
    const actions = {
        add: ['primary','plus'], save: ['primary','save'], edit: ['primary','pencil'], view: ['primary','eye'],
        delete: ['danger','trash'], archive: ['neutral','archive'], restore: ['success','archive-restore'],
        publish: ['success','check'], approve: ['success','check'], activate: ['success','check'],
        pause: ['neutral','pause'], cancel: ['neutral','x'], reject: ['danger','x'], search: ['primary','search'], refresh: ['primary','rotate-ccw']
    };
    // Explicit selectors keep labels and business state out of presentation inference.
    const selectors = {
        '.btn-save,.hd-save,#ga-category-save,#ga-submit,#exa-submit,#exa-save-code': 'save',
        '.btn-edit,.hd-edit,.hd-room-edit:not([data-delete-room])': 'edit',
        '.btn-delete,.btn-reject,[data-delete-room]': 'delete',
        '.btn-confirm,.btn-complete,.btn-approve': 'approve',
        '.btn-cancel': 'reject',
        '.btn-secondary,.btn-cancel-modal,.hd-cancel,[data-ga-close],[data-exa-close],#ga-category-reset,#ga-category-close': 'cancel',
        '.btn-detail,.hotel-detail-link,.btn-booking,.ga-head a,.exa-heading a,#hotelWebsiteLink': 'view',
        '#ga-create,#exa-create,#addRoomType': 'add',
        '.hd-retry,#ga-retry,#ga-setup-retry,#exa-retry': 'refresh',
        '#ga-undo': 'restore', '#ga-archived-tab,#ga-view-archived': 'archive',
        '#ga-filters button[type="submit"],#exa-filters button[type="submit"]': 'search',
        '[data-image-action="delete"],[data-image-action="remove-new"]': 'delete'
    };
    const selector = Object.keys(selectors).join(',') + ',[data-admin-action]';
    function decorate(button) {
        if (!button.dataset.adminAction) {
            const entry = Object.entries(selectors).find(([match]) => button.matches(match));
            if (entry) button.dataset.adminAction = entry[1];
        }
        const action = actions[button.dataset.adminAction];
        if (!action) return;
        // A caller may override the tone (e.g. a disabled archived toggle).
        if (!button.dataset.adminTone) button.dataset.adminTone = action[0];
        if (!button.querySelector('svg') && button.textContent.trim() && button.getAttribute('aria-busy') !== 'true') {
            if (/^[×✕]$/.test(button.textContent.trim()) && button.hasAttribute('aria-label')) button.textContent = '';
            else if (button.dataset.adminAction === 'add') {
                for (const child of button.childNodes) if (child.nodeType === Node.TEXT_NODE) child.textContent = child.textContent.replace(/^\s*[＋+]\s*/, '');
            }
            button.insertAdjacentHTML('afterbegin', adminIcon(action[1]));
        }
        if (button.getAttribute('aria-label') && !button.title) button.title = button.getAttribute('aria-label');
    }
    function enhance(node) {
        if (!(node instanceof Element) || node.closest('[contenteditable]')) return;
        if (node.matches(selector)) decorate(node);
        node.querySelectorAll(selector).forEach(decorate);
    }
    enhance(document.body);
    window.adminBusy = (button, busy) => button?.setAttribute('aria-busy', String(busy));
    // Tables and editor buttons are rendered again after every API response.
    const observer = new MutationObserver(records => {
        const changed = new Set();
        for (const record of records) {
            if (record.target.closest?.('[contenteditable]')) continue;
            if (record.type === 'attributes') changed.add(record.target);
            else { if (record.target.matches?.(selector)) changed.add(record.target); record.addedNodes.forEach(node => { if (node instanceof Element && !node.matches('svg,.admin-icon,use')) changed.add(node); }); }
        }
        changed.forEach(enhance);
    });
    observer.observe(document.body, {subtree:true, childList:true, attributes:true, attributeFilter:['data-admin-action','aria-busy']});

    const toast = document.getElementById('adminToast');
    let toastTimer;
    window.adminToast = (message, duration = 4000, tone = 'info') => {
        // Keep older callers compatible while allowing explicit success/warning/error tones.
        if (typeof duration === 'string') { tone = duration; duration = 4000; }
        const types = {info:'primary',success:'success',warning:'warning',error:'danger',danger:'danger',neutral:'neutral'};
        const text = String(message ?? '');
        if (tone === 'info') {
            if (/^(❌|✕|Không thể|Chưa thể|Lỗi)|thất bại/i.test(text)) tone = 'error';
            else if (/^(✓|✅|Đã )/.test(text)) tone = 'success';
        }
        toast.dataset.adminTone = types[tone] || 'primary';
        toast.setAttribute('role', tone === 'error' || tone === 'danger' ? 'alert' : 'status');
        toast.setAttribute('aria-live', tone === 'error' || tone === 'danger' ? 'assertive' : 'polite');
        document.getElementById('adminToastIcon').innerHTML = adminIcon(tone === 'success' ? 'check' : tone === 'error' || tone === 'danger' || tone === 'warning' ? 'circle-alert' : 'info');
        document.getElementById('adminToastMsg').textContent = text.replace(/^[✓✅❌✕]\s*/u, '');
        clearTimeout(toastTimer); toast.classList.add('show');
        toastTimer = setTimeout(() => toast.classList.remove('show'), Number.isFinite(duration) ? duration : 4000);
    };
    document.getElementById('adminToastClose').addEventListener('click', () => { clearTimeout(toastTimer); toast.classList.remove('show'); });

    const dialog = document.getElementById('adminConfirm');
    const cancel = document.getElementById('adminConfirmCancel');
    const submit = document.getElementById('adminConfirmSubmit');
    let resolveConfirm = null, trigger = null;
    function finish(accepted) {
        if (!resolveConfirm) return;
        const resolve = resolveConfirm; resolveConfirm = null;
        dialog.close(); if (trigger?.isConnected) trigger.focus({preventScroll:true}); resolve(accepted);
    }
    window.adminConfirm = ({title = 'Xác nhận thao tác', message, confirmText = 'Xác nhận', tone = 'danger'} = {}) => {
        if (resolveConfirm) return Promise.resolve(false);
        trigger = document.activeElement;
        dialog.dataset.adminTone = ['danger','neutral','warning','success'].includes(tone) ? tone : 'danger';
        document.getElementById('adminConfirmTitle').textContent = title;
        document.getElementById('adminConfirmMessage').textContent = message || '';
        document.getElementById('adminConfirmIcon').innerHTML = adminIcon(tone === 'neutral' ? 'archive' : 'circle-alert');
        submit.textContent = confirmText;
        submit.dataset.adminTone = dialog.dataset.adminTone;
        submit.dataset.adminAction = tone === 'danger' ? 'delete' : tone === 'neutral' ? 'archive' : 'restore';
        dialog.showModal(); cancel.focus();
        return new Promise(resolve => { resolveConfirm = resolve; });
    };
    cancel.addEventListener('click', () => finish(false));
    submit.addEventListener('click', () => finish(true));
    dialog.addEventListener('cancel', event => { event.preventDefault(); finish(false); });
    dialog.addEventListener('click', event => { const box = dialog.getBoundingClientRect(); if (event.target === dialog && (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom)) finish(false); });
    // A queued close event from the previous confirmation must not cancel a newly opened one.
    dialog.addEventListener('close', () => { if (!dialog.open && resolveConfirm) finish(false); });
})();
</script>
