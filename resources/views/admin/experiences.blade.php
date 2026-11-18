@extends('admin.layouts.admin')
@section('title', 'Trải nghiệm')
@push('styles')
<style>
    .exa { color:#142B49; } .exa-heading { display:flex; align-items:center; justify-content:space-between; gap:18px; margin-bottom:26px; }
    .exa h1 { font-size:30px; font-weight:800; letter-spacing:-.03em; } .exa-heading p { color:#728198; margin-top:6px; font-size:14px; }
    .exa-button { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:40px; border:1px solid #DDE4ED; border-radius:9px; background:white; padding:9px 14px; color:#142B49; font-size:13px; font-weight:700; }
    .exa-button--primary { background:#2563EB; border-color:#2563EB; color:white; } .exa-button:disabled { opacity:.45; cursor:default; }
    .exa-toolbar { display:grid; grid-template-columns:1fr 1fr 1.3fr auto; gap:12px; padding:20px; background:white; border:1px solid #E3E8F0; border-radius:14px; margin-bottom:22px; }
    .exa :is(input:not([type=checkbox],[type=radio],[type=file]),select,textarea) { width:100%; min-width:0; border:1px solid #D9E1EC; border-radius:9px; padding:10px 12px; background:white; color:#142B49; font-size:14px; }
    .exa-table-wrap { overflow-x:auto; background:white; border:1px solid #E3E8F0; border-radius:14px; } .exa table { width:100%; min-width:760px; border-collapse:collapse; text-align:left; }
    .exa th { padding:15px 18px; font-size:12px; color:#6D7D94; background:#F8FAFD; } .exa td { padding:16px 18px; border-top:1px solid #EDF0F5; font-size:13px; vertical-align:middle; }
    .exa-name { display:flex; align-items:center; gap:14px; min-width:220px; } .exa-thumb { width:64px; height:54px; object-fit:cover; border-radius:8px; background:#EDF1F6; flex-shrink:0; }
    .exa-name strong { display:block; font-size:14px; } .exa-name small { display:block; color:#7B899C; margin-top:5px; }
    .exa-status { display:inline-block; border-radius:6px; padding:5px 8px; font-size:11px; background:#EFF3F9; color:#60728B; } .exa-status--published { background:#E9F4EF; color:#28634A; }
    .exa-actions { display:flex; flex-wrap:wrap; gap:8px; } .exa-state { border:1px dashed #D0DAE7; border-radius:14px; padding:38px 22px; background:white; text-align:center; color:#6C7D94; line-height:1.8; }
    .exa-pagination { display:flex; gap:16px; align-items:center; justify-content:center; margin-top:20px; }
    .exa-dialog { width:min(920px,calc(100vw - 28px)); max-height:calc(100dvh - 40px); padding:0; border:0; border-radius:16px; background:white; }
    .exa-dialog::backdrop { background:#142B4980; } .exa-dialog-header { display:flex; justify-content:space-between; align-items:center; padding:22px 26px; border-bottom:1px solid #E5EAF1; }
    .exa-dialog h2 { font-size:23px; font-weight:800; } .exa-form-body { padding:24px 26px; } .exa-form-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
    .exa-field { display:grid; gap:7px; min-width:0; } .exa-field label { font-size:12px; font-weight:700; color:#5C6D85; } .exa-wide { grid-column:1/-1; }
    .exa-note { color:#74849A; font-size:12px; line-height:1.7; } .exa-code-row { display:flex; gap:10px; align-items:center; } .exa-code-row input { flex:1; }
    .exa-error { padding:14px; border-radius:10px; background:#FFF1F1; color:#A83838; font-size:13px; line-height:1.7; white-space:pre-line; margin-bottom:18px; }
    .exa-image-list { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; } .exa-image { min-width:0; border:1px solid #DFE6F0; border-radius:10px; overflow:hidden; background:#FAFBFD; }
    .exa-image img { width:100%; height:110px; object-fit:cover; } .exa-image-controls { padding:10px; display:grid; gap:7px; font-size:12px; } .exa-image label { display:flex; align-items:center; gap:6px; }
    .exa-checks { display:grid; gap:13px; padding:18px; border:1px solid #DCE6F4; border-radius:12px; background:#F8FAFE; margin-top:22px; font-size:13px; }
    .exa-checks label { display:flex; align-items:flex-start; gap:10px; line-height:1.7; } .exa-checks input { margin-top:4px; }
    .exa-form-footer { display:flex; align-items:center; justify-content:flex-end; gap:10px; padding-top:22px; }
    .exa [hidden] { display:none !important; } .exa :is(button,input,select,textarea):focus-visible { outline:2px solid #2563EB; outline-offset:2px; }
    @media(max-width:900px) { .exa-toolbar { grid-template-columns:1fr 1fr; } .exa-image-list { grid-template-columns:repeat(3,minmax(0,1fr)); } }
    @media(max-width:639px) { .exa-heading { align-items:flex-start; flex-direction:column; } .exa h1 { font-size:26px; } .exa-toolbar,.exa-form-grid { grid-template-columns:1fr; } .exa-wide { grid-column:auto; } .exa-form-body,.exa-dialog-header { padding:20px 16px; } .exa-image-list { grid-template-columns:repeat(2,minmax(0,1fr)); } .exa-dialog { max-height:calc(100dvh - 20px); } .exa-code-row { flex-wrap:wrap; } }
</style>
@include('admin.partials.compact-picker-styles')
@endpush
@section('content')
<div class="exa" id="admin-experiences">
    <header class="exa-heading"><div><h1>Trải nghiệm</h1><p>Quản lý địa điểm, hình ảnh và nội dung trước khi công bố.</p></div><div class="exa-actions"><a class="exa-button" href="{{ route('experiences.index') }}">Xem trang Trải nghiệm ↗</a><button type="button" class="exa-button exa-button--primary" id="exa-create" disabled>＋ Thêm trải nghiệm</button></div></header>
    <form id="exa-filters" class="exa-toolbar"><select id="exa-location-filter" aria-label="Lọc địa phương"><option value="">Tất cả địa phương</option></select><select id="exa-archive-filter" aria-label="Lọc lưu trữ"><option value="0">Đang quản lý</option><option value="1">Đã lưu trữ</option></select><input id="exa-query" type="search" maxlength="120" placeholder="Tìm theo tên trải nghiệm" aria-label="Tìm trải nghiệm"><button class="exa-button" type="submit">Tìm kiếm</button></form>
    <div id="exa-loading" class="exa-state" role="status">Đang tải trải nghiệm…</div>
    <div id="exa-error" class="exa-state" role="alert" hidden><p>Chưa thể tải dữ liệu. Vui lòng thử lại sau.</p><button type="button" class="exa-button" id="exa-retry">Thử lại</button></div>
    <div id="exa-empty" class="exa-state" hidden>Chưa có trải nghiệm phù hợp. Bạn có thể thêm trải nghiệm hoặc đổi bộ lọc.</div>
    <div class="exa-table-wrap" id="exa-table" hidden><table><thead><tr><th>Trải nghiệm</th><th>Danh mục</th><th>Công bố</th><th>Hoạt động</th><th>Thao tác</th></tr></thead><tbody id="exa-rows"></tbody></table></div>
    <nav class="exa-pagination" id="exa-pagination" hidden aria-label="Phân trang quản trị"><button class="exa-button" id="exa-prev" type="button">← Trước</button><span id="exa-page-number"></span><button class="exa-button" id="exa-next" type="button">Tiếp →</button></nav>
    <dialog class="exa-dialog" id="exa-editor" aria-labelledby="exa-editor-title">
        <header class="exa-dialog-header"><h2 id="exa-editor-title">Thêm trải nghiệm</h2><button type="button" class="exa-button" data-exa-close aria-label="Đóng form">×</button></header>
        <form id="exa-form" class="exa-form-body">
            <div id="exa-form-error" class="exa-error" role="alert" hidden></div>
            <fieldset id="exa-fields"><div class="exa-form-grid">
                <div class="exa-field exa-wide"><label for="exa-name">Tên trải nghiệm *</label><input name="name" id="exa-name" maxlength="200" required></div>
                <div class="exa-field"><label for="exa-location">Địa phương *</label><select name="location_id" id="exa-location" required></select></div>
                <div class="exa-field"><label for="exa-category">Danh mục trải nghiệm</label><select name="experience_category" id="exa-category"><option value="">Chưa phân loại</option></select></div>
                <div class="exa-field"><label for="exa-item-type">Loại địa điểm *</label><select name="item_type" id="exa-item-type" required><option value="attraction">Tham quan</option><option value="restaurant">Ăn uống</option><option value="activity">Hoạt động</option><option value="hotel">Khách sạn — dữ liệu cũ</option><option value="transport">Di chuyển — dữ liệu cũ</option><option value="shopping">Mua sắm — dữ liệu cũ</option></select></div>
                <div class="exa-field"><label for="exa-slug">Đường dẫn cố định</label><input name="slug" id="exa-slug" readonly placeholder="Được tạo khi lưu"></div>
                <div class="exa-field exa-wide"><label for="exa-location-code">Mã địa phương</label><div class="exa-code-row"><input id="exa-location-code" maxlength="80" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="Ví dụ: vn-ha-noi"><button type="button" id="exa-save-code" class="exa-button">Gán mã</button></div><p class="exa-note">Kiểm tra đúng địa phương trước khi gán mã nội bộ. Mã đã gán được giữ cố định, không phụ thuộc ID hoặc tên hiển thị.</p></div>
                <div class="exa-field exa-wide"><label for="exa-summary">Mô tả ngắn</label><textarea name="experience_summary" id="exa-summary" rows="2" maxlength="500"></textarea></div>
                <div class="exa-field exa-wide"><label for="exa-description">Giới thiệu địa điểm</label><textarea name="description" id="exa-description" rows="5" maxlength="20000"></textarea></div>
                <div class="exa-field exa-wide"><label for="exa-address">Địa chỉ</label><input name="address" id="exa-address" maxlength="255"></div>
                <div class="exa-field"><label for="exa-duration">Thời lượng đã kiểm tra (phút)</label><input name="experience_duration_minutes" id="exa-duration" type="number" min="1" max="1440" placeholder="Để trống nếu chưa có thông tin"></div>
                <div class="exa-field"><label for="exa-source">Nguồn thông tin</label><input name="experience_source_url" id="exa-source" type="url" maxlength="2048" placeholder="https://…"></div>
                <div class="exa-field"><label for="exa-open-time">Giờ mở cửa</label><input name="open_time" id="exa-open" type="time" data-aurel-picker="time" data-picker-label="Giờ mở cửa"></div><div class="exa-field"><label for="exa-close-time-time">Giờ đóng cửa</label><input name="close_time" id="exa-close-time" type="time" data-aurel-picker="time" data-picker-label="Giờ đóng cửa"></div>
                <div class="exa-field exa-wide"><label>Gallery</label><div id="exa-images" class="exa-image-list"></div><label for="exa-files">Thêm ảnh JPG, PNG hoặc WebP</label><input id="exa-files" type="file" accept="image/jpeg,image/png,image/webp" multiple><p class="exa-note">Tối đa 12 ảnh/trải nghiệm, 2 MB/ảnh. Chỉ tải ảnh đúng địa điểm và có quyền sử dụng. Ảnh bị bỏ khỏi gallery vẫn được giữ trên hệ thống.</p><div id="exa-new-images" class="exa-image-list"></div></div>
                <div class="exa-field exa-wide"><label for="exa-image-source">Nguồn và quyền sử dụng ảnh mới</label><input name="image_source" id="exa-image-source" maxlength="500" placeholder="Đơn vị/người cung cấp ảnh và quyền sử dụng"></div>
                <div class="exa-field exa-wide"><label for="exa-image-caption">Mô tả ảnh mới (nếu có)</label><input name="image_caption" id="exa-image-caption" maxlength="200"></div>
            </div>
            <div class="exa-checks"><label><input type="checkbox" name="is_active" id="exa-active">Địa điểm đang hoạt động</label><label><input type="checkbox" name="verify" id="exa-verify">Tôi đã đối chiếu đúng địa phương, nội dung và quyền sử dụng ảnh với nguồn thông tin.</label><label><input type="checkbox" name="publish" id="exa-publish">Xuất bản lên trang Trải nghiệm</label><p class="exa-note">Mục cũ không tự xuất bản. Thay đổi nội dung hoặc ảnh cần xác minh lại; dữ liệu chung còn được dùng để gợi ý lịch trình.</p></div>
            </fieldset>
            <p id="exa-save-help" class="exa-note" role="status" style="margin-top:18px"></p>
            <footer class="exa-form-footer"><button type="button" class="exa-button" data-exa-close>Hủy</button><button type="submit" class="exa-button exa-button--primary" id="exa-submit">Lưu bản nháp</button></footer>
        </form>
    </dialog>
</div>
@endsection
@push('scripts')
@include('admin.partials.compact-picker-script')
@include('admin.partials.experiences-script')
@endpush
