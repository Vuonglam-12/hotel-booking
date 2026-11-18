# Giá và vòng đời đặt phòng DEMO — 31/10/2026

Yêu cầu này bổ sung, không thay thế các tính năng đã chốt trong [AGENTS.md](../AGENTS.md) và [yêu cầu đã chốt](itinerary-approved-requirements.md).

## Giá đang dùng

- Admin mở Khách sạn → quản lý loại phòng hoặc trang chi tiết khách sạn → sửa loại phòng → bảng giá ngày/mùa. Ngày đầu/cuối của mùa đều tính vào khoảng giá; giá ngày có cùng ngày đầu và cuối.
- `RoomPricingService` dùng giá ngày → giá mùa → giá cơ bản riêng của khách sạn. Các khoảng cùng ưu tiên bị chặn chồng chéo dưới khóa Hotel. Tính tiền nguyên theo đơn vị 1/100 VND để tránh sai số số thực; ngày checkout không là đêm tính tiền.
- Chọn phòng, sidebar và modal lấy cùng API báo giá. Khi bảng giá đổi trước khi đặt, `quote_hash` cũ trả 409 và khách phải xem giá mới rồi xác nhận lại. Lựa chọn vẫn là loại phòng và số lượng.
- Đơn mới lưu `booking_nightly_rate`; không lấy lại giá hiện tại để tính tiền đơn đã tạo. Đơn cũ chưa có snapshot tiếp tục giữ số tiền/giá đã lưu, không tự suy ra bảng giá lịch sử.
- Hóa đơn DEMO mới dùng các dòng giá từng đêm; tổng không tính từ giá trung bình đã làm tròn.

## Hết hạn, giới hạn giữ và trạng thái

- `config/booking-demo.php`: `hold_minutes=10`, `max_pending_bookings=3`, `max_pending_rooms=10`. Giới hạn mỗi lần vẫn 1–10. Sửa cấu hình áp dụng lần tạo tiếp theo, không sửa thời hạn các đơn đã lưu.
- Tạo đơn khóa Customer trước khi kiểm tra hạn mức bằng locking read, kể cả các request tới khách sạn khác nhau. Tồn kho/giá được kiểm tra dưới khóa Hotel và Room. Hủy/hết hạn hoặc deadline đã qua không chiếm hạn mức chờ.
- Quá hạn chuyển Booking sang `cancelled`, `cancelled_by=system`; Payment chưa nhận tiền sang `expired`. Có hỗ trợ đọc đơn `expired` lịch sử. Retry không gia hạn đơn.
- Đơn đã hủy/hết hạn không thể khôi phục qua API trạng thái. Chỉ công cụ thông báo thanh toán DEMO của Admin được xác nhận, khách không tự báo đã trả tiền. Hoàn thành yêu cầu Payment thành công khớp Booking, hóa đơn tương ứng, kết quả DEMO thành công và đã tới `check_out + hotel.check_out_time` theo `Asia/Ho_Chi_Minh` (mặc định dự án 12:00 khi fixture thiếu trường).
- Mỗi thông báo thành công có sổ nhận `booking_demo_payment_notice`, trong cùng transaction với xử lý tài chính. Mã thông báo tường minh trùng nhưng đổi số tiền bị 409; lặp chính xác trả kết quả đã lưu. Simulator không truyền mã dùng định danh mặc định theo lượt thanh toán và số tiền.

## Đối soát

- `/admin/payment-reconciliation` được liên kết từ danh sách đặt phòng và trang xử lý thanh toán. API yêu cầu auth Admin và middleware DEMO loopback.
- Sai số tiền chỉ lưu đối soát, không xác nhận. Tiền hợp lệ đến sau hủy/hết hạn vẫn ghi nhận Payment đã nhận tiền và hồ sơ đối soát, không khôi phục phòng, tạo hóa đơn xác nhận hay gửi thông báo booking thành công.
- Trạng thái xử lý: `open`, `reviewed`, `refund_verified`. Ghi chú được append vào bảng review, không xóa lịch sử. `refund_verified` bắt buộc mã tham chiếu và tệp PDF/JPG/PNG ≤5 MB đã lưu thành công, có SHA-256. Tệp trong `storage/app/private/reconciliation-evidence`, không công khai.
- Ghi chú/ghi nhận bằng chứng không tự gọi ngân hàng, hoàn tiền, sửa Booking/Payment hoặc cộng tồn.

## Scheduler Laragon đã triển khai

- Windows Scheduled Task duy nhất: `\HotelBooking-Laragon-Scheduler`, chu kỳ `PT1M`, chạy khi người dùng Windows đang đăng nhập; có trigger khi đăng nhập. Windows và MySQL/Laragon phải đang chạy. Không phụ thuộc mở trình duyệt/truy cập website.
- Task gọi ẩn `scripts/run-laragon-scheduler.ps1` với đường dẫn PHP Laragon cố định lúc cài. Có mutex của script, chính sách Windows IgnoreNew và Laravel `withoutOverlapping(2)` cho lệnh nhả phòng.
- Đã kiểm tra qua COM trước khi cài: không có task dự án khác hoặc PHP `schedule:run/work` đang chạy. Không tạo thêm task khi đã có task này. Nếu đường dẫn PHP thay đổi, cập nhật task hiện hữu, không tạo task mới.
- Đã thấy nhiều lượt chạy tự động thành công. Lần xác minh 31/10/2026 15:32 (UTC+7): Windows LastTaskResult=0; heartbeat 08:32:18 UTC ghi `trigger=windows_scheduler`, `status=success`; log stderr 0 byte. Bằng chứng ở `storage/app/private/booking-scheduler-verification.json` và heartbeat cập nhật mỗi phút. Log ở `storage/logs/booking-expiry-scheduler.log`, `laragon-scheduler-output.log`, `laragon-scheduler-error.log`.
- Lệnh chạy thủ công ghi `trigger=manual` nên không dùng heartbeat thủ công để khẳng định Windows Scheduler đã chạy. Khi đổi host/tài khoản Windows phải kiểm tra/cài lại scheduler ở host mới.

## Migration và an toàn dữ liệu

- Chỉ áp dụng ba migration bổ sung `2026_10_31_000003`, `000004`, `000005`: bảng giá, snapshot, đối soát/review và sổ nhận thông báo.
- Không chạy `migrate:fresh`, reset, seed hay xóa dữ liệu ứng dụng. Kiểm tra số dòng các bảng nghiệp vụ cũ không thay đổi khi thêm bảng; báo cáo an toàn không chứa credentials ở `storage/app/private/booking-safeguards-deployment.json`.
- Provider V2 vẫn chưa đăng ký, các cổng tiền thật vẫn đóng. QR/OTP/Google Login không thay cấu hình hoặc luồng xác thực.

## Kiểm thử

- `RoomPricingTest`, `BookingSafeguardsTest` và `LocalBookingDemoTest` chạy trên server MySQL mới riêng, các schema test mới, account không có quyền ghi DB ứng dụng. Không reset schema test đã dùng để chạy lại.
- Frontend: `tests/Frontend/booking-pricing.test.cjs`, `tests/Frontend/admin-hotel-roomtype-source.test.cjs`.
- Các trường hợp: giá ngày/mùa/cơ bản, checkout exclusive, snapshot và hóa đơn chính xác, báo giá cũ, 10 phút và lệnh hết hạn không có HTTP, quota và tranh chấp đồng thời, tranh phòng cuối, sai tiền, lặp/tiền muộn, chuyển trạng thái, giờ checkout, ghi chú/bằng chứng đối soát.
- Kết quả đã chạy: **53 test MySQL / 546 assertions đạt** (giá 5/45; safeguards 25/219 và kiểm tra ledger chi tiết mới nhất 1/17; DEMO hồi quy 22/265). **13 test OTP/Google/recovery / 169 assertions đạt** trên SQLite `:memory:`; extension SQLite chỉ bật cho tiến trình test, không đổi php.ini. **14 test hành vi JavaScript đạt** (7 giá/đối soát, 7 editor loại phòng).
- Đã render trang đối soát và kiểm tra cú pháp 6 script xuất ra. Không gọi mail/ngân hàng thật; bộ auth chặn HTTP ngoài và giả lập mail. Không khẳng định đã kiểm thử giao dịch ngân hàng hay đăng nhập Google thật.
- Đã kiểm tra route báo giá trên runtime local trả HTTP 200 với đúng 2 dòng cho 2 đêm, có quote hash, không tạo booking test trên DB ứng dụng. V2 và webhook tiền thật vẫn tắt.
- Ba instance MySQL mới cho test đã được dừng sau khi xác minh đúng datadir/port; giữ nguyên mọi dữ liệu và artifact test, không xóa schema. DB Laragon chính và Windows Schedule vẫn chạy. Báo cáo không chứa credentials: `storage/app/private/booking-safeguards-tests-20261031.json`.
- Admin cần nhập các giá ngày/mùa thực tế; không tự tạo giá kinh doanh. Khi chưa có quy tắc cho đêm đó, hệ thống dùng giá cơ bản hiện có. Scheduler hiện áp dụng trên máy/tài khoản Windows này, cần phiên đăng nhập Windows và MySQL hoạt động; không yêu cầu có khách truy cập website.
