# AUREL — Loại bỏ actor Staff khỏi source đang hoạt động

Ngày thực hiện: 11/10/2026. Hai actor nghiệp vụ còn lại là Admin và Customer. Người dùng đã xác nhận Admin đăng nhập thành công trên trình duyệt trước lần dọn source này.

## Rà soát database trước thay đổi

- Có 1 Admin và 1 token Admin đang hoạt động.
- Bảng `staff` có 0 bản ghi; không có token mang `tokenable_type = App\Models\Staff`.
- Khóa ngoại duy nhất liên quan Staff: `staff.hotel_id → hotel.id`, tên `fk_staff_hotel`, `ON DELETE SET NULL`, `ON UPDATE NO ACTION`.
- Không có bảng khác tham chiếu Staff; không có view, trigger, routine hoặc event SQL dùng Staff.
- Các truy vấn kiểm tra chạy trong transaction READ ONLY. Không dùng token thật để thử API và không thay đổi tài khoản, token hoặc lịch sử migration.

## Source đã loại bỏ hoặc cập nhật

File xóa:

- `app/Models/Staff.php`: Model không có caller trong runtime. Xóa cùng logic role `staff/manager/superadmin`, `isSuperAdmin()` và quan hệ Staff → Hotel của Model này.

File chỉnh sửa:

| File | Thay đổi |
| --- | --- |
| `resources/views/admin/login.blade.php` | Bỏ toàn bộ bộ chọn vai trò, lựa chọn Nhân viên, CSS `role-*`, `roleGrid`, hàm `selectRole()`; nội dung Login chỉ dành cho Admin. Bỏ đoạn dọn khóa cache đã ngừng sử dụng. |
| `resources/views/admin/layouts/admin.blade.php` | Đổi reader hiển thị tài khoản sang `js-admin-name` / `js-admin-role`; bỏ các tham chiếu `admin_staff`. Giữ nguyên xác minh Sanctum, trạng thái khóa UI, retry và bảo vệ API. |
| `resources/views/admin/partials/topbar.blade.php` | Đổi selector hiển thị tên/role sang `js-admin-*`. |
| `resources/views/admin/partials/sidebar.blade.php` | Bỏ menu Nhân viên chưa hoạt động; đổi selector sang `js-admin-*`. Giữ tài khoản Admin, logout và các menu nghiệp vụ. |
| `ARCHITECTURE.md` | Sơ đồ use case có Admin và Customer; truy cập ẩn danh là trạng thái Customer, cron/payment là hạ tầng. Bỏ Model/quan hệ Staff trong sơ đồ active; thể hiện Customer.role và kiểu trả về Customer của checkAdmin. |
| `README-VI.md` | Mô tả hai actor, Model Customer dùng chung, guard/token Admin, bỏ mô tả guard Staff lỗi thời; ghi rõ phần schema chờ xử lý riêng. |
| `docs/admin-authentication-and-staff-review.md` | Đánh dấu báo cáo trước là lịch sử, dẫn sang báo cáo hiện tại. |
| `tests/Feature/AdminAuthenticationTest.php` | Thêm kiểm thử Customer Register/Login/Logout và giữ token Admin; kiểm tra Blade không còn role picker, cache hoặc selector cũ. |
| `tests/Frontend/admin-authentication.test.cjs` | Bỏ fixture cache cũ; kiểm tra hiển thị tên, role, avatar qua selector thật trong topbar/sidebar. |

Composer autoload được cập nhật bằng `composer dump-autoload --no-scripts`; không cài/cập nhật dependency hoặc chạy script Laravel. Đây là artifact sinh tự động trong `vendor`, không phải thay đổi cấu hình Authentication.

Autoload không còn ánh xạ Model Staff và `class_exists('App\\Models\\Staff')` trả về false. Composer có cảnh báo PSR-4 về `Webcontroller.php` và một số Service cũ khác; các file đó được giữ nguyên ngoài phạm vi Staff. WebController, Customer, AuthAdminController và BookingService vẫn được kiểm tra tải thành công trên môi trường hiện tại; API khách sạn trả HTTP 200.

Không tìm thấy route, controller, middleware, policy, service hoặc guard riêng của Staff đang hoạt động. `AdminMiddleware` là bảo vệ Admin hiện tại và được giữ nguyên. Controllers, Services, các route nghiệp vụ và Model Customer/Hotel/Booking không bị chỉnh sửa trong lần này.

## Phần còn tồn tại và lý do

| Phần | Lý do giữ |
| --- | --- |
| Bảng `staff`, index và FK `fk_staff_hotel` | Xóa schema cần phê duyệt riêng. Chưa tạo hoặc chạy migration xóa bảng. |
| `2026_04_02_013436_create_staff_table.php` | Migration lịch sử, giữ nguyên theo yêu cầu. |
| `2026_04_02_013439_add_foreign_keys_to_staff_table.php` | Migration lịch sử khóa ngoại, giữ nguyên theo yêu cầu. |
| Comment nhắc Staff trong migration tạo Booking/BookingRoom | Không sửa migration lịch sử; các bảng/cột vẫn phục vụ nghiệp vụ Admin/Customer/system. |
| Role `staff/manager/superadmin` trong regression test | Dữ liệu đầu vào không hợp lệ cần bị từ chối; không khai báo actor, Model hoặc quyền Staff. |
| Các assertion kiểm tra không có selector/cache Staff cũ | Bảo vệ việc dọn source, không phải logic runtime. |
| Báo cáo kiểm tra trước và ghi chú về schema trong tài liệu | Lưu dấu vết lịch sử và mô tả phần database chưa được phép loại bỏ. Sơ đồ kiến trúc đang hoạt động không có Staff. |
| “Nhân viên” trong email OTP và persona chatbot | Văn bản thông thường, không tham chiếu Model/bảng/guard Staff; giữ nguyên nội dung và module. |

Runtime không còn đọc, ghi hoặc dọn `admin_staff`; chỉ dùng `admin_account`. Khóa không sử dụng còn lưu trong một browser cũ, nếu có, không có tác dụng xác thực. Lần Login Admin đã sửa trước đây đã dọn khóa cũ; không cần xóa `admin_token` hay yêu cầu đăng nhập lại để dọn source lần này.

## Kiểm thử

Backend: **17 test, 178 assertion đạt**, gồm đăng nhập/đăng xuất Admin, role/token boundary, Customer Register/Login/Logout, bảo toàn phiên còn lại, Dashboard qua route/Sanctum/middleware, production Blade và dọn giao diện.

```powershell
php -d extension=pdo_sqlite vendor/bin/phpunit -c phpunit.admin-auth.xml
```

Các tài khoản, token và bảng fixture chỉ tồn tại trong SQLite `:memory:`. Không chạy migration/seeder. Dữ liệu nghiệp vụ Dashboard được giả lập; backend xác thực và middleware chạy thật.

JavaScript: **17 test đạt**, gồm giữ UI ẩn khi chưa xác minh, lỗi mạng/401/403/429/500, retry, bảo toàn trạng thái Customer, login/logout, API chờ xác minh và hiển thị tài khoản qua selector mới.

```powershell
node --test tests/Frontend/admin-authentication.test.cjs
```

Kiểm tra route: **27 API Admin được bảo vệ**, Login vẫn public. Blade cache và đối chiếu source không còn tham chiếu Staff trong `app`, `routes`, `config`, `resources` được thực hiện riêng. Kiểm thử không dùng hoặc thu hồi token Admin đang hoạt động của người dùng.

Kiểm tra HTTP sau thay đổi: `/admin/login`, `/admin`, `/login`, `/register`, `/api/hotels` trả 200. Các trang Admin không còn role picker, selector hay cache Staff cũ. `/api/admin/me`, `/api/admin/dashboard`, `/api/admin/locations` không có token trả 401 JSON, không có HTTP 302.

Đối chiếu fingerprint trước/sau xác nhận ID, email, mật khẩu và role Admin không đổi; các token hiện có không bị thay đổi/thu hồi; lịch sử migration và FK Staff giữ nguyên. Cuối kiểm tra vẫn có 1 Admin, 1 token Admin, 0 bản ghi Staff và 0 token Staff. Các thay đổi chưa commit ngoài phạm vi được đối chiếu hash và giữ nguyên.

Booking, Payment, schema khách sạn và Home/Hotel Detail được bảo toàn bằng việc giữ nguyên source ngoài phạm vi và không thực hiện thao tác ghi trên database chính. Không tuyên bố đã chạy đầy đủ bộ test nghiệp vụ hoặc đăng nhập lại bằng tài khoản thật trong lần này.

## Phương án database cần phê duyệt riêng

Đề xuất một migration mới, ví dụ `2026_10_11_XXXXXX_drop_unused_staff_table.php`. Không sửa/xóa migration lịch sử. Chưa tạo file migration thực thi trong lần dọn source này.

Trước khi chạy trên đúng database triển khai:

1. Sao lưu database/schema; xác minh lại Staff có 0 bản ghi và 0 token đa hình. Nếu số lượng khác 0, dừng để người dùng quyết định xử lý dữ liệu.
2. Rà lại incoming FK, view, trigger, routine, event. Nếu có phụ thuộc mới, dừng thay vì cascade hoặc sửa bảng nghiệp vụ.
3. Phần `up()` kiểm tra lại các điều kiện an toàn; nếu bảng tồn tại và rỗng, bỏ FK `fk_staff_hotel`, rồi bỏ bảng `staff`. Không tác động Hotel, Customer, Booking, Payment hoặc token Admin/Customer.
4. Phần `down()` tái tạo bảng rỗng, index và FK theo schema lịch sử; không khôi phục dữ liệu đã xóa hoặc bật lại actor Staff trong source. MySQL DDL không có rollback transaction như DML, nên bản sao lưu và phương án phục hồi phải sẵn sàng.
5. Kiểm thử migration mới ở database tách biệt, cả nâng cấp schema hiện có và tạo schema mới. Chỉ chạy trên database chính sau khi được phê duyệt riêng.

Tác động dự kiến: chỉ loại bỏ một bảng không được runtime sử dụng, các index của bảng và một FK hướng tới Hotel. Admin Authentication và hai actor không cần đổi Model, guard, email, mật khẩu, ID hoặc role.

Không chạy migration/seeder, không thay dữ liệu tài khoản, không commit/push trong lần thực hiện này.
