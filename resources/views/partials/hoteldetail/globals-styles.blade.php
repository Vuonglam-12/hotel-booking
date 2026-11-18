<style>
body.hv-detail-page { background: var(--hv-bg); }
.hv-hotel-detail { --soft-sky: #87CEFA; --soft-bg: #EAF3FF; --soft-gray: #C9D3DD; --dark-soft: #3A4A5A; background: var(--hv-bg); color: var(--hv-text); padding-bottom: 48px; }
.hv-hotel-detail [hidden] { display: none; }
.hv-detail-container { width: 100%; max-width: 1280px; margin-inline: auto; padding-inline: 32px; }
.hv-detail-heading { padding-top: 32px; padding-bottom: 24px; }
.hv-detail-heading h1 { font-size: 32px; line-height: 1.25; }
.hv-detail-meta { display: flex; flex-wrap: wrap; gap: 8px 24px; margin-top: 12px; color: var(--hv-text-secondary); font-size: 14px; }
.hv-detail-gallery-section { padding-bottom: 32px; }
.hv-detail-gallery-wrap { position: relative; }
.hv-detail-gallery { display: grid; grid-template-columns: 2fr 1fr 1fr; grid-template-rows: repeat(2, minmax(0, 1fr)); gap: 8px; height: 400px; overflow: hidden; border-radius: 16px; }
.hv-detail-gallery-tile { min-width: 0; min-height: 0; padding: 0; overflow: hidden; background: var(--hv-bg-secondary); }
.hv-detail-gallery-tile:first-child { grid-row: span 2; }
.hv-detail-gallery-tile img { display: block; width: 100%; height: 100%; object-fit: cover; }
.hv-detail-gallery--1 { grid-template-columns: 1fr; grid-template-rows: 1fr; height: clamp(240px, 28vw, 400px); }
.hv-detail-gallery--2 { grid-template-columns: repeat(2, minmax(0, 1fr)); grid-template-rows: 1fr; }
.hv-detail-gallery--1 .hv-detail-gallery-tile:first-child, .hv-detail-gallery--2 .hv-detail-gallery-tile:first-child { grid-row: auto; }
.hv-detail-gallery--3 { grid-template-columns: 2fr 1fr; }
.hv-detail-gallery--4 .hv-detail-gallery-tile:last-child { grid-column: span 2; }
.hv-detail-gallery-all, .hv-detail-gallery-empty { position: absolute; bottom: 16px; right: 16px; padding: 8px 16px; border: 1px solid var(--hv-border); border-radius: 10px; background: var(--hv-bg); font-size: 14px; }
.hv-detail-layout { display: grid; grid-template-columns: minmax(0, 1fr) 360px; align-items: start; gap: 32px; }
.hv-detail-sections { display: grid; gap: 32px; min-width: 0; }
.hv-detail-sidebar { position: sticky; top: 80px; min-width: 0; max-height: calc(100dvh - 96px); overflow-y: auto; overscroll-behavior: contain; scrollbar-width: thin; }
.hv-hotel-detail .info-card { border-radius: 16px; box-shadow: none; }
.hv-detail-section-heading h2, .hv-booking-panel h2 { font-size: 24px; line-height: 1.3; }
.hv-detail-section-heading p, .hv-booking-intro { margin-top: 8px; color: var(--hv-text-secondary); font-size: 14px; line-height: 1.5; }
.hv-room-list { display: grid; gap: 16px; margin-top: 24px; }
.hv-room-card { display: grid; grid-template-columns: 220px minmax(0, 1fr); overflow: hidden; border: 1px solid var(--hv-border); border-radius: 16px; background: var(--hv-bg); cursor: pointer; transition: border-color var(--hv-duration); }
.hv-room-card[data-room-selected="true"] { border-color: var(--hv-accent); }
.hv-room-cover { min-width: 0; padding: 0; background: var(--hv-bg-secondary); }
.hv-room-cover img { display: block; width: 100%; height: 100%; min-height: 220px; object-fit: cover; }
.hv-room-card-content { min-width: 0; padding: 24px; }
.hv-room-card-heading { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; }
.hv-room-card h3 { font-size: 20px; line-height: 1.3; }
.hv-room-card h3 button { text-align: left; }
.hv-room-selected-label { flex-shrink: 0; color: var(--hv-accent); font-size: 14px; }
.hv-room-facts { display: flex; flex-wrap: wrap; gap: 4px 16px; margin-top: 8px; color: var(--hv-text-secondary); font-size: 14px; }
.hv-room-description { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-top: 12px; color: var(--hv-text-secondary); font-size: 14px; line-height: 1.5; }
.hv-room-availability { margin-top: 12px; color: var(--hv-text-secondary); font-size: 14px; line-height: 1.5; }
.hv-room-card-bottom { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-top: 16px; }
.hv-room-price { font-size: 16px; }
.hv-room-price strong { color: var(--hv-text); }
.hv-room-price span { color: var(--hv-text-secondary); font-size: 14px; }
.hv-booking-panel { padding: 24px; border: 1px solid var(--hv-border); border-radius: 16px; background: var(--hv-bg); scroll-margin-top: 80px; }
.hv-booking-fields { display: grid; gap: 16px; margin-top: 24px; }
.hv-booking-dates { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.hv-booking-panel label, .hv-booking-label { display: block; margin-bottom: 8px; font-size: 14px; }
.hv-booking-panel .hv-input { min-width: 0; font-size: 14px; }
.hv-booking-help { margin-top: 8px; font-size: 12px; line-height: 1.5; color: var(--hv-text-secondary); }
.hv-booking-status { margin-top: 16px; font-size: 14px; color: var(--hv-text-secondary); }
.hv-booking-error { margin-top: 16px; padding: 14px; border: 1px solid #FCA5A5; border-left: 4px solid #DC2626; border-radius: 10px; background:#FEF2F2; color:#B91C1C; font-size: 14px; font-weight:600; line-height: 1.65; }
.hv-booking-error::before { content:'!'; display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; margin-right:8px; border-radius:50%; color:white; background:#DC2626; font-weight:700; }
.hv-booking-note { margin-top:20px; }
.hv-booking-note label { font-weight:600; }
.hv-booking-note label span { color:var(--hv-text-secondary); font-size:12px; font-weight:400; }
.hv-booking-note textarea { width:100%; resize:vertical; min-height:84px; }
.hv-booking-change { display:inline-flex; align-items:center; min-height:36px; color:#1D4ED8; font-size:13px; font-weight:600; text-decoration:underline; text-underline-offset:3px; }
.hv-booking-change:hover { color:#142B49; }
.hv-room-section { scroll-margin-top:96px; }
.hv-hotel-detail [data-availability="success"], .hv-detail-dialog [data-availability="success"] { color:#2563EB; font-weight:600; }
#room-type-display { display:flex; align-items:center; min-height:50px; padding:12px 14px; border:1px solid #CBD5E1; border-radius:12px; background:#F8FAFC; font-size:14px; line-height:1.6; overflow-wrap:anywhere; }
.hv-hotel-detail .hv-detail-wishlist { color:#DC2626; border-color:#FECACA; background:#FFF; }
.hv-hotel-detail .hv-detail-wishlist[aria-pressed="true"] { color:#DC2626; border-color:#FCA5A5; background:#FEF2F2; }
.hv-hotel-detail .hv-detail-wishlist:hover:not(:disabled) { color:#B91C1C; border-color:#FCA5A5; background:#FEF2F2; }
.hv-hotel-detail .hv-detail-wishlist[aria-pressed]:focus-visible { outline-color:#DC2626; }
.hv-detail-wishlist svg { width:18px; height:18px; flex-shrink:0; }
.hv-detail-wishlist[aria-pressed="true"] svg { fill:currentColor; }
.hv-hotel-detail [data-availability="danger"], .hv-detail-dialog [data-availability="danger"] { color:#B91C1C; background:#FEF2F2; border:1px solid #FCA5A5; border-left:4px solid #DC2626; border-radius:8px; padding:10px 12px; font-weight:600; }
.hv-hotel-detail [data-availability="loading"], .hv-detail-dialog [data-availability="loading"] { color:#1D4ED8; font-weight:500; }
.hv-hotel-detail .hv-button:focus-visible { outline:3px solid #93C5FD; outline-offset:3px; }
.hv-booking-estimate { margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--hv-border); }
.hv-booking-estimate dl { display: grid; gap: 12px; font-size: 14px; }
.hv-booking-estimate dl > div { display: flex; justify-content: space-between; align-items: baseline; gap: 16px; }
.hv-booking-estimate dt { color: var(--hv-text-secondary); }
.hv-booking-estimate dd { text-align: right; }
.hv-booking-total { padding-top: 12px; border-top: 1px solid var(--hv-border); }
.hv-booking-panel > .hv-button { width: 100%; margin-top: 24px; }
.hv-booking-panel > .hv-detail-wishlist { margin-top: 16px; }
.hv-detail-empty { padding: 24px; color: var(--hv-text-secondary); }
.hv-stay-details { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-top: 18px; }
.hv-stay-details div { display: grid; gap: 8px; min-width: 0; padding: 14px; border: 1px solid var(--hv-border); border-radius: 12px; background: var(--hv-bg-secondary); }
.hv-stay-details dt { color: var(--hv-text-secondary); font-size: 13px; }
.hv-stay-details dd { font-size: 22px; font-variant-numeric: tabular-nums; font-weight: 700; line-height: 1.2; }
.hv-detail-reviews { display: grid; gap: 16px; margin-top: 16px; }
.hv-detail-review { padding-top: 16px; border-top: 1px solid var(--hv-border); }
.hv-detail-review h3 { font-size: 16px; }
.hv-detail-review time { font-size: 12px; color: var(--hv-text-secondary); }
.hv-detail-review > p:last-child { margin-top: 12px; font-size: 14px; }
.hv-detail-dialog { position: fixed; inset: 0; width: min(1000px, calc(100vw - 32px)); max-width: none; max-height: calc(100dvh - 48px); margin: auto; padding: 0; overflow: auto; overscroll-behavior: contain; border: 1px solid var(--hv-border); border-radius: 16px; background: var(--hv-bg); color: var(--hv-text); font-family: var(--hv-font); }
.hv-detail-dialog::backdrop { background: rgb(17 24 39 / 45%); }
.hv-detail-dialog-heading { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 20px 24px; border-bottom: 1px solid var(--hv-border); }
.hv-detail-dialog-heading h2 { font-size: 24px; line-height: 1.3; }
.hv-detail-dialog-close { flex-shrink: 0; width: 40px; height: 40px; border-radius: 50%; font-size: 24px; line-height: 1; }
.hv-detail-dialog-close:hover { background: var(--hv-bg-secondary); }
.hv-room-modal-body { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr); align-items: start; gap: 32px; padding: 24px; }
.hv-room-modal-info { min-width: 0; font-size: 16px; line-height: 1.6; }
.hv-room-modal-facts { display: grid; gap: 12px; margin-top: 24px; font-size: 14px; }
.hv-room-modal-facts div { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px; }
.hv-room-modal-facts dt { color: var(--hv-text-secondary); }
.hv-room-modal-note { margin-top: 24px; font-size: 14px; }
.hv-room-modal-note h3 { margin-bottom: 4px; font-size: 16px; }
.hv-room-modal-note p { color: var(--hv-text-secondary); }
.hv-room-modal-footer { position: sticky; bottom: 0; display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 20px 24px; border-top: 1px solid var(--hv-border); background: var(--hv-bg); }
.hv-modal-photo-stage { position: relative; overflow: hidden; border-radius: 12px; background: var(--hv-bg-secondary); }
.hv-modal-photo-stage > img { display: block; width: 100%; aspect-ratio: 4 / 3; object-fit: cover; }
.hv-photo-arrow { position: absolute; top: 50%; transform: translateY(-50%); display: flex; justify-content: center; align-items: center; width: 40px; height: 40px; border: 1px solid var(--hv-border); border-radius: 50%; background: var(--hv-bg); font-size: 26px; }
.hv-photo-arrow--prev { left: 12px; }.hv-photo-arrow--next { right: 12px; }
.hv-photo-counter { margin-top: 12px; text-align: center; font-size: 14px; color: var(--hv-text-secondary); }
.hv-photo-thumbnails { display: flex; gap: 8px; overflow-x: auto; margin-top: 12px; padding: 4px; }
.hv-photo-thumbnails button { flex-shrink: 0; width: 72px; height: 56px; overflow: hidden; border: 2px solid transparent; border-radius: 8px; }
.hv-photo-thumbnails button[aria-pressed="true"] { border-color: var(--hv-accent); }
.hv-photo-thumbnails img { width: 100%; height: 100%; object-fit: cover; }
.hv-hotel-photo-dialog > .hv-modal-photo-stage { margin: 24px 24px 0; }
.hv-hotel-photo-dialog > .hv-modal-photo-stage > img { max-height: calc(100dvh - 240px); object-fit: contain; }
.hv-hotel-photo-dialog > .hv-photo-thumbnails { margin: 12px 24px 24px; }
.hv-detail-dialog [hidden] { display: none; }
.hv-detail-dialog button:focus-visible { outline: 2px solid var(--hv-accent); outline-offset: 2px; }
/* Preserve the existing checkout styles; its booking integration is not enabled here. */
.hv-hotel-detail .font-display { font-family: var(--hv-font); }
.hv-hotel-detail .btn-gold { background: linear-gradient(135deg, #F59E0B, #FBBF24); color: white; }
.hv-hotel-detail #payment-overlay { position: absolute; inset: 0; background: rgba(255,255,255,.97); border-radius: 1rem; z-index: 10; }
@media (max-width: 1023px) {
 .hv-detail-container { padding-inline: 24px; }
 .hv-detail-layout { grid-template-columns: minmax(0, 1fr); }
 .hv-detail-sidebar { position: static; max-height: none; overflow: visible; }
 .hv-detail-gallery { height: 320px; }
}
@media (max-width: 639px) {
 .hv-detail-container { padding-inline: 16px; }
 .hv-detail-heading { padding-top: 24px; }.hv-detail-heading h1 { font-size: 26px; }
 .hv-detail-meta { gap: 8px 16px; }
 .hv-stay-details { grid-template-columns: minmax(0, 1fr); }
 .hv-detail-gallery { height: 240px; gap: 4px; border-radius: 12px; }
 .hv-room-card { grid-template-columns: minmax(0, 1fr); }
 .hv-room-cover img { height: auto; min-height: 0; aspect-ratio: 16 / 10; }
 .hv-room-card-content, .hv-booking-panel { padding: 20px; }
 .hv-room-modal-body { grid-template-columns: minmax(0, 1fr); gap: 24px; padding: 16px; }
 .hv-detail-dialog { max-height: calc(100dvh - 24px); }
 .hv-detail-dialog-heading, .hv-room-modal-footer { padding: 16px; }
 .hv-detail-dialog-heading h2 { font-size: 20px; }
 .hv-hotel-photo-dialog > .hv-modal-photo-stage { margin: 16px 16px 0; }
 .hv-hotel-photo-dialog > .hv-photo-thumbnails { margin-inline: 16px; }
}
</style>
