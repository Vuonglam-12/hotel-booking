# AUREL — thêm ảnh phòng từ form sửa khách sạn

13/10/2026. Ảnh người dùng gửi là tab Hình ảnh của form **sửa khách sạn**. Các nút upload trong gallery đó lưu ảnh Hotel, nên chưa có lối vào rõ ràng để cập nhật ảnh từng RoomType.

## Giao diện và cách sử dụng

**Sửa khách sạn → Hình ảnh → Thêm / sửa ảnh phòng → chọn loại phòng → Tải ảnh lên / + Thêm ảnh → Lưu ảnh phòng.**

- Card “Ảnh các loại phòng” nằm ngay đầu tab. Gallery bên dưới đổi nhãn thành “Ảnh khách sạn” / “Thư viện ảnh khách sạn”.
- Modal mở từ card chỉ có bộ chọn loại phòng và quản lý ảnh. Một ảnh đại diện nằm trong cùng bộ ảnh phòng; gallery cho chọn nhiều file, giữ cover hiện tại khi thêm ảnh. Chọn cover, xóa/hoàn tác, bỏ file mới dùng editor cũ.
- “Lưu ảnh phòng” lưu độc lập. UI giải thích rõ việc này: thay đổi khách sạn đang nhập vẫn giữ nguyên. Hủy form khách sạn sau đó không hoàn tác ảnh phòng đã lưu thành công.
- Khi Hotel chưa được tạo, nút bị khóa và hướng dẫn lưu Hotel trước. Hotel chưa có RoomType thì hướng dẫn thêm loại phòng trong mục Phòng; không tạo phòng rỗng hoặc upload ảnh chưa xác định chủ sở hữu.
- Form thêm/sửa loại phòng đầy đủ trong mục Phòng vẫn dùng cùng modal ở chế độ hiện có.

## Logic

- Tái sử dụng PUT `/api/admin/hotels/{hotel}/room-types/{type}`; multipart dùng `_method=PUT`. Chế độ ảnh gửi `inventory_model`, `image_changes`, `images[]`; không gửi name, base_price, total_rooms hoặc metadata khác.
- Backend hiện có hỗ trợ cập nhật ảnh riêng, kiểm tra Admin và Hotel–RoomType–Image. Không sửa backend/API/storage/validation hay quy tắc xóa liên kết.
- Mở modal phòng giữ toàn bộ draft Hotel. Hotel modal được đặt inert/aria-hidden tạm thời; chặn lưu/đóng/chuyển Hotel khi modal phòng còn mở. Đóng modal phòng khôi phục Hotel và focus, giữ khóa cuộn cho đến khi cả hai form đóng.
- Focus vào nút đóng, Tab/Shift-Tab giữ trong modal phòng, Esc/click ngoài đóng modal trên cùng. Header/footer cố định; nội dung cuộn, gallery desktop 4 cột/mobile 2 cột.
- Lỗi lưu giữ file/cover, khóa gửi trùng khi đang lưu. Tái sử dụng abort/version chống response cũ và kiểm tra hotel_id trong danh sách RoomType.

## File thay đổi

- `resources/views/admin/hotels.blade.php`
- `resources/views/prepared-v2/admin-hotels.blade.php`
- `resources/views/admin/partials/hotel-room-images-entry.blade.php` (mới)
- `resources/views/admin/partials/accommodation-modal-script.blade.php`
- `resources/views/admin/partials/accommodation-modal-styles.blade.php`
- `resources/views/admin/partials/room-type-modal.blade.php`
- `resources/views/admin/partials/room-type-modal-script.blade.php`
- `tests/Frontend/admin-room-image-logic.test.cjs`
- `tests/Frontend/admin-images.browser.cjs`
- Báo cáo này.

## Kiểm tra thực tế

- **16 test logic PASS**: `node tests/Frontend/admin-room-image-logic.test.cjs`. Thêm kiểm tra Hotel chưa lưu, ownership URL, chỉ gửi ảnh, metadata không bắt buộc ở chế độ ảnh, lỗi lưu và catalog rỗng.
- **85 kiểm tra Chrome + HTTP + DB synthetic PASS**, **0 uncaught errors**. Có lưu thật và mở lại; kiểm tra Type metadata/Hotel metadata/Hotel gallery không đổi khi lưu ảnh phòng. Kiểm tra giữ nguyên Hotel draft, Hủy không ghi, invalid file, lỗi lưu, khóa lúc lưu, đổi Type, cả hai trang Hotel thường/prepared, focus/Tab/Esc/click ngoài/scroll, desktop 1440px/mobile 390px.
- Đã xem trực tiếp screenshot desktop/mobile của card và modal ảnh phòng. Blade được render thực tế ở cả hai trang Hotel. Không thay asset Vite, không chạy lại các phase khác.
- Evidence: `storage/app/private/roomtype-v2-tests/38d3cd82e6d5/admin-images-browser-results.json`; screenshot `admin-room-images-entry-{desktop,mobile}.png`, `admin-room-images-only-{desktop,mobile}.png`.

Môi trường MySQL owned riêng `38d3cd82e6d5`, port `62565`, storage/profile riêng, Checkout tắt. Chrome/PHP và MySQL đã đóng; giữ evidence/datadir. Ảnh mẫu lấy từ artifact private đã có, không khôi phục các ảnh demo public bị gỡ.

So với baseline của lượt này, backend, routes, config, migration, `.env`, frontend khách hàng và booking/payment giữ nguyên. Không kết nối DB chính hoặc mở V2 production. Vì guard V2 vẫn giữ nguyên, chức năng ở môi trường thường chỉ ghi khi môi trường đã đáp ứng contract V2 hiện có; lỗi chưa sẵn sàng hiển thị rõ và khóa upload/lưu. Kiểm tra lưu thực tế ở trên chỉ diễn ra trong môi trường thử nghiệm được cấp descriptor.
