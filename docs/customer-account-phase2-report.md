# AUREL — Profile / Customer Account, Giai đoạn 2

Ngày kiểm tra: 19/10/2026. Phạm vi: giao diện tài khoản khách hàng và các điểm nối UI với API hiện có. Đối chiếu với snapshot đầu Giai đoạn 2, không đối chiếu với HEAD vì workspace đã có thay đổi từ các giai đoạn trước.

## Kết quả

| Hạng mục | Trạng thái | Kết quả cụ thể |
| --- | --- | --- |
| Layout dùng chung | DONE | Nền `#F8F8F8`, card trắng, navy `#193448`, font tiếng Việt và icon SVG cục bộ. Gom 5 khối CSS inline thành một stylesheet dùng chung. |
| Sidebar | DONE | Đúng thứ tự 6 mục đã duyệt. Active theo pathname chính xác, có `aria-current`. Drawer trên mobile/tablet dưới 1024px, overlay, Escape, focus và đóng khi chuyển breakpoint. |
| Thông tin cá nhân | DONE | Avatar, họ tên, email đăng nhập chỉ đọc, điện thoại, ngày sinh và tổng Booking từ API. Có tải, lỗi, thử lại, validation và trạng thái đang lưu. Không khôi phục dữ liệu mẫu. |
| Ngày sinh trên database chính | PARTIAL | UI theo `profile_fields.dob` của API: chưa hỗ trợ thì trường vẫn hiện, bị khóa và có lý do; không gửi `dob` khi chưa được hỗ trợ. Chưa kiểm tra hoặc migrate database chính. |
| Booking legacy/V2 | DONE | Giữ hợp đồng dữ liệu đã PASS; phân biệt Booking và Payment; phương thức không rõ không biến thành tiền mặt; tổng tiền từ API. Danh sách, chi tiết, trạng thái trống, lọc, phân trang và lỗi tải được đồng bộ UI. |
| Hủy, đánh giá, đặt lại, hóa đơn | DONE | Tái sử dụng endpoint hiện có. Nút hủy lọc theo trạng thái và hạn 24 giờ; server vẫn quyết định cuối cùng. Modal chờ phản hồi, giữ nguyên khi thất bại. Hóa đơn chỉ có nút tải khi có Invoice, dùng GET hiện có. Giữ luồng đánh giá/đặt lại. |
| Tiếp tục thanh toán | PARTIAL | Có điểm nối tới trang thanh toán đã tồn tại, chỉ hiện khi route/cấu hình thực sự cho phép, Booking pending, Payment pending, phương thức banking/VNPay, và hạn thanh toán còn hiệu lực. Tự ẩn nếu thiếu dữ liệu hoặc cần đối soát. Checkout production vẫn bị khóa; không bật route/cờ triển khai. |
| Lịch trình AI | DONE | Danh sách đã lưu/bản nháp, bộ lọc, xem/xóa, tạo, lưu, timeline và chat dùng API hiện có. Chi phí chỉ trình bày dữ liệu trả về, không có rating giả. Văn bản chat dùng `textContent`; dữ liệu AI đưa vào template được escape và ID được kiểm tra. |
| Bảo mật tài khoản | DONE | Chức năng đổi mật khẩu tại `/password`, nhãn sidebar mới. Đồng bộ validation, hiện/ẩn mật khẩu có nhãn truy cập, thông báo lỗi/thành công. Không có trạng thái 2FA/thiết bị giả. Token lifecycle giữ nguyên. |
| Cài đặt | DONE | Dùng route `/settings` có sẵn. Tùy chọn giảm chuyển động chạy thật và lưu trên trình duyệt, thông báo đúng nếu trình duyệt từ chối lưu. Tiếng Việt/VND/giao diện sáng là thông tin chỉ đọc. |
| URL và đăng xuất | DONE | Giữ `/profile`, `/bookings`, `/itineraries`, `/password`, `/settings`. `/profile#password` chuyển tới `/password`. Không tạo route trùng. Hàm API/thu hồi token logout được giữ nguyên nguồn từ Giai đoạn 1. |

Không có hạng mục UI được duyệt nào còn BLOCKED. Những khả năng cần database hoặc mở Checkout production vẫn chờ quy trình phê duyệt riêng, không được kích hoạt trong lần này.

## Các vấn đề UI/điểm tích hợp đã xử lý

- Layout và CSS riêng từng trang gây khác biệt sidebar, spacing, button và modal: thay bằng layout, stylesheet, icon và hàm phản hồi dùng chung; không còn `<style>` riêng trong các view tài khoản.
- Sidebar active dựa trên hash hoặc phép so khớp quá rộng: dùng ánh xạ pathname; giữ tương thích đường dẫn đổi mật khẩu cũ.
- Avatar từng lưu ảnh preview vào cache trước khi server xác nhận: chỉ cập nhật ảnh/cache sau phản hồi upload thành công. Upload bị từ chối không để lại ảnh trông như đã lưu.
- Booking thiếu chi tiết thanh toán rõ ràng: tách nhãn thanh toán khỏi trạng thái Booking, thể hiện phương thức thực tế và Invoice thực tế. Chặn phản hồi danh sách cũ ghi đè khi đổi bộ lọc nhanh; bỏ phản hồi chi tiết khi người dùng đã đóng hoặc chọn Booking khác.
- Hủy Booking từng dùng `window.confirm`: chuyển sang modal dùng chung, disable khi chờ, không đóng hoặc báo thành công nếu API từ chối.
- Lịch trình có menu ẩn, modal riêng và nút xuất PDF trỏ tới route không tồn tại: dùng hành động rõ ràng và modal chung; bỏ nút PDF chưa có backend. Không ảnh hưởng module Invoice PDF.
- Ngân sách AI hai đầu nhưng API nhận một tổng: UI dùng một ngân sách dự kiến; chỉ áp dụng mặc định 5.000.000 VND khi để trống, giữ số 0 được nhập rõ ràng. Hiển thị rõ mặc định này trong form.
- Trường ngày bắt đầu nối tới `start_date` đã có trong API. Sở thích được đưa thêm vào `notes` đã có để prompt hiện tại thực sự nhận được, kèm validation tổng 1.000 ký tự. Không đổi prompt, model AI hoặc database.
- Bỏ điều khiển phương tiện/khu vực lưu trú khỏi form: backend hiện đọc các trường này nhưng không sử dụng chúng trong prompt. Không bổ sung nghiệp vụ mới để làm nút trông như hoạt động.
- Chat không có dấu trạng thái online giả. Lỗi tạo AI kết thúc loading và hiện thông báo rõ ràng; lỗi lưu/xóa không báo thành công. Xóa bản nháp giữ các lịch trình đã lưu và cập nhật vùng chi tiết nếu bản đang xem đã bị xóa.

## File thay đổi

14 file đã có ở đầu Giai đoạn 2:

| File | Nội dung |
| --- | --- |
| `resources/views/user/layouts/user.blade.php` | Layout, sidebar, header/footer, modal/toast chung. |
| `resources/views/user/profile.blade.php` | Wrapper và tiêu đề hồ sơ. |
| `resources/views/user/profile/profile-section.blade.php` | Form và tóm tắt hồ sơ thật. |
| `resources/views/user/profile/profile-script.blade.php` | Trạng thái tải/lưu, validation, upload chỉ cập nhật khi được xác nhận. |
| `resources/views/user/password.blade.php` | Wrapper trang bảo mật. |
| `resources/views/user/password/password-section.blade.php` | Form mật khẩu và hướng dẫn. |
| `resources/views/user/password/password-script.blade.php` | Phản hồi/visibility/strength thống nhất; endpoint giữ nguyên. |
| `resources/views/user/booking.blade.php` | Wrapper Booking. |
| `resources/views/user/bookings/bookings-section.blade.php` | Layout danh sách/chi tiết và kiểm tra khả năng hiển thị link thanh toán. |
| `resources/views/user/bookings/bookings-script.blade.php` | Renderer, hành động UI, modal, phân trang và chống phản hồi cũ. |
| `resources/views/user/itineraries.blade.php` | Wrapper AI. |
| `resources/views/user/itineraries/itineraries-section.blade.php` | Danh sách, bộ lọc và form AI. |
| `resources/views/user/itineraries/itineraries-script.blade.php` | Renderer an toàn, trạng thái UI và nối trường form vào API sẵn có. |
| `resources/views/user/settings.blade.php` | Cài đặt giảm chuyển động và thông tin hệ thống. |

8 file mới:

| File | Nội dung |
| --- | --- |
| `public/css/aurel-account.css` | CSS chung và responsive. |
| `public/vendor/aurel-account/icons.svg` | Bộ icon SVG cục bộ, tái sử dụng bộ icon hiện có và giữ ghi chú giấy phép. |
| `resources/views/user/partials/icon.blade.php` | Partial SVG. |
| `resources/views/user/layouts/account-script.blade.php` | Script layout, modal/toast, menu, điều hướng tương thích; giữ nguyên hàm API/logout. |
| `tests/Feature/CustomerAccountUiTest.php` | Render, route, sidebar, Settings và kiểm tra không có truy vấn DB khi render. |
| `phpunit.customer-account-ui.xml` | Cấu hình test SQLite RAM, mail/cache/session cô lập. |
| `tests/Frontend/customer-account-phase2.cjs` | Chrome/CDP, API synthetic, chặn request ngoài, responsive và ảnh chụp. |
| `docs/customer-account-phase2-report.md` | Báo cáo này. |

Font và logo tái sử dụng từ tài nguyên cục bộ đã có, không sửa tài nguyên của Admin. `routes/web.php` đã có đủ năm URL nên không cần thêm route. Các alias nội bộ như `HolidayVietImages` được giữ để tương thích code ảnh; giao diện hiển thị AUREL.

## Kết quả kiểm thử

| Bộ kiểm thử | Kết quả |
| --- | --- |
| `phpunit.customer-account-ui.xml` | **60/60 PASS, 785 assertions** — gồm 7 test UI mới và 53 test Profile/Auth/Admin/OTP/reset/token/DOB/AI đã có. SQLite `:memory:`, mail fake/array, chặn HTTP ngoài. |
| `phpunit.customer-account-v2.xml --filter CustomerBookingContractTest` | **3/3 PASS, 45 assertions** — legacy/V2, lựa chọn Payment đúng, Invoice thật, quyền sở hữu và đọc không tạo hiệu ứng phụ. MySQL riêng. |
| `tests/Frontend/customer-account-phase2.cjs` | **178/178 PASS**, không có lỗi JavaScript, **50 ảnh chụp**. API hoàn toàn synthetic, chặn mọi request ra ngoài máy cục bộ. |
| Phạm vi thay đổi | **308/308 file bảo vệ không thay đổi** theo SHA-256; nguồn hàm API/logout giữ nguyên. Không có lỗi whitespace từ `git diff --check` cho các view tài khoản. |

Các ca trình duyệt bao gồm DOB chưa/có schema, lưu và tải lại, validation, upload thất bại/thành công, lỗi tải/thử lại; logout chưa được xác nhận và phản hồi muộn; đổi mật khẩu; Settings lưu/không lưu được; Booking envelope legacy/V2, Payment chưa rõ, Invoice, hạn hủy, điều kiện thanh toán, bộ lọc nhanh/phân trang/đánh giá; AI CRUD/generate/save, ngân sách 0, XSS tags/timeline/chat/toast; keyboard/focus của sidebar và modal.

| Chiều rộng | 5 trang không tràn ngang | Form AI mở rộng | Modal | Sidebar |
| --- | --- | --- | --- | --- |
| 320px | PASS | PASS | PASS | Drawer, mở/đóng/Escape/focus PASS |
| 375px | PASS | PASS | PASS | Drawer, mở/đóng/Escape/focus PASS |
| 768px | PASS | PASS | PASS | Drawer, mở/đóng/Escape/focus PASS |
| 1024px | PASS | PASS | PASS | Sidebar cố định, active đúng |
| 1440px | PASS | PASS | PASS | Sidebar cố định, active đúng |

Danh sách/chi tiết Booking có nội dung và timeline AI có nội dung cũng được kiểm tra ở đủ năm kích thước. Các card không có scroll riêng; vùng chat giới hạn chiều cao khi cần đọc hội thoại. Font tiếng Việt và CSS cục bộ được xác minh tải thành công. Đã xem trực tiếp ảnh desktop/mobile, form, lỗi tải và modal.

MySQL test dùng port **61346**, 48 schema test trong datadir riêng do helper tạo; tài khoản test không có quyền trên database chính hoặc quyền ghi toàn cục. Server test đã được shutdown và xác nhận không còn listen trên port này. Không dùng kết nối mặc định 3306 hoặc truy vấn `hotel_booking`.

Lệnh chạy:

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit -c phpunit.customer-account-ui.xml

# Chỉ dùng descriptor/settings của server test riêng do helper tạo:
$env:AUREL_ROOMTYPE_V2_TEST_SETTINGS = '<owned-test-runtime>/test-environment.json'
php vendor/bin/phpunit -c phpunit.customer-account-v2.xml --filter CustomerBookingContractTest

node tests/Frontend/customer-account-phase2.cjs
```

## Ảnh và bằng chứng để duyệt

- [Bộ 50 ảnh và chú thích](../storage/app/private/customer-account-phase2/index.html).
- [Profile desktop](../storage/app/private/customer-account-phase2/screenshots/profile-1440.png) và [Profile mobile](../storage/app/private/customer-account-phase2/screenshots/profile-320.png).
- [Kết quả trình duyệt](../storage/app/private/customer-account-phase2/browser-results.json).
- [Đối chiếu phạm vi](../storage/app/private/customer-account-phase2/scope-verification.json) và [danh sách file](../storage/app/private/customer-account-phase2/changed-files.json).

Ảnh mặc định sử dụng trạng thái trống và tài khoản kiểm thử được ghi rõ. Ảnh có `fixture` trong tên dùng dữ liệu synthetic để kiểm chứng Booking/timeline/XSS; không phải dữ liệu khách hàng hoặc dữ liệu được thêm vào template. Gallery là artifact cục bộ, chưa publish/deploy.

## Giới hạn và phần cần phê duyệt riêng

- **DOB trên database chính:** cần phê duyệt bước áp dụng migration DOB tối thiểu đã chuẩn bị ở Giai đoạn 1, xác minh schema/backup và kiểm tra sau áp dụng. Giai đoạn 2 không sửa migration hay chạy nó trên `hotel_booking`.
- **Checkout production:** vẫn theo gate phê duyệt riêng của hệ thống; UI không mở Checkout để làm xuất hiện nút thanh toán. Luồng tài chính thực tế không được gọi trong kiểm thử giao diện.
- **Cài đặt tài khoản mở rộng:** đổi ngôn ngữ/tiền tệ/dark mode/thông báo/quyền riêng tư đồng bộ server cần đặc tả và phê duyệt nghiệp vụ/database riêng. Không có nút giả cho các tính năng này.
- **2FA, loyalty, voucher, lưu thẻ, thiết bị/phiên đăng nhập:** không triển khai hoặc hiển thị như đang hoạt động.
- **AI PDF:** không có endpoint hoạt động trong code hiện tại; bỏ nút dẫn tới route thiếu. Nếu cần xuất PDF lịch trình, cần duyệt riêng. Không thay đổi Invoice PDF.
- **Giới hạn kiểm thử:** browser dùng Chrome headless và API synthetic; chưa xác minh trực tiếp Safari/iOS/Firefox hoặc gọi nhà cung cấp AI/Google thật. Hồi quy backend dùng các fixture cô lập hiện có, không xác minh dữ liệu tài khoản thật hay trạng thái triển khai production.

Không sửa `.env`, app/controller/service/model, `routes/api.php`, schema/migration, backend Booking/Payment/SePay/VNPay/Invoice/Email hoặc Admin trong Giai đoạn 2. Không gửi email/tiền thật, không truy cập tài khoản thật, không commit/push/deploy/reset/stash.
