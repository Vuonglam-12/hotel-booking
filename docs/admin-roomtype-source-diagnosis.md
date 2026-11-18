# Lỗi nguồn RoomType trên Admin — 13/10/2026

## Nguyên nhân và bằng chứng

- Thông báo trong ảnh xuất phát từ `AdminController::getRoomTypes`, qua `GET /api/admin/hotels/1/room-types?inventory_model=v2`. Response là HTTP **503**, code `room_type_v2_unavailable`.
- Chuỗi kiểm tra: `RoomTypeV2Inventory::open('mysql')` → `RoomTypeV2Database::connection` → `RoomTypeV2TestDatabase::connection` khi không có production manifest. Log thực tế ghi `Missing owned isolated test settings.`. Guard dừng trước khi mở connection V2; đây là kiểm tra môi trường triển khai, không phải thiếu quyền tài khoản Admin.
- Trước bản sửa này, trang chi tiết Admin và modal RoomType đều hardcode `inventory_model=v2`. Form tạo phòng cũng gửi `total_rooms`, `status` và ảnh theo contract V2. Đối chiếu `git show HEAD:resources/views/admin/hotels.blade.php`: form gốc gửi `room_count`, `floor_start` vào API legacy. Có thay đổi nguồn trong các sửa đổi giao diện đang có ở workspace.
- Runtime kiểm tra: `APP_ENV=local`, MySQL `127.0.0.1:3306`, database `hotel_booking`, config cache tắt. Không có biến `AUREL_ROOMTYPE_V2_TEST_SETTINGS` trong tiến trình hiện tại; `production_manifest=null`, `prepared_routes=false`, `integration_complete=false`, `final_integration=false`, `delivery_safety=false`; không đăng ký provider V2.
- Database hiện có 6 loại phòng dùng chung, 336 phòng và 336 liên kết ảnh phòng. `room_type` chưa có `hotel_id`, `total_rooms`, `status`; chưa có `room_type_image` và `room_type_legacy_map`. Các migration expand/image/map V2 chưa được áp dụng. Những artifact kiểm thử V2 trong private storage không tự cấp quyền cho runtime này.
- MEANDER Sai Gon có ID 1. Legacy đọc được Standard **5**, Superior **4**, Deluxe **3**, Suite **2**, Family **2**: tổng **16 phòng**.

## Bản sửa

- Hai giao diện Admin gọi `inventory_model=auto`. Backend chọn V2 khi có cấu hình prepared routes hoặc production manifest; các kiểm tra V2 hiện có vẫn áp dụng. Nếu V2 đã được chọn nhưng guard thất bại, response vẫn là 503, không tự chuyển nguồn để che lỗi cấu hình.
- Khi V2 chưa được cấu hình, API trả envelope `inventory_model=legacy` và danh sách chuẩn hóa từ RoomType/Room cũ, có số phòng và ảnh đại diện lấy từ phòng thuộc đúng khách sạn. `hotel_id` trong DTO là phạm vi truy vấn, không biến loại phòng dùng chung thành bản ghi sở hữu riêng trong database.
- Request legacy không có tham số vẫn giữ response array với `rooms_count`, bảo toàn consumer cũ.
- Tạo loại phòng legacy dùng `room_count` và `floor_start`, giữ transaction tạo RoomType cùng các Room có số phòng riêng. Không gửi `total_rooms`, `status` hoặc ảnh V2; backend từ chối những trường này trên luồng legacy thay vì âm thầm bỏ qua.
- Giao diện legacy hỗ trợ **xem và tạo**. Sửa loại phòng, chỉnh tồn/trạng thái theo loại và ảnh riêng theo loại chưa được mở: type cũ dùng chung giữa khách sạn, trạng thái và ảnh thuộc từng Room. Giao diện hiển thị giới hạn này. Luồng chỉnh sửa/ảnh V2 được giữ khi backend chọn V2.
- Thao tác với database chính trong lần kiểm tra này chỉ đọc. Không sửa `.env`, bật provider, chạy migration/backfill/remap hoặc cutover.

## Xác minh

- `phpunit.admin-roomtype.xml`: **6 tests / 40 assertions PASS**, trên server MySQL cô lập, dùng schema legacy thực sự chưa có cột/bảng V2. Bao gồm phạm vi khách sạn, contract array cũ, tạo phòng, từ chối payload sai nguồn, guard V2 và xác thực Admin.
- `phpunit.admin-images.xml`: **8 tests / 89 assertions PASS**, trên cùng server cô lập. Test tài sản dùng chung tự tạo ảnh fixture trong fake storage, không phụ thuộc ảnh mẫu đã bị xóa khỏi public. Có kiểm tra catalog tự chọn V2 và các thao tác ảnh V2.
- JavaScript: **56 tests PASS** (`admin-room-image-logic`: 19; `admin-hotel-roomtype-source`: 4; `hotel-booking-phase1`: 16; `admin-authentication`: 17). Harness Phase One đã bổ sung các dependency của modal hiện tại.
- Trên cấu hình/dữ liệu chính hiện tại, gọi controller với Admin resolver trong tiến trình kiểm tra: `auto` → **200 / legacy / 5 types / 16 rooms**; `v2` → **503 / room_type_v2_unavailable**. Không tạo hoặc thay đổi dữ liệu chính để kiểm thử thao tác ghi.
- `http://localhost:8000/admin/hotels/1` trả **200** và HTML đã có request `inventory_model=auto`. Blade render và PHP syntax checks đạt. Luồng UI được kiểm thử bằng harness JavaScript; chưa chạy nghiệm thu trực tiếp trong trình duyệt với phiên Admin đang mở của người dùng.
