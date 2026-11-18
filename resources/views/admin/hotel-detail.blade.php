@extends('admin.layouts.admin')

@section('title', 'Chi tiết khách sạn')

@push('styles')
<style>
    .admin-hotel-detail { --hd-border:#e4eaf1; --hd-muted:#667085; --hd-blue:#2563eb; color:#1f2937; }
    .hd-back { display:flex;align-items:center;gap:8px;margin-bottom:14px;color:#667085;font-size:13px; }
    .hd-back a { color:#475467;text-decoration:none; }
    .hd-back a:hover { color:var(--hd-blue); }
    .hd-back-current { min-width:0;color:#1f2937;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
    .hd-header { display:flex;align-items:center;gap:14px;padding:14px 16px;background:#fff;border:1px solid var(--hd-border);border-radius:14px; }
    .hd-cover { width:76px;height:60px;flex:0 0 76px;object-fit:cover;border:1px solid var(--hd-border);border-radius:10px;background:#f2f5f9; }
    .hd-header-main { min-width:0;flex:1; }
    .hd-title { margin:0;font-size:19px;line-height:1.35;font-weight:700;overflow-wrap:anywhere; }
    .hd-meta { display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin-top:5px;color:var(--hd-muted);font-size:12px; }
    .hd-status { display:inline-flex;align-items:center;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700; }
    .hd-status-active { background:#dcfce7;color:#15803d; }
    .hd-status-inactive { background:#fef9c3;color:#a16207; }
    .hd-status-closed { background:#fee2e2;color:#b91c1c; }
    .hd-menu { position:relative;align-self:flex-start; }
    .hd-menu summary { display:grid;place-items:center;width:36px;height:36px;border:1px solid var(--hd-border);border-radius:9px;background:#fff;cursor:pointer;font-size:21px;line-height:1;list-style:none; }
    .hd-menu summary::-webkit-details-marker { display:none; }
    .hd-menu-list { position:absolute;right:0;top:calc(100% + 6px);z-index:5;min-width:185px;padding:5px;background:#fff;border:1px solid var(--hd-border);border-radius:10px;box-shadow:0 10px 28px #1018281a; }
    .hd-menu-list a,.hd-menu-list button { display:block;width:100%;padding:9px 10px;border:0;border-radius:7px;background:transparent;color:#344054;text-align:left;text-decoration:none;font:inherit;font-size:12px;cursor:pointer; }
    .hd-menu-list a:hover,.hd-menu-list button:hover { background:#f2f6fc; }
    .hd-tabs { display:flex;gap:4px;margin:16px 0 12px;padding:4px;border:1px solid var(--hd-border);border-radius:11px;background:#fff; }
    .hd-tab { padding:8px 14px;border:0;border-radius:8px;background:transparent;color:#667085;font:inherit;font-size:13px;font-weight:600;cursor:pointer; }
    .hd-tab[aria-selected="true"] { background:#eff6ff;color:#1d4ed8; }
    .hd-panel[hidden] { display:none!important; }
    .hd-overview-grid { display:grid;grid-template-columns:minmax(0,1.25fr) minmax(280px,.85fr);align-items:start;gap:14px; }
    .hd-column { display:grid;gap:14px;min-width:0; }
    .hd-card { min-width:0;padding:15px 16px;background:#fff;border:1px solid var(--hd-border);border-radius:12px; }
    .hd-card-head { display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px; }
    .hd-card-title { margin:0;font-size:14px;font-weight:700; }
    .hd-edit,.hd-save,.hd-cancel,.hd-retry { min-height:32px;padding:6px 10px;border:1px solid var(--hd-border);border-radius:8px;background:#fff;color:#344054;font:inherit;font-size:12px;font-weight:600;cursor:pointer; }
    .hd-edit:hover,.hd-cancel:hover,.hd-retry:hover { background:#f8fafc; }
    .hd-edit { border-color:#93c5fd;background:#eff6ff;color:#1d4ed8;box-shadow:0 1px 3px #2563eb1f; }
    .hd-edit:hover { border-color:#60a5fa;background:#dbeafe;color:#1e40af; }
    .hd-edit:active { background:#bfdbfe; }
    .hd-edit:focus-visible { outline:2px solid #60a5fa;outline-offset:2px; }
    .hd-save { border-color:var(--hd-blue);background:var(--hd-blue);color:white; }
    .hd-save:hover { background:#1d4ed8; }
    .hd-save:disabled,.hd-edit:disabled,.hd-cancel:disabled { opacity:.55;cursor:wait; }
    .hd-data { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 18px; }
    .hd-field { min-width:0; }
    .hd-field-wide { grid-column:1/-1; }
    .hd-label { display:block;margin-bottom:4px;color:var(--hd-muted);font-size:11px;font-weight:600; }
    .hd-value { min-height:20px;font-size:13px;line-height:1.55;overflow-wrap:anywhere;white-space:pre-wrap; }
    .hd-description { max-height:5.8em;overflow:hidden; }
    .hd-description.is-expanded { max-height:none; }
    .hd-more { margin-top:5px;padding:0;border:0;background:transparent;color:var(--hd-blue);font:inherit;font-size:12px;cursor:pointer; }
    .hd-form-grid { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px 12px; }
    .hd-form-field { min-width:0; }
    .hd-form-field.wide { grid-column:1/-1; }
    .hd-form-field label { display:block;margin-bottom:5px;color:#344054;font-size:12px;font-weight:600; }
    .hd-form-field input,.hd-form-field select,.hd-form-field textarea { width:100%;min-height:37px;padding:8px 10px;border:1px solid #cfd8e3;border-radius:8px;background:#fff;color:#1f2937;font:inherit;font-size:13px; }
    .hd-form-field textarea { min-height:94px;resize:vertical; }
    .hd-form-field input:focus,.hd-form-field select:focus,.hd-form-field textarea:focus { outline:2px solid #bfdbfe;border-color:var(--hd-blue); }
    .hd-form-actions { display:flex;justify-content:flex-end;gap:7px;margin-top:12px; }
    .hd-form-error,.hd-feedback { margin:8px 0 0;color:#b42318;font-size:12px;line-height:1.5;overflow-wrap:anywhere; }
    .hd-feedback { color:#667085; }
    .hd-feedback[data-error="true"] { color:#b42318; }
    .hd-amenities { display:flex;flex-wrap:wrap;gap:6px; }
    .hd-amenity { padding:4px 8px;border:1px solid #e4eaf1;border-radius:999px;color:#475467;font-size:11px; }
    .hd-image-save { display:flex;justify-content:flex-end;margin-top:12px; }
    .hd-room-toolbar { display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:10px; }
    .hd-room-toolbar p { margin:0;color:#667085;font-size:12px; }
    .hd-add { min-height:36px;padding:8px 12px;border:1px solid #2563eb;border-radius:8px;background:#2563eb;color:#fff;font:inherit;font-size:12px;font-weight:700;cursor:pointer; }
    .hd-add:hover { background:#1d4ed8; }
    .hd-room-state { padding:16px;border:1px solid var(--hd-border);border-radius:11px;background:#fff;color:#475467;font-size:13px;line-height:1.6; }
    .hd-room-state[data-error="true"] { border-color:#fda29b;background:#fff8f7;color:#912018; }
    .hd-room-table-wrap { overflow:visible;background:#fff;border:1px solid var(--hd-border);border-radius:12px; }
    .hd-room-table { width:100%;border-collapse:collapse; }
    .hd-room-table th,.hd-room-table td { padding:10px 11px;border-bottom:1px solid #edf0f4;text-align:left;vertical-align:middle;font-size:12px; }
    .hd-room-table th { color:#667085;background:#f8fafc;font-size:10px;text-transform:uppercase;letter-spacing:.03em; }
    .hd-room-table tbody tr:last-child td { border-bottom:0; }
    .hd-room-name { display:flex;align-items:center;gap:9px;min-width:150px; }
    .hd-room-thumb { width:46px;height:36px;flex:0 0 46px;object-fit:cover;border:1px solid var(--hd-border);border-radius:6px;background:#f2f5f9; }
    .hd-room-edit { padding:5px 9px;border:1px solid #bfdbfe;border-radius:7px;background:#eff6ff;color:#1d4ed8;font:inherit;font-size:11px;font-weight:700;cursor:pointer; }
    .hd-room-editor { margin-top:14px;padding:15px;background:#fff;border:1px solid var(--hd-border);border-radius:12px; }
    .hd-room-editor h3 { margin:0 0 13px;font-size:14px; }
    .hd-price-note { margin:5px 0 0;color:#667085;font-size:11px;line-height:1.45; }
    .hd-loading { padding:20px;border:1px solid var(--hd-border);border-radius:10px;background:#fff;color:#667085;font-size:13px; }
    .hd-loading[hidden] { display:none!important; }
    @media(max-width:850px) { .hd-overview-grid { grid-template-columns:1fr; } }
    @media(max-width:640px) {
        .hd-header { align-items:flex-start;gap:10px;padding:12px; }
        .hd-cover { width:58px;height:50px;flex-basis:58px; }
        .hd-title { font-size:16px; }
        .hd-tabs { display:grid;grid-template-columns:repeat(3,minmax(0,1fr)); }
        .hd-tab { padding:8px 5px;font-size:12px; }
        .hd-data,.hd-form-grid { grid-template-columns:1fr; }
        .hd-field-wide,.hd-form-field.wide { grid-column:auto; }
        .hd-room-toolbar { align-items:flex-start;flex-direction:column; }
        .hd-room-table-wrap { border:0;background:transparent; }
        .hd-room-table,.hd-room-table tbody { display:block;width:100%; }
        .hd-room-table thead { display:none; }
        .hd-room-table tbody tr { display:block;margin-bottom:10px;border:1px solid var(--hd-border);border-radius:10px;background:#fff; }
        .hd-room-table td { display:flex;justify-content:space-between;align-items:center;gap:10px;padding:9px 10px;border-bottom:1px solid #edf0f4;text-align:right; }
        .hd-room-table td::before { content:attr(data-label);flex:0 0 38%;color:#667085;font-size:10px;text-align:left; }
        .hd-room-table td:first-child { display:block;text-align:left; }
        .hd-room-table td:first-child::before { display:block;margin-bottom:6px; }
        .hd-room-name { justify-content:flex-end; }
        .hd-form-actions { flex-wrap:wrap; }
    }
</style>
@endpush

@section('content')
<div class="admin-hotel-detail" id="hotelDetailPage" data-hotel-id="{{ $hotelId }}">
    <nav class="hd-back" aria-label="Breadcrumb">
        <a href="/admin/hotels" id="hotelListBack">← Danh sách khách sạn</a>
        <span aria-hidden="true">/</span>
        <span class="hd-back-current" id="hotelBreadcrumbName">Chi tiết khách sạn</span>
    </nav>

    <div class="hd-loading" id="hotelLoading">Đang tải thông tin khách sạn…</div>
    <div class="hd-room-state" id="hotelLoadError" hidden role="alert">
        <span id="hotelLoadErrorText"></span>
        <button type="button" class="hd-retry" id="hotelRetry">Thử lại</button>
    </div>

    <section class="hd-header" id="hotelDetailHeader" hidden>
        <img class="hd-cover" id="hotelCover" alt="">
        <div class="hd-header-main">
            <h1 class="hd-title" id="hotelName"></h1>
            <div class="hd-meta"><span id="hotelLocation"></span><span class="hd-status" id="hotelStatus"></span></div>
        </div>
        <details class="hd-menu">
            <summary aria-label="Hành động khách sạn">⋯</summary>
            <div class="hd-menu-list">
                <a id="hotelWebsiteLink" target="_blank" rel="noopener">Xem trên website</a>
                <button type="button" id="hotelStatusAction"></button>
            </div>
        </details>
    </section>

    <div id="hotelDetailContent" hidden>
        <div class="hd-tabs" role="tablist" aria-label="Thông tin khách sạn">
            <button type="button" class="hd-tab" id="tabOverview" role="tab" aria-selected="true" aria-controls="panelOverview">Tổng quan</button>
            <button type="button" class="hd-tab" id="tabImages" role="tab" aria-selected="false" aria-controls="panelImages" tabindex="-1">Hình ảnh</button>
            <button type="button" class="hd-tab" id="tabRoomTypes" role="tab" aria-selected="false" aria-controls="panelRoomTypes" tabindex="-1">Loại phòng</button>
        </div>

        <section class="hd-panel" id="panelOverview" role="tabpanel" aria-labelledby="tabOverview">
            <div class="hd-overview-grid">
                <div class="hd-column">
                    <section class="hd-card" id="basicCard"></section>
                    <section class="hd-card" id="amenitiesCard"></section>
                </div>
                <div class="hd-column">
                    <section class="hd-card" id="contactCard"></section>
                </div>
            </div>
        </section>

        <section class="hd-panel" id="panelImages" role="tabpanel" aria-labelledby="tabImages" hidden>
            <section class="hd-card">
                <h2 class="hd-card-title">Hình ảnh khách sạn</h2>
                @include('admin.partials.image-editor', ['editorId' => 'hotel-detail-images', 'editorTitle' => 'Ảnh đại diện và thư viện ảnh', 'galleryTitle' => 'Thư viện ảnh khách sạn'])
                <div class="hd-image-save"><button type="button" class="hd-save" id="saveHotelImages">Lưu ảnh</button></div>
            </section>
        </section>

        <section class="hd-panel" id="panelRoomTypes" role="tabpanel" aria-labelledby="tabRoomTypes" hidden>
            <div class="hd-room-toolbar">
                <p>Giá bên dưới là giá cơ bản mỗi đêm; giá cuối cùng có thể thay đổi theo ngày và phụ thu.</p>
                <button type="button" class="hd-add" id="addRoomType" hidden>+ Thêm loại phòng</button>
            </div>
            <p class="hd-price-note" id="roomTypesSourceNote" hidden>Số lượng được tính từ các phòng hiện có của khách sạn. Hiện hỗ trợ xem và thêm loại phòng; chỉnh sửa và ảnh riêng theo loại phòng chưa khả dụng.</p>
            <div class="hd-room-state" id="roomTypesLoading" hidden>Đang tải loại phòng…</div>
            <div class="hd-room-state" id="roomTypesError" data-error="true" hidden role="alert">
                <span id="roomTypesErrorText"></span>
                <button type="button" class="hd-retry" id="retryRoomTypes">Thử lại</button>
            </div>
            <div class="hd-room-state" id="roomTypesEmpty" hidden>Khách sạn này chưa có loại phòng.</div>
            <div class="hd-room-table-wrap" id="roomTypesTableWrap" hidden>
                <table class="hd-room-table">
                    <thead><tr><th>Loại phòng</th><th>Giá cơ bản/đêm</th><th>Sức chứa</th><th>Tổng số phòng</th><th>Trạng thái</th><th>Hành động</th></tr></thead>
                    <tbody id="roomTypesBody"></tbody>
                </table>
            </div>
            <section class="hd-room-editor" id="roomTypeEditor" hidden>
                <h3 id="roomEditorTitle"></h3>
                <div class="hd-form-grid">
                    <div class="hd-form-field"><label for="roomName">Tên loại phòng</label><input id="roomName" maxlength="100"></div>
                    <div class="hd-form-field"><label for="roomPrice">Giá cơ bản mỗi đêm (VNĐ)</label><input id="roomPrice" inputmode="decimal"><p class="hd-price-note">Chưa bao gồm giá theo ngày hoặc phụ thu nếu có.</p></div>
                    <div class="hd-form-field"><label for="roomCapacity">Sức chứa</label><input id="roomCapacity" type="number" min="1" step="1"></div>
                    <div class="hd-form-field"><label for="roomBedType">Loại giường</label><input id="roomBedType" maxlength="50"></div>
                    <div class="hd-form-field"><label for="roomTotal" id="roomTotalLabel">Tổng số phòng quản lý</label><input id="roomTotal" type="number" min="0" step="1"><p class="hd-price-note" id="roomTotalNote">Đây là tổng tồn quản lý, không phải số phòng còn trống theo ngày.</p></div>
                    <div class="hd-form-field" id="roomFloorField" hidden><label for="roomFloor">Tầng bắt đầu</label><input id="roomFloor" type="number" min="1" max="99" value="1" step="1"></div>
                    <div class="hd-form-field" id="roomStatusField"><label for="roomStatus">Trạng thái</label><select id="roomStatus"><option value="active">Đang bán</option><option value="inactive">Ngừng bán</option></select></div>
                    <div class="hd-form-field wide"><label for="roomDescription">Mô tả</label><textarea id="roomDescription"></textarea></div>
                    <div class="hd-form-field wide" id="roomAmenitiesField" hidden><label for="roomAmenities">Tiện nghi riêng (mỗi dòng một tiện nghi)</label><textarea id="roomAmenities" rows="3"></textarea></div>
                </div>
                <div id="roomImageField" hidden>
                    @include('admin.partials.image-editor', ['editorId' => 'hotel-room-images', 'editorTitle' => 'Ảnh loại phòng', 'galleryTitle' => 'Ảnh đã lưu và ảnh mới'])
                </div>
                @include('admin.partials.room-rate-editor')
                <div class="hd-form-error" id="roomEditorError" hidden role="alert"></div>
                <div class="hd-form-actions">
                    <button type="button" class="hd-cancel" id="cancelRoomType">Hủy</button>
                    <button type="button" class="hd-save" id="saveRoomType">Lưu loại phòng</button>
                </div>
            </section>
        </section>
    </div>
</div>
<div class="admin-toast" id="hotelDetailToast" role="status" aria-live="polite"><span id="hotelDetailToastText"></span></div>
@endsection

@include('admin.partials.image-editor-assets')
@include('admin.partials.amenity-editor-assets')

@push('scripts')
<script>
(() => {
    const root = document.getElementById('hotelDetailPage');
    const hotelId = Number(root.dataset.hotelId);
    const placeholder = @js(\App\Support\AccommodationImage::placeholder());
    const amenityOptions = @js(\App\Support\HotelAmenityCatalog::options());
    const amenityLabels = Object.fromEntries(amenityOptions.map(item => [item.key, item.label]));
    const hotelImages = new AdminImageEditor('hotel-detail-images', () => {
        const saveButton = byId('saveHotelImages');
        if (saveButton) saveButton.disabled = !hotelImages.ready || hotelImages.saving || hotelSaving;
    });
    let roomInventoryModel = null;
    let hotel = null;
    let activeTab = 'overview';
    let roomTypes = [];
    let roomTypesState = 'idle';
    let roomTypesController = null;
    let roomEditorTypeId = null;
    let roomEditorVersion = 0;
    let hotelSaving = false;
    let roomSaving = false;
    let amenityEditor = null;
    const roomImages = new AdminImageEditor('hotel-room-images', () => {
        document.getElementById('saveRoomType').disabled = roomSaving || (roomInventoryModel !== 'legacy' && !roomImages.ready);
    });
    let descriptionExpanded = false;

    const byId = id => document.getElementById(id);
    const roomValidation=AdminRoomTypeValidation.editor(Object.fromEntries(Object.entries({name:'roomName',base_price:'roomPrice',capacity:'roomCapacity',bed_type:'roomBedType',total_rooms:'roomTotal',floor_start:'roomFloor',status:'roomStatus',description:'roomDescription',amenities:'roomAmenities'}).map(([key,id])=>[key,byId(id)])),byId('roomEditorError'),()=>({model:roomInventoryModel,creating:roomEditorTypeId===null}));
    window.AdminRoomRateHost={isSaving:()=>roomSaving||hotelSaving,setSaving:value=>{roomSaving=value;},update:()=>{
        byId('saveRoomType').disabled=roomSaving||(roomInventoryModel!=='legacy'&&!roomImages.ready);
        byId('cancelRoomType').disabled=roomSaving;
    }};
    const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    const valueOrMissing = value => value === null || value === undefined || String(value).trim() === '' ? 'Chưa cập nhật' : esc(value);
    const apiError = async response => {
        let data = {};
        try { data = await response.json(); } catch (_) {}
        const validation = Object.values(data.errors || {}).flat().filter(Boolean).join(' ');
        return validation || data.message || `Yêu cầu thất bại (${response.status}).`;
    };
    function toast(message, tone) {
        if (typeof adminToast === 'function') { adminToast(message, tone || 'info'); return; }
        byId('hotelDetailToastText').textContent = message;
        const node = byId('hotelDetailToast');
        node.classList.add('show');
        setTimeout(() => node.classList.remove('show'), 3000);
    }
    function clearRoomImageSelection() { roomImages.reset(); }
    function safeReturnUrl() {
        const candidate = new URLSearchParams(location.search).get('return');
        if (!candidate) return '/admin/hotels';
        try {
            const parsed = new URL(candidate, location.origin);
            return parsed.origin === location.origin && parsed.pathname === '/admin/hotels'
                ? parsed.pathname + parsed.search
                : '/admin/hotels';
        } catch (_) {
            return '/admin/hotels';
        }
    }
    byId('hotelListBack').href = safeReturnUrl();

    function statusText(status) {
        return ({active:'Đang hoạt động',inactive:'Tạm ngừng',closed:'Đóng cửa'})[status] || 'Chưa cập nhật';
    }
    function renderHeader() {
        byId('hotelCover').src = hotel.cover_image_url || placeholder;
        byId('hotelCover').alt = hotel.name ? 'Ảnh đại diện ' + hotel.name : 'Ảnh đại diện khách sạn';
        byId('hotelCover').onerror = () => { byId('hotelCover').src = placeholder; };
        byId('hotelName').textContent = hotel.name || 'Khách sạn';
        byId('hotelBreadcrumbName').textContent = hotel.name || 'Chi tiết khách sạn';
        byId('hotelLocation').textContent = hotel.location?.name || 'Chưa cập nhật địa điểm';
        const status = byId('hotelStatus');
        status.textContent = statusText(hotel.status);
        status.className = 'hd-status hd-status-' + (['active','inactive','closed'].includes(hotel.status) ? hotel.status : 'inactive');
        byId('hotelWebsiteLink').href = '/hotels/' + encodeURIComponent(hotel.id);
        byId('hotelStatusAction').textContent = hotel.status === 'active' ? 'Tạm ngừng bán' : 'Mở bán';
        byId('hotelStatusAction').dataset.adminAction = hotel.status === 'active' ? 'pause' : 'activate';
        byId('hotelStatusAction').dataset.adminTone = hotel.status === 'active' ? 'neutral' : 'success';
    }
    function amenityValues() {
        return (hotel.amenities || []).map(item => typeof item === 'string' ? item : item.amenity ?? item.key).filter(Boolean);
    }
    function renderReadField(label, value, wide = false, extra = '') {
        return `<div class="hd-field ${wide?'hd-field-wide':''}"><span class="hd-label">${esc(label)}</span><div class="hd-value ${extra}">${valueOrMissing(value)}</div></div>`;
    }
    function renderBasicCard() {
        const description = hotel.description || '';
        byId('basicCard').innerHTML = `
            <div class="hd-card-head"><h2 class="hd-card-title">Thông tin cơ bản</h2><button type="button" class="hd-edit" data-edit-group="basic">Chỉnh sửa</button></div>
            <div class="hd-data">
                ${renderReadField('Tên khách sạn',hotel.name)}
                ${renderReadField('Tỉnh / thành phố',hotel.location?.name)}
                ${renderReadField('Địa chỉ',hotel.address,true)}
                ${renderReadField('Số sao',hotel.star_rating ? `${hotel.star_rating} sao` : null)}
                ${renderReadField('Mô tả',description || null,true,'hd-description' + (descriptionExpanded?' is-expanded':''))}
            </div>
            ${description.length > 260 ? `<button type="button" class="hd-more" id="descriptionToggle">${descriptionExpanded?'Thu gọn':'Xem thêm'}</button>` : ''}
        `;
    }
    function renderAmenitiesCard() {
        const values = amenityValues();
        const chips = values.length
            ? values.map(key => `<span class="hd-amenity">${esc(amenityLabels[key] || key.replace(/_/g,' '))}</span>`).join('')
            : '<span class="hd-value">Chưa cập nhật</span>';
        byId('amenitiesCard').innerHTML = `<div class="hd-card-head"><h2 class="hd-card-title">Tiện nghi khách sạn</h2><button type="button" class="hd-edit" data-edit-group="amenities">Chỉnh sửa</button></div><div class="hd-amenities">${chips}</div>`;
    }
    function renderContactCard() {
        byId('contactCard').innerHTML = `
            <div class="hd-card-head"><h2 class="hd-card-title">Liên hệ</h2><button type="button" class="hd-edit" data-edit-group="contact">Chỉnh sửa</button></div>
            <div class="hd-data">${renderReadField('Số điện thoại',hotel.phone)}${renderReadField('Email',hotel.email)}</div>`;
    }
    function renderOverview() {
        renderBasicCard();
        renderAmenitiesCard();
        renderContactCard();
    }
    function inputField(id, label, value, type = 'text', wide = false, options = '') {
        const renderedValue = type === 'textarea'
            ? `<textarea id="${id}" ${options}>${esc(value ?? '')}</textarea>`
            : `<input id="${id}" type="${type}" value="${esc(value ?? '')}" ${options}>`;
        return `<div class="hd-form-field ${wide?'wide':''}"><label for="${id}">${esc(label)}</label>${renderedValue}</div>`;
    }
    function beginEdit(group) {
        if (hotelSaving) return;
        const target = ({basic:'basicCard',amenities:'amenitiesCard',contact:'contactCard'})[group];
        const values = {
            basic: `
                ${inputField('editName','Tên khách sạn',hotel.name,'text',true,'maxlength="200" required')}
                <div class="hd-form-field"><label for="editLocation">Tỉnh / thành phố</label><select id="editLocation" required><option value="">Đang tải địa điểm…</option></select></div>
                <div class="hd-form-field"><label for="editStars">Số sao</label><select id="editStars" required><option value="" ${Number(hotel.star_rating)>=1&&Number(hotel.star_rating)<=5?'':'selected'}>Chưa cập nhật</option>${[1,2,3,4,5].map(star=>`<option value="${star}" ${Number(hotel.star_rating)===star?'selected':''}>${star} sao</option>`).join('')}</select></div>
                ${inputField('editAddress','Địa chỉ',hotel.address,'text',true,'maxlength="300" required')}
                ${inputField('editDescription','Mô tả',hotel.description,'textarea',true,'')}`,
            contact: `
                ${inputField('editPhone','Số điện thoại',hotel.phone,'tel',false,'maxlength="20"')}
                ${inputField('editEmail','Email',hotel.email,'email',false,'maxlength="100"')}`,
            amenities: `<div class="hd-form-field wide"><span class="hd-label">Tìm, chọn hoặc thêm tiện nghi</span><div id="editAmenities"></div></div>`
        }[group];
        const card = byId(target);
        card.innerHTML = `
            <div class="hd-card-head"><h2 class="hd-card-title">${({basic:'Thông tin cơ bản',amenities:'Tiện nghi khách sạn',contact:'Liên hệ'})[group]}</h2></div>
            <div class="hd-form-grid">${values}</div>
            <div class="hd-form-error" id="groupEditError" hidden role="alert"></div>
            <div class="hd-form-actions"><button type="button" class="hd-cancel" data-cancel-group="${group}">Hủy</button><button type="button" class="hd-save" data-save-group="${group}">Lưu</button></div>`;
        if (group === 'basic') loadHotelLocations();
        if (group === 'amenities') {
            amenityEditor = new AdminAmenityEditor('editAmenities', amenityValues());
        }
    }
    async function loadHotelLocations() {
        const select = byId('editLocation');
        try {
            const response = await fetch('/api/locations', {headers:{Accept:'application/json'}});
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Không tải được danh sách địa điểm.');
            const locations = Array.isArray(data) ? data : (data.data || []);
            select.innerHTML = '<option value="">— Chọn địa điểm —</option>' + locations.map(location=>`<option value="${esc(location.id)}">${esc(location.name)}</option>`).join('');
            select.value = hotel.location_id ?? '';
        } catch (error) {
            select.innerHTML = '<option value="">Không tải được địa điểm</option>';
            showGroupError(error.message);
        }
    }
    function showGroupError(message) {
        const error = byId('groupEditError');
        if (!error) return;
        error.textContent = message;
        error.hidden = false;
    }
    async function saveGroup(group, button) {
        if (hotelSaving) return;
        if (group === 'amenities') {
            const editorToSave = amenityEditor;
            const ready = await editorToSave.readyForSave();
            if (!ready || amenityEditor !== editorToSave || !editorToSave.root.isConnected || !button.isConnected) return;
        }
        if (hotelSaving) return;
        const payload = {};
        if (group === 'basic') {
            payload.name = byId('editName').value.trim();
            payload.location_id = byId('editLocation').value;
            payload.star_rating = byId('editStars').value;
            payload.address = byId('editAddress').value.trim();
            payload.description = byId('editDescription').value;
        } else if (group === 'amenities') {
            payload.amenities = amenityEditor.values();
        } else if (group === 'contact') {
            payload.phone = byId('editPhone').value.trim() || null;
            payload.email = byId('editEmail').value.trim() || null;
        }
        hotelSaving = true;
        const card = button.closest('.hd-card');
        card.querySelectorAll('input,select,textarea,button').forEach(control=>control.disabled=true);
        button.disabled = true;
        adminBusy(button,true);
        button.textContent = 'Đang lưu…';
        try {
            const response = await adminApi(`/hotels/${hotelId}`, {method:'PUT',body:JSON.stringify(payload)});
            if (!response.ok) throw new Error(await apiError(response));
            await loadHotel();
            toast('Đã lưu thay đổi.');
        } catch (error) {
            showGroupError(error.message || 'Không thể lưu thay đổi.');
            button.disabled = false;
            button.textContent = 'Lưu';
        } finally {
            hotelSaving = false; adminBusy(button,false);
            if (card.isConnected) card.querySelectorAll('input,select,textarea,button').forEach(control=>control.disabled=false);
        }
    }
    async function loadHotel() {
        byId('hotelLoading').hidden = false;
        byId('hotelLoadError').hidden = true;
        byId('hotelDetailHeader').hidden = true;
        byId('hotelDetailContent').hidden = true;
        try {
            const response = await adminApi(`/hotels/${hotelId}`);
            if (!response.ok) throw new Error(await apiError(response));
            const data = await response.json();
            if (!data.hotel || Number(data.hotel.id) !== hotelId) throw new Error('Phản hồi khách sạn không hợp lệ.');
            hotel = data.hotel;
            byId('hotelLoading').hidden = true;
            byId('hotelDetailHeader').hidden = false;
            byId('hotelDetailContent').hidden = false;
            renderHeader();
            renderOverview();
        } catch (error) {
            byId('hotelLoading').hidden = true;
            byId('hotelLoadErrorText').textContent = error.message || 'Không tải được thông tin khách sạn.';
            byId('hotelLoadError').hidden = false;
        }
    }
    async function changeHotelStatus() {
        const button = byId('hotelStatusAction');
        const nextStatus = hotel.status === 'active' ? 'inactive' : 'active';
        button.disabled = true;
        button.textContent = 'Đang cập nhật…';
        try {
            const response = await adminApi(`/hotels/${hotelId}/status`,{method:'PUT',body:JSON.stringify({status:nextStatus})});
            if (!response.ok) throw new Error(await apiError(response));
            hotel.status = nextStatus;
            renderHeader();
            toast(nextStatus === 'active' ? 'Đã mở bán khách sạn.' : 'Đã tạm ngừng bán; dữ liệu và đơn hiện có được giữ nguyên.');
        } catch (error) {
            toast(error.message || 'Không thể cập nhật trạng thái.','error');
            button.disabled = false;
            button.textContent = nextStatus === 'active' ? 'Mở bán' : 'Tạm ngừng bán';
        }
    }
    async function loadHotelImages() {
        hotelImages.open(`/hotels/${hotelId}/images`);
    }
    async function saveHotelImageChanges(button) {
        if (hotelSaving || !hotelImages.ready) return;
        let body;
        try {
            body = hotelImages.body({},'PUT');
        } catch (error) {
            hotelImages.status(error.message || 'Không thể chuẩn bị thay đổi ảnh.',true);
            return;
        }
        button.disabled = true;
        hotelSaving = true;
        hotelImages.setSaving(true); adminBusy(button,true);
        try {
            const response = await adminApi(`/hotels/${hotelId}`,{method:body instanceof FormData?'POST':'PUT',body});
            if (!response.ok) throw new Error(await apiError(response));
            await hotelImages.open(`/hotels/${hotelId}/images`);
            if (hotelImages.ready) {
                const cover = hotelImages.saved.find(image=>image.key===hotelImages.primary);
                hotel.cover_image_url = cover?.display_url || placeholder;
                renderHeader();
            }
            toast('Đã lưu thư viện ảnh khách sạn.');
        } catch (error) {
            hotelImages.status(error.message || 'Không lưu được ảnh. Vui lòng thử lại.',true);
        } finally {
            hotelSaving = false;
            hotelImages.setSaving(false); adminBusy(button,false);
        }
    }
    function setTab(tab) {
        activeTab = tab;
        const tabMap = {overview:'Overview',images:'Images',roomTypes:'RoomTypes'};
        Object.entries(tabMap).forEach(([key,suffix])=>{
            const selected = key === tab;
            const control = byId('tab'+suffix);
            control.setAttribute('aria-selected',String(selected));
            control.tabIndex = selected ? 0 : -1;
            byId('panel'+suffix).hidden = !selected;
        });
        if (tab === 'images' && hotelImages.endpoint !== `/hotels/${hotelId}/images`) loadHotelImages();
        if (tab === 'roomTypes' && roomTypesState === 'idle') loadRoomTypes();
    }
    async function loadRoomTypes() {
        roomTypesController?.abort();
        roomTypesController = new AbortController();
        roomTypesState = 'loading';
        renderRoomTypesState();
        try {
            const response = await adminApi(`/hotels/${hotelId}/room-types?inventory_model=auto`,{signal:roomTypesController.signal});
            const data = await response.json();
            if (!response.ok || !Array.isArray(data.room_types) || !['legacy','scoped','v2'].includes(data.inventory_model)) {
                const error = new Error(data.message || 'Không tải được loại phòng.');
                error.status = response.status;
                throw error;
            }
            if (data.room_types.some(type=>Number(type.hotel_id)!==hotelId)) throw new Error('Dữ liệu loại phòng không khớp khách sạn đang xem.');
            roomInventoryModel = data.inventory_model;
            roomTypes = data.room_types;
            roomTypesState = roomTypes.length ? 'ready' : 'empty';
            renderRoomTypesState();
        } catch (error) {
            if (error.name === 'AbortError') return;
            roomTypesState = 'error';
            byId('roomTypesErrorText').textContent = error.message || 'Không tải được loại phòng.';
            renderRoomTypesState();
        }
    }
    function renderRoomTypesState() {
        byId('roomTypesSourceNote').hidden = roomInventoryModel !== 'legacy' || !['ready','empty'].includes(roomTypesState);
        byId('roomTypesLoading').hidden = roomTypesState !== 'loading';
        byId('roomTypesError').hidden = roomTypesState !== 'error';
        byId('roomTypesEmpty').hidden = roomTypesState !== 'empty';
        byId('roomTypesTableWrap').hidden = roomTypesState !== 'ready';
        byId('addRoomType').hidden = !['ready','empty'].includes(roomTypesState);
        if (roomTypesState === 'ready') {
            byId('roomTypesBody').innerHTML = roomTypes.map(type=>`
                <tr>
                    <td data-label="Loại phòng"><div class="hd-room-name"><img class="hd-room-thumb" src="${esc(type.cover_image_url||placeholder)}" alt="" onerror="this.onerror=null;this.src='${esc(placeholder)}'"><span>${esc(type.name||'Chưa cập nhật')}</span></div></td>
                    <td data-label="Giá cơ bản/đêm">${type.base_price==null?'<span class="room-readiness-note">Giá phòng chưa nhập</span>':esc(new Intl.NumberFormat('vi-VN',{maximumFractionDigits:2}).format(Number(type.base_price)))+' ₫'}</td>
                    <td data-label="Sức chứa">${esc(type.capacity)}</td>
                    <td data-label="Tổng số phòng">${esc(type.total_rooms)}</td>
                    <td data-label="Trạng thái">${roomInventoryModel==='legacy'?'Theo từng phòng':type.status==='active'?(type.base_price==null||Number(type.total_rooms)===0?'Đã bật · Chưa sẵn sàng':'Đang bán'):'Ngừng bán'}${Number(type.total_rooms)===0?'<span class="room-readiness-note">Chưa có phòng quản lý</span>':''}${type.capacity==null?'<span class="room-readiness-note">Sức chứa chưa nhập</span>':''}</td>
                    <td data-label="Hành động">${roomInventoryModel==='legacy'?'—':`<button type="button" class="hd-room-edit" data-edit-room="${esc(type.id)}">Sửa</button>${roomInventoryModel==='scoped'?` <button type="button" class="hd-room-edit" data-delete-room="${esc(type.id)}">Xóa</button>`:''}`}</td>
                </tr>`).join('');
        }
    }
    async function deleteRoomType(type, button) {
        if (!type || roomSaving || hotelSaving || roomInventoryModel !== 'scoped') return;
        if (!await adminConfirm({title:'Xóa loại phòng?',message:`Xóa loại phòng “${type.name}” khỏi khách sạn này? Dữ liệu lịch sử và phòng vật lý sẽ được giữ lại nếu đang có liên kết.`,confirmText:'Xóa loại phòng'})) return;
        roomSaving=true;button.disabled=true;button.textContent='Đang xóa…';
        try {
            const response=await adminApi(`/hotels/${hotelId}/room-types/${type.id}`,{method:'DELETE'});
            if(!response.ok)throw new Error(await apiError(response));
            const data=await response.json();roomSaving=false;closeRoomEditor();await loadRoomTypes();toast(data.message||'Đã xóa loại phòng.');
        }catch(error){byId('roomTypesErrorText').textContent=error.message;byId('roomTypesError').hidden=false;}
        finally{roomSaving=false;button.disabled=false;button.textContent='Xóa';}
    }
    function roomFieldErrors(errors = {}) {
        if(Object.keys(errors).length)roomValidation.show(errors);
        else roomValidation.reset();
    }
    async function openRoomEditor(type = null) {
        if (!['ready','empty'].includes(roomTypesState) || roomSaving || hotelSaving) return;
        const legacy = roomInventoryModel === 'legacy';
        const scoped = roomInventoryModel === 'scoped';
        if (legacy && type) return;
        const version = ++roomEditorVersion;
        clearRoomImageSelection();
        if (scoped && type) {
            byId('saveRoomType').disabled=true;
            try {
                const response=await adminApi(`/hotels/${hotelId}/room-types/${type.id}`);
                if (!response.ok) throw new Error(await apiError(response));
                const data=await response.json();
                if (version!==roomEditorVersion) return;
                if (Number(data.room_type?.hotel_id)!==hotelId || Number(data.room_type?.id)!==Number(type.id)) throw new Error('Dữ liệu loại phòng không khớp khách sạn.');
                type=data.room_type;
            } catch(error) { byId('roomTypesErrorText').textContent=error.message;byId('roomTypesError').hidden=false;return; }
        }
        roomEditorTypeId = type ? Number(type.id) : null;
        window.AdminRoomRates?.select(hotelId,roomEditorTypeId,roomInventoryModel);
        byId('roomEditorTitle').textContent = type ? `Sửa loại phòng: ${type.name}` : 'Thêm loại phòng';
        byId('roomName').value = type?.name ?? '';
        byId('roomPrice').value = type?.base_price ?? '';
        byId('roomCapacity').value = type?.capacity ?? '';
        byId('roomBedType').value = type?.bed_type ?? '';
        byId('roomTotal').value = type?.total_rooms ?? '';
        byId('roomStatus').value = type?.status ?? 'active';
        byId('roomDescription').value = type?.description ?? '';
        byId('roomAmenitiesField').hidden=!scoped;
        byId('roomAmenities').value=(type?.amenities||[]).join('\n');
        roomFieldErrors();
        byId('roomStatusField').hidden = legacy;
        byId('roomFloorField').hidden = !legacy && !scoped;
        byId('roomFloor').value = '1';
        const creatingPhysical = legacy || (scoped && !type);
        byId('roomTotalLabel').textContent = creatingPhysical ? 'Số phòng cần tạo' : 'Tổng số phòng quản lý';
        byId('roomTotalNote').textContent = creatingPhysical ? 'Tạo các phòng có số phòng riêng trong khách sạn này.' : 'Đây là tổng tồn quản lý, không phải số phòng còn trống theo ngày.';
        byId('roomTotal').min = creatingPhysical ? '1' : '0';
        byId('roomTotal').max = creatingPhysical ? '100' : scoped ? '1000' : '2147483647';
        byId('roomCapacity').max = legacy || scoped ? '10' : '2147483647';
        byId('roomDescription').maxLength = legacy ? 500 : 65535;
        byId('roomEditorError').hidden = true;
        byId('roomTypeEditor').hidden = false;
        byId('saveRoomType').disabled = false;
        byId('roomImageField').hidden = legacy;
        if (!legacy) await roomImages.open(type ? `/hotels/${hotelId}/room-types/${type.id}/images` : null, {ordering:scoped});
        byId('roomTypeEditor').scrollIntoView({behavior:'smooth',block:'start'});
        byId('roomName').focus({preventScroll:true});
    }
    function closeRoomEditor() {
        if (roomSaving) return;
        roomEditorVersion++;
        roomEditorTypeId = null;
        window.AdminRoomRates?.select(null,null,null);
        clearRoomImageSelection();
        byId('roomTypeEditor').hidden = true;
        byId('saveRoomType').disabled = true;
    }
    async function saveRoomType(button) {
        const legacy = roomInventoryModel === 'legacy';
        const scoped = roomInventoryModel === 'scoped';
        if (roomSaving || !['ready','empty'].includes(roomTypesState) || (!legacy && !roomImages.ready)) return;
        const name = byId('roomName').value.trim();
        const price = byId('roomPrice').value.trim();
        const capacity = byId('roomCapacity').value;
        const total = byId('roomTotal').value;
        if(!roomValidation.validate())return;
        const payload = {
            name,
            base_price:price,
            capacity,
            bed_type:byId('roomBedType').value.trim() || null,
            total_rooms:total,
            status:byId('roomStatus').value,
            description:byId('roomDescription').value.trim() || null
        };
        const isNew = roomEditorTypeId === null;
        roomFieldErrors();
        if (scoped) {
            payload.amenities=[...new Set(byId('roomAmenities').value.split('\n').map(value=>value.trim()).filter(Boolean))];
            payload.floor_start=byId('roomFloor').value;
            if (isNew) { delete payload.total_rooms;payload.room_count=total; }
        }
        if (legacy) {
            if (!isNew) return;
            delete payload.total_rooms;
            delete payload.status;
            payload.room_count = total;
            payload.floor_start = byId('roomFloor').value;
        }
        payload.inventory_model = roomInventoryModel;
        const endpoint = isNew ? `/hotels/${hotelId}/room-types` : `/hotels/${hotelId}/room-types/${roomEditorTypeId}`;
        const imageUpdated = !legacy && (roomImages.fresh.length || roomImages.deleted.size || roomImages.primary !== roomImages.originalPrimary);
        let body;
        try { body = legacy ? JSON.stringify(payload) : roomImages.body(payload, isNew ? 'POST' : 'PUT'); }
        catch(error) { byId('roomEditorError').textContent=error.message;byId('roomEditorError').hidden=false;return; }
        roomSaving = true;
        roomImages.setSaving(true);
        ['roomName','roomPrice','roomCapacity','roomBedType','roomTotal','roomFloor','roomStatus','roomDescription','roomAmenities','cancelRoomType'].forEach(id=>byId(id).disabled=true);
        button.disabled = true;
        adminBusy(button,true);
        button.textContent = 'Đang lưu…';
        try {
            const response = await adminApi(endpoint,{method:body instanceof FormData?'POST':isNew?'POST':'PUT',body});
            if (!response.ok) {
                const data=await response.json();roomFieldErrors(data.errors||{});
                throw new Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Không lưu được loại phòng.');
            }
            roomEditorTypeId = null;
            clearRoomImageSelection();
            byId('roomTypeEditor').hidden = true;
            roomTypesState = 'idle';
            await loadRoomTypes();
            toast(imageUpdated?'Đã lưu loại phòng và ảnh phòng.':isNew?'Đã thêm loại phòng.':'Đã lưu loại phòng.');
        } catch (error) {
            if(!byId('roomEditorError').dataset.validationSummary){byId('roomEditorError').textContent=error.message||'Không thể lưu loại phòng.';byId('roomEditorError').hidden=false;}
        } finally {
            roomSaving = false;
            roomImages.setSaving(false); adminBusy(button,false);
            ['roomName','roomPrice','roomCapacity','roomBedType','roomTotal','roomFloor','roomStatus','roomDescription','roomAmenities','cancelRoomType'].forEach(id=>byId(id).disabled=false);
            button.disabled = !['ready','empty'].includes(roomTypesState);
            button.textContent = 'Lưu loại phòng';
            roomValidation.focus();
        }
    }

    document.addEventListener('click',event=>{
        const edit = event.target.closest('[data-edit-group]');
        if (edit) beginEdit(edit.dataset.editGroup);
        const cancel = event.target.closest('[data-cancel-group]');
        if (cancel && !hotelSaving) renderOverview();
        const save = event.target.closest('[data-save-group]');
        if (save) saveGroup(save.dataset.saveGroup,save);
        const tab = event.target.closest('[role="tab"]');
        if (tab && tab.closest('.hd-tabs')) setTab(({tabOverview:'overview',tabImages:'images',tabRoomTypes:'roomTypes'})[tab.id]);
        const roomEdit = event.target.closest('[data-edit-room]');
        if (roomEdit) openRoomEditor(roomTypes.find(type=>Number(type.id)===Number(roomEdit.dataset.editRoom)));
        const remove = event.target.closest('[data-delete-room]');
        if (remove) deleteRoomType(roomTypes.find(type=>Number(type.id)===Number(remove.dataset.deleteRoom)), remove);
    });
    document.addEventListener('click',event=>{
        if (!event.target.closest('#descriptionToggle')) return;
        descriptionExpanded=!descriptionExpanded;
        renderBasicCard();
    });
    byId('hotelRetry').addEventListener('click',loadHotel);
    byId('hotelStatusAction').addEventListener('click',changeHotelStatus);
    byId('saveHotelImages').addEventListener('click',event=>saveHotelImageChanges(event.currentTarget));
    byId('retryRoomTypes').addEventListener('click',loadRoomTypes);
    byId('addRoomType').addEventListener('click',()=>openRoomEditor());
    byId('cancelRoomType').addEventListener('click',closeRoomEditor);
    byId('saveRoomType').addEventListener('click',event=>saveRoomType(event.currentTarget));
    byId('saveHotelImages').disabled = true;
    byId('saveRoomType').disabled = true;
    document.querySelectorAll('.hd-menu,.hd-room-toolbar').forEach(node=>node.addEventListener('click',event=>{
        if (event.target.closest('button')) node.querySelector('details')?.removeAttribute('open');
    }));
    document.querySelectorAll('.hd-tabs .hd-tab').forEach((tab,index)=>{
        tab.addEventListener('keydown',event=>{
            if (!['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) return;
            event.preventDefault();
            const tabs=[...document.querySelectorAll('.hd-tabs .hd-tab')];
            const next=event.key==='Home'?0:event.key==='End'?tabs.length-1:(index+(event.key==='ArrowRight'?1:tabs.length-1))%tabs.length;
            tabs[next].focus();
            tabs[next].click();
        });
    });
    document.addEventListener('DOMContentLoaded',loadHotel);
})();
</script>
@include('admin.partials.room-rate-editor-script')
@endpush
