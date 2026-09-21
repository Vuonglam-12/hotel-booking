@extends('layout')

@section('title', 'Trang chủ')

@section('content')

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<style>
    /* ========== HERO CAROUSEL ========== */
    .hero-wrapper { position: relative; width: 100%; margin-bottom: 40px; }
    .hero-container { position: relative; width: 100%; height: 680px; min-height: 620px; overflow: hidden; }
    .hero-slide { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; transition: opacity 1.1s cubic-bezier(0.4, 0, 0.2, 1); pointer-events: none; }
    .hero-slide.active { opacity: 1; pointer-events: auto; }
    .hero-slide img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: center 35%; transform: scale(1.04); transition: transform 5s ease; }
    .hero-slide.active img { transform: scale(1); }
    .hero-gradient { position: absolute; inset: 0; background: linear-gradient(90deg, rgba(15, 23, 42, 0.82) 0%, rgba(15, 23, 42, 0.45) 45%, rgba(15, 23, 42, 0.08) 100%); z-index: 1; }
    .hero-content { position: relative; z-index: 10; height: 100%; display: flex; flex-direction: column; justify-content: center; max-width: 1280px; margin: 0 auto; padding: 0 5%; }
    .hero-arrow { position: absolute; top: 50%; transform: translateY(-50%); z-index: 20; background: rgba(255,255,255,0.15); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,0.3); color: #fff; width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s, transform 0.2s; }
    .hero-arrow:hover { background: rgba(255,255,255,0.28); transform: translateY(-50%) scale(1.08); }
    .hero-arrow-prev { left: 20px; } .hero-arrow-next { right: 20px; }
    .hero-dots { position: absolute; bottom: 22px; left: 50%; transform: translateX(-50%); z-index: 20; display: flex; gap: 8px; align-items: center; }
    .hero-dot { width: 8px; height: 8px; border-radius: 50%; background: rgba(255,255,255,0.4); border: 1px solid rgba(255,255,255,0.5); cursor: pointer; transition: all 0.3s ease; }
    .hero-dot.active { background: #fff; width: 24px; border-radius: 4px; }

    /* ========== SEARCH BAR & DROPDOWN ========== */
    .search-wrapper { position: absolute; bottom: -38px; left: 50%; transform: translateX(-50%); z-index: 100; width: 100%; max-width: 1100px; padding: 0 20px; }
    .search-glass { background: rgba(255, 255, 255, 0.97); backdrop-filter: blur(20px); border-radius: 9999px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3); border: 1px solid rgba(255,255,255,0.7); padding: 10px 12px; transition: box-shadow 0.3s; }
    .search-glass:hover { box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
    @media (max-width: 768px) { .hero-container { height: 580px; } .hero-arrow { width: 38px; height: 38px; } .hero-arrow-prev { left: 10px; } .hero-arrow-next { right: 10px; } }
    #searchDropdown { scrollbar-width: thin; scrollbar-color: #C9D3DD transparent; }
    #searchDropdown::-webkit-scrollbar { width: 5px; } #searchDropdown::-webkit-scrollbar-thumb { background: #C9D3DD; border-radius: 99px; }
    .ac-item { display: flex; align-items: center; gap: 12px; padding: 10px 16px; cursor: pointer; transition: background 0.15s ease; border-bottom: 1px solid #F1F5F9; }
    .ac-item:last-child { border-bottom: none; } .ac-item:hover, .ac-item.ac-active { background: #EAF3FF; }
    .ac-img-wrap { width: 52px; height: 44px; border-radius: 10px; overflow: hidden; flex-shrink: 0; background: #EAF3FF; }
    .ac-img-wrap img { width: 100%; height: 100%; object-fit: cover; }
    .ac-info { flex: 1; min-width: 0; } .ac-name { font-size: 14px; font-weight: 600; color: #1E3A5F; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ac-name mark { background: none; color: #0E5ED8; font-weight: 700; }
    .ac-location { font-size: 12px; color: #94A3B8; margin-top: 2px; display: flex; align-items: center; gap: 3px; }
    .ac-price { font-size: 13px; font-weight: 700; color: #87CEFA; flex-shrink: 0; text-align: right; }
    .ac-header { padding: 8px 16px 4px; font-size: 11px; font-weight: 700; color: #94A3B8; text-transform: uppercase; letter-spacing: 0.08em; background: #F8FAFC; }
    .ac-empty { padding: 24px 16px; text-align: center; color: #94A3B8; font-size: 14px; }
    .ac-footer { padding: 10px 16px; background: #F8FAFC; border-top: 1px solid #F1F5F9; font-size: 13px; color: #64748B; display: flex; align-items: center; gap: 6px; cursor: pointer; transition: background 0.15s; }
    .ac-footer:hover { background: #EAF3FF; color: #0E5ED8; }
    @keyframes acDropIn { from { opacity: 0; transform: translateY(-8px); } to { opacity: 1; transform: translateY(0); } }
    #searchDropdown:not(.hidden) { animation: acDropIn 0.18s ease; }

    /* ========== FLASH SALE ========== */
    .flash-wrapper { margin-bottom: 60px; }
    .flash-banner { position: relative; height: 400px; border-radius: 24px; overflow: hidden; box-shadow: 0 20px 40px -12px rgba(30, 58, 95, 0.2); }
    .flash-slide { position: absolute; inset: 0; opacity: 0; transition: opacity 1s cubic-bezier(0.4, 0, 0.2, 1); z-index: 0; }
    .flash-slide.active { opacity: 1; }
    .flash-bg { position: absolute; inset: 0; background-size: cover; background-position: center; transition: transform 6s ease; transform: scale(1.04); z-index: 0; }
    .flash-slide.active .flash-bg { transform: scale(1); }
    .flash-overlay { position: absolute; inset: 0; background: linear-gradient(90deg, rgba(15, 23, 42, 0.95) 0%, rgba(15, 23, 42, 0.7) 50%, rgba(15, 23, 42, 0.1) 100%); z-index: 1; }
    .flash-content { position: relative; z-index: 10; height: 100%; display: flex; align-items: center; padding: 0 6%; }
    .flash-badge { display: inline-flex; align-items: center; gap: 6px; background: linear-gradient(135deg, #F59E0B, #EF4444); color: white; font-size: 13px; font-weight: 700; padding: 6px 16px; border-radius: 20px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 16px; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4); }
    .countdown-container { position: absolute; bottom: 30px; right: 6%; z-index: 10; display: flex; align-items: center; gap: 15px; }
    .countdown-box { background: rgba(255, 255, 255, 0.15); backdrop-filter: blur(8px); border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 12px; padding: 10px 16px; text-align: center; min-width: 65px; }
    .flash-dots { position: absolute; bottom: 18px; left: 6%; z-index: 20; display: flex; gap: 7px; align-items: center; }
    .flash-dot { width: 7px; height: 7px; border-radius: 50%; background: rgba(255,255,255,0.35); border: 1px solid rgba(255,255,255,0.5); cursor: pointer; transition: all 0.3s ease; }
    .flash-dot.active { background: #F59E0B; width: 20px; border-radius: 4px; border-color: #F59E0B; }
    .flash-arrow { position: absolute; top: 50%; transform: translateY(-50%); z-index: 20; background: rgba(255,255,255,0.12); backdrop-filter: blur(6px); border: 1px solid rgba(255,255,255,0.25); color: #fff; width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: background 0.2s, transform 0.2s; }
    .flash-arrow:hover { background: rgba(255,255,255,0.22); transform: translateY(-50%) scale(1.08); }
    .flash-arrow-prev { left: 16px; } .flash-arrow-next { right: 16px; }

    /* ========== BUTTONS & SKELETONS ========== */
    .load-more-btn { position: relative; overflow: hidden; min-width: 240px; }
    .load-more-btn::before { content: ''; position: absolute; top: 50%; left: 50%; width: 0; height: 0; border-radius: 50%; background: rgba(14, 94, 216, 0.1); transform: translate(-50%, -50%); transition: width 0.6s, height 0.6s; }
    .load-more-btn:hover::before { width: 300px; height: 300px; }
    .load-more-btn.loading { pointer-events: none; opacity: 0.7; }
    .load-more-btn.loading svg { animation: spin 0.8s linear infinite; }
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    @keyframes skeleton-loading { 0% { background-position: -200px 0; } 100% { background-position: calc(200px + 100%) 0; } }
    .skeleton-card { background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200px 100%; animation: skeleton-loading 1.5s infinite; }

    /* ========== HOTELS BADGES & ANIMATIONS ========== */
    @keyframes fhStarPulse { 0%, 100% { transform: scale(1) rotate(0deg); filter: drop-shadow(0 0 6px #FFD700) drop-shadow(0 0 14px #FFA500); } 50% { transform: scale(1.18) rotate(10deg); filter: drop-shadow(0 0 14px #FFD700) drop-shadow(0 0 28px #FFD700); } }
    @keyframes fhTextShimmer { 0% { background-position: -200% center; } 100% { background-position: 200% center; } }
    @keyframes fhBadgePop { 0%, 100% { transform: scale(1); box-shadow: 0 0 8px #FFD700, 0 0 16px #FFA500; } 50% { transform: scale(1.04); box-shadow: 0 0 18px #FFD700, 0 0 36px #FFA500; } }
    @keyframes fhFloat { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-3px); } }
    @keyframes fhSp1 { 0%, 100% { opacity: 0; transform: translate(0,0) scale(0); } 50% { opacity: 1; transform: translate(-5px,-5px) scale(1); } }
    @keyframes fhSp2 { 0%, 100% { opacity: 0; transform: translate(0,0) scale(0); } 35% { opacity: 1; transform: translate(5px,-4px) scale(1); } }
    @keyframes fhSp3 { 0%, 100% { opacity: 0; transform: translate(0,0) scale(0); } 65% { opacity: 1; transform: translate(-3px, 6px) scale(1); } }
    @keyframes fhCross { 0%, 100% { opacity: 0; transform: rotate(0deg) scale(0); } 40%, 60% { opacity: 1; transform: rotate(45deg) scale(1); } }
    .featured-hotel-badge { display: inline-flex; align-items: center; gap: 10px; background: linear-gradient(135deg, #1a0f00, #2d1a00); border: 1.5px solid #b8860b; border-radius: 14px; padding: 8px 16px; animation: fhBadgePop 2.5s ease-in-out infinite, fhFloat 3s ease-in-out infinite; cursor: pointer; }
    .fh-icon-wrap { position: relative; width: 26px; height: 26px; flex-shrink: 0; }
    .fh-star { width: 26px; height: 26px; fill: #FFD700; stroke: #FFA500; stroke-width: 0.5; animation: fhStarPulse 2s ease-in-out infinite; }
    .fh-sp { position: absolute; border-radius: 50%; background: #FFD700; }
    .fh-sp1 { width: 4px; height: 4px; top: -2px; right: -2px; animation: fhSp1 2s ease-in-out infinite; }
    .fh-sp2 { width: 3px; height: 3px; bottom: 0; right: -4px; animation: fhSp2 2s 0.4s ease-in-out infinite; }
    .fh-sp3 { width: 3px; height: 3px; top: 4px; left: -4px; animation: fhSp3 2s 0.8s ease-in-out infinite; }
    .fh-cross { position: absolute; width: 12px; height: 12px; }
    .fh-cross::before, .fh-cross::after { content: ''; position: absolute; background: #FFD700; border-radius: 2px; }
    .fh-cross::before { width: 2px; height: 100%; left: 5px; top: 0; } .fh-cross::after { width: 100%; height: 2px; left: 0; top: 5px; }
    .fh-cross1 { right: -14px; top: -8px; animation: fhCross 2.5s 0s ease-in-out infinite; }
    .fh-cross2 { left: -14px; bottom: -8px; animation: fhCross 2.5s 0.6s ease-in-out infinite; }
    .fh-text { font-size: 15px; font-weight: 500; background: linear-gradient(90deg, #b8860b, #FFD700, #FFA500, #FFD700, #b8860b); background-size: 200% auto; -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; animation: fhTextShimmer 2.5s linear infinite; white-space: nowrap; }

    @keyframes ratingPop { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.08); } }
    @keyframes starWiggle { 0%, 100% { transform: rotate(0deg); } 25% { transform: rotate(-12deg); } 75% { transform: rotate(12deg); } }
    .rating-badge { background: #FFF8E1; border: 1.5px solid #F59E0B; border-radius: 20px; padding: 3px 10px 3px 7px; animation: ratingPop 2.8s ease-in-out infinite; }
    .rating-star { animation: starWiggle 2.8s ease-in-out infinite; }
    .rating-score { color: #92400E; font-weight: 700; letter-spacing: 0.01em; }
    
    .info-card { background: #FFFBEB; border: 1.5px solid #F59E0B; border-radius: 12px; padding: 14px 16px; display: flex; flex-direction: column; gap: 8px; }
    .info-row { display: flex; align-items: center; gap: 8px; }
    .info-icon { font-size: 15px; flex-shrink: 0; }
    .info-star-svg { width: 15px; height: 15px; flex-shrink: 0; animation: starWiggle 2.8s ease-in-out infinite; }
    .info-text { font-size: 13px; color: #92400E; }
    .info-label { font-weight: 700; color: #78350F; }
</style>

    {{-- ===== 1. HERO CAROUSEL & SEARCH ===== --}}
    @include('partials.home.hero-section')

    {{-- ===== 2. ABOUT SECTION ===== --}}
    @include('partials.home.about-section')

    {{-- ===== 3. FLASH SALE DEALS ===== --}}
    @include('partials.home.flash-section')

    {{-- ===== 4. KHÁCH SẠN NỔI BẬT ===== --}}
    @if(!request()->hasAny(['search', 'city', 'star']))
        @include('partials.home.featured')
    @endif

    {{-- ===== 5. DANH SÁCH KHÁCH SẠN ===== --}}
    @include('partials.home.all')

    {{-- ===== 6 & CHATBOT ===== --}}
    @include('partials.home.chatbot-section')

@endsection

@push('scripts')
    {{-- Tải JS theo từng module rất dễ debug --}}
    @include('partials.home.hero-script')
    @include('partials.home.flash-script')
    @include('partials.home.script')
    @include('partials.home.chatbot-script')
@endpush