# AUREL — Chuẩn bị migration loại bỏ schema Staff

Ngày: 11/10/2026. Migration mới đã chuẩn bị; không được áp dụng lên database chính khi chưa có phê duyệt tiếp theo.

## Sự cố kiểm thử và quyết định của người dùng

Trong test chạy qua Laravel migrator, cấu hình connection kiểm thử được sao chép từ `mysql` nhưng giữ nhầm thuộc tính `name = mysql`. Migrator dùng `Connection::getName()` để đổi connection mặc định trước khi chạy `up()`. Kết quả: phần DDL đã xóa bảng Staff rỗng trên database chính, trong khi migration chỉ được ghi vào ledger của database kiểm thử.

Đây là thao tác ngoài phạm vi được cho phép. Sau khi xác minh, agent đã báo sự cố, dừng ghi database chính và chuẩn bị SQL khôi phục chính xác schema ban đầu. Người dùng chọn: **“Giữ nguyên hiện trạng, không ghi thêm database chính”**. SQL khôi phục không được thực thi.

Hiện trạng được giữ theo quyết định đó:

- Bảng `staff` không còn trên database chính; FK thuộc bảng cũng đã bị loại bỏ cùng bảng.
- Hai migration lịch sử vẫn có trạng thái Ran, batch 1.
- Migration mới vẫn **Pending**, chưa được ghi vào bảng `migrations` của database chính.
- Đối chiếu fingerprint xác nhận dữ liệu Customer, Admin, Booking, Hotel, Room, Payment, token và lịch sử migration không thay đổi.
- Schema trước test được giữ trong `docs/staff-schema-recovery.sql` chỉ để tham khảo/lưu dấu vết; không được tự chạy.

Cơ chế cách ly test đã sửa: đặt đúng tên cho từng connection, kiểm tra cả tên/default connection/database, và chặn hoàn toàn alias `mysql` trong tiến trình test trước khi chạy bất kỳ migration nào. Test riêng xác minh không thể lấy PDO của connection chính.

## Migration lịch sử và schema trước sự cố

| Migration | Trạng thái | Nội dung |
| --- | --- | --- |
| `2026_04_02_013436_create_staff_table.php` | Ran, batch 1 | Tạo bảng Staff: id INT signed auto-increment, hotel_id INT nullable, name/email VARCHAR(100), phone VARCHAR(20) nullable, password_hash VARCHAR(255), role VARCHAR(30) default staff, created_at TIMESTAMP current timestamp. |
| `2026_04_02_013439_add_foreign_keys_to_staff_table.php` | Ran, batch 1 | Thêm FK `fk_staff_hotel`: staff.hotel_id → hotel.id; ON DELETE SET NULL, ON UPDATE NO ACTION. |

Các migration tạo Booking và BookingRoom chỉ có comment nhắc Staff; không tạo FK Staff. Chúng được giữ nguyên cùng tất cả migration lịch sử khác.

Trước test: Staff có 0 bản ghi, 0 token đa hình; chỉ có FK hướng tới Hotel, không có FK tham chiếu ngược kể cả từ schema khác. Không có cột staff_id ở bảng khác hoặc view/trigger/routine/event Staff trong database dự án. Schema dùng MySQL 8.0.30, InnoDB, utf8mb4_unicode_ci. Tất cả 65 migration đã ghi nhận đều đã chạy; không có migration đang chờ trước khi thêm file mới.

## Migration mới

`database/migrations/2026_10_11_000001_drop_unused_staff_table.php`

### up()

Chỉ thực hiện một DDL xóa bảng Staff sau khi kiểm tra:

- Driver MySQL và foreign_key_checks vẫn bật.
- Bảng Staff rỗng, không có token Staff hoặc alias Staff cũ.
- Không có incoming FK, kể cả từ database khác, hoặc cột staff_id ở bảng khác.
- Không có view, routine, event hoặc trigger phụ thuộc; definition không đọc được cũng làm migration dừng.
- Nếu có outgoing FK, chỉ chấp nhận FK lịch sử staff.hotel_id → hotel.id.

FK hướng tới Hotel thuộc bảng Staff nên được loại bỏ cùng một lệnh DROP TABLE. Không cần ALTER bảng Hotel, không tắt FK checks và không xóa constraint/bảng khác. Mọi kiểm tra diễn ra trước DDL.

Nếu Staff đã không tồn tại, up() kết thúc sau các kiểm tra và không tạo/xóa schema khác. Vì hiện trạng chính đang như vậy, lần chạy sau phê duyệt dự kiến chỉ ghi nhận migration vào ledger, không xóa thêm bảng.

### down()

Chỉ tái tạo bảng rỗng với schema, index, comment, default và FK đúng hai migration lịch sử. Không tạo tài khoản, token hoặc dữ liệu giả. Nếu Staff đã tồn tại, không ghi đè. Nếu Hotel thiếu hoặc hotel.id không phải INT signed primary key trên InnoDB, dừng trước khi tạo bảng để tránh cấu trúc khôi phục dở dang.

## Kiểm tra và kiểm thử

- PHP syntax migration và test: đạt.
- Bộ test MySQL tách biệt sau khi sửa cách ly: **19 test, 954 assertion đạt**.
- Test up/down trực tiếp và test Laravel migrate/rollback có --database chỉ định connection fixture và --path chỉ định đúng file mới.
- Down được so sánh bằng SHOW CREATE TABLE với schema tạo từ chính hai migration lịch sử: khớp chính xác, không có dữ liệu được phục hồi.
- Các test chặn bảng có dữ liệu, token FQN/alias, incoming/outgoing FK bất thường, quan hệ staff_id không FK, view/routine/event/trigger và FK checks bị tắt.
- Các test kiểm tra lặp up khi bảng đã mất, không ghi đè bảng khi down, và không tạo bảng dở dang khi Hotel không phù hợp.
- Mỗi test dùng database mới tên `aurel_staff_schema_test_<random>`; chỉ database do test tạo được dọn sau test. Không tái sử dụng hoặc xóa database kiểm thử có sẵn của dự án.
- Không tìm thấy phụ thuộc Staff trong runtime Admin/Customer. Staff còn trong migration lịch sử, migration mới, test từ chối role/cấu trúc cũ và tài liệu lịch sử.
- Không chỉnh Authentication, tài khoản, business code, .env, migration lịch sử hoặc các thay đổi chưa commit đang có; không commit/push.

Lưu ý: kết quả cách ly cuối cùng không thay đổi việc sự cố trước đó đã tác động database chính. Không được diễn đạt rằng database chính chưa từng bị thay đổi.

## Rủi ro và điều kiện trước khi áp dụng

1. MySQL DDL không có rollback transaction như DML. Backup schema/database và chỉ chạy đúng file migration mới.
2. Guard không thay thế việc ngăn ghi đồng thời: dữ liệu hoặc phụ thuộc có thể được thêm sau kiểm tra. Xác nhận không còn writer Staff hoặc thay đổi schema trong khoảng chạy.
3. Down chỉ khôi phục cấu trúc rỗng; không khôi phục dữ liệu hoặc bật lại actor Staff trong source.
4. Cần quyền đọc metadata và quyền DDL phù hợp. Definition không đọc được hoặc phụ thuộc mới sẽ khiến migration dừng để rà soát.
5. Chạy migrate toàn bộ hoặc rollback toàn bộ batch có thể tác động các migration khác; không sử dụng migrate:fresh, migrate:refresh hoặc db:wipe.

## Câu lệnh sau phê duyệt riêng

Kiểm tra trạng thái, chỉ đọc:

```powershell
php artisan migrate:status --database=mysql --path=database/migrations/2026_10_11_000001_drop_unused_staff_table.php
```

Chỉ sau khi người dùng phê duyệt ghi nhận migration trên database chính:

```powershell
php artisan migrate --database=mysql --path=database/migrations/2026_10_11_000001_drop_unused_staff_table.php
```

Chưa chạy câu lệnh áp dụng này trên database chính. Quyết định giữ hiện trạng của người dùng không được coi là phê duyệt chạy migration hoặc ghi thêm ledger.
