# Danh mục tiện nghi khách sạn

Tái sử dụng `HotelAmenity`, bảng `hotel_amenity`, `HotelAmenityCatalog` và cơ chế xác thực API Admin hiện có. Không thêm bảng hoặc model danh mục khác.

- Bản ghi danh mục có `hotel_id = NULL` và `catalog_identity` là SHA-256 của tên đã chuẩn hóa.
- Bản ghi gán cho khách sạn giữ `hotel_id` như trước; quan hệ `Hotel::amenities()` và dữ liệu trả về khách sạn không thay đổi.
- Unique index của `catalog_identity` ngăn trùng khi có nhiều yêu cầu đồng thời. Chuẩn hóa gộp khoảng trắng, bỏ khoảng trắng đầu/cuối, không phân biệt chữ hoa/thường và giữ các alias có sẵn như `airport`/`airport_shuttle`.

`GET /api/admin/amenities` tải danh mục. `POST /api/admin/amenities` nhận `{ "name": "Vườn trên mái" }`, trả 201 khi lưu thành công, 422 khi tên rỗng/quá dài/trùng và 503 khi database không ghi được. Các endpoint chỉ dành cho Admin. API cập nhật khách sạn tiếp tục nhận `amenities` như trước; việc đồng bộ chỉ xóa các liên kết không được chọn của chính khách sạn đang sửa.

Nút **Thêm tiện nghi** lưu danh mục ngay, sau đó chọn tiện nghi cho bản nháp hiện tại. **Lưu** áp dụng lựa chọn cho khách sạn. Hủy hoặc bỏ chọn không xóa danh mục. Form tải lại danh mục mỗi lần mở; chờ yêu cầu tạo đang chạy trước khi lưu và không áp dụng kết quả cho form đã đóng/thay thế.

Migration chỉ thay đổi bảng tiện nghi và bổ sung các tên có sẵn vào danh mục; giữ nguyên ID, khách sạn, tên và icon của mọi liên kết cũ:

```powershell
php artisan migrate --path=database/migrations/2026_10_17_000002_persist_hotel_amenity_catalog.php --force
```

Kiểm thử trên MySQL biệt lập bằng harness hiện có:

```powershell
$env:AUREL_ROOMTYPE_V2_TEST_SETTINGS='<owned-runtime>/test-environment.json'
php vendor/phpunit/phpunit/phpunit -c phpunit.amenities.xml
node tests/Frontend/hotel-amenity-catalog.browser.cjs '<owned-runtime>/scoped-browser/session.json' 'http://127.0.0.1:<port>' '<chrome-debug-port>'
```

Các ca bao gồm tạo và tải lại danh mục, tái sử dụng ở khách sạn khác, chuẩn hóa/chống trùng ở API và database, validation, lỗi ghi database, bảo toàn liên kết khác, tìm kiếm, bỏ chọn, lỗi tải và thử lại, nhấn Lưu khi đang tạo, mở lại modal và hủy form trong lúc chờ. Test không dùng database ứng dụng.
