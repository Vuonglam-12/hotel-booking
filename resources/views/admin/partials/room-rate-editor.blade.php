@include('admin.partials.room-rate-editor-styles')
<details id="rtRateEditor" class="room-rate-editor" hidden open>
    <summary>Giá phòng</summary>
    <p id="rtRateContext" class="rr-context"></p>
    <div class="rr-base"><div><strong>Giá cơ bản</strong><small>Giữ nguyên; mỗi đêm cộng thêm phụ thu áp dụng.</small></div><output id="rtRateBasePrice">—</output></div>
    <section class="rr-card" aria-labelledby="rtRateFormTitle">
        <h4 id="rtRateFormTitle">Phụ thu theo ngày / mùa</h4>
        <p class="rr-note">Cộng thêm vào giá cơ bản. Ưu tiên phụ thu ngày cụ thể → phụ thu mùa; không cộng dồn hai khoản. Không có quy tắc thì phụ thu bằng 0.</p>
        <form id="rtRateForm">
            <div class="rr-fields">
                <div class="rr-field"><label for="rtRateKind">Loại giá</label><select id="rtRateKind"><option value="day">Ngày cụ thể</option><option value="season">Khoảng mùa</option></select></div>
                <div class="rr-field"><label for="rtRateLabel">Tên gợi nhớ <span style="font-weight:400">(tùy chọn)</span></label><input id="rtRateLabel" type="text" maxlength="150" placeholder="Ví dụ: Tết, mùa hè"></div>
                <div class="rr-field"><label for="rtRateStart" id="rtRateStartLabel">Ngày áp dụng</label><input id="rtRateStart" type="date" required></div>
                <div class="rr-field" id="rtRateEndField" hidden><label for="rtRateEnd">Đến hết ngày</label><input id="rtRateEnd" type="date"><small>Tính cả ngày bắt đầu và ngày kết thúc.</small></div>
                <div class="rr-field"><label for="rtRatePrice">Phụ thu ngày/mùa (₫/phòng/đêm)</label><input id="rtRatePrice" type="text" inputmode="decimal" required pattern="[0-9]{1,10}(\.[0-9]{1,2})?" placeholder="Ví dụ: 100000"><small>Cộng thêm vào giá cơ bản. Không nhập dấu phân cách hàng nghìn.</small></div>
            </div>
            <div class="rr-actions"><button class="rr-button rr-button-primary" id="rtRateSave" type="submit">Lưu mức giá</button><button class="rr-button" id="rtRateReset" type="button" hidden>Hủy chỉnh sửa</button></div>
        </form>
        <p id="rtRateFeedback" class="rr-feedback" role="status" aria-live="polite"></p>
    </section>
    <section class="rr-card" aria-labelledby="rtRateListTitle">
        <div class="rr-list-head"><h4 id="rtRateListTitle">Các mức giá đã lưu <span id="rtRateCount"></span></h4><button type="button" class="rr-button" id="rtRateReload">Tải lại</button></div>
        <table class="rr-table"><colgroup><col style="width:22%"><col style="width:30%"><col style="width:25%"><col style="width:23%"></colgroup><thead><tr><th>Loại giá</th><th>Thời gian áp dụng</th><th style="text-align:right">Phụ thu / phòng / đêm</th><th style="text-align:right">Thao tác</th></tr></thead><tbody id="rtRateRows"></tbody></table>
        <p class="rr-note" style="margin-bottom:0">Các mức cùng loại không được trùng ngày. Đơn đã đặt luôn giữ giá đã lưu.</p>
    </section>
</details>
