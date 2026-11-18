# AUREL Admin — Refine UI Layout

Ngày: 11/10/2026. Báo cáo này cập nhật giao diện sau Phase 1; Topbar của bản Phase 1 đã được gỡ bỏ.

## File của lần chỉnh sửa này

| File | Thay đổi |
| --- | --- |
| `resources/views/admin/layouts/admin.blade.php` | Bỏ include Topbar; đặt breadcrumb và nút mở drawer trong content; include style chung sau CSS từng trang. |
| `resources/views/admin/partials/sidebar.blade.php` | Thay thanh “Thu gọn menu” bằng toggle icon ở mép phải; giữ thông tin Admin và Logout tại footer. |
| `resources/views/admin/partials/styles.blade.php` | Logo nhỏ hơn, style toggle, bỏ CSS Topbar, main không còn khoảng trống dành cho Topbar. |
| `resources/views/admin/partials/navigation-script.blade.php` | Bỏ tham chiếu Topbar/dropdown; cập nhật toggle icon, giữ persistence/drawer/tooltip. |
| `resources/views/admin/partials/content-styles.blade.php` — mới | Typography, header/action, KPI, bảng, empty state và responsive dùng chung. |
| `resources/views/admin/revenue.blade.php` | Font Be Vietnam Pro; dùng Lucide local; gỡ tỷ lệ/forecast tĩnh; căn tiêu đề, chart và empty state. |
| `resources/views/admin/hotels.blade.php` | Font, h1, Lucide KPI/empty state và wrapper cuộn bảng. |
| `resources/views/admin/customers.blade.php` | Font, h1, icon KPI và wrapper cuộn bảng. |
| `resources/views/admin/bookings.blade.php` | Font, h1, nhãn tiếng Việt, Lucide KPI/empty state và wrapper cuộn bảng. |
| `resources/views/admin/reviews.blade.php` | Chỉ đồng bộ font và dùng h1 cho tiêu đề; không thay nghiệp vụ đánh giá. |
| `resources/views/admin/login.blade.php` | Chỉ đổi font local Be Vietnam Pro; JavaScript Login giữ nguyên. |
| `tests/Frontend/admin-authentication.test.cjs` | Fixture thông tin Admin đọc Sidebar thay vì Topbar đã xóa. |
| `resources/views/admin/partials/topbar.blade.php` — xóa | Không còn được layout hoặc JavaScript tham chiếu. |
| `docs/admin-ui-layout-refinement.md` — mới | Báo cáo này. |

`dashboard.blade.php` không cần chỉnh source trong lần này: Dashboard nhận typography, KPI và spacing từ style chung mới.

Artifacts kiểm tra riêng tại `storage/app/private/admin-ui-refine/`: helper PHP/Chrome, kết quả JSON và ảnh chụp. Thư mục này không được đưa vào Git.

## Layout đã thực hiện

- Toàn bộ UI Admin sử dụng Be Vietnam Pro, gồm tiêu đề, menu, card, bảng, form và Login. Logo thương hiệu giữ artwork gốc và khai báo font thương hiệu riêng.
- Logo đầy đủ giảm từ 128px xuống 108px. Collapsed dùng crop biểu tượng chim 32px từ đúng `public/image/logothuonghieu.png`; không sửa ảnh gốc hoặc vẽ lại logo. Vùng logo giữ #F8F8F8.
- Toggle desktop là icon tròn 28px, đặt tại mép phải Sidebar; không còn thanh button có chữ. Sidebar vẫn rộng 248px/80px, transition 200ms; main co giãn tương ứng.
- Topbar, chuông, avatar/dropdown và tên Admin ở hàng ngang trên cùng đã bỏ hoàn toàn. Breadcrumb ở content; title/subtitle/action nằm trong header của từng trang. Tên Admin, role và Logout nằm ở footer Sidebar.
- Main bắt đầu với padding 24px/32px trên desktop, 20px/16px trên mobile nhỏ; breadcrumb, header, KPI và bảng có spacing thống nhất.
- KPI padding 16px, chiều cao tối thiểu 108px, số liệu 27px trên desktop, icon 16px trong nền 26px. Grid 4 cột trên desktop rộng, 2 cột trên tablet/mobile, 1 cột dưới 360px.
- Bảng sử dụng header 11px, dữ liệu 12px; border và padding thống nhất. Bảng lớn cuộn ngang trong wrapper riêng, không làm tràn toàn trang. Tên khách sạn giữ độ rộng dễ đọc.
- Bảng rỗng trên mobile thu về đúng chiều rộng content và ẩn header không có dữ liệu; empty state không bị lệch trong vùng bảng rộng. Biểu đồ Revenue bằng 0 hiển thị thông báo dữ liệu trống.
- Dùng lại Lucide sprite và font local có sẵn; Revenue không còn tải Font Awesome/Nunito riêng. Giữ nền trắng, brand blue #2563EB và active #EEF4FF.
- Drawer vẫn có backdrop, Escape, focus trap Tab/Shift+Tab, khóa nền bằng inert và trả focus khi đóng. Tôn trọng prefers-reduced-motion.

## Kiểm tra

| Kiểm tra | Kết quả |
| --- | --- |
| Chrome headless | **65 kiểm tra đạt**, không có JavaScript exception chưa xử lý. |
| PHP Authentication regression | **17 test / 178 assertion đạt**, SQLite trong RAM. |
| JavaScript Authentication regression | **17 test đạt**. |
| Render Blade | **7 trang HTTP 200**, font Be Vietnam Pro, không có Topbar. |
| Whitespace của diff Admin | Đạt. |
| Đối chiếu Authentication | Inline script xác thực trong layout và toàn bộ JavaScript Login giữ nguyên nội dung. |
| Đối chiếu API/công thức | Các lời gọi adminApi giữ nguyên; hàm renderKPI Revenue giữ nguyên công thức. |

Chrome kiểm tra Dashboard, Revenue, Hotel, Customer, Booking ở desktop 1440px và mobile 390px; thêm breakpoint 1024px, 768px, 320px. Đã kiểm tra mở/thu gọn, kích thước và artwork logo, content tự co giãn, active menu, persistence qua navigation/reload, hover/focus tooltip, drawer/resize, Escape/Tab/backdrop, font bảng/KPI/chart và Logout từ footer.

Dữ liệu của 5 trang được đọc từ controller hiện tại trong transaction read-only rồi dùng làm payload cho preview. Browser dùng account/session fixture riêng và chặn mọi API thật; không sử dụng mật khẩu hoặc phiên đăng nhập thật. Nhánh biểu đồ có doanh thu chỉ dùng fixture cô lập để kiểm tra font, đơn vị và reduced motion; không thêm dữ liệu mẫu vào sản phẩm/database. Customer empty state cũng được kiểm tra bằng fixture trình duyệt riêng.

PHP regression bao gồm Admin Login/Logout, Customer Register/Login/Logout, role/token/API và Dashboard. Chưa kiểm tra thủ công bằng mật khẩu tài khoản thật trong lần này.

Đã đối chiếu hash 114 file đang có thay đổi trước khi bắt đầu: ngoài 7 file được phép chỉnh/xóa thuộc lần này, 107 file còn lại giữ nguyên. Các trang trước đó sạch được đối chiếu với snapshot source riêng. Không chỉnh backend, route, API, Sanctum, database, migration, .env, frontend khách hàng hoặc ảnh logo; không commit/push.

## Những điểm còn tồn tại ngoài phạm vi UI

- API Customer hiện trả cả tài khoản Admin vào danh sách/tổng khách hàng. Đây là hành vi backend có sẵn, chưa sửa.
- Bộ lọc thời gian Revenue hiện chỉ đổi trạng thái nút; chưa truyền khoảng thời gian vào API. Giữ nguyên hành vi, không thêm tính năng mới.
- “Giao dịch gần đây” của Revenue đang render từ `top_hotels`, không phải bản ghi Booking/Payment riêng; trạng thái “Hoàn thành” có sẵn trong renderer. Chưa thay nguồn dữ liệu hoặc nghiệp vụ.
- Các khối loại phòng/dịch vụ thêm/phương thức thanh toán chưa có API riêng: hiển thị empty state.
- Hành động duyệt đánh giá trong Dashboard vẫn là logic UI có sẵn; chưa bổ sung API.
- Màn hình có nhiều cột cần cuộn bảng cục bộ trên mobile; có thể thiết kế riêng từng module trong bước sau nếu muốn tối ưu thêm.

