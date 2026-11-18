# AUREL — Admin Authentication và rà soát Staff

> Đây là báo cáo lịch sử của giai đoạn sửa Authentication, trước khi loại bỏ actor Staff. Người dùng đã xác nhận Admin đăng nhập thành công trên trình duyệt. Trạng thái source và danh sách còn tồn tại mới nhất nằm trong [báo cáo loại bỏ Staff](staff-removal.md); các ghi nhận bên dưới được giữ làm lịch sử kiểm tra.

Ngày kiểm tra: 11/10/2026. Phạm vi: sửa Authentication; chỉ lập danh sách Staff để xử lý sau.

## Thay đổi đã thực hiện

| Lỗi trước đây | Cách xử lý |
| --- | --- |
| Hai Blade Admin yêu cầu `resources/js/app.js` không tồn tại | Chỉ yêu cầu entry CSS đã có trong Vite manifest. |
| Login lưu `admin_account`, layout đọc `admin_staff` | Dùng `admin_account` xuyên suốt. Chỉ xóa khóa cũ để tránh cache tồn đọng. |
| Lỗi mạng khi xác minh `/me` vẫn mở Dashboard | Toàn bộ giao diện Admin mặc định bị ẩn; chỉ mở sau `/me` thành công và role đúng. |
| Không phân biệt lỗi xác thực với sự cố tạm thời | `401/403` khóa giao diện, xóa phiên Admin và chuyển Login. Mất mạng, `429`, `5xx` hoặc JSON lỗi giữ token, khóa giao diện và có nút thử lại. |
| Một số API Admin chưa kiểm tra role | Toàn bộ 27 API Admin cần bảo vệ dùng `auth:admin` và `AdminMiddleware` hiện có. Login là endpoint public duy nhất. |
| Token Customer có thể được dùng ở khu vực Admin nếu cùng tài khoản | Middleware yêu cầu role `admin`, PersonalAccessToken mang tên `admin_token` và ability `admin`. Phiên web/cookie không thay thế được token này. |
| Login xóa toàn bộ token của tài khoản | Login Admin chỉ thay token Admin; login Customer chỉ thay token không phải Admin. |
| Gọi nhầm endpoint logout có thể xóa token bên còn lại | Logout Admin được middleware kiểm tra loại token. Logout Customer từ chối token Admin và phiên không có PersonalAccessToken. |

Sanctum, Model `Customer`, guard/provider, cấu trúc database và các tài khoản thật được giữ nguyên.

## File thay đổi trong lần sửa này

Production:

- `app/Http/Controllers/AuthAdminController.php`
- `app/Http/Controllers/AuthController.php` — chỉ phạm vi thu hồi token khi login/logout.
- `app/Http/Middleware/AdminMiddleware.php`
- `routes/api.php` — bổ sung middleware cho nhóm Admin.
- `resources/views/admin/login.blade.php`
- `resources/views/admin/layouts/admin.blade.php`

Kiểm thử và báo cáo:

- `phpunit.admin-auth.xml`
- `tests/Feature/AdminAuthenticationTest.php`
- `tests/Frontend/admin-authentication.test.cjs`
- Báo cáo này.

## Kiểm thử tự động

Backend: **16 test, 158 assertion đều đạt**.

```powershell
php -d extension=pdo_sqlite vendor/bin/phpunit -c phpunit.admin-auth.xml
```

Extension SQLite được bật riêng cho tiến trình test; không chỉnh `php.ini` hay `.env`. Các bảng fixture, tài khoản giả và token giả chỉ tồn tại trong SQLite `:memory:`. Không chạy migration, seeder, RefreshDatabase hay kết nối database chính trong bộ test này.

Các trường hợp được kiểm tra: email/mật khẩu đúng và sai; validation; role không phải Admin; token không có/sai/hết hạn/sai ability/sai loại; guard cookie không thay thế Bearer; login/logout hai bên không thu hồi token bên kia; tất cả route Admin có middleware; hai Blade render bằng production manifest.

Test Dashboard chạy route, Sanctum và middleware thật; phần dữ liệu nghiệp vụ của controller được giả lập để không phụ thuộc hoặc thay đổi module nghiệp vụ.

Frontend: **17 test đều đạt**.

```powershell
node --test tests/Frontend/admin-authentication.test.cjs
```

Các test thực thi JavaScript hiện có bằng DOM, localStorage và fetch giả lập. Bao gồm giữ Dashboard ẩn trước xác minh, mất mạng/429/500/JSON lỗi, 401/403, retry, đợi xác minh trước API nghiệp vụ, token hết hạn trong phiên, login/logout và giữ riêng trạng thái Customer.

Kiểm tra HTTP trên ứng dụng local:

- `/admin/login`: HTTP 200, không còn yêu cầu file JS thiếu.
- `/admin`: HTTP 200 với `admin-shell` mặc định ẩn.
- `/api/admin/me`, `/api/admin/dashboard`, `/api/admin/locations` không có token: HTTP 401 JSON, không chuyển hướng HTTP 302.
- `/api/admin/login` dùng email kiểm thử không tồn tại: HTTP 401; payload thiếu trường: HTTP 422. Không dùng tài khoản thật và không cấp token.
- `php artisan route:list --path=api/admin -v`: 28 route, 27 route được bảo vệ.
- `php artisan view:cache`: thành công.

## Kiểm thử thủ công còn cần người dùng

Chưa đăng nhập bằng tài khoản Admin thật hoặc Customer thật; chưa tạo/reset tài khoản. Database chính vẫn có 1 Admin, 0 Staff và 0 token tại thời điểm kiểm tra.

1. Mở `http://hotel-booking.test/admin/login`, nhập email Admin hiện có và mật khẩu trực tiếp trên máy.
2. Xác nhận chuyển tới `/admin`, tên tài khoản hiển thị và Dashboard tải dữ liệu.
3. Đăng xuất, xác nhận quay về Login và truy cập lại `/admin` không mở Dashboard.
4. Nếu cần kiểm tra phiên song song, đăng nhập Customer rồi Admin trên cùng trình duyệt; đăng xuất một bên và xác nhận phiên bên còn lại vẫn hoạt động.
5. Nếu lỗi, chỉ cung cấp mã HTTP và thông báo giao diện. Không gửi mật khẩu, Authorization header hoặc token qua chat.

## Danh sách phụ thuộc Staff

### Runtime và database

| Phần | Vị trí | Trạng thái và xử lý dự kiến |
| --- | --- | --- |
| Model Staff | `app/Models/Staff.php` | `Authenticatable`, `HasApiTokens`; role `superadmin/admin/manager/staff`; quan hệ `belongsTo(Hotel)`. Không được luồng Authentication hiện tại gọi. Có thể loại bỏ sau khi kiểm tra lại. |
| Bảng Staff | `staff` | 0 bản ghi; các index `PRIMARY`, `uq_staff_email`, `idx_staff_hotel`. Chưa thay đổi. |
| Khóa ngoại | `staff.hotel_id → hotel.id`, `fk_staff_hotel` | `ON DELETE SET NULL`, `ON UPDATE NO ACTION`. Không có khóa ngoại từ bảng khác trỏ vào Staff. |
| Migration tạo bảng | `database/migrations/2026_04_02_013436_create_staff_table.php` | Lịch sử schema Staff. Nên giữ lịch sử migration; dùng migration mới để loại bỏ bảng ở bước được phê duyệt sau. |
| Migration khóa ngoại | `database/migrations/2026_04_02_013439_add_foreign_keys_to_staff_table.php` | Lịch sử FK Staff. Không xóa hoặc chạy trong lần sửa này. |
| Token đa hình | `personal_access_tokens.tokenable_type = App\Models\Staff` | 0 token Staff. Không có FK SQL cho quan hệ đa hình; vẫn cần kiểm tra lại trước khi loại bỏ Model. |
| Quan hệ ngược từ Hotel | `app/Models/Hotel.php` | Không có `staff()` hoặc quan hệ Staff. Giữ nguyên Model Hotel. |
| Route, Controller, Middleware, Policy, Guard Staff | `routes/`, `app/`, `config/auth.php` | Không tìm thấy tuyến/module Staff hoạt động riêng. Admin hiện dùng Customer. Không loại bỏ AdminMiddleware hoặc các API quản trị. |
| View/trigger/routine/event SQL dùng Staff | Metadata database chính | Không tìm thấy. Không có cột `staff_id` hay cột mang tên Staff ở bảng khác. |

### Giao diện và cache cũ

| File | Phần cần xử lý ở bước loại bỏ Staff |
| --- | --- |
| `resources/views/admin/login.blade.php` | Bộ chọn vai trò có mục “Nhân viên”, `roleGrid`, `selectRole`, các CSS `role-*`, nội dung giới thiệu dành cho nhân viên. Bộ chọn này không quyết định role ở backend. |
| `resources/views/admin/partials/sidebar.blade.php` | Mục “Nhân viên / Soon” chỉ có `href="#"`; selector `.js-staff-name` và `.js-staff-role` đang hiển thị thông tin Admin. |
| `resources/views/admin/partials/topbar.blade.php` | `.js-staff-name`, `.js-staff-role` là tên selector cũ cho thông tin Admin, không truy vấn Staff. |
| `resources/views/admin/layouts/admin.blade.php` | Reader của hai selector cũ; phải đổi cùng hai partial. `admin_staff` chỉ còn được xóa để dọn cache cũ, không còn dùng để xác thực hoặc đọc thông tin. |
| `resources/views/admin/login.blade.php` | Dọn `admin_staff` cũ sau login thành công. Có thể kết thúc hỗ trợ khóa cũ sau giai đoạn chuyển tiếp. |

### Logic dùng chung và tài liệu

| File/phần | Phân loại |
| --- | --- |
| `database/migrations/2026_04_02_013436_create_booking_table.php` | Comment của `booking.cancelled_by` còn nhắc `staff`. Cột này cũng phục vụ `customer/system`; không được xóa cột hoặc module Booking khi bỏ Staff. Không có dữ liệu cancellation cần chuyển đổi trong database được kiểm tra. |
| `database/migrations/2026_04_02_013436_create_booking_room_table.php` | Comment nhắc việc Staff phân phòng lúc check-in; không có FK hoặc cột Staff. Giữ bảng/quan hệ BookingRoom và RoomType. |
| `app/Models/Booking.php`, `app/Services/BookingService.php` | `cancelled_by` đang dùng cho Customer. Giữ nguyên. |
| `app/Console/Commands/CancelExpiredBookings.php`, `app/Http/Controllers/PaymentController.php`, `app/Services/PaymentService.php` | `cancelled_by = system`; không phụ thuộc actor Staff. Giữ nguyên. |
| `ARCHITECTURE.md` | Actor/use case/class/quan hệ Staff và kiểu trả về Staff trong sơ đồ Admin đã lỗi thời; cần sửa thành mô hình Admin/Customer. |
| `README-VI.md` | “Admin/Staff Dashboard”, “sanctum:staff”, danh sách Model và mô tả `/api/admin/login (staff)` đã lỗi thời. |
| `tests/Feature/AdminAuthenticationTest.php` | Role `staff/manager/superadmin` chỉ là trường hợp bị từ chối. Đây là regression test bảo vệ giới hạn hai actor, nên giữ. |
| `resources/views/emails/otp.blade.php`, `app/Http/Controllers/ChatController.php` | Cụm từ “nhân viên” trong nội dung email/persona chatbot; không liên quan bảng Staff hay phân quyền. Chỉ chỉnh nội dung nếu cần, không xóa module. |

Không tìm thấy seeder Staff đang hoạt động hoặc import Model Staff từ các Model/Controller khác. Các bản text `AI_Full_Code.txt`, `UI_Code.txt` và README/TODO còn lại không có tham chiếu Staff trong lần rà soát này.

## Kế hoạch loại bỏ Staff ở bước tiếp theo

1. Kiểm tra lại số bản ghi, token Staff, FK và phụ thuộc trên đúng database triển khai; sao lưu trước mọi thay đổi schema/dữ liệu.
2. Xóa bộ chọn “Nhân viên” và mục menu chưa hoạt động; đổi đồng bộ selector `js-staff-*` sang `js-admin-*` trong layout, topbar và sidebar. Không xóa thành phần hiển thị tài khoản Admin.
3. Cập nhật tài liệu/sơ đồ và các comment về actor. Giữ các cột nghiệp vụ dùng chung của Customer/system.
4. Sau khi chắc chắn không còn runtime tham chiếu, loại bỏ Model Staff. Xử lý token đa hình cũ nếu có, chỉ sau khi được phép.
5. Tạo migration mới, được phê duyệt riêng, để bỏ FK và bảng Staff. Không sửa/xóa migration lịch sử đã chạy. Không xóa bảng Hotel, Booking, BookingRoom hoặc Customer.
6. Chạy lại test Authentication và kiểm thử thủ công hai actor. Kiểm tra cả cài đặt schema mới và nâng cấp schema hiện có trước triển khai.

Chưa thực hiện bất kỳ bước loại bỏ Staff, thay đổi tài khoản, migration/seeder, commit hoặc push nào.
