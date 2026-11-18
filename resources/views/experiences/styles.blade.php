<style>
    .exp-page { --exp-ink:#142B49; --exp-blue:#2563EB; --exp-line:#E4E8EE; color:var(--exp-ink); background:#F8F8F8; min-height:60vh; padding-bottom:72px; }
    .exp-wrap { max-width:1232px; margin:auto; padding-inline:24px; }
    .exp-hero { padding:52px 0 40px; display:grid; grid-template-columns:1.5fr 1fr; gap:64px; align-items:center; }
    .exp-eyebrow { display:flex; align-items:center; gap:10px; color:#52667E; font-size:12px; font-weight:800; letter-spacing:.13em; text-transform:uppercase; }
    .exp-eyebrow::before { content:''; width:24px; height:2px; background:var(--exp-blue); }
    .exp-page h1 { color:var(--exp-ink); font-size:clamp(34px,4.4vw,60px); font-weight:800; letter-spacing:-.045em; line-height:1.13; margin:16px 0; }
    .exp-page h1 span { color:var(--exp-blue); }
    .exp-intro { max-width:570px; color:#637286; font-size:17px; line-height:1.8; }
    .exp-hero-note { border-left:1px solid #D9E0E9; padding:12px 0 12px 32px; }
    .exp-hero-note p { color:#718096; font-size:13px; margin-bottom:16px; }
    .exp-hero-note ul { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
    .exp-hero-note li { display:flex; gap:10px; align-items:center; font-size:14px; }
    .exp-symbol { width:34px; height:34px; border-radius:10px; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0; background:#EAF0FA; color:var(--exp-blue); }
    .exp-symbol svg { width:18px; height:18px; }
    .exp-categories { display:flex; gap:10px; flex-wrap:wrap; padding-bottom:28px; }
    .exp-category { min-height:44px; border:1px solid var(--exp-line); border-radius:999px; padding:10px 20px; background:white; color:#596A80; font-size:14px; transition:background .16s,color .16s,border-color .16s; }
    .exp-category[aria-pressed="true"] { background:var(--exp-ink); color:white; border-color:var(--exp-ink); }
    .exp-category:hover { border-color:#94A9C4; }
    .exp-filters { display:grid; grid-template-columns:1.2fr 1.4fr 1fr auto; gap:16px; align-items:end; border:1px solid var(--exp-line); border-radius:20px; background:white; padding:22px; }
    .exp-field { display:grid; gap:7px; min-width:0; }
    .exp-field label { font-size:12px; font-weight:700; color:#56677D; }
    .exp-field :is(input,select) { width:100%; min-height:44px; border:1px solid #DCE3EC; border-radius:10px; padding:10px 12px; background:white; color:var(--exp-ink); font-size:14px; }
    .exp-button { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:44px; border:1px solid var(--exp-blue); border-radius:10px; padding:10px 18px; background:var(--exp-blue); color:white; font-size:14px; font-weight:700; transition:background .16s; }
    .exp-button:hover { background:#1D4ED8; }
    .exp-button--quiet { color:var(--exp-ink); border-color:var(--exp-line); background:white; }
    .exp-button--quiet:hover { background:#EFF3F9; }
    .exp-button:disabled { opacity:.45; cursor:default; }
    .exp-results-heading { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:36px 0 22px; }
    .exp-results-heading h2,.exp-section-title { color:var(--exp-ink); font-size:25px; font-weight:800; letter-spacing:-.025em; }
    .exp-results-heading p { font-size:13px; color:#718096; }
    .exp-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:24px; }
    .exp-card { min-width:0; border:1px solid var(--exp-line); border-radius:18px; background:white; overflow:hidden; transition:transform .16s,box-shadow .16s; }
    .exp-card:hover { transform:translateY(-3px); box-shadow:0 12px 32px #142B4910; }
    .exp-card-link { display:block; height:100%; }
    .exp-cover { position:relative; aspect-ratio:4/3; background:#EDF1F6; overflow:hidden; }
    .exp-cover img { width:100%; height:100%; object-fit:cover; }
    .exp-no-image { min-height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:10px; color:#7C8DA2; font-size:13px; }
    .exp-no-image svg { width:38px; height:38px; stroke-width:1.2; }
    .exp-badge { display:inline-flex; align-items:center; color:#2453A4; background:#EBF1FC; padding:5px 10px; border-radius:7px; font-size:11px; font-weight:700; }
    .exp-card-body { padding:20px; }
    .exp-card h3 { color:var(--exp-ink); font-size:20px; font-weight:800; line-height:1.35; margin:12px 0 7px; }
    .exp-card-place { color:#64758B; font-size:13px; }
    .exp-card-summary { color:#6F7D90; font-size:14px; line-height:1.7; margin:12px 0 18px; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden; }
    .exp-card-action { display:flex; align-items:center; justify-content:space-between; color:var(--exp-blue); font-size:13px; font-weight:700; }
    .exp-state { padding:54px 24px; border:1px dashed #CCD6E3; border-radius:18px; text-align:center; background:white; }
    .exp-state h2 { color:var(--exp-ink); font-size:22px; font-weight:800; margin-bottom:10px; }
    .exp-state p { color:#6A7C91; line-height:1.8; margin-bottom:20px; }
    .exp-pagination { display:flex; gap:18px; align-items:center; justify-content:center; margin-top:32px; font-size:14px; color:#637286; }
    .exp-skeleton { height:380px; border-radius:18px; border:1px solid var(--exp-line); background:linear-gradient(110deg,#EDF1F5 8%,#FDFDFD 18%,#EDF1F5 33%); background-size:200% 100%; animation:exp-pulse 1.5s linear infinite; }
    .exp-breadcrumb { display:flex; flex-wrap:wrap; gap:10px; color:#74839A; font-size:13px; padding-top:30px; }
    .exp-breadcrumb a:hover { color:var(--exp-blue); }
    .exp-detail-heading { padding:32px 0; max-width:920px; }
    .exp-detail-heading h1 { font-size:clamp(32px,4vw,50px); }
    .exp-gallery { display:grid; grid-template-columns:2fr 1fr 1fr; grid-template-rows:180px 180px; gap:10px; border-radius:20px; overflow:hidden; }
    .exp-gallery button { position:relative; min-width:0; overflow:hidden; background:#EDF1F6; }
    .exp-gallery button:first-child { grid-row:1/-1; }
    .exp-gallery img { width:100%; height:100%; object-fit:cover; transition:transform .2s; }
    .exp-gallery button:hover img { transform:scale(1.025); }
    .exp-gallery--single { display:block; height:400px; }
    .exp-gallery--single button { width:100%; height:100%; }
    .exp-gallery--two { grid-template-columns:2fr 1fr; grid-template-rows:360px; }
    .exp-gallery--three { grid-template-columns:2fr 1fr; }
    .exp-gallery--empty { height:240px; background:#EDF1F6; display:block; }
    .exp-gallery--empty .exp-no-image { height:100%; }
    .exp-gallery-note { padding:10px 0; color:#77869A; font-size:12px; }
    .exp-details { display:grid; grid-template-columns:minmax(0,1.7fr) minmax(270px,1fr); gap:40px; margin:36px 0 54px; align-items:start; }
    .exp-description { color:#53647B; font-size:16px; line-height:1.95; white-space:pre-line; margin-top:18px; overflow-wrap:anywhere; }
    .exp-facts { padding:26px; border:1px solid var(--exp-line); background:white; border-radius:18px; }
    .exp-facts h2 { color:var(--exp-ink); font-size:20px; font-weight:800; margin-bottom:20px; }
    .exp-facts dl { display:grid; gap:22px; }
    .exp-facts dt { font-size:12px; color:#7A889A; margin-bottom:5px; }
    .exp-facts dd { font-size:14px; line-height:1.7; overflow-wrap:anywhere; }
    .exp-source { display:inline-block; color:var(--exp-blue); margin-top:22px; font-size:13px; text-decoration:underline; text-underline-offset:3px; }
    .exp-hotels { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:20px; margin-top:24px; }
    .exp-hotel { display:block; min-width:0; background:white; border:1px solid var(--exp-line); border-radius:14px; overflow:hidden; }
    .exp-hotel img { width:100%; aspect-ratio:4/3; object-fit:cover; background:#EDF1F6; }
    .exp-hotel div { padding:16px; }
    .exp-hotel h3 { color:var(--exp-ink); font-size:16px; font-weight:800; }
    .exp-hotel p { margin-top:8px; color:#708097; font-size:13px; line-height:1.7; }
    .exp-lightbox { width:min(1100px,calc(100vw - 32px)); padding:18px; border:0; border-radius:18px; background:#FFF; color:var(--exp-ink); }
    .exp-lightbox::backdrop { background:#071525D9; }
    .exp-lightbox img { width:100%; height:min(65dvh,700px); object-fit:contain; background:#F8F8F8; }
    .exp-lightbox-bar { display:flex; gap:12px; align-items:center; justify-content:space-between; padding:12px 0; }
    .exp-lightbox p { color:#65768C; font-size:13px; overflow-wrap:anywhere; }
    .exp-page [hidden] { display:none !important; }
    .exp-page :is(a,button,input,select):focus-visible { outline:2px solid var(--exp-blue); outline-offset:3px; }
    @keyframes exp-pulse { to { background-position:-200% 0; } }
    @media(max-width:1023px) { .exp-hero { gap:30px; grid-template-columns:1.4fr 1fr; } .exp-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } .exp-filters { grid-template-columns:1fr 1fr; } .exp-details { gap:24px; } .exp-hotels { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media(max-width:639px) { .exp-wrap { padding-inline:18px; } .exp-hero { grid-template-columns:1fr; padding:32px 0 24px; gap:24px; } .exp-hero-note { border:0; padding:0; } .exp-hero-note p { display:none; } .exp-hero-note ul { gap:14px; } .exp-intro { font-size:15px; } .exp-categories { flex-wrap:nowrap; overflow-x:auto; margin-right:-18px; padding-right:18px; gap:8px; } .exp-category { flex-shrink:0; padding-inline:16px; } .exp-filters { grid-template-columns:1fr; padding:18px; border-radius:16px; gap:14px; } .exp-results-heading { align-items:flex-start; flex-direction:column; gap:8px; padding-top:28px; } .exp-grid { grid-template-columns:1fr; gap:20px; } .exp-details { grid-template-columns:1fr; gap:28px; } .exp-gallery { grid-template-columns:1fr 1fr; grid-template-rows:240px 110px; } .exp-gallery button:first-child { grid-column:1/-1; grid-row:auto; } .exp-gallery button:nth-child(n+4) { display:none; } .exp-gallery--single { height:280px; } .exp-gallery--empty { height:200px; } .exp-hotels { grid-template-columns:1fr; } .exp-section-title { font-size:23px; } .exp-page { padding-bottom:48px; } }
    @media(max-width:639px) { .exp-hero-note { display:none; } .exp-filters { grid-template-columns:repeat(2,minmax(0,1fr)); } .exp-filters .exp-field:nth-child(2) { grid-column:1/-1; grid-row:1; } .exp-filters > .exp-button { grid-column:1/-1; } }
    @media(prefers-reduced-motion:reduce) { .exp-page *, .exp-lightbox * { animation:none !important; transition:none !important; } }
    .exp-state[role="alert"] { color:#B91C1C; background:#FEF2F2; border:1px solid #FCA5A5; border-top:4px solid #DC2626; }
    .exp-state[role="alert"] p { color:#991B1B; }
    .exp-button:hover:not(:disabled) { background:#1D4ED8; border-color:#1D4ED8; }
    .exp-button--quiet:hover:not(:disabled) { background:#EFF6FF; color:#1D4ED8; border-color:#93C5FD; }
</style>
