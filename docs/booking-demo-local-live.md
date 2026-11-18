# AUREL — Đặt phòng và thanh toán DEMO trên website chính

Đã bật ngày 26/10/2026 trên `127.0.0.1:3306/hotel_booking`, chỉ trong môi trường Laravel `local` và truy cập loopback. Không nhận/chuyển tiền thật, không gửi email, không mở VNPay hoặc SePay.

1. Đăng nhập Customer tại `http://127.0.0.1:8000/login`.
2. Mở `/hotels/46` hoặc khách sạn đang hoạt động khác; chọn ngày tương lai, loại phòng, số phòng và số khách.
3. Kiểm tra thông tin và nhập **Yêu cầu thêm** (nếu cần) ngay trên nút **Tiếp tục đặt phòng**. Bấm nút để tạo đơn và chuyển thẳng sang thanh toán, không có popup xác nhận. Customer không có quyền tự đặt kết quả thanh toán.
4. Mở một tab Admin: `/admin/bookings` → **Xử lý thanh toán** → **Thông báo thành công / thất bại / Hủy thanh toán / Kết thúc thời hạn**. Đây là công cụ giả lập tín hiệu thanh toán; backend xử lý thật trên MySQL, không gọi ngân hàng hoặc SePay. Sau thất bại, Customer có thể thử lại trong thời hạn giữ phòng **10 phút** (cập nhật đã chốt 31/10/2026).
5. Tab Customer tự cập nhật trong khoảng 10 giây, hoặc bấm **Cập nhật**. Sau thành công bấm **Tải hóa đơn**, hoặc xem `/bookings`. Mã hiển thị `AUREL-PAY-…`, hóa đơn `AUREL-HD-…`; mã gốc và dấu mô phỏng vẫn giữ trong database, không tính doanh thu thật. Không yêu cầu chuyển khoản vào QR.

Hai migration đã áp dụng và ghi ledger:

- `2026_10_12_000005_replace_booking_status_unique`: thay unique theo trạng thái bằng unique cho đơn đang hoạt động; giữ lịch sử đơn hủy/hết hạn.
- `2026_10_26_000001_create_local_booking_demo_audit`: thêm `booking_demo` và `booking_demo_event`. Không seed hoặc chuyển đổi lại phòng.

Giữ nguyên danh mục scoped, ảnh, số lượng phòng và tài khoản hiện có. Giao dịch có phương thức `demo`, mã `DEMO_LOCAL_…`; không cộng vào doanh thu thực. Khóa theo khách sạn và kiểm tra tiền/phòng bằng backend; kết quả thành công tạo hóa đơn/thông báo một lần. Hết hạn được xử lý khi đọc đơn, tạo đơn mới, đọc lịch sử hoặc chạy lệnh expiry hiện có.

Kiểm chứng lượt sửa UI: **3 tests / 46 assertions**, **19 nhóm Chrome trên bản sao MySQL** (đổi qua lại các loại phòng, cảnh báo sức chứa, ghi chú, tạo đơn trực tiếp/chống bấm trùng, thanh toán, hóa đơn, tìm kiếm và desktop/tablet/mobile), **3 GET không đăng nhập trên website chính** đều PASS. Không tạo giao dịch thử trên database chính trong lần chỉnh sửa này. Chủ dự án nghiệm thu bằng tài khoản Customer/Admin hiện có theo các bước trên.

Backup mới đã phục hồi thử: `C:\Users\ASUS\AppData\Local\AUREL\backups\booking-local-20261026-5898d403b5c0`; SQL SHA-256 `558db769702340a05bff293e6544060285ef5e118e9bf24b008a87a28c2a2954`.

Để đóng DEMO: đặt `booking-demo.local_enabled=false` trong cấu hình, giữ nguyên lịch sử và audit. Không rollback/xóa bảng khi đã có giao dịch; migration tự chặn rollback mất lịch sử. Bản V2 production và các cổng thanh toán thật vẫn chưa được kích hoạt.
