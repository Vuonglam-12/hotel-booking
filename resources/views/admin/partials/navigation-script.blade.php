<script>
(() => {
    const root = document.documentElement;
    const sidebar = document.getElementById('adminSidebar');
    const main = document.getElementById('adminMain');
    const toggle = document.getElementById('sidebarCollapse');
    const openButton = document.getElementById('sidebarOpen');
    const closeButton = document.getElementById('sidebarClose');
    const backdrop = document.getElementById('sidebarBackdrop');
    const tooltip = document.getElementById('adminSidebarTooltip');
    const mobile = window.matchMedia('(max-width: 1023px)');
    let drawerOpen = false;
    let tooltipTrigger = null;

    const hideTooltip = () => {
        tooltip.hidden = true;
        tooltipTrigger?.removeAttribute('aria-describedby');
        tooltipTrigger = null;
    };
    const syncCollapse = () => {
        const collapsed = root.classList.contains('sidebar-collapsed');
        const label = collapsed ? 'Mở rộng menu' : 'Thu gọn menu';
        toggle.setAttribute('aria-expanded', String(!collapsed));
        toggle.setAttribute('aria-label', label);
        toggle.dataset.sidebarTooltip = label;
        toggle.title = label;
        hideTooltip();
    };
    const setDrawer = (open, returnFocus = true) => {
        drawerOpen = open && mobile.matches;
        root.classList.toggle('sidebar-drawer-open', drawerOpen);
        backdrop.hidden = !drawerOpen;
        openButton.setAttribute('aria-expanded', String(drawerOpen));
        main.inert = drawerOpen;
        sidebar.inert = mobile.matches && !drawerOpen;
        if (mobile.matches) sidebar.setAttribute('aria-hidden', String(!drawerOpen));
        else sidebar.removeAttribute('aria-hidden');
        if (drawerOpen) {
            sidebar.setAttribute('role', 'dialog');
            sidebar.setAttribute('aria-modal', 'true');
        } else {
            sidebar.removeAttribute('role');
            sidebar.removeAttribute('aria-modal');
        }
        document.body.style.overflow = drawerOpen ? 'hidden' : '';
        hideTooltip();
        if (drawerOpen) closeButton.focus();
        else if (returnFocus && mobile.matches) openButton.focus();
    };
    toggle.addEventListener('click', () => {
        const collapsed = root.classList.toggle('sidebar-collapsed');
        try { localStorage.setItem('aurel_admin_sidebar_collapsed', collapsed ? '1' : '0'); } catch (_) {}
        syncCollapse();
    });
    openButton.addEventListener('click', () => setDrawer(true));
    closeButton.addEventListener('click', () => setDrawer(false));
    backdrop.addEventListener('click', () => setDrawer(false));
    mobile.addEventListener('change', () => setDrawer(false, false));
    sidebar.addEventListener('click', event => {
        const link = event.target.closest('a');
        if (link?.getAttribute('aria-disabled') === 'true') event.preventDefault();
        else if (link && mobile.matches) setDrawer(false, false);
    });
    document.addEventListener('keydown', event => {
        if (!drawerOpen) return;
        if (event.key === 'Escape') { event.preventDefault(); setDrawer(false); }
        if (event.key === 'Tab') {
            const targets = [...sidebar.querySelectorAll('a[href]:not([tabindex="-1"]), button:not([disabled])')].filter(element => element.getClientRects().length);
            const first = targets[0], last = targets.at(-1);
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        }
    });
    sidebar.querySelectorAll('[data-sidebar-tooltip]').forEach(element => {
        const show = () => {
            if (mobile.matches || !root.classList.contains('sidebar-collapsed')) return;
            hideTooltip();
            tooltip.textContent = element.dataset.sidebarTooltip;
            tooltip.hidden = false;
            const rect = element.getBoundingClientRect();
            tooltip.style.left = `${rect.right + 12}px`;
            tooltip.style.top = `${Math.max(8, Math.min(window.innerHeight - tooltip.offsetHeight - 8, rect.top + (rect.height - tooltip.offsetHeight) / 2))}px`;
            tooltipTrigger = element;
            element.setAttribute('aria-describedby', tooltip.id);
        };
        element.addEventListener('mouseenter', show);
        element.addEventListener('focus', show);
        element.addEventListener('mouseleave', hideTooltip);
        element.addEventListener('blur', hideTooltip);
    });
    sidebar.querySelector('.sb-nav').addEventListener('scroll', hideTooltip);
    syncCollapse();
    setDrawer(false, false);

    const sprite = document.querySelector('meta[name="admin-icon-sprite"]').content;
    window.adminIcon = name => /^[a-z0-9-]+$/.test(name)
        ? `<svg class="admin-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="${sprite}#${name}"></use></svg>` : '';
})();
</script>
