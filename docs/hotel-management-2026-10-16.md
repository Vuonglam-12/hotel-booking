# AUREL — sửa quản lý khách sạn ngày 16/10/2026

Đã sửa trực tiếp luồng đang hoạt động: `HotelRoomTypeService`, danh mục `hotel_room_type` trên inventory phòng vật lý. Kiểm tra thực tế cho thấy scoped catalog đang bật theo schema, `prepared_routes=false`, không có production manifest. Không đổi các cờ, không chuyển sang V2, không chạy migration/seeder hoặc ghi dữ liệu nghiệp vụ vào database chính.

## Nguyên nhân thực tế

Ảnh demo `image/img_room/room (n).jpg` đã bị gỡ khỏi workspace nhưng vẫn là ảnh `is_primary` trong danh mục. Một số loại phòng đã có ảnh upload còn tồn tại trong `storage/accommodation/hotel_room_type_image/...`. Admin lấy primary hỏng rồi trả placeholder; trang khách có thể lấy ảnh còn đọc được trong gallery. Upload và liên kết ảnh mới vẫn được lưu. Trang Admin chi tiết còn dùng một bộ chọn ảnh chỉ thêm file, không nối bộ quản lý gallery có sẵn.

Tiện nghi bị giới hạn bởi whitelist trong validation. Đồng bộ tên bằng SQL collation không phân biệt hoa/thường và `array_diff` phân biệt hoa/thường còn có thể tạo liên kết trùng khi chỉ đổi cách viết tên.

## Thay đổi

- Dùng chung cách lấy ảnh đại diện: ưu tiên primary, sau đó ảnh còn tải được trong đúng gallery của đối tượng. Resolver đọc đúng public storage disk đang cấu hình. Bảng Admin, form và trang khách đồng nhất; ảnh không còn tồn tại không che mất ảnh upload hợp lệ. Không đổi liên kết ảnh trong dữ liệu thật để sửa hiển thị.
- Nối lại `AdminImageEditor` tại trang chi tiết và modal loại phòng: xem ảnh cũ, thêm/xóa/hoàn tác từng ảnh, chọn đại diện, đổi thứ tự, trạng thái tải/lưu/lỗi và Hủy. Xóa đại diện chọn ảnh còn dùng được. Mọi thay đổi được gửi cùng lần lưu form.
- Cleanup file kiểm tra cả liên kết tương đối, URL tuyệt đối, prefix public và đường dẫn mã hóa trong các bảng ảnh trước khi xóa file.
- Thêm `DELETE /api/admin/hotels/{hotel}/room-types/{type}` dưới middleware Admin hiện có, xác minh đúng cặp hotel/type và khóa hotel giống luồng booking. Chặn đơn chưa hoàn tất kể cả đã qua ngày checkout, phòng đang giữ/sử dụng và tồn phòng tương lai có booked_count. Thông báo hướng dẫn Ngừng bán.
- Mục chưa có booking và không có phòng vật lý được xóa liên kết catalog. Mục còn phòng vật lý hoặc lịch sử được lưu trữ bằng `status=archived`, retire tồn phòng và loại khỏi danh sách Admin/bán. Giữ nguyên phòng vật lý, legacy RoomType dùng chung, ảnh và booking/snapshot. Không cascade xóa booking. Không cho sửa để mở bán lại mục đã lưu trữ qua endpoint cập nhật thường.
- Tái sử dụng `hotel_amenity` cho tiện nghi tùy chỉnh, không cần schema mới. Chuẩn hóa khoảng trắng/tên có sẵn và alias airport, chặn tên trống/trùng không phân biệt hoa/thường. Đồng bộ bằng identity trong phạm vi hotel, giữ row/icon đã có khi phù hợp. Tiện nghi phòng vẫn ở catalog riêng.
- Bộ chọn tiện nghi dùng chung cho hai form khách sạn: tìm kiếm, thêm bằng tên, checkbox 14px kèm icon, nền xanh khi chọn, chips đã chọn, gỡ tiện nghi riêng khỏi khách sạn, 2–3 cột desktop/1 cột mobile. Trang khách hiển thị tên tùy chỉnh, icon mặc định và danh sách đầy đủ chỉ gồm các mục đã chọn.
- Bỏ nhập/hiển thị/validation/default trong ứng dụng cho giờ chính sách; bỏ khối chỉ chứa giờ ở hai phía và giờ trong email/PDF. Giữ cột, dữ liệu và default schema lịch sử để tương thích. Ngày booking, kiểm tra ngày, số đêm và kiểm tra tồn theo khoảng ngày vẫn giữ nguyên.
- Giữ nguyên cách tổng hợp giá thấp nhất, không sửa giá/tên/dữ liệu phòng thật, thanh toán hoặc đăng nhập.

## Kết quả kiểm tra

Toàn bộ ghi dữ liệu kiểm thử dùng MySQL riêng tại loopback port 53325, tài khoản chỉ có quyền trên các schema thử nghiệm được allowlist. Storage, token và hồ sơ Chrome thử nghiệm riêng; các request bên ngoài bị chặn trong browser harness.

- `phpunit.hotel-roomtype.xml`: **14 tests / 126 assertions PASS**.
- `phpunit.hotel-management.xml`: **10 tests / 96 assertions PASS**. Bao gồm primary demo hỏng + upload + lưu/đọc lại, đổi/xóa đại diện, bảo vệ file có liên kết dùng chung, xóa/lưu trữ/chặn xóa, quyền Admin/phạm vi hotel, giữ lịch sử, tồn phòng, tiện nghi cũ/tùy chỉnh/tên trùng và khác biệt hoa/thường, bỏ giờ chính sách, giá thấp nhất và booking hai đêm.
- `node tests/Frontend/admin-hotel-roomtype-source.test.cjs`: **7 tests PASS**.
- `node tests/Frontend/admin-room-image-ui.test.cjs`: **2 tests PASS**; cập nhật các kỳ vọng cũ chỉ cho phép bộ chọn upload để phù hợp yêu cầu quản lý gallery.
- Chrome thật qua CDP: **7 nhóm kiểm tra PASS, 0 uncaught exceptions**, desktop 1440px/mobile 390px. Đã thao tác upload, đổi/xóa primary, lưu/tải lại, Hủy, thêm/tìm/chặn trùng/gỡ/lưu tiện nghi, bảo vệ khách sạn khác, mở danh sách tiện nghi phía khách, bộ chọn ngày hai đêm + tải tồn phòng, modal ảnh ở danh sách khách sạn và xác nhận xóa có tên loại phòng.
- Đã xem ảnh chụp desktop/mobile. PHP lint và `git diff --check` đạt.
- `npm run build` đạt. Còn cảnh báo sẵn có ở `resources/css/app.css:16` (`.text-[#C9D3DD]`) và dữ liệu Browserslist cũ; không mở rộng sửa CSS toàn trang/dependency.

Evidence riêng: `storage/app/private/roomtype-v2-tests/f921281ef13d/scoped-browser/management-browser-proof.json`, `management-desktop-room-types.png`, `management-mobile-amenities.png`, `management-mobile-customer-amenities.png`.

Giới hạn: kiểm tra tích hợp và Chrome thực hiện trên dữ liệu synthetic; không upload/xóa hoặc thay đổi tiện nghi trên dữ liệu nghiệp vụ thật. Không xác minh lại toàn bộ hệ thống thanh toán/đăng nhập hoặc mọi trình duyệt. Không cần migration cho các sửa đổi này. Đã tắt PHP/Chrome/MySQL thử nghiệm, giữ evidence và dữ liệu thử nghiệm để review.
