# Báo cáo doanh thu Admin — 04/11/2026

## Nguyên nhân

Route đang dùng gọi `AdminController::revenue`, còn `Admin\RevenueController`/service cũ không được route này sử dụng. API chỉ có tổng, tháng của năm hiện tại và Top 5 toàn thời gian. Nút lọc chỉ là HTML, không có xử lý; bảng giao dịch dựng từ Top 5, KPI khách sạn đếm số dòng Top 5, ba khối loại phòng/dịch vụ/phương thức chỉ hiện ô chờ. Lỗi API còn bị biến thành số 0, và biểu đồ làm tròn tiền sang triệu nên có thể làm mất số tiền nhỏ.

## Đã sửa

- Hai controller gọi chung `RevenueService`, route/protection Admin giữ nguyên. API đọc từ DB, không thêm migration hoặc sửa Payment/Booking/Invoice.
- Lọc theo ngày thanh toán, giờ Việt Nam. Đồng bộ múi giờ PHP/DB trước khi lọc và nhóm ngày; không dựa vào `now()` UTC để xác định ngày Việt Nam. Kỳ 7/30 ngày gồm hôm nay; tháng/năm tính đến hôm nay. Tùy chỉnh gồm cả ngày đầu/cuối, tối đa 366 ngày.
- Chỉ `payment_status=success`, `paid_at` tồn tại, số tiền dương khớp `booking.total_price`, booking confirmed/completed. Chọn lần thanh toán hợp lệ đầu tiên của mỗi đơn trên toàn lịch sử để không cộng lặp ở các kỳ khác nhau. Đơn hủy hoặc thanh toán sai tiền không được cộng. Báo cáo này tổng hợp số tiền thanh toán thành công theo quy tắc trên; không thực hiện hoặc suy diễn hoàn tiền từ ghi chú đối soát.
- Giữ mặc định nguồn thực tế, bổ sung nguồn DEMO riêng. Nhận diện DEMO bằng phương thức, mã giao dịch và liên kết booking_demo khi có. Đổi nguồn không bật thanh toán tiền thật.
- KPI tổng/các khách sạn/giao dịch, biểu đồ, Top 5 và bảng tháng dùng chung tập thanh toán trong một snapshot DB. KPI tháng ghi rõ tháng cuối kỳ và chỉ phần tháng nằm trong kỳ đã chọn.
- Giao dịch có bản ghi thực và phân trang 20 dòng, sắp theo thời gian/id, chặn trang vượt giới hạn. Có khách hàng, mã booking, khách sạn, tiền, phương thức và trạng thái thật.
- Loại phòng Top 8 đọc snapshot từng đêm, fallback giá đã lưu của đơn cũ; không tính bằng giá catalog hôm nay. Dịch vụ chỉ đọc bảng sẵn có, không tạo hay cộng phụ thu lần nữa. Hai khối này ghi rõ giá phòng/dịch vụ trước phân bổ giảm giá và thuế.
- Biểu đồ giữ nguyên giá trị VND; đơn vị hiển thị rút gọn không làm tròn dữ liệu nguồn. Nếu thư viện biểu đồ không tải được vẫn có bảng giá trị theo thời gian và danh sách phương thức.
- Giao diện giữ các khối cũ, thêm ngày tùy chỉnh, nguồn dữ liệu, tải lại, trạng thái tải/lỗi/rỗng và phân trang. Thay bộ lọc nhanh hủy request cũ, không giữ số cũ dưới kỳ mới. Lỗi tải hiện chưa tải được, không tự ghi số 0.

## Đối chiếu đã thực hiện

- Đã đọc schema/thanh toán thật trên DB chính; hiện không có bảng `service`/`booking_service`, nên dịch vụ thêm hiện thông báo chưa có dữ liệu.
- Kỳ **06/10–04/11/2026**: nguồn thực tế **0 ₫ / 0 giao dịch / 0 khách sạn**; nguồn DEMO **24.197.620 ₫ / 5 giao dịch / 5 khách sạn**. Doanh thu tháng 10 cùng tổng này, tháng 11 trong kỳ bằng 0; tổng theo phương thức DEMO khớp tổng báo cáo.
- Đã gọi service trong môi trường chỉ cho SELECT/SHOW/EXPLAIN. Fingerprint trước–sau của `payment`, `booking`, `booking_room`, `invoice`, `invoice_item` khớp. Bằng chứng không chứa credentials: `storage/app/private/revenue-review/report-summary.json`.
- Đã kiểm tra cú pháp PHP/JavaScript và biên dịch Blade. Không chạy bộ PHPUnit/browser test tự động theo yêu cầu trước; không tạo thanh toán thử. Không khẳng định đã kiểm thử mọi tình huống đồng thời hoặc mọi nhánh dữ liệu dịch vụ chưa tồn tại.

## Danh sách tự kiểm tra

1. Admin → Báo cáo doanh thu: nguồn thực tế không có giao dịch phải hiện 0 và giải thích rõ.
2. Chọn Thanh toán DEMO → 30 ngày: đối chiếu với các payment thành công đã có (tổng nêu trên đúng thời điểm đối chiếu; dữ liệu mới có thể làm thay đổi).
3. Đổi Hôm nay/7 ngày/Tháng này/Năm nay: ngày kỳ, KPI, biểu đồ, Top 5, phương thức, bảng giao dịch và bảng tháng phải cùng đổi.
4. Tùy chỉnh 31/10/2026–31/10/2026: có khoản DEMO đã ghi nhận ngày 31/10; ngày kết thúc được tính trọn ngày. Thử thiếu hai ngày/đảo đầu-cuối/quá 366 ngày: báo lỗi cụ thể.
5. Tùy chỉnh qua năm: tháng phải có cả tháng/năm, không nhập hai tháng cùng số của hai năm vào một dòng.
6. Đối chiếu mã payment/booking, thời gian, khách hàng và số tiền ở bảng với DB. Tổng khách sạn phải có thể lớn hơn 5 nếu dữ liệu có hơn 5 khách sạn; Top 5 chỉ hiển thị 5.
7. Trên DB test riêng có >20 giao dịch, đổi trang; đổi bộ lọc phải về trang 1. Trang vượt cuối phải trở về trang hợp lệ.
8. Trên DB test riêng, payment pending/failed/refunded/sai tiền, booking hủy và payment trùng không được làm tăng doanh thu đủ điều kiện. Tên/phương thức chứa ký tự HTML phải được hiển thị như văn bản.
9. Loại phòng giữ tiền snapshot khi Admin đổi bảng giá; phụ thu không xuất hiện lần nữa ở Dịch vụ thêm. Không có bảng dịch vụ vẫn tải được các khối khác.
10. Đổi bộ lọc nhanh, mất mạng, lỗi API hoặc không tải được thư viện biểu đồ: không hiện số cũ dưới kỳ mới, có thông báo và khả năng tải lại/bảng giá trị dự phòng. Kiểm tra trên màn hình nhỏ.
