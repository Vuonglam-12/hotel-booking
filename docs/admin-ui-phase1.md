# AUREL Admin UI — Phase 1

Ngày: 11/10/2026 (Asia/Bangkok). Phạm vi: layout, Sidebar, Topbar, Dashboard và các tài nguyên UI riêng của Admin.

## Các file chỉnh sửa

- `resources/views/admin/layouts/admin.blade.php`
- `resources/views/admin/partials/sidebar.blade.php`
- `resources/views/admin/partials/topbar.blade.php`
- `resources/views/admin/dashboard.blade.php`

Thêm mới:

- `resources/views/admin/partials/styles.blade.php`: toàn bộ token/style của shell, chỉ được include trong Admin.
- `resources/views/admin/partials/navigation-script.blade.php`: Sidebar/drawer/tooltip/dropdown và helper SVG, tách khỏi script Authentication.
- `resources/views/admin/partials/icon.blade.php`: component Lucide dùng sprite SVG cùng origin.
- `public/vendor/lucide/aurel-admin.svg` và `LICENSE.txt`: 28 icon, sprite khoảng 5.5 KB, từ commit `70562c1ee1c4fdcf736fe97bc893fb8511927934` của kho chính thức.
- `public/fonts/admin/be-vietnam-pro.css`, 12 file WOFF2 và `OFL.txt`: Be Vietnam Pro 400/500/600/700, tổng binary khoảng 190 KB. Chỉ Admin tải font này.
- Báo cáo này.

Các script preview, JSON kiểm thử và ảnh chụp được lưu riêng ở `storage/app/private/admin-ui-phase1/`, bị Git bỏ qua.

## Thành phần UI

- Sidebar trắng, rộng 248px khi expanded và 80px khi collapsed trên desktop từ 1024px.
- Lưu lựa chọn bằng `aurel_admin_sidebar_collapsed`, độc lập với các khóa Authentication. Áp dụng ở head trước paint để hạn chế giật khi chuyển trang.
- Expanded dùng lại component logo chung. Collapsed crop bằng CSS từ cùng `image/logothuonghieu.png`, chỉ lấy biểu tượng chim. Không sửa ảnh hoặc dựng lại wordmark; kiểu chữ của artwork AUREL gốc được giữ nguyên.
- Khu vực logo #F8F8F8; active #EEF4FF; brand #2563EB, khớp --hv-accent trong CSS website khách hàng.
- Tablet/mobile dưới 1024px dùng drawer. Desktop vẫn giữ rail icon khi collapsed.
- Tooltip xuất hiện khi hover hoặc focus icon; active menu dùng route hiện có và aria-current.
- Drawer có backdrop, Escape, focus trap Tab/Shift+Tab, trả focus về nút mở và khóa tương tác nền bằng inert.
- Topbar trắng, cao 64px, breadcrumb gọn; giữ tài khoản, thông báo và Logout hiện có. Nút collapse chỉ nằm ở Sidebar desktop; nút mở drawer chỉ nằm ở Topbar mobile.
- UI dùng Be Vietnam Pro. Lucide dùng SVG local, stroke 1.8 và kích thước thống nhất; không tải thêm runtime icon library.
- KPI card gọn hơn, có typography/tabular numbers rõ ràng; grid 4/2/1 cột theo màn hình.
- Bảng đặt phòng có header, border, padding, số tiền VND và scroll ngang cục bộ khi cần.
- Biểu đồ giữ ApexCharts hiện có, dùng đúng series API, nhãn/tooltip VND; không dựng ngày hoặc số liệu mẫu khi thiếu dữ liệu.
- Dữ liệu bằng 0 có empty state; lỗi tải hiển thị “—” và thông báo, không giả thành số 0.
- Escape HTML khi render tên khách hàng/khách sạn/nội dung đánh giá.
- Transition 200ms; tôn trọng prefers-reduced-motion.

## Kiểm thử

1. PHPUnit Authentication: **17 test, 178 assertion đạt** trên SQLite RAM. Bao gồm Admin Login/Logout, Customer Register/Login/Logout, bảo vệ role/token/API và render layout.
2. Test JavaScript Authentication hiện có: **17 test đạt**.
3. Render Blade cả 6 trang Dashboard, Booking, Hotel, Customer, Review, Revenue: đạt.
4. Chrome headless: **36 kiểm tra đạt**, không có JavaScript exception chưa xử lý.
5. Viewport thực tế: 1440, 1024, 768, 390 và 320px. Dashboard không có horizontal page overflow.
6. Kiểm tra collapse/expand, logo, main width, hover/focus tooltip, active menu, điều hướng/reload giữ state, drawer/resize, backdrop, Escape/Tab, dropdown và Logout giữ phiên Customer.
7. Kiểm tra cả trường hợp desktop collapsed chuyển sang mobile: drawer vẫn hiển thị logo đầy đủ, khi trở về desktop vẫn giữ lựa chọn collapsed.
8. Kiểm tra chart zero/missing series, nhánh non-zero với ApexCharts thật, format VND, load error và escape nội dung HTML.
9. Payload Dashboard lấy từ controller hiện tại trên `hotel_booking` trong transaction read-only. Số liệu thật hiện có 0 booking và 0 doanh thu 7 ngày; ảnh chụp dùng payload này.
10. Browser chạy trên server preview riêng chặn mọi API thật. Dữ liệu non-zero và văn bản XSS chỉ là fixture của test, không được ghi vào DB hoặc thêm vào sản phẩm.
11. Không thử Login thủ công bằng mật khẩu/tài khoản thật trong lần này.

## Bảo toàn source và database

- Nội dung script Authentication trong layout được đối chiếu với bản trước chỉnh sửa và giữ nguyên; chỉ bỏ listener dropdown cũ không còn dùng và chuyển phần tương tác UI sang partial riêng.
- Controller, middleware, config/guard, route và API không đổi. Login Blade không đổi.
- Không sửa template chi tiết Hotel/Customer/Booking/Review/Revenue; chỉ thay shell dùng chung và Dashboard.
- Không chỉnh frontend khách hàng, logo gốc, app.css, Vite config, .env hoặc migration.
- Đối chiếu fingerprint toàn bộ file thay đổi/untracked có sẵn: không có chỉnh sửa ngoài 3 file shell được cho phép; Dashboard là file tracked trước đó chưa dirty, được sửa trong task này.
- Database chính chỉ được đọc, không thực hiện migration/seeder hoặc thao tác ghi.
- So với snapshot giai đoạn migration: 23 bảng ngoài migrations/personal_access_tokens vẫn khớp toàn bộ dữ liệu/schema, gồm tài khoản Admin và mọi bảng nghiệp vụ. Schema personal_access_tokens giữ nguyên; migrations vẫn có 66 dòng, Staff Ran batch 2, bảng staff không tồn tại.
- Hiện có 1 tài khoản Admin và 1 admin_token đúng ability admin. Token hiện tại khác snapshot migration cũ; không có snapshot token ngay đầu Phase 1 để xác định thời điểm thay đổi. Các test UI không dùng token thật hoặc gửi API ghi đến database chính.
- Không commit/push.

## Những phần còn tồn tại

- Nút “Duyệt” đánh giá trong Dashboard có logic cũ chỉ làm mờ/xóa item khỏi giao diện và hiện toast, chưa gọi API duyệt. Phase 1 giữ nguyên logic này, không thêm API hay thay đổi nghiệp vụ.
- Hồ sơ/Cài đặt/Thông báo và các mục “Sắp có” giữ trạng thái placeholder hiện có; không bổ sung chức năng mới.
- ApexCharts vẫn dùng CDN sẵn có của dự án; nếu tải thư viện thất bại, vùng chart có thông báo phù hợp.
- Các trang quản lý chi tiết vẫn giữ thiết kế riêng, để xử lý ở phase sau.

Nguồn icon: [Lucide Static](https://lucide.dev/guide/static), [kho Lucide](https://github.com/lucide-icons/lucide/tree/70562c1ee1c4fdcf736fe97bc893fb8511927934).
