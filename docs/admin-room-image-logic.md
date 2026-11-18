# AUREL — làm rõ ảnh đại diện và ảnh phòng

Ngày 13/10/2026. Phạm vi theo phản hồi: làm rõ hai vai trò ảnh trong form sửa phòng, giữ contract/storage/backend ảnh hiện có.

- **Ảnh đại diện**: một ảnh trong bộ ảnh phòng, dùng để hiển thị đầu tiên. Đổi từ ảnh đã có bằng “Chọn đại diện”, hoặc tải một file mới bằng “Đổi ảnh đại diện”. File mới được thêm vào bộ ảnh và đánh dấu làm đại diện; các ảnh cũ giữ nguyên.
- **Ảnh phòng**: toàn bộ bộ ảnh chi tiết, gồm cả ảnh đại diện. Nút “Tải ảnh lên” và ô “+ Thêm ảnh” cho chọn nhiều file; thêm ảnh giữ đại diện đang chọn. Có giải thích ngay trong form.
- Hai bộ chọn file riêng: cover chọn một file, gallery chọn nhiều file. Hủy picker hoặc file cover không hợp lệ không đổi cover. Bỏ cover mới chưa lưu khôi phục cover đã chọn trước đó, kể cả trước đó người dùng đã chọn một cover khác từ ảnh cũ.
- Gỡ/hoàn tác ảnh cũ vẫn chỉ thay lựa chọn tạm; cover bị gỡ được preview theo fallback hiện có. Hủy form không upload hoặc xóa file.
- Modal quản lý phòng chờ tải danh sách, yêu cầu chọn đúng RoomType để sửa hoặc bấm “Thêm loại phòng mới”. Không tự mở form tạo mới khi chưa biết danh sách. Nếu Hotel chưa có Type, form thêm mới được mở sau khi API xác nhận danh sách rỗng.
- Lỗi tải danh sách/gallery hiển thị lỗi và tải lại; không giả thành “chưa có ảnh”, không cho lưu khi chưa xác định RoomType. AbortController + số phiên vẫn chặn response cũ; kiểm tra Hotel ID trong dữ liệu trả về trước khi cho sửa.

File ứng dụng sửa: `resources/views/admin/partials/image-editor.blade.php`, `image-editor-assets.blade.php`, `room-type-modal.blade.php`, `room-type-modal-script.blade.php`, `resources/views/prepared-v2/admin-room-types.blade.php`.

Kiểm tra: **12 test logic PASS** (`tests/Frontend/admin-room-image-logic.test.cjs`), **63 kiểm tra Chrome/HTTP/DB thử nghiệm PASS**, **0 uncaught errors**, desktop/mobile. Có kiểm tra lưu/mở lại thật trên DB/storage synthetic; native picker, lỗi lưu/tải, Hủy, cập nhật cover/gallery, restore cover trước và ownership/response muộn. Không chạy lại suite backend không bị sửa hoặc toàn bộ các phase.

Harness browser/staging được cập nhật để dùng ảnh mẫu từ artifact private vì các ảnh demo public đã bị gỡ trước lượt này. Không khôi phục hoặc sửa các ảnh public đã gỡ. MySQL owned instance `240bc00bb294`, port `61738`; bootstrap/identity/grants guard cũ, Checkout tắt, storage/profile private. Evidence `storage/app/private/roomtype-v2-tests/240bc00bb294/admin-images-browser-results.json` và screenshot, đã xem trực tiếp.

Backend ảnh, API, validation, `.env`, cấu hình V2 và luồng booking/payment giữ hash baseline. Không mở V2 production hay kết nối DB chính. Kiểm tra thực tế chỉ trên môi trường synthetic; nếu V2 chưa được triển khai ở môi trường thường, form báo chưa sẵn sàng và giữ khóa ghi theo guard hiện có.

Đã đóng đúng Chrome/PHP và MySQL owned, kiểm tra các listener thử nghiệm đã đóng; giữ datadir và evidence để review.
