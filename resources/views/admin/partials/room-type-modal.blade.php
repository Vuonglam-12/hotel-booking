<div id="roomTypeModal" class="rt-modal-overlay" onclick="closeRoomTypeModalOnBg(event)">
    <div class="rt-modal" role="dialog" aria-modal="true" aria-labelledby="roomTypeModalTitle">
        <div class="modal-header"><h3 id="roomTypeModalTitle">Thêm loại phòng</h3><button type="button" id="rtCloseBtn" onclick="closeRoomTypeModal()" aria-label="Đóng form loại phòng">&times;</button></div>
        <div class="rt-selector"><label for="rtTypeSelect" id="rtTypeSelectLabel">Chọn loại phòng cần sửa</label><select id="rtTypeSelect" disabled><option value="">Đang tải loại phòng…</option></select>
            <p class="image-editor-status" id="rtListFeedback" role="status" aria-live="polite"></p>
            <button type="button" class="image-editor-action" id="rtListRetry" hidden>Tải lại danh sách</button>
            <button type="button" class="image-editor-action" id="rtNewType" disabled>Thêm loại phòng mới</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="rtHotelId">
            <div id="rtInfoFields">
            <div class="form-row"><label for="rtName">Tên loại phòng *</label><input type="text" id="rtName" maxlength="100" placeholder="VD: Deluxe, Superior..."></div>
            <div class="form-row two-col">
                <div><label for="rtPrice">Giá cơ bản/đêm (VNĐ) *</label><input type="text" inputmode="decimal" id="rtPrice" placeholder="500000"></div>
                <div><label for="rtCapacity">Sức chứa *</label><input type="number" id="rtCapacity" value="2" min="1" max="2147483647" step="1"></div>
            </div>
            <div class="form-row two-col">
                <div><label for="rtBedType">Loại giường</label><input type="text" id="rtBedType" maxlength="50" placeholder="VD: King, Twin..."></div>
                <div><label for="rtCount" id="rtCountLabel">Tổng số phòng có thể bán *</label><input type="number" id="rtCount" value="5" min="0" max="2147483647" step="1"></div>
            </div>
            <div class="form-row" id="rtFloorField" hidden><label for="rtFloor">Tầng bắt đầu</label><input type="number" id="rtFloor" value="1" min="1" max="99" step="1"></div>
            <div class="form-row" id="rtStatusField"><label for="rtStatus">Trạng thái</label><select id="rtStatus"><option value="active">Hoạt động</option><option value="inactive">Ngừng bán</option></select></div>
            <div class="form-row"><label for="rtDesc">Mô tả</label><textarea id="rtDesc" rows="2" placeholder="Mô tả ngắn..."></textarea></div>
            <div class="form-row" id="rtAmenitiesField" hidden><label for="rtAmenities">Tiện nghi riêng (mỗi dòng một tiện nghi)</label><textarea id="rtAmenities" rows="3"></textarea></div>
            </div>
            <div id="rtImagesField" hidden>
                @include('admin.partials.image-editor', ['editorId' => 'modal-room-images', 'editorTitle' => 'Ảnh loại phòng', 'galleryTitle' => 'Ảnh đã lưu và ảnh mới'])
            </div>
            @include('admin.partials.room-rate-editor')
        </div>
        <div class="room-validation-summary" id="rtValidationSummary" hidden role="alert"></div>
        <div class="modal-footer"><button type="button" onclick="closeRoomTypeModal()" class="btn-cancel-modal">Hủy</button><button type="button" onclick="saveRoomType()" class="btn-save" id="rtSaveBtn">Tạo loại phòng</button></div>
    </div>
</div>
