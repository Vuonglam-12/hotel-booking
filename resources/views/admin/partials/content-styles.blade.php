<style>
    /* Content shared by every Admin page; loaded after page-specific styles. */
    .admin-main :is(.page-header, .dashboard-header) { display: flex; flex-direction: row; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin: 0 0 20px; }
    .admin-main :is(.page-title, .dashboard-title h1) { margin: 0; color: var(--dark); font-size: 23px; font-weight: 600; line-height: 1.35; letter-spacing: -.025em; }
    .admin-main :is(.page-sub, .dashboard-title p) { margin: 5px 0 0; color: var(--gray); font-size: 12px; line-height: 1.6; }
    .admin-main .btn-primary { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 38px; padding: 9px 14px; border: 1px solid var(--blue); border-radius: 8px; background: var(--blue); color: white; box-shadow: none; font-size: 12px; font-weight: 500; transform: none; }
    .admin-main .btn-primary:hover { background: var(--blue-dark); box-shadow: none; transform: none; }
    .admin-main .stats-grid, .admin-main .kpi-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin: 0 0 20px; }
    .admin-main .stats-grid:where(:has(> :nth-child(3):last-child)) { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .admin-main .stat-card, .admin-main .kpi-card { position: relative; min-width: 0; min-height: 108px; padding: 16px; border: 1px solid var(--border); border-radius: 12px; background: white; box-shadow: none; text-align: left; }
    .admin-main .stat-card::before { display: none; }
    .admin-main .stat-card:hover { box-shadow: none; transform: none; }
    .admin-main .stat-card.active { background: var(--blue-soft); border-color: var(--blue); box-shadow: none; }
    .admin-main .stat-card > .stat-icon { position: absolute; top: 16px; right: 16px; margin: 0; }
    .admin-main :is(.stat-icon, .kpi-icon) { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; flex-shrink: 0; border-radius: 7px; background: var(--blue-soft); color: var(--blue); }
    .admin-main :is(.stat-icon, .kpi-icon) .admin-icon { width: 16px; height: 16px; }
    .admin-main .stat-heading { display: flex; flex-direction: row-reverse; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px; }
    .admin-main :is(.stat-label, .kpi-label) { margin: 0 0 8px; color: var(--gray); font-size: 11px; font-weight: 500; line-height: 1.5; text-transform: none; letter-spacing: 0; }
    .admin-main .stat-card > .stat-label { padding-right: 36px; }
    .admin-main .stat-heading .stat-label { margin: 0; }
    .admin-main :is(.stat-value, .kpi-value) { margin: 0; color: var(--dark); font-size: 27px; font-weight: 600; line-height: 1.15; letter-spacing: -.025em; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
    .admin-main :is(.stat-sub, .kpi-sub, .stat-change) { margin-top: 6px; color: var(--gray); font-size: 11px; line-height: 1.5; }
    .admin-main .stat-change.up { color: #15803D; }
    .admin-main .stat-change.down { color: #DC2626; }
    .admin-main .kpi-card { display: grid; grid-template-columns: minmax(0, 1fr) 26px; grid-template-areas: 'label icon' 'value value' 'sub sub'; align-content: start; row-gap: 6px; }
    .admin-main .kpi-header { display: contents; }
    .admin-main .kpi-icon { grid-area: icon; }
    .admin-main .kpi-label { grid-area: label; margin: 0; align-self: center; }
    .admin-main .kpi-value { grid-area: value; }
    .admin-main .kpi-sub { grid-area: sub; margin: 0; }
    .admin-main :is(.dash-card, .table-card, .dashboard-card, .sidebar-card) { min-width: 0; border: 1px solid var(--border); border-radius: 12px; background: white; box-shadow: none; }
    .admin-main :is(.dashboard-card, .sidebar-card) { padding: 18px; }
    .admin-main :is(.card-head, .table-head-row) { padding: 15px 18px; gap: 12px; flex-wrap: wrap; border-bottom: 1px solid var(--border); }
    .admin-main :is(.card-title, .table-title, .sidebar-title) { color: var(--dark); font-size: 13px; font-weight: 600; line-height: 1.5; }
    .admin-main .card-title { justify-content: flex-start; flex-wrap: wrap; gap: 8px; }
    .admin-main .card-subtitle { margin-left: auto; color: var(--gray); font-size: 11px; font-weight: 400; }
    .admin-main .card-title > span:first-child { display: inline-flex; align-items: center; gap: 8px; }
    .admin-main .card-title > .admin-icon { margin-right: 6px; }
    .admin-main .card-title .admin-icon { width: 17px; height: 17px; color: var(--gray); }
    .admin-main .toolbar { min-width: 0; margin-bottom: 16px; padding: 12px 16px; gap: 10px; border: 1px solid var(--border); border-radius: 10px; background: white; }
    .admin-main .toolbar-search { min-width: 0; flex: 1 1 220px; }
    .admin-main .toolbar input, .admin-main .toolbar select { min-width: 0; max-width: 100%; border: 1px solid var(--border); border-radius: 7px; background: white; font-family: var(--admin-font); font-size: 12px; }
    .admin-main .toolbar-btn { border: 1px solid var(--border); background: white; color: var(--gray); font-size: 12px; }
    .admin-main .toolbar-btn:hover, .admin-main .toolbar-btn.active { background: var(--blue-soft); border-color: var(--blue); color: var(--blue); }
    .admin-main .table-count { background: #F8F8F8; border-radius: 5px; font-size: 11px; }
    .admin-table-scroll, .admin-main .table-wrapper { width: 100%; max-width: 100%; overflow-x: auto; border-radius: 0; }
    .admin-main :is(.dash-table, .bk-table, .ht-table, .cust-table, .transaction-table) { width: 100%; border-collapse: collapse; font-family: var(--admin-font); }
    .admin-main :is(.dash-table, .bk-table, .ht-table, .cust-table, .transaction-table) th { padding: 11px 14px; border-bottom: 1px solid var(--border); background: #F8F8F8; color: var(--gray); font-size: 11px; font-weight: 500; line-height: 1.5; text-transform: none; letter-spacing: 0; }
    .admin-main :is(.dash-table, .bk-table, .ht-table, .cust-table, .transaction-table) td { padding: 13px 14px; border-bottom-color: var(--border); font-size: 12px; line-height: 1.55; font-variant-numeric: tabular-nums; }
    .admin-main :is(.bk-table, .ht-table, .cust-table, .transaction-table) { min-width: 600px; }
    .admin-main .ht-table .hotel-info { min-width: 250px; }
    .admin-main :is(.bk-table, .ht-table, .cust-table, .transaction-table) tbody tr:hover td { background: #FAFAFA; }
    .admin-main .pagination { padding: 12px 16px; gap: 12px; flex-wrap: wrap; }
    .admin-main .pagination-info { font-size: 11px; }
    .admin-main .empty-state { padding: 28px 18px; color: var(--gray); font-size: 12px; line-height: 1.6; text-align: center; }
    .admin-main .empty-icon { display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; margin: 0 auto 8px; border-radius: 9px; background: #F8F8F8; color: var(--gray); }
    .admin-main .empty-icon .admin-icon { width: 19px; height: 19px; }
    .admin-main .empty-text { color: var(--gray); font-size: 12px; }
    .admin-main .dash-empty { min-height: 144px; padding: 24px 18px; gap: 7px; }
    .admin-main .dash-empty-icon { width: 36px; height: 36px; margin-bottom: 2px; border-radius: 9px; }
    .admin-main .dash-empty strong { font-size: 12px; }
    .admin-main .dash-empty p { font-size: 11px; }
    .admin-main .chart-wrap { min-height: 170px; padding: 8px 12px 0; }
    .admin-main .chart-wrap .dash-empty { min-height: 158px; }
    .admin-main .chart-summary { padding: 10px 18px; }
    .admin-main .two-col { gap: 18px; margin-bottom: 18px; align-items: stretch; }
    .admin-main .main-cols { grid-template-columns: minmax(0, 1fr) 280px; gap: 18px; }
    .admin-main .main-cols > div { min-width: 0; }
    .admin-main .two-columns { grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr); gap: 18px; margin-bottom: 18px; }
    .admin-main .three-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; margin-bottom: 18px; }
    .admin-main .filter-group { max-width: 100%; padding: 4px; gap: 4px; border: 1px solid var(--border); border-radius: 8px; box-shadow: none; }
    .admin-main .filter-btn { display: inline-flex; align-items: center; gap: 5px; padding: 7px 10px; border-radius: 5px; font-size: 11px; }
    .admin-main .filter-btn.active { background: var(--blue-soft); color: var(--blue); box-shadow: none; }
    .admin-main .filter-btn .admin-icon { width: 14px; height: 14px; }
    .admin-main .month-grid { grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 10px; }
    .admin-main .month-card { min-width: 0; border-radius: 8px; padding: 12px; border: 1px solid var(--border); background: white; }
    .admin-main .cust-avatar, .admin-main .cust-detail-avatar { background: var(--blue-soft); color: var(--blue); }
    @media (max-width: 1279px) {
        .admin-main .stats-grid, .admin-main .kpi-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .admin-main .main-cols, .admin-main .two-columns { grid-template-columns: minmax(0, 1fr); }
        .admin-main .main-cols > div:last-child { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
        .admin-main .main-cols .sidebar-card { margin-bottom: 0; }
    }
    @media (max-width: 767px) {
        .admin-main .three-grid { grid-template-columns: minmax(0, 1fr); }
        .admin-main .month-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .admin-main :is(.admin-table-scroll, .table-wrapper) table:has(tbody .empty-state) { min-width: 0; }
        .admin-main :is(.admin-table-scroll, .table-wrapper) table:has(tbody .empty-state) thead { display: none; }
    }
    @media (max-width: 599px) {
        .admin-content-context { margin-bottom: 14px; }
        .admin-main :is(.page-title, .dashboard-title h1) { font-size: 21px; }
        .admin-main .main-cols > div:last-child { grid-template-columns: minmax(0, 1fr); }
        .admin-main .stats-grid, .admin-main .kpi-grid { gap: 12px; }
        .admin-main .stat-card, .admin-main .kpi-card { padding: 14px; }
        .admin-main .stat-card > .stat-icon { top: 14px; right: 14px; }
        .admin-main :is(.stat-value, .kpi-value) { font-size: 24px; }
        .admin-main .toolbar { padding: 12px; }
        .admin-main .toolbar-search { flex-basis: 100%; }
        .admin-main .toolbar select, .admin-main .toolbar input[type=date] { flex: 1 1 130px; }
    }
    @media (max-width: 359px) {
        .admin-main .stats-grid, .admin-main .kpi-grid { grid-template-columns: minmax(0, 1fr); }
        .admin-main .month-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
</style>
