# Thông báo form đặt phòng — 02/11/2026

Chỉ sửa các partial giao diện đặt phòng; không sửa dữ liệu DB, công thức giá/phụ thu, API thanh toán hoặc cấu hình DEMO/V2.

## Hành vi đã bổ sung

- Lỗi thiếu ngày nhận và trả tách riêng ngay dưới nút chọn ngày. Phân biệt ngày đã qua, ngày sai định dạng, trả không sau nhận, kỳ lưu trú vượt 365 đêm.
- Thiếu loại phòng, khách sạn chưa có loại phòng để đặt trực tuyến, thiếu/sai số khách, sức chứa chưa xác định, số khách vượt tổng sức chứa. Khi có sức chứa xác định, nêu rõ số khách đã chọn/sức chứa và số phòng tối thiểu cần chọn, không suy ra tồn phòng.
- Thiếu số phòng, số không nguyên/ngoài 1–10, hết phòng hoặc chỉ còn X trong khi yêu cầu Y. Yêu cầu thêm vượt 500 ký tự được báo ở đúng ô.
- Danh sách mục cần sửa bấm tới đúng trường; viền và aria-invalid, aria-describedby đồng bộ; dưới nút tiếp tục nêu rõ lý do chưa thể đặt. Lỗi được xóa/cập nhật theo lựa chọn mới.
- Phân biệt kiểm tra phòng trống, lấy giá từng đêm, lỗi giá, lỗi tồn, chờ xác nhận giá mới, cần đăng nhập, sẵn sàng, đang tạo đơn, đã tạo/chuyển sang thanh toán. Yêu cầu đọc giá/tồn chờ quá 20 giây có thông báo tải lại; phản hồi của lựa chọn cũ không ghi đè lựa chọn mới.
- Giữ lý do tiếng Việt từ backend cho giới hạn giữ phòng/đơn trùng. Lỗi xác thực có link đăng nhập; đơn trùng/giới hạn/không xác định kết quả có link Lịch sử đặt phòng. Lỗi validation của backend về trường nào đặt sát trường đó; không hiển thị lỗi framework/SQL/JSON/network thô.
- Không tự gửi lại POST khi mất kết nối; giữ khóa idempotency và hướng dẫn kiểm tra lịch sử trước khi đặt lại. Đã tạo đơn thì khóa nút trong khi chuyển trang.
- Sửa thông báo thiếu ngày trong lịch. Rà luồng preview cũ và sửa đồng bộ thuộc tính hidden để lỗi không bị ẩn dù đã gán nội dung.

## Phạm vi rà soát

- Đã đối chiếu guard/validation hiện có trong LocalBookingDemoService và RoomAvailabilityService, route lịch sử `/bookings`, route đăng nhập, và luồng frontend đang dùng.
- Đã kiểm tra cú pháp JavaScript và biên dịch/cú pháp PHP của các Blade sửa; không chạy bộ test tự động hay tạo đơn/thanh toán theo yêu cầu trước của người dùng. Chưa tuyên bố kiểm thử end-to-end đạt.

## Danh sách tự kiểm tra

1. Mở form với loại phòng đã chọn nhưng chưa có ngày: có hai nhắc riêng nhận/trả, danh sách hai mục và lý do nút bị khóa.
2. Chọn ngày nhận nhưng chưa trả: chỉ nhắc còn thiếu ngày trả. Nhập ngày đã qua hoặc ngày trả không sau nhận qua URL/giá trị form: chỉ đúng ngày cần sửa.
3. Chưa chọn loại phòng: có link Chọn loại phòng. Chọn phòng xong lỗi này tự ẩn, giữ ngày/số khách/số phòng.
4. Xóa số phòng, nhập 0/11/số lẻ: hiện lý do sát ô và nút chưa dùng được. Nhập lại 1–10: lỗi biến mất.
5. Chọn khách vượt sức chứa: hiện số khách, sức chứa tổng của lựa chọn, gợi ý số phòng tối thiểu. Giảm khách hoặc tăng số phòng phù hợp: cập nhật ngay.
6. Đủ ngày/loại/số phòng: thấy đang kiểm tra tồn/giá, sau đó sẵn sàng. Với khách chưa đăng nhập, thông báo cần đăng nhập và nút tiếp tục vẫn dẫn luồng đăng nhập hiện có.
7. Chọn khoảng hết phòng hoặc số yêu cầu lớn hơn tồn: hiện hết phòng hoặc còn X/đang chọn Y; đổi ngày/số phòng được giữ để điều chỉnh.
8. Mất mạng hoặc yêu cầu giá/tồn quá lâu: có lỗi tiếng Việt và nút tải lại. Đổi ngày nhanh: kết quả cũ không thay lựa chọn hiện tại.
9. Admin sửa phụ thu trong lúc khách đang xem: giá mới hiện, yêu cầu xác nhận lại; thông báo giải thích bước còn thiếu.
10. Trên DB test riêng, thử gửi đơn khi phiên hết hạn/đã có đơn cùng kỳ/đạt giới hạn giữ phòng: hiện đúng lý do và link đăng nhập hoặc lịch sử.
11. Trên DB test riêng, giả lập lỗi mạng lúc tạo đơn: hướng dẫn kiểm tra lịch sử, không tự gửi lại. Thành công: khóa nút và hiện đang chuyển trang thanh toán.
12. Trên điện thoại, bấm từng dòng danh sách lỗi: cuộn/focus đúng ô; nội dung không tràn ngang. Kiểm tra yêu cầu thêm, chi tiết phụ thu và chọn phòng vẫn còn nguyên.
