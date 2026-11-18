# AUREL — cải tiến quản lý ảnh Admin

Hoàn tất ngày 12/10/2026 từ code ảnh Admin đã có. Chỉ sửa Admin, backend ảnh và các kiểm tra liên quan; không sửa giao diện khách hàng, migration, cấu hình/provider V2 production hoặc booking/payment.

## Giao diện và hành vi

- Hotel thêm/sửa có hai tab **Thông tin / Hình ảnh**, giữ thông tin, file mới, lựa chọn đại diện và ảnh đánh dấu xóa khi chuyển tab. Tab hỗ trợ bàn phím Arrow/Home/End.
- Preview đại diện nằm phía trên, tỷ lệ 16:9, tối đa 360px trên desktop, có nút **Thay ảnh**. Thay ảnh chọn cover mới và thêm vào gallery; không tự gỡ ảnh cũ.
- Một gallery chung, desktop 4 cột/mobile 2 cột; thumbnail 4:3, `object-fit: cover`. Badge **Đại diện / Chưa lưu / Sẽ xóa**, ô **+ Thêm ảnh**, input file mặc định ẩn. Không hiển thị ID kỹ thuật, tên file dạng dòng thông tin hoặc khung “Chưa chọn ảnh mới”.
- Ảnh mới có thể bỏ riêng từng file. Ảnh cũ được đánh dấu xóa, làm mờ và hoàn tác. Xóa cover preview ảnh còn lại theo quy tắc fallback hiện có; hoàn tác khôi phục lựa chọn trước đó nếu chưa chọn cover khác.
- Header/tab/footer luôn nhìn thấy; chỉ `.modal-body` cuộn. Khóa/khôi phục cuộn nền, đóng qua Hủy/X/Escape/nền ngoài, khóa thao tác đóng/chuyển đối tượng khi đang lưu.
- Modal **Phòng** từ danh sách Hotel trước đây chưa có ảnh nay dùng chung editor cho **thêm và sửa**. Có danh sách chọn RoomType của đúng Hotel; các field theo V2 (`total_rooms`, giá DECIMAL, status), không tạo Room vật lý. Trang RoomType riêng giữ form đã có và tự nhận giao diện editor chung mới.
- Chọn nhiều ảnh trực tiếp; không thêm dò trùng, chặn trùng hoặc cảnh báo trùng. Test hai file có cùng nội dung vẫn lưu đủ hai liên kết.
- Chỉ ghi khi Lưu. Hủy, đóng, đổi tab hoặc đổi đối tượng không upload/xóa file. Lỗi lưu giữ thông tin/lựa chọn; lưu thành công đọc lại gallery đúng đối tượng. Guard response muộn, phiên form và ownership giữ nguyên.

## Backend tối thiểu

- Tạo Hotel cũng dùng transaction/upload cleanup của `AdminAccommodationImageService` hiện có, giống sửa Hotel. Tái sử dụng validation JPG/PNG/WebP, 2 MB/ảnh, 20 ảnh mới/lần và disk `public`.
- Không đổi contract `images[]`, `image_changes`, `image_url`, `is_primary`, `hotel_image`, `room_type_image` hoặc các API prepared V2 hiện có.
- Modal Phòng gửi `inventory_model=v2`. Root `getRoomTypes/createRoomType` chỉ chuyển yêu cầu có marker này sang service V2 hiện có. Các request legacy không có marker giữ nguyên contract cũ. Thêm hai route Admin guarded cho PUT RoomType và GET gallery, cùng prepared controller hiện có.
- **Giữ V2 connection/deployment guard**: các endpoint V2 chỉ chạy trong owned test environment hoặc deployment đã có manifest hợp lệ. Không đăng ký provider hay kích hoạt V2 production. Khi production V2 còn đóng, các thao tác V2 vẫn bị guard từ chối; đây không phải lần bật production.
- Gallery khách hàng đã đọc `room_type_image`; test tạo/sửa ảnh rồi đọc đúng contract public. Không chỉnh controller/view/layout khách hàng trong application source.
- Gỡ ảnh chỉ gỡ liên kết; giữ file đã lưu, shared asset và RoomImage legacy. Không thay kiểm tra Admin role/session ability hoặc quan hệ Hotel–RoomType–Image.

## File của lượt này

9 file sửa:

- `resources/views/admin/hotels.blade.php`
- `resources/views/prepared-v2/admin-hotels.blade.php`
- `resources/views/admin/partials/image-editor.blade.php`
- `resources/views/admin/partials/image-editor-assets.blade.php`
- `app/Http/Controllers/AdminController.php`
- `routes/api.php` — chỉ phần route Admin RoomType
- `tests/Feature/AdminAccommodationImagesTest.php`
- `tests/Frontend/admin-images.browser.cjs`
- `tests/Support/AdminImagesFixture.php`

5 file thêm:

- `resources/views/admin/partials/accommodation-modal-styles.blade.php`
- `resources/views/admin/partials/accommodation-modal-script.blade.php`
- `resources/views/admin/partials/room-type-modal.blade.php`
- `resources/views/admin/partials/room-type-modal-script.blade.php`
- `docs/admin-image-management-refinement.md`

## Kiểm tra

Tái sử dụng owned MySQL/fixtures/guard và image-only staging harness. Instance riêng **c86a70c7b1cb**, MySQL port **56014**; credentials, public disk, uploads và browser profile trong private runtime. Bootstrap kiểm descriptor/host/port/schema allowlist/identity/grants trước PDO; không dùng DB chính. Router chỉ có overlay Admin ảnh/RoomType và public RoomType read phục vụ kiểm tra contract; giữ Checkout tắt.

| Kiểm tra | Kết quả thực tế |
|---|---|
| PHPUnit ảnh Admin | **8 tests / 87 assertions PASS**: các case ảnh/ownership/role/validation/rollback cũ; tạo Hotel nhiều ảnh/cover; tạo RoomType V2 có hai file giống nội dung; root quick-form delegation; giữ Room vật lý và đọc ảnh qua public contract |
| Regression Hotel amenities/nullable/KPI | **1 test / 11 assertions PASS** |
| Regression Admin create/status, gallery legacy, role/ability | **3 tests / 27 assertions PASS** |
| Chrome + HTTP + DB/storage test | **54 checks PASS, 0 uncaught errors**: thêm/sửa Hotel và RoomType, tab giữ dữ liệu, picker từ ô thêm ảnh, cover/thay cover, xóa/hoàn tác, Hủy không ghi, file sai, lỗi lưu giữ lựa chọn, lưu/mở lại, ảnh đi vào contract public, response muộn/tải lại, multipart boundary, desktop/mobile và body-only scrolling |
| Syntax/whitespace | PHP lint, CJS syntax và `git diff --check` PASS; các Blade/script mới render/chạy trong cả trang Hotel root/prepared và RoomType |

Evidence: `storage/app/private/roomtype-v2-tests/c86a70c7b1cb/admin-images-browser-results.json`; sáu screenshot `admin-images-{hotel,type,quick-room}-{desktop,mobile}.png`, đã xem trực tiếp. Dữ liệu/token thử nghiệm không đưa vào Git. Không chạy lại toàn bộ các phase; không build lại asset khách hàng đang được làm ở lượt khác vì thay đổi CSS/JS ở đây nằm trong partial Blade Admin và dùng build hiện có.

Đã đóng đúng Chrome/PHP thử nghiệm và graceful shutdown MySQL owned; xác nhận các listener thử nghiệm đã đóng, giữ datadir/artifact. 13 file cấu hình/auth Admin/booking/payment/dependency được bảo vệ và 72 migration có sẵn giữ hash baseline.

Đối chiếu baseline không mất file có sẵn. Các file khách hàng `resources/views/auth/Login.blade.php`, `resources/views/Layout.blade.php`, `resources/views/partials/layout/header.blade.php`, `resources/views/partials/layout/header-script.blade.php` đổi hash trong lúc làm việc ở lượt khác. Migration `2026_10_12_000009_create_password_reset_tokens_table.php` cũng xuất hiện ở lượt khác. Công việc ảnh Admin không ghi/hoàn tác/chạy các file này. Các thay đổi trước đó giữ nguyên ngoài những phần Admin được yêu cầu cải tiến.

Giới hạn: xác nhận trên môi trường synthetic độc lập, chưa nghiệm thu DB/hosting thật. Giới hạn tổng multipart của PHP/web server vẫn áp dụng độc lập và không sửa cấu hình máy chủ. V2 production tiếp tục đóng theo yêu cầu. Dừng ở phần quản lý ảnh.
