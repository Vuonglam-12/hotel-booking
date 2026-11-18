<section class="admin-image-editor" id="{{ $editorId }}" aria-label="Quản lý ảnh">
    <p class="image-editor-status" data-image-status role="status" aria-live="polite"></p>
    <button type="button" class="image-editor-action" data-image-retry hidden>Tải lại ảnh</button>
    <fieldset data-image-controls>
        <legend class="image-editor-heading">{{ $editorTitle ?? 'Cập nhật hình ảnh' }}</legend>
        <h3 class="image-editor-heading">Ảnh đại diện</h3>
        <p class="image-editor-hint">Một ảnh dùng để hiển thị đầu tiên. Chọn từ bộ ảnh bên dưới hoặc tải một ảnh đại diện mới.</p>
        <div class="image-editor-cover" data-image-cover></div>
        <div class="image-editor-gallery-head"><h3 class="image-editor-heading">{{ $galleryTitle ?? 'Thư viện ảnh' }}</h3><span class="image-editor-hint" data-image-count></span></div>
        <p class="image-editor-hint">Bộ ảnh chi tiết, gồm cả ảnh đại diện. Thêm ảnh giữ nguyên các ảnh đã có và ảnh đại diện đang chọn.</p>
        <div class="image-editor-upload-row"><button type="button" class="image-editor-upload" data-image-pick="add">Tải ảnh lên</button><span class="image-editor-hint">Chọn một hoặc nhiều ảnh.</span></div>
        <input id="{{ $editorId }}-files" data-image-files type="file" accept="image/jpeg,image/png,image/webp" multiple hidden aria-describedby="{{ $editorId }}-limits">
        <input id="{{ $editorId }}-cover-file" data-image-cover-file type="file" accept="image/jpeg,image/png,image/webp" hidden aria-describedby="{{ $editorId }}-limits">
        <div class="image-editor-grid" data-image-gallery></div>
        <p class="image-editor-hint" id="{{ $editorId }}-limits" data-image-limits>{{ \App\Services\AdminAccommodationImageService::limits()['label'] }}</p>
        <p class="image-editor-hint">Thay đổi ảnh chỉ được áp dụng khi lưu. Hủy sẽ bỏ các lựa chọn chưa lưu.</p>
    </fieldset>
</section>
