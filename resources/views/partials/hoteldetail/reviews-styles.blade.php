<style>
.hv-hotel-detail .hv-reviews { padding:28px; border:1px solid #DDE7F3; border-radius:22px; background:#fff; box-shadow:0 8px 28px rgb(20 43 73 / 4%); min-width:0; scroll-margin-top:90px; }
.hv-reviews-heading { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:18px; }
.hv-reviews-eyebrow { color:#2563EB; font-size:10px; font-weight:700; letter-spacing:.12em; margin-bottom:7px; }
.hv-reviews-heading h2 { font-size:24px; font-weight:700; line-height:1.35; }
.hv-reviews-intro { color:#64748B; font-size:13px; line-height:1.7; margin-top:8px; }
.hv-review-summary { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:24px; align-items:center; padding:22px; margin-top:24px; border:1px solid #DBEAFE; border-radius:18px; background:linear-gradient(120deg,#F8FBFF,#EFF6FF); }
.hv-review-overall { display:flex; align-items:center; gap:15px; min-width:0; }
.hv-review-score { display:flex; flex-direction:column; justify-content:center; align-items:center; flex-shrink:0; width:82px; height:88px; border-radius:17px; background:linear-gradient(145deg,#142B49,#2563EB); color:#fff; box-shadow:0 6px 16px rgb(37 99 235 / 15%); }
.hv-review-score strong { font-size:32px; line-height:1.15; letter-spacing:-1px; }
.hv-review-score > span { font-size:12px; margin-top:5px; color:#DBEAFE; }
.hv-review-overall h3 { font-size:19px; font-weight:700; color:#142B49; line-height:1.4; overflow-wrap:anywhere; }
.hv-review-stars { display:inline-block; position:relative; color:#CBD5E1; font-size:20px; line-height:1.5; letter-spacing:2px; margin-top:3px; }
.hv-review-stars::after { content:'★★★★★'; position:absolute; top:0; left:0; width:var(--review-fill,0%); overflow:hidden; white-space:nowrap; color:#EAB308; }
.hv-review-basis { margin-top:5px; color:#52616E; font-size:11px; line-height:1.7; }
.hv-review-distribution { display:grid; gap:5px; }
.hv-review-histogram-row { display:grid; grid-template-columns:34px minmax(0,1fr) 22px; align-items:center; gap:9px; padding:4px 6px; width:100%; border:0; border-radius:7px; background:transparent; font-size:12px; color:#52616E; text-align:left; }
.hv-review-histogram-row:hover,.hv-review-histogram-row[aria-pressed="true"] { background:#DBEAFE; color:#142B49; }
.hv-review-bar { height:7px; border-radius:10px; background:#DFE8F4; overflow:hidden; }
.hv-review-bar > span { display:block; height:100%; border-radius:inherit; background:#2563EB; transition:width .2s; }
.hv-review-histogram-row > span:last-child { text-align:right; font-variant-numeric:tabular-nums; }
.hv-review-gold { color:#EAB308; }
.hv-review-metrics { grid-column:1/-1; display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; border-top:1px solid #DCE7F5; padding-top:16px; }
.hv-review-metric { display:grid; grid-template-columns:1fr auto; align-items:center; gap:4px 8px; min-width:0; font-size:12px; color:#52616E; }
.hv-review-metric strong { color:#142B49; font-size:16px; }
.hv-review-metric strong small { font-size:10px; margin-left:2px; font-weight:400; color:#64748B; }
.hv-review-metric > small { grid-column:1/-1; font-size:10px; color:#64748B; }
.hv-review-toolbar { display:flex; align-items:flex-end; justify-content:space-between; flex-wrap:wrap; gap:14px; margin:22px 0 18px; }
.hv-review-filters { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:12px; width:100%; max-width:390px; }
.hv-review-filters label { display:block; font-size:11px; color:#64748B; min-width:0; }
.hv-review-filters select { width:100%; min-height:42px; margin-top:5px; padding:9px 30px 9px 11px; border:1px solid #DDE4EE; border-radius:10px; background:#fff; color:#142B49; font-size:12px; }
.hv-review-result-count { font-size:11px; color:#64748B; padding-bottom:10px; }
.hv-review-list { display:grid; gap:14px; margin-top:18px; }
.hv-review-card { display:grid; grid-template-columns:145px minmax(0,1fr); gap:20px; padding:20px; border:1px solid #E4EAF2; border-radius:15px; background:#fff; }
.hv-review-author { display:flex; align-items:center; gap:10px; min-width:0; }
.hv-review-avatar { position:relative; display:grid; place-items:center; width:42px; height:42px; border-radius:50%; overflow:hidden; flex-shrink:0; background:#EFF6FF; color:#1D4ED8; font-size:13px; font-weight:700; }
.hv-review-avatar img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
.hv-review-author h3 { font-size:12px; color:#142B49; font-weight:700; line-height:1.6; overflow-wrap:anywhere; }
.hv-review-content { min-width:0; }
.hv-review-meta { display:flex; align-items:center; flex-wrap:wrap; gap:10px; }
.hv-review-score-badge { display:inline-flex; align-items:center; gap:4px; background:#EFF6FF; color:#1D4ED8; padding:4px 8px; border-radius:7px; font-size:12px; }
.hv-review-meta time { color:#64748B; font-size:11px; }
.hv-review-comment { margin-top:12px; color:#334155; font-size:13px; line-height:1.85; white-space:pre-line; overflow-wrap:anywhere; }
.hv-review-pagination { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-top:20px; font-size:12px; color:#64748B; }
.hv-review-pagination button,.hv-review-load-error button { min-height:38px; padding:8px 12px; border:1px solid #DBEAFE; border-radius:9px; color:#1D4ED8; background:#EFF6FF; font-size:12px; font-weight:600; }
.hv-review-pagination button:hover:not(:disabled) { background:#DBEAFE; }
.hv-review-pagination button:disabled { opacity:.4; cursor:not-allowed; }
.hv-review-empty { padding:30px 16px 18px; text-align:center; }
.hv-review-empty > svg { color:#2563EB; margin:0 auto 12px; }
.hv-review-empty h3 { color:#142B49; font-size:16px; font-weight:600; }
.hv-review-empty p { color:#64748B; font-size:12px; line-height:1.8; margin-top:8px; }
.hv-review-load-error { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-top:18px; padding:14px; border:1px solid #FECACA; border-radius:12px; color:#B91C1C; background:#FEF2F2; font-size:12px; }
.hv-reviews [hidden] { display:none!important; }
.hv-reviews[data-loading="true"] .hv-review-list { opacity:.5; }
.hv-reviews[data-loading="true"] .hv-review-result-count::before { content:''; display:inline-block; width:12px; height:12px; margin-right:7px; vertical-align:middle; border:2px solid #DBEAFE; border-top-color:#2563EB; border-radius:50%; animation:hv-review-spin .8s linear infinite; }
.hv-reviews :is(button,select):focus-visible { outline:2px solid #2563EB; outline-offset:3px; }
@keyframes hv-review-spin { to { transform:rotate(360deg); } }
@media(max-width:639px) {
    .hv-hotel-detail .hv-reviews { padding:18px; border-radius:18px; }
    .hv-reviews-heading h2 { font-size:22px; }
    .hv-review-summary { grid-template-columns:minmax(0,1fr); padding:18px; gap:18px; }
    .hv-review-card { grid-template-columns:minmax(0,1fr); padding:16px; gap:14px; }
    .hv-review-author h3 { font-size:13px; }
    .hv-review-metrics { gap:12px; }
    .hv-review-metric { grid-template-columns:1fr; gap:2px; }
    .hv-review-metric > small { grid-column:auto; }
}
@media(prefers-reduced-motion:reduce) { .hv-review-bar > span { transition:none; } .hv-reviews[data-loading="true"] .hv-review-result-count::before { animation:none; } }
</style>
