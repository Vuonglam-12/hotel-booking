<style>
/* Semantic colors shared by Admin only. Darker text on pale surfaces keeps small labels readable. */
.admin-ui {
    --admin-primary: #2563EB; --admin-primary-text: #1D4ED8; --admin-primary-soft: #EFF6FF; --admin-primary-border: #BFDBFE;
    --admin-success: #16A34A; --admin-success-text: #15803D; --admin-success-soft: #F0FDF4; --admin-success-border: #BBF7D0;
    --admin-warning: #D97706; --admin-warning-text: #92400E; --admin-warning-soft: #FFFBEB; --admin-warning-border: #FDE68A;
    --admin-danger: #DC2626; --admin-danger-text: #B91C1C; --admin-danger-soft: #FEF2F2; --admin-danger-border: #FECACA;
    --admin-neutral: #64748B; --admin-neutral-text: #475569; --admin-neutral-soft: #F1F5F9; --admin-neutral-border: #CBD5E1;
    --dark: #142B49; --gray: #475569;
    color: var(--dark); background: #F8F8F8;
}
.admin-ui .admin-main { min-width: 0; background: #F8F8F8; }
.admin-ui :is([data-admin-tone="primary"], .badge-info, .badge-completed, .hd-status-info) {
    --tone: var(--admin-primary); --tone-text: var(--admin-primary-text); --tone-soft: var(--admin-primary-soft); --tone-border: var(--admin-primary-border);
}
.admin-ui :is([data-admin-tone="success"], .badge-success, .badge-active, .badge-confirmed, .badge-approved, .badge-paid, .hd-status-active, .btn-confirm, .btn-complete, .btn-approve, .btn-toggle-off) {
    --tone: var(--admin-success); --tone-text: var(--admin-success-text); --tone-soft: var(--admin-success-soft); --tone-border: var(--admin-success-border);
}
.admin-ui :is([data-admin-tone="warning"], .badge-warning, .badge-warn, .badge-pending) {
    --tone: var(--admin-warning); --tone-text: var(--admin-warning-text); --tone-soft: var(--admin-warning-soft); --tone-border: var(--admin-warning-border);
}
.admin-ui :is([data-admin-tone="danger"], .badge-danger, .badge-cancelled, .badge-rejected, .btn-delete, .btn-reject, .btn-cancel, [data-delete-room]) {
    --tone: var(--admin-danger); --tone-text: var(--admin-danger-text); --tone-soft: var(--admin-danger-soft); --tone-border: var(--admin-danger-border);
}
.admin-ui :is([data-admin-tone="neutral"], .badge-expired, .badge-draft, .badge-archived, .badge-hidden, .badge-inactive, .hd-status-inactive, .badge-closed, .hd-status-closed, .btn-secondary, .btn-cancel-modal, .hd-cancel) {
    --tone: var(--admin-neutral); --tone-text: var(--admin-neutral-text); --tone-soft: var(--admin-neutral-soft); --tone-border: var(--admin-neutral-border);
}
.admin-ui :is(.btn-primary, .btn-save, .hd-save, .hd-add, .ga-primary, .exa-button--primary, .image-editor-upload) {
    display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 38px;
    border: 1px solid var(--admin-primary); border-radius: 8px; background: var(--admin-primary); color: #FFF;
    padding: 9px 14px; font-weight: 600; box-shadow: none; text-decoration: none; transform: none;
}
.admin-ui :is(.btn-primary, .btn-save, .hd-save, .hd-add, .ga-primary, .exa-button--primary, .image-editor-upload):hover:not(:disabled) {
    background: #1D4ED8; border-color: #1D4ED8; color: #FFF; box-shadow: none; transform: none;
}
.admin-ui :is(.btn-action, .btn-act, .btn-modal-action, .btn-confirm, .btn-complete, .btn-approve, .btn-reject, .btn-secondary, .btn-cancel-modal, .hd-edit, .hd-room-edit, .hd-cancel, .hd-retry, .ga-button, .exa-button, .image-editor-action, .admin-action) {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 34px;
    border: 1px solid var(--tone-border, #CBD5E1); border-radius: 8px; padding: 7px 10px;
    background: var(--tone-soft, #FFF); color: var(--tone-text, #475569); font-weight: 550; line-height: 1.45;
    text-decoration: none; box-shadow: none; transition: background-color 150ms, border-color 150ms;
}
/* Primary wins over generic button styles, including inside native dialogs. */
.admin-ui :is(.ga-button.ga-primary, .exa-button.exa-button--primary) { background: var(--admin-primary); border-color: var(--admin-primary); color: #FFF; }
.admin-ui :is(.btn-action, .btn-act, .btn-modal-action, .btn-confirm, .btn-complete, .btn-approve, .btn-reject, .btn-secondary, .btn-cancel-modal, .hd-edit, .hd-room-edit, .hd-cancel, .hd-retry, .ga-button, .exa-button, .image-editor-action, .admin-action):hover:not(:disabled) {
    border-color: var(--tone, #64748B); background: var(--tone-soft, #F1F5F9); color: var(--tone-text, #142B49); box-shadow: none; transform: none;
}
.admin-ui :is(.ga-button.ga-primary, .exa-button.exa-button--primary):hover:not(:disabled) { background: #1D4ED8; border-color: #1D4ED8; color: #FFF; }
.admin-ui :is(.ga-actions, .exa-actions, .hotel-row-actions) { gap: 6px; }
.admin-ui :is(button, a)[data-admin-action] .admin-icon { width: 16px; height: 16px; }
.admin-ui :is(.ga-toolbar, .filter-group) { flex-wrap: wrap; }
.admin-ui :is(.ga-head, .exa-heading) { flex-wrap: wrap; }
.admin-ui :is(.ga-head, .exa-heading) h1 { font-size: 24px; font-weight: 650; }
.admin-ui :is(.filter-btn, .toolbar-btn, .ga-actions [aria-pressed]) { min-height: 36px; }
.admin-ui :is(.filter-btn.active, .toolbar-btn.active, #ga-active-tab[aria-pressed="true"], #ga-archived-tab[aria-pressed="true"]) {
    background: var(--admin-primary-soft); border-color: var(--admin-primary-border); color: var(--admin-primary-text); box-shadow: none;
}
.admin-ui :is(.badge, .ga-badge, .exa-status, .hd-status) {
    display: inline-flex; align-items: center; gap: 6px; max-width: 100%; padding: 4px 8px;
    border: 1px solid var(--tone-border, #CBD5E1); border-radius: 6px;
    background: var(--tone-soft, #F1F5F9); color: var(--tone-text, #475569); font-size: 11px; font-weight: 600; line-height: 1.5;
}
.admin-ui :is(.badge, .ga-badge, .exa-status, .hd-status)::before { content: ''; width: 6px; height: 6px; border-radius: 50%; background: var(--tone, #64748B); flex: 0 0 auto; }
.admin-ui .sb-badge { background: var(--admin-warning-soft); color: var(--admin-warning-text); border: 1px solid var(--admin-warning-border); }
.admin-ui .sb-badge.js-booking-badge { background: var(--admin-danger-soft); color: var(--admin-danger-text); border-color: var(--admin-danger-border); }
.admin-ui :is(.hotel-action-menu-list, .hd-action-menu-list) { padding: 6px; border: 1px solid #E2E8F0; box-shadow: 0 8px 24px #142B4914; }
.admin-ui .hotel-action-menu-list :is(a, button) { display: flex; align-items: center; gap: 7px; min-height: 36px; border-radius: 6px; color: var(--tone-text, #475569); }
.admin-ui .hotel-action-menu-list :is(a, button):hover { background: var(--tone-soft, #F1F5F9); }
.admin-ui :is(.modal-close, .ga-dialog-head > button, .exa-dialog-header > button) { min-width: 36px; min-height: 36px; border-radius: 8px; background: #F1F5F9; color: #475569; }
.admin-ui :is(button, a, input, select, textarea, summary, [tabindex]):focus-visible { outline: 3px solid #2563EB; outline-offset: 3px; }
.admin-ui :is(input[type="checkbox"], input[type="radio"]) { accent-color: #2563EB; }
.admin-ui :is(button, input, select, textarea):disabled, .admin-ui a[aria-disabled="true"] { opacity: .55; cursor: not-allowed; box-shadow: none; transform: none; }
.admin-ui button[aria-busy="true"] { cursor: progress; }
.admin-ui button[aria-busy="true"] > .admin-icon { display: none; }
.admin-ui button[aria-busy="true"]::before { content: ''; display: inline-block; flex-shrink: 0; width: 14px; height: 14px; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: admin-spin .7s linear infinite; }
.admin-ui :is(.ga-error, .exa-error, .hd-form-error, .dash-error, .image-editor-status[data-error="true"]) {
    border: 1px solid var(--admin-danger-border); border-radius: 9px; background: var(--admin-danger-soft); color: var(--admin-danger-text);
    padding: 12px 14px; font-size: 12px; line-height: 1.7; overflow-wrap: anywhere;
}
.admin-ui :is(.ga-error, .exa-error, .hd-form-error, .dash-error)::before { content: '!'; display: inline-grid; place-items: center; width: 17px; height: 17px; margin-right: 7px; border: 1px solid currentColor; border-radius: 50%; font-weight: 700; line-height: 1; }
.admin-ui #ga-notice { border-color: var(--admin-success-border); background: var(--admin-success-soft); color: var(--admin-success-text); }
.admin-ui #ga-notice::before { content: '✓'; display: inline-block; margin-right: 7px; font-weight: 700; }
.admin-ui .admin-toast {
    align-items: flex-start; gap: 10px; width: max-content; max-width: min(460px, calc(100vw - 32px));
    background: var(--tone-soft, #EFF6FF); color: var(--tone-text, #1D4ED8); border: 1px solid var(--tone-border, #BFDBFE);
    border-left: 4px solid var(--tone, #2563EB); box-shadow: 0 8px 28px #142B491F; overflow-wrap: anywhere;
}
.admin-ui .admin-toast > .admin-icon { width: 19px; height: 19px; margin-top: 1px; }
.admin-ui #adminToastIcon { flex: 0 0 20px; }
.admin-ui .admin-toast-close { flex-shrink: 0; width: 26px; height: 26px; border: 0; border-radius: 5px; background: transparent; color: inherit; margin: -3px -5px -3px 4px; }
.admin-ui .admin-toast-close:hover { background: #142B4910; }
.admin-ui .admin-confirm { width: min(440px, calc(100vw - 32px)); max-height: calc(100dvh - 32px); padding: 24px; border: 1px solid #E2E8F0; border-radius: 14px; background: #FFF; color: #142B49; box-shadow: 0 20px 60px #142B4933; overflow: auto; }
.admin-ui .admin-confirm::backdrop { background: #142B4970; }
.admin-ui .admin-confirm-icon { display: inline-flex; padding: 12px; margin-bottom: 14px; border-radius: 12px; background: var(--tone-soft); color: var(--tone-text); }
.admin-ui .admin-confirm h2 { margin: 0 0 10px; font-size: 20px; font-weight: 650; }
.admin-ui .admin-confirm p { margin: 0; font-size: 13px; line-height: 1.8; color: #475569; white-space: pre-line; overflow-wrap: anywhere; }
.admin-ui .admin-confirm-actions { display: flex; justify-content: flex-end; flex-wrap: wrap; gap: 10px; margin-top: 24px; }
.admin-ui .admin-confirm-submit { background: var(--tone-text); border-color: var(--tone-text); color: #FFF; }
.admin-ui .admin-confirm-submit:hover:not(:disabled) { background: var(--tone-text); border-color: var(--tone-text); color: #FFF; filter: brightness(.92); }
.admin-ui :is(.ga-note, .exa-note, .ga-head p, .exa-heading p, .ga-post small, .exa-name small, .ga-field label, .exa-field label, .ga th, .exa th) { color: #475569; }
@media (pointer: coarse) { .admin-ui :is(button[data-admin-action], a[data-admin-action], .admin-icon-button, .filter-btn, .toolbar-btn, .modal-close) { min-height: 44px; min-width: 44px; } }
@media (max-width: 599px) {
    .admin-ui :is(.ga-head, .exa-heading) .ga-actions, .admin-ui .exa-heading .exa-actions { width: 100%; }
    .admin-ui :is(.ga-category-row, .ga-category-form) { flex-wrap: wrap; }
    .admin-ui .ga-category-form { grid-template-columns: minmax(0, 1fr); }
    .admin-ui :is(.ga-form-footer, .exa-form-footer, .admin-confirm-actions) { flex-wrap: wrap; }
    .admin-ui .admin-confirm-actions > button { flex: 1 1 auto; }
}
</style>
