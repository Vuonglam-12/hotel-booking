<style>
    :root {
        --blue: #2563EB; --blue-dark: #1D4ED8; --blue-soft: #EEF4FF; --gold: #F59E0B;
        --sb-bg: #FFFFFF; --sb-border: #E5E7EB; --sb-hover: #F8F8F8; --sb-active: #EEF4FF;
        --sb-text: #6B7280; --sb-text-hi: #111827;
        --bg: #FFFFFF; --dark: #111827; --gray: #6B7280; --gray-lt: #9CA3AF;
        --border: #E5E7EB; --white: #FFFFFF; --sky: #2563EB; --sky-dark: #1D4ED8;
        --sidebar-w: 248px; --sidebar-expanded-w: 248px; --sidebar-collapsed-w: 80px; --sidebar-brand-h: 64px;
        --admin-font: 'Be Vietnam Pro', system-ui, -apple-system, sans-serif;
        --admin-duration: 200ms; --admin-radius: 12px;
        --shadow-sm: 0 1px 3px rgb(17 24 39 / 3%);
        --shadow-md: 0 4px 16px rgb(17 24 39 / 5%);
        --shadow-lg: 0 8px 24px rgb(17 24 39 / 8%);
    }
    html.sidebar-collapsed { --sidebar-w: var(--sidebar-collapsed-w); }
    *, *::before, *::after { box-sizing: border-box; }
    html, body { margin: 0; min-height: 100%; background: var(--white); color: var(--dark); }
    body.admin-ui { font-family: var(--admin-font); font-size: 14px; line-height: 1.5; -webkit-font-smoothing: antialiased; }
    .admin-ui button, .admin-ui input, .admin-ui select, .admin-ui textarea { font: inherit; }
    .admin-ui :is(h1, h2, h3, h4, h5, h6, .page-title, .modal-title, .revenue-dashboard) { font-family: var(--admin-font) !important; }
    .admin-ui [hidden] { display: none !important; }
    .admin-ui :is(a, button, input, select, textarea):focus-visible { outline: 2px solid var(--blue); outline-offset: 3px; }
    .admin-ui button { cursor: pointer; }
    .admin-ui button:disabled { cursor: not-allowed; }
    .admin-icon { display: inline-block; flex-shrink: 0; width: 20px; height: 20px; vertical-align: middle; }
    .admin-skip-link { position: fixed; top: 8px; left: 12px; transform: translateY(-160%); padding: 10px 16px; border-radius: 8px; background: var(--blue); color: white; z-index: 10000; }
    .admin-skip-link:focus { transform: none; }

    .admin-sidebar { position: fixed; inset: 0 auto 0 0; width: var(--sidebar-w); background: var(--sb-bg); border-right: 1px solid var(--border); display: flex; flex-direction: column; z-index: 120; transition: width var(--admin-duration) ease; }
    .sb-brand-area { min-height: var(--sidebar-brand-h); display: flex; align-items: center; padding: 0 20px; background: #F8F8F8; border-bottom: 1px solid var(--border); }
    .sb-brand { display: inline-flex; align-items: center; text-decoration: none; --brand-logo-width: 108px; font-family: 'Cinzel', serif; font-weight: 500; }
    .sb-brand:hover { opacity: .85; }
    .sb-logo-mark { display: none; position: relative; width: 32px; aspect-ratio: 1; overflow: hidden; flex-shrink: 0; }
    .sb-logo-mark img { position: absolute; top: -7px; left: -4px; width: 138px; max-width: none; height: auto; }
    .sb-edge-toggle { position: absolute; top: 18px; right: -14px; z-index: 2; width: 28px; height: 28px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--border); border-radius: 50%; background: white; color: var(--gray); box-shadow: var(--shadow-sm); transition: color var(--admin-duration), border-color var(--admin-duration); }
    .sb-edge-toggle .admin-icon { width: 16px; height: 16px; }
    .sb-edge-toggle:hover { color: var(--blue); border-color: var(--blue); }
    .sb-expand-icon, .sb-brand-area .sb-mobile-close { display: none; }
    .sb-nav { padding: 12px; overflow-y: auto; scrollbar-width: thin; flex: 1; min-height: 0; }
    .sb-group + .sb-group { margin-top: 20px; }
    .sb-group-label { display: block; padding: 0 12px 8px; color: var(--gray); font-size: 10px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; white-space: nowrap; }
    .sb-link { display: flex; align-items: center; gap: 12px; min-height: 42px; padding: 10px 12px; border-radius: 8px; color: var(--sb-text); font-size: 13px; text-decoration: none; position: relative; transition: background-color var(--admin-duration), color var(--admin-duration); }
    .sb-link + .sb-link { margin-top: 4px; }
    .sb-link:hover { background: var(--sb-hover); color: var(--dark); }
    .sb-link.active { background: var(--sb-active); color: var(--blue); font-weight: 600; }
    .sb-icon-wrap { display: flex; align-items: center; justify-content: center; width: 20px; height: 20px; flex-shrink: 0; }
    .sb-label { white-space: nowrap; }
    .sb-link[aria-disabled="true"] { color: var(--gray-lt); cursor: default; }
    .sb-soon { margin-left: auto; padding: 2px 5px; border: 1px solid var(--border); border-radius: 5px; font-size: 9px; color: var(--gray); white-space: nowrap; }
    .sb-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 20px; height: 20px; margin-left: auto; padding: 0 5px; border-radius: 6px; background: var(--blue-soft); color: var(--blue); font-size: 10px; font-variant-numeric: tabular-nums; }
    .sb-user { display: flex; align-items: center; gap: 10px; min-height: 76px; margin-top: auto; padding: 16px; border: 0; border-top: 1px solid var(--border); width: 100%; background: var(--white); color: var(--gray); text-align: left; }
    .sb-user:hover { background: var(--sb-hover); }
    .sb-user-avatar { display: flex; align-items: center; justify-content: center; flex-shrink: 0; width: 32px; height: 32px; border-radius: 50%; background: var(--blue-soft); color: var(--blue); font-size: 11px; font-weight: 600; }
    .sb-user-info { flex: 1; min-width: 0; }
    .sb-user-name { color: var(--dark); font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .sb-user-role { font-size: 10px; color: var(--gray); }
    .sb-user-logout { width: 18px; height: 18px; }
    .sb-tooltip { position: fixed; z-index: 400; max-width: 220px; padding: 7px 10px; border: 1px solid var(--border); border-radius: 7px; background: var(--dark); color: white; font-size: 12px; pointer-events: none; box-shadow: var(--shadow-sm); }
    html.sidebar-collapsed .sb-brand-area { justify-content: center; padding-inline: 0; }
    html.sidebar-collapsed .sb-logo-full, html.sidebar-collapsed .sb-label, html.sidebar-collapsed .sb-user-info, html.sidebar-collapsed .sb-user-avatar, html.sidebar-collapsed .sb-group-label, html.sidebar-collapsed .sb-soon, html.sidebar-collapsed .sb-collapse-icon { display: none; }
    html.sidebar-collapsed .sb-badge { display: none !important; }
    html.sidebar-collapsed .sb-logo-mark, html.sidebar-collapsed .sb-expand-icon { display: inline-block; }
    html.sidebar-collapsed .sb-link { justify-content: center; padding-inline: 0; }
    html.sidebar-collapsed .sb-user { justify-content: center; padding-inline: 0; }
    html.sidebar-collapsed .sb-group + .sb-group { margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border); }

    .admin-icon-button { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 32px; height: 32px; padding: 0; border: 1px solid var(--border); border-radius: 8px; background: white; color: var(--gray); }
    .admin-icon-button:hover { background: var(--blue-soft); color: var(--blue); }
    .admin-icon-button .admin-icon { width: 18px; height: 18px; }
    .sb-mobile-open { display: none; }
    .admin-main { margin-left: var(--sidebar-w); margin-top: 0; min-height: 100dvh; padding: 24px 32px; min-width: 0; background: var(--white); transition: margin-left var(--admin-duration) ease; }
    .admin-page-body, .admin-content-context { max-width: 1440px; margin-inline: auto; }
    .admin-content-context { display: flex; align-items: center; gap: 10px; min-height: 20px; margin-bottom: 16px; }
    .admin-breadcrumb { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; color: var(--gray); font-size: 11px; line-height: 1.5; }
    .admin-breadcrumb a { color: var(--gray); text-decoration: none; }
    .admin-breadcrumb a:hover { color: var(--blue); }
    .admin-breadcrumb .admin-icon { width: 12px; height: 12px; color: var(--gray-lt); }
    .admin-breadcrumb [aria-current] { color: var(--dark); }
    .sb-backdrop { display: none; position: fixed; inset: 0; border: 0; background: rgb(17 24 39 / 30%); z-index: 110; }

    .auth-overlay { position: fixed; inset: 0; background: var(--white); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px; padding: 24px; text-align: center; z-index: 9999; transition: opacity var(--admin-duration); }
    .auth-overlay.hide { opacity: 0; pointer-events: none; }
    .auth-overlay p { margin: 0; color: var(--gray); }
    .auth-retry { padding: 10px 18px; border: 1px solid var(--blue); border-radius: 8px; background: white; color: var(--blue); }
    .auth-spinner { width: 34px; height: 34px; border: 2px solid var(--blue-soft); border-top-color: var(--blue); border-radius: 50%; animation: admin-spin .8s linear infinite; }
    @keyframes admin-spin { to { transform: rotate(360deg); } }
    .admin-toast { position: fixed; bottom: 24px; right: 24px; max-width: calc(100vw - 32px); display: none; align-items: center; gap: 8px; padding: 12px 16px; border: 1px solid var(--border); border-radius: 10px; background: var(--dark); color: white; font-size: 13px; box-shadow: var(--shadow-md); z-index: 9999; }
    .admin-toast.show { display: flex; }

    @media (max-width: 1023px) {
        :root, html.sidebar-collapsed { --sidebar-w: var(--sidebar-expanded-w); }
        .admin-sidebar { width: min(var(--sidebar-expanded-w), calc(100vw - 40px)); transform: translateX(-100%); visibility: hidden; transition: transform var(--admin-duration) ease, visibility 0s linear var(--admin-duration); }
        html.sidebar-drawer-open .admin-sidebar { transform: translateX(0); visibility: visible; transition: transform var(--admin-duration) ease, visibility 0s; }
        html.sidebar-drawer-open .sb-backdrop { display: block; }
        .admin-main { margin-left: 0; padding: 24px; }
        .sb-mobile-open, .sb-brand-area .sb-mobile-close { display: inline-flex; }
        .sb-mobile-close { margin-left: auto; }
        .sb-edge-toggle { display: none; }
        html.sidebar-collapsed .sb-brand-area { justify-content: flex-start; padding-inline: 20px; }
        html.sidebar-collapsed .sb-logo-full, html.sidebar-collapsed .sb-label, html.sidebar-collapsed .sb-user-info, html.sidebar-collapsed .sb-user-avatar, html.sidebar-collapsed .sb-group-label, html.sidebar-collapsed .sb-soon { display: initial; }
        html.sidebar-collapsed .sb-user-avatar { display: flex; }
        html.sidebar-collapsed .sb-group-label { display: block; }
        html.sidebar-collapsed .sb-logo-mark { display: none; }
        html.sidebar-collapsed .sb-link { justify-content: flex-start; padding-inline: 12px; }
        html.sidebar-collapsed .sb-user { justify-content: flex-start; padding-inline: 16px; }
        html.sidebar-collapsed .sb-group + .sb-group { margin-top: 20px; padding-top: 0; border: 0; }
        html.sidebar-collapsed .sb-badge[style*="inline-flex"] { display: inline-flex !important; }
    }
    @media (max-width: 599px) {
        .admin-main { padding: 20px 16px; }
        .admin-toast { bottom: 16px; right: 16px; }
    }
    @media (prefers-reduced-motion: reduce) {
        :root { --admin-duration: 0ms; }
        .admin-ui *, .admin-ui *::before, .admin-ui *::after { animation: none !important; scroll-behavior: auto !important; transition-duration: 0ms !important; }
    }
</style>
