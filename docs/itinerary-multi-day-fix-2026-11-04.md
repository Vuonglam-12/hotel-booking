# Sửa lỗi tạo lịch từ 3 ngày — 04/11/2026

Người dùng gặp thông báo `AI trả lịch chưa hợp lệ. Vui lòng thử tạo lại.` ở Đà Lạt khi chọn từ 3 ngày; lịch 1 ngày vẫn tạo được.

## Nguyên nhân đã tái hiện

Lượt gọi Groq thật trước sửa: yêu cầu 3 ngày, 18 ứng viên, ưu tiên 3–6 điểm/ngày. API trả HTTP 200, `finish_reason=stop`, JSON giải mã thành công và `is_valid` là boolean. Tuy nhiên `days` có 5 phần tử, số hoạt động lần lượt `[4, null, 3, null, 4]`; hai phần tử thiếu `date`/`items`. Validator từ chối vì sai số ngày và thiếu trường. Đây là lỗi đáp ứng cấu trúc, không phải lỗi đọc Sheet, xác thực hoặc cắt đầu ra trong lượt tái hiện này.

Chế độ cũ `json_object` chỉ yêu cầu JSON hợp lệ. Cách gọi mới dùng Structured Outputs trên GPT-OSS 20B/120B theo [tài liệu Groq](https://console.groq.com/docs/structured-outputs), vẫn kiểm tra nghiệp vụ ở backend.

## Thay đổi

- `GroqItineraryContract` tạo schema có đúng khóa ngày yêu cầu, các đối tượng đóng, trường bắt buộc và ID thuộc danh sách ứng viên. Dùng `$defs`/`$ref` cho phần lặp. Chỉ chuẩn hóa về mảng `days` hiện có khi toàn bộ khóa/ngày và cấu trúc khớp; không bỏ phần tử sai hoặc tạo ngày thiếu. Model ngoài danh sách hỗ trợ giữ `json_object`.
- `GenerationService` chia yêu cầu nguồn Sheet quá 3 ngày thành các phần tối đa 3 ngày. Các phần dùng cùng danh mục nguồn; truyền tiếp địa điểm đã dùng/loại trừ và chi phí đã tính. Chỉ trả kết quả ghép khi toàn bộ phần thành công. API trực tiếp và trình lập lịch đều dùng bước này.
- Giữ kiểm tra đủ ngày, ngày chính xác, ID nguyên trong danh sách, không trùng, thời lượng, mô tả, giờ tham khảo và ngân sách. Nguồn Sheet có thể ít hơn số điểm ưu tiên khi các thời lượng dài từ nguồn và khoảng chừa chiếm ít nhất 75% quỹ giờ; lịch ít điểm nhưng quá ngắn vẫn bị từ chối. Mức tối thiểu được yêu cầu rõ vẫn được giữ.
- Phản hồi sai được yêu cầu sửa một lần trong cùng phần, không thay bằng lịch giả. Ghi metadata lỗi gồm model, lần thử, số ngày, tên trường và trạng thái kết thúc; không ghi prompt, phản hồi thô, nội dung suy luận hay credentials.
- Giữ tối đa 4.000 token đầu ra, mô tả mỗi điểm gọn. Đã gặp HTTP 413 khi gửi 5 ngày cùng lúc và HTTP 429 khi gửi nhiều phần liên tiếp. Yêu cầu tối đa 3 ngày xử lý giới hạn kích thước; 429 chỉ được chờ/thử lại một lần khi `Retry-After` hợp lệ và không quá 45 giây. Header tính bằng giây theo [tài liệu hạn mức Groq](https://console.groq.com/docs/rate-limits). Khi không phục hồi vẫn báo lỗi thật; không đổi model/gói dịch vụ.
- Không đổi dữ liệu nguồn, cấu hình credentials, giá/booking, giao diện hoặc luồng lưu/mở lại/PDF. Đà Lạt và Phú Quốc vẫn dùng nguồn Sheet riêng, không quay lại tra địa điểm/ranh giới Geoapify.

## Xác nhận thực tế

Các lượt dưới đây dùng Groq thật, dữ liệu MySQL thật ở chế độ đọc, 2 người lớn, mốc bắt đầu tham khảo 15/11/2026. Không lưu lịch thử và không ghi Sheet. Khóa Geoapify được tắt riêng trong tiến trình xác nhận, không sửa `.env`.

| Trường hợp | Kết quả ghi nhận |
| --- | --- |
| Đà Lạt 1 ngày | Thành công, đủ 1 ngày, 3 điểm; kết thúc 16:15. |
| Đà Lạt 3 ngày | Thành công, đủ 3 ngày, lần lượt 3/2/2 điểm; kết thúc 16:45/15:45/15:45. |
| Đà Lạt 5 ngày | Thành công, đủ 5 ngày, 3/2/2/3/4 điểm. Hai phần 3+2 ngày; phần sau từng gặp 429, chờ theo header 22 giây rồi HTTP 200. |
| Đà Lạt 7 ngày | Thành công, đủ 7 ngày, 3/2/3/4/4/4/4 điểm; không lặp qua các phần 3+2+2 ngày. Các lượt chạm hạn mức phục hồi theo `Retry-After` 6/40/43 giây. |
| Phú Quốc 3 ngày, ngân sách 5 triệu đồng cả nhóm | Thành công, đủ 3 ngày, mỗi ngày 3 điểm, kết thúc 17:00; các khoản chưa có giá vẫn được báo thiếu, không coi ngân sách là đã đủ. |

Trong các lượt thành công: không trùng địa điểm, ID đúng nguồn/địa phương, Maps và thời lượng khớp bản ghi nguồn; không vượt khung kết thúc 17:30 và không có yêu cầu HTTP gửi Geoapify. Khoảng trống sau khi cân đối và chi phí thiếu tiếp tục được cảnh báo, không coi lịch đã kín hay mọi chi phí đã được xác minh. Nhãn ngày của cảnh báo được tính từ ngày đầu toàn chuyến để không quay lại “Ngày 1” ở phần thứ hai.

Đây là các lượt xác nhận hoạt động thực tế, không bảo đảm dịch vụ AI không bao giờ lỗi hoặc hết hạn mức. Chưa xác nhận thao tác lưu/mở lại/PDF trong trình duyệt cho đợt sửa này. Không chạy bộ kiểm thử tự động theo yêu cầu đã chốt.

## Danh sách tự kiểm tra

1. Tạo lại Đà Lạt 1, 3 và 5 ngày; kiểm tra đủ số ngày, không lặp địa điểm, link Maps đúng nguồn và có cảnh báo khi giá/tuyến đi chưa đủ dữ liệu.
2. Thử ngân sách cụ thể, sở thích, trẻ em và khung giờ ngắn; không được mất ràng buộc, cắt thời lượng nguồn hoặc ép đủ điểm bằng địa điểm giả.
3. Thử Phú Quốc và một điểm đến dùng nguồn cũ; xác nhận nguồn không bị trộn, các chức năng trước đây vẫn giữ.
4. Trong lịch đã sửa thủ công, yêu cầu đổi riêng ngày/buổi rồi thử Áp dụng, Bỏ qua và hoàn tác. Các phần ngoài phạm vi phải giữ nguyên.
5. Lưu, mở lại và tải PDF; so sánh số ngày/điểm/giờ. Nếu một phần tạo lỗi hoặc hết hạn mức, không được lưu lịch dở hay tự ghi đè lịch đang chỉnh.
