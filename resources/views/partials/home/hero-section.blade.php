<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
  <title>Green Haven Homestay</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;1,400&family=Inter:wght@300;400;500&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    :root{
      --brand:#C96A2E;--brand-lt:#F0E6D3;--brand-dk:#2C2118;--brand-mid:#7A6856;--brand-hero:#EFE3D4;
      --sage:#87A878;--sage-lt:#EBF2E6;--sage-mid:#C4D9BA;--sage-dk:#3A5030;
      --cream:#FAFAF5;--white:#ffffff;
      --r-sm:8px;--r-md:12px;--r-lg:16px;--r-pill:9999px
    }
    html{scroll-behavior:smooth}
    body{font-family:'Inter',sans-serif;background:var(--cream);color:var(--brand-dk);font-size:15px;line-height:1.6;-webkit-font-smoothing:antialiased}
    .serif{font-family:'Playfair Display',serif}

    /* NAV */
    nav{position:sticky;top:0;z-index:100;background:var(--white);border-bottom:0.5px solid #E4D9CF;padding:0 48px;height:60px;display:flex;align-items:center;justify-content:space-between}
    .nav-logo{font-family:'Playfair Display',serif;font-size:20px;font-weight:600;color:var(--brand-dk);text-decoration:none;letter-spacing:-0.3px}
    .nav-logo span{color:var(--sage)}
    .nav-links{display:flex;gap:32px;align-items:center}
    .nav-links a{font-size:14px;color:var(--brand-mid);text-decoration:none;transition:color .2s}
    .nav-links a:hover{color:var(--brand-dk)}
    .nav-cta{background:var(--brand);color:#fff;border:none;border-radius:var(--r-sm);padding:9px 20px;font-size:14px;font-weight:500;cursor:pointer;font-family:'Inter',sans-serif;transition:background .2s}
    .nav-cta:hover{background:#b05a22}

    /* HERO BANNER SLIDER */
    .hero-slider{position:relative;overflow:hidden;height:560px}
    .hero-slides{display:flex;height:100%;transition:transform .65s cubic-bezier(.4,0,.2,1)}
    .hero-slide{min-width:100%;height:100%;flex-shrink:0;position:relative}
    .hero-slide img{width:100%;height:100%;object-fit:cover;display:block}

    /* overlay tối nhẹ để text đọc được trên ảnh */
    .hero-overlay{position:absolute;inset:0;background:rgba(30,20,12,.38);pointer-events:none}

    /* text content đặt chính giữa, trên overlay */
    .hero-content{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:0 48px 60px;z-index:2}
    .hero-eye{display:inline-block;font-size:11px;font-weight:500;color:#E8C9A0;letter-spacing:2px;text-transform:uppercase;margin-bottom:20px}
    .hero-content h1{font-family:'Playfair Display',serif;font-size:52px;font-weight:600;color:#fff;line-height:1.15;margin-bottom:16px;letter-spacing:-0.5px}
    .hero-content h1 em{font-style:italic;color:var(--sage-mid)}
    .hero-sub{font-size:16px;color:rgba(255,255,255,.82);max-width:420px;margin:0 auto;line-height:1.7;font-weight:300}

    /* mũi tên trái / phải */
    .hero-arrow{position:absolute;top:50%;transform:translateY(-60%);background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.35);border-radius:50%;width:46px;height:46px;color:#fff;font-size:26px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .2s;z-index:5;line-height:1}
    .hero-arrow:hover{background:rgba(255,255,255,.38)}
    .hero-prev{left:20px}
    .hero-next{right:20px}

    /* dots navigation */
    .hero-dots{position:absolute;bottom:72px;left:50%;transform:translateX(-50%);display:flex;gap:8px;z-index:5}
    .hero-dot{width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,.45);border:none;cursor:pointer;transition:width .25s,background .25s,border-radius .25s;padding:0}
    .hero-dot.active{width:26px;border-radius:4px;background:#fff}

    /* PILL SEARCH */
    .search-wrap{position:relative;z-index:200;margin:-32px auto 0;max-width:800px;padding:0 48px}
    .pill-bar{background:#EDEBE8;border-radius:var(--r-pill);padding:6px;display:flex;align-items:center;position:relative}
    .pill-bg{position:absolute;top:6px;left:6px;height:calc(100% - 12px);background:var(--white);border-radius:var(--r-pill);box-shadow:0 2px 10px rgba(44,33,24,.14);transition:transform .3s cubic-bezier(.4,0,.2,1),width .3s cubic-bezier(.4,0,.2,1);pointer-events:none;z-index:0;opacity:0}
    .pill-bg.visible{opacity:1}
    .sf{flex:1;display:flex;flex-direction:column;gap:2px;padding:10px 22px;border-radius:var(--r-pill);cursor:pointer;position:relative;z-index:1;border:none;background:transparent;font-family:'Inter',sans-serif;text-align:left;transition:background .15s}
    .sf:hover:not(.active){background:rgba(255,255,255,.45)}
    .sf.active .sf-label{color:var(--brand-dk)}
    .sf-label{font-size:11px;color:var(--brand-mid);font-weight:500;letter-spacing:.2px;pointer-events:none}
    .sf-val{font-size:14px;color:var(--brand-dk);font-weight:500;pointer-events:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .sf-div{width:1px;height:22px;background:#D4CBC2;flex-shrink:0;transition:opacity .2s;z-index:1}
    .sf-div.hide{opacity:0}
    .pill-btn{flex-shrink:0;background:var(--brand);color:#fff;border:none;border-radius:var(--r-pill);width:48px;height:48px;display:flex;align-items:center;justify-content:center;cursor:pointer;margin-left:6px;transition:background .2s,transform .15s;position:relative;z-index:1}
    .pill-btn:hover{background:#b05a22;transform:scale(1.05)}
    .pill-btn svg{width:18px;height:18px;stroke:#fff;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round}

    /* DROPDOWNS */
    .sf-dropdown{position:absolute;top:calc(100% + 14px);background:var(--white);border-radius:20px;border:0.5px solid #E4D9CF;box-shadow:0 8px 32px rgba(44,33,24,.14);padding:24px;display:none;z-index:300;animation:dropIn .2s ease}
    .sf-dropdown.open{display:block}
    @keyframes dropIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}

    /* Room dropdown */
    .dd-room{min-width:280px;left:0}
    .room-opt{display:flex;align-items:center;gap:14px;padding:12px 14px;border-radius:12px;cursor:pointer;transition:background .15s;border:1.5px solid transparent}
    .room-opt:hover{background:var(--sage-lt)}
    .room-opt.sel{border-color:var(--brand);background:var(--brand-lt)}
    .room-opt-icon{font-size:26px;width:36px;text-align:center}
    .room-opt-name{font-size:14px;font-weight:500;color:var(--brand-dk)}
    .room-opt-price{font-size:12px;color:var(--brand-mid)}

    /* Calendar dropdown */
    .dd-cal{min-width:300px;left:0}
    .cal-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
    .cal-title{font-size:14px;font-weight:600;color:var(--brand-dk)}
    .cal-nav{background:none;border:0.5px solid #E4D9CF;border-radius:50%;width:30px;height:30px;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--brand-mid);font-size:16px;transition:border-color .15s;line-height:1}
    .cal-nav:hover{border-color:var(--brand-dk)}
    .cal-dnames{display:grid;grid-template-columns:repeat(7,1fr);margin-bottom:6px}
    .cal-dname{font-size:11px;color:var(--brand-mid);text-align:center;padding:3px 0}
    .cal-days{display:grid;grid-template-columns:repeat(7,1fr);gap:2px}
    .cal-day{aspect-ratio:1;display:flex;align-items:center;justify-content:center;font-size:13px;color:var(--brand-dk);border-radius:50%;cursor:pointer;transition:background .1s;border:none;background:none;font-family:'Inter',sans-serif}
    .cal-day:hover:not(.empty):not(.past){background:var(--brand-lt)}
    .cal-day.past{color:#CFC5BA;cursor:default}
    .cal-day.empty{cursor:default}
    .cal-day.s-start,.cal-day.s-end{background:var(--brand);color:#fff}
    .cal-day.in-range{background:var(--brand-lt);border-radius:0}
    .cal-day.s-start{border-radius:50% 0 0 50%}
    .cal-day.s-end{border-radius:0 50% 50% 0}
    .cal-day.s-start.s-end{border-radius:50%}

    /* Guest dropdown */
    .dd-guests{min-width:300px;right:60px;left:auto}
    .g-row{display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:0.5px solid #F0EAE4}
    .g-row:last-child{border-bottom:none}
    .g-label{font-size:14px;font-weight:500;color:var(--brand-dk)}
    .g-sub{font-size:12px;color:var(--brand-mid)}
    .g-ctrl{display:flex;align-items:center;gap:14px}
    .g-btn{width:28px;height:28px;border-radius:50%;border:1px solid #D4CBC2;background:none;cursor:pointer;font-size:18px;color:var(--brand-mid);display:flex;align-items:center;justify-content:center;transition:border-color .15s,color .15s;line-height:1}
    .g-btn:hover:not(:disabled){border-color:var(--brand-dk);color:var(--brand-dk)}
    .g-btn:disabled{opacity:.3;cursor:default}
    .g-count{font-size:15px;font-weight:500;color:var(--brand-dk);min-width:18px;text-align:center}

    /* SECTIONS */
    .section{padding:72px 48px}
    .section-white{background:var(--white)}
    .section-sage{background:var(--sage-lt)}
    .section-warm{background:var(--brand-hero)}
    .sec-hd{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:32px}
    .sec-hd h2{font-family:'Playfair Display',serif;font-size:28px;font-weight:600;color:var(--brand-dk);letter-spacing:-0.3px}
    .sec-hd h2.sg{color:var(--sage-dk)}
    .sec-hd a{font-size:13px;color:var(--brand);text-decoration:none;font-weight:500}
    .sec-hd a:hover{opacity:.7}

    /* CAROUSEL */
    .car-outer{overflow:hidden;border-radius:var(--r-lg)}
    .car-track{display:flex;gap:20px;transition:transform .45s cubic-bezier(.4,0,.2,1)}
    .room-card{min-width:calc(33.333% - 14px);background:var(--white);border-radius:var(--r-lg);border:0.5px solid #E4D9CF;overflow:hidden;flex-shrink:0;transition:transform .25s}
    .room-card:hover{transform:translateY(-4px)}
    .room-thumb{height:180px;display:flex;align-items:center;justify-content:center;font-size:48px;position:relative}
    .r-badge{position:absolute;top:12px;left:12px;font-size:10px;font-weight:500;padding:4px 10px;border-radius:6px}
    .b-warm{background:var(--brand);color:#fff}
    .b-sage{background:var(--sage);color:#fff}
    .b-dark{background:var(--brand-dk);color:#fff}
    .room-body{padding:16px 18px 18px}
    .room-name{font-family:'Playfair Display',serif;font-size:16px;font-weight:600;color:var(--brand-dk);margin-bottom:4px}
    .room-loc{font-size:12px;color:var(--brand-mid);margin-bottom:12px}
    .room-ft{display:flex;justify-content:space-between;align-items:center}
    .room-price{font-size:17px;font-weight:500;color:var(--brand)}
    .room-price span{font-size:12px;font-weight:400;color:var(--brand-mid)}
    .room-stars{font-size:13px;color:var(--sage-dk);letter-spacing:1px}
    .dots{display:flex;gap:6px;justify-content:center;margin-top:20px}
    .dot{width:6px;height:6px;border-radius:50%;background:#CFC5BA;border:none;padding:0;cursor:pointer;transition:width .25s,background .25s,border-radius .25s}
    .dot.active{width:22px;border-radius:4px;background:var(--brand)}

    /* AMENITIES */
    .amen-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
    .amen-card{background:var(--white);border:0.5px solid var(--sage-mid);border-radius:var(--r-lg);padding:24px 20px;text-align:center;transition:transform .2s}
    .amen-card:hover{transform:translateY(-3px)}
    .amen-ico{width:52px;height:52px;border-radius:50%;background:var(--sage-lt);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;color:var(--sage-dk)}
    .amen-ico svg{width:24px;height:24px;stroke:currentColor;fill:none;stroke-width:1.75;stroke-linecap:round;stroke-linejoin:round}
    .amen-name{font-size:14px;font-weight:500;color:var(--brand-dk);margin-bottom:4px}
    .amen-desc{font-size:12px;color:var(--brand-mid)}

    /* ABOUT */
    .about-grid{display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:center}
    .about-tag{font-size:11px;font-weight:500;color:var(--sage-dk);text-transform:uppercase;letter-spacing:1.5px;margin-bottom:14px}
    .about-grid h2{font-family:'Playfair Display',serif;font-size:32px;font-weight:600;color:var(--brand-dk);line-height:1.25;margin-bottom:16px;letter-spacing:-0.3px}
    .about-grid p{font-size:15px;color:var(--brand-mid);line-height:1.75;margin-bottom:28px;font-weight:300}
    .stats{display:flex;gap:32px}
    .stat-n{font-family:'Playfair Display',serif;font-size:32px;font-weight:600;color:var(--brand);line-height:1;margin-bottom:4px}
    .stat-l{font-size:12px;color:var(--brand-mid)}
    .about-vis{border-radius:var(--r-lg);height:340px;background:var(--sage-mid);display:flex;align-items:center;justify-content:center;font-size:80px}

    /* REVIEWS */
    .rev-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
    .rev-card{background:var(--white);border:0.5px solid var(--sage-mid);border-radius:var(--r-lg);padding:20px}
    .rev-stars{font-size:14px;color:var(--sage-dk);margin-bottom:10px;letter-spacing:1px}
    .rev-text{font-size:14px;color:var(--brand-dk);line-height:1.65;margin-bottom:16px;font-weight:300}
    .rev-auth{display:flex;align-items:center;gap:10px}
    .rev-av{width:36px;height:36px;border-radius:50%;background:var(--sage-lt);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:500;color:var(--sage-dk);flex-shrink:0}
    .rev-name{font-size:13px;font-weight:500;color:var(--brand-dk)}
    .rev-date{font-size:11px;color:var(--brand-mid)}

    /* CTA */
    .cta-ban{background:var(--brand-dk);padding:64px 48px;text-align:center}
    .cta-ban h2{font-family:'Playfair Display',serif;font-size:32px;font-weight:600;color:#fff;margin-bottom:10px}
    .cta-ban p{font-size:15px;color:#C4B5A5;margin-bottom:28px;font-weight:300}
    .cta-btn{background:var(--brand);color:#fff;border:none;border-radius:var(--r-sm);padding:14px 32px;font-size:15px;font-weight:500;cursor:pointer;font-family:'Inter',sans-serif;transition:background .2s;margin:0 8px}
    .cta-btn:hover{background:#b05a22}
    .cta-btn.out{background:transparent;border:1px solid #C4B5A5;color:#C4B5A5}
    .cta-btn.out:hover{border-color:#fff;color:#fff;background:transparent}

    /* FOOTER */
    footer{background:var(--brand-dk);border-top:0.5px solid rgba(255,255,255,.06);padding:40px 48px 28px}
    .ft-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:32px}
    .ft-logo{font-family:'Playfair Display',serif;font-size:18px;font-weight:600;color:#fff}
    .ft-logo span{color:var(--sage-mid)}
    .ft-tag{font-size:13px;color:#A8998A;margin-top:6px;font-weight:300}
    .ft-cols{display:flex;gap:40px}
    .ft-col-t{font-size:12px;font-weight:500;color:#fff;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px}
    .ft-col a{display:block;font-size:13px;color:#A8998A;text-decoration:none;margin-bottom:8px;transition:color .2s;font-weight:300}
    .ft-col a:hover{color:#fff}
    .ft-bot{border-top:0.5px solid rgba(255,255,255,.06);padding-top:20px;display:flex;justify-content:space-between;align-items:center}
    .ft-copy{font-size:12px;color:#6B5E52}
    .ft-soc{display:flex;gap:16px}
    .ft-soc a{font-size:12px;color:#6B5E52;text-decoration:none;transition:color .2s}
    .ft-soc a:hover{color:#A8998A}

    /* ANIMATIONS */
    .fade-up{opacity:0;transform:translateY(24px);transition:opacity .6s ease,transform .6s ease}
    .fade-up.visible{opacity:1;transform:translateY(0)}

    /* RESPONSIVE */
    @media(max-width:900px){
      .hero-slider{height:380px}
      .hero-content h1{font-size:32px}
      .hero-prev{left:10px}.hero-next{right:10px}
      nav{padding:0 24px}
      .nav-links a{display:none}
      .hero{padding:48px 24px 80px}
      .hero h1{font-size:36px}
      .search-wrap{padding:0 16px}
      .pill-bar{flex-wrap:wrap;border-radius:20px;gap:4px}
      .sf{min-width:40%;border-radius:12px}
      .sf-div{display:none}
      .pill-btn{width:100%;border-radius:12px;height:44px;margin-left:0}
      .section{padding:48px 24px}
      .amen-grid{grid-template-columns:repeat(2,1fr)}
      .about-grid{grid-template-columns:1fr;gap:32px}
      .rev-grid{grid-template-columns:1fr}
      .room-card{min-width:calc(80% - 10px)}
      .dd-cal{min-width:320px;left:0;transform:none}
      .cal-grid{grid-template-columns:1fr}
      .cal-sep{display:none}
      footer{padding:32px 24px 20px}
      .ft-top{flex-direction:column;gap:32px}
    }
  </style>
</head>
<body>
