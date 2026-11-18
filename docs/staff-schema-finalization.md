# AUREL — Hoàn tất migration loại bỏ Staff

Ngày: 11/10/2026, múi giờ Asia/Bangkok.

## Database đích và kiểm tra trước khi ghi

- Connection Laravel: `mysql`, cũng là connection mặc định.
- Database thực tế từ `SELECT DATABASE()`: `hotel_booking`.
- Host/port: `127.0.0.1:3306`; MySQL 8.0.30; FK checks bật.
- Bảng `staff` không tồn tại, đúng hiện trạng đã được người dùng chấp nhận ở giai đoạn trước.
- Trước khi thực hiện: 65 migration Ran, chỉ `2026_10_11_000001_drop_unused_staff_table` Pending.
- Nội dung migration được giữ nguyên. Nhánh up() khi Staff không tồn tại chỉ kiểm tra rồi return, không chạy DDL hay cập nhật nghiệp vụ.
- Fingerprint dữ liệu, Admin, token và migration lịch sử khớp báo cáo trước.

## Bản sao lưu đã xác minh

- Thư mục riêng, ngoài public và được Git bỏ qua:
  `storage/app/private/backups/staff-finalize-2026-10-11-4c4b41d3dd80/`.
- File: `hotel_booking-before-staff-finalize.sql`, 110375 byte.
- SHA-256: `b590737c34456a1609b1c04e5b0ee7ddbbc9f08426407c68945f2af00b11c189`.
- Backup dùng mysqldump single-transaction, không khóa bảng, bao gồm dữ liệu/schema, trigger/routine/event nếu có.
- Đã khôi phục vào database tạm mới và đối chiếu đủ 25 bảng: tất cả dữ liệu, cột, kiểu, charset/collation, default, index, constraint, FK, check constraint và AUTO_INCREMENT thực tế đều khớp.
- SHOW CREATE TABLE sau restore có phần CHARACTER SET được ghi tường minh hơn. Metadata vật lý không khác; thống kê AUTO_INCREMENT được đọc với information_schema_stats_expiry=0 để tránh cache.
- Database tạm đã được xóa; database chính không thay đổi trong quá trình xác minh backup.
- Backup có dữ liệu nhạy cảm nên không đưa nội dung SQL, tài khoản, password hash hoặc token vào báo cáo.

## Lệnh thực hiện và kết quả

```powershell
php artisan migrate --database=mysql --path=database/migrations/2026_10_11_000001_drop_unused_staff_table.php
```

Exit code 0; chỉ migration được chỉ định chạy, kết quả DONE (12ms).

| Hạng mục | Trước | Sau |
| --- | --- | --- |
| Migration Staff mới | Pending | Ran, batch 2 |
| Tổng dòng migration | 65 | 66 |
| Migration Pending khác | 0 | 0 |
| Bảng staff | Không tồn tại | Không tồn tại |
| Hai migration lịch sử Staff | Ran, batch 1 | Giữ nguyên |

Không sửa/xóa migration lịch sử, không khôi phục bảng Staff, không chạy rollback/fresh/refresh/seeder hoặc migrate toàn bộ.

## Đối chiếu sau thực hiện

- So sánh fingerprint dữ liệu và SHOW CREATE TABLE của toàn bộ 25 bảng.
- Chỉ `migrations` thay đổi: thêm đúng một dòng cho migration mới. Tất cả dòng migration cũ vẫn khớp.
- Mọi bảng nghiệp vụ, Customer, tài khoản Admin và token giữ nguyên cả dữ liệu/schema.
- Customer: 1; Hotel: 21; Room: 336; Booking: 0; Payment: 0; token: 1.
- Không có database kiểm thử do lần xác minh này tạo còn sót lại.

## Authentication và API

- Bộ PHPUnit hiện có: **17 test, 178 assertion đạt**, sử dụng SQLite RAM. Bao gồm Admin Login/Logout, Customer Register/Login/Logout, ranh giới role/token, bảo vệ API và render Blade.
- Bộ JavaScript hiện có: **17 test đạt**, DOM/network giả lập, bao gồm xác minh /me, trạng thái Dashboard, lỗi mạng/token, đăng nhập và đăng xuất.
- Smoke test bằng Laravel HTTP Kernel với guard Sanctum, middleware và controller thật: **30 request đạt** trên database MySQL mới khôi phục từ backup.
- Connection chính bị chặn trong tiến trình smoke test; chỉ tạo Admin/Customer giả trong database tạm, không sử dụng mật khẩu tài khoản thật.
- GET Admin `me`, `dashboard`, `hotels`, `bookings`, `customers`, `revenue`: token Admin trả 200; thiếu token trả 401; token Customer trả 403.
- Token không hợp lệ hoặc đã logout trả 401. Customer logout không làm mất phiên Admin.
- Customer Register trả 201, role=user kể cả input yêu cầu admin; Login/Logout/Profile trả kết quả đúng.
- Kiểm tra route xác nhận cả 27 Admin API cần bảo vệ vẫn có auth:admin và AdminMiddleware.
- Không thử lại đăng nhập thủ công trên trình duyệt bằng tài khoản thật trong lần này.

## Source code và giới hạn

92 file thay đổi/untracked có sẵn trước task đều giữ nguyên fingerprint. Không chỉnh UI, Authentication, module nghiệp vụ, migration mới, migration lịch sử hoặc .env; không commit/push. Chỉ thêm báo cáo này và các artifact kiểm tra/sao lưu trong thư mục storage riêng bị Git bỏ qua.

Không còn lỗi được xác nhận trong phạm vi kiểm tra này. Báo cáo chuẩn bị migration trước đây ghi trạng thái Pending là trạng thái tại giai đoạn trước; báo cáo này ghi nhận trạng thái đã hoàn tất.

