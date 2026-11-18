# Phụ thu ngày/mùa — 01/11/2026

## Đã sửa và áp dụng

- `RoomPricingService` tính tiền mỗi đêm bằng giá cơ bản + phụ thu ngày, hoặc phụ thu mùa nếu không có ngày; không có quy tắc thì +0. Không cộng hai phụ thu và không tính đêm trả phòng. Giữ khóa giao dịch, giới hạn và chống chồng chéo.
- Admin giữ bố cục hiện tại, đổi nhãn/giải thích phụ thu. Danh sách phân biệt phụ thu cộng thêm với bản ghi giá thay thế cũ. Khi sửa bản ghi cũ chưa rõ ý nghĩa, không điền sẵn số tiền vào ô phụ thu; phải nhập lại và xác nhận chuyển đổi.
- Chi tiết giá khách thể hiện giá cơ bản, phụ thu, đơn giá, số phòng và thành tiền mỗi đêm. Báo giá tự cập nhật như luồng hiện có; nếu giá thay đổi với cùng lựa chọn, chặn nút đặt cho đến khi khách xác nhận giá mới. Giữ kiểm tra quote hash phía server.
- Đơn mới lưu riêng giá cơ bản/phụ thu và đơn giá từng đêm. Checkout, chi tiết hóa đơn và PDF đọc snapshot; không tính lại theo bảng giá hiện tại. Đơn cũ không có phân tách thì giữ giá đã lưu, không đoán phần phụ thu.
- Không bật V2 hoặc thanh toán thật; không sửa QR Banking, OTP, Google Login, yêu cầu thêm hay bố cục khác.

## Dữ liệu thực tế

- Đã đọc lại DB sau khi người dùng bật Laragon. Regent Phu Quoc (`hotel_id=61`), Ocean View Suite (`room_type_id=64`): giá cơ bản loại phòng và hai phòng vật lý đều `6500000.00`. Không có bằng chứng bị ghi đè, không khôi phục/đoán giá.
- Đã áp dụng migration bổ sung ý nghĩa quy tắc và thành phần snapshot, không reset DB. Chỉ đổi `hotel_room_rate.id=1`, ngày 31/10/2026, `100000.00` sang phụ thu theo xác nhận người dùng. Số tiền/ngày/bản ghi giữ nguyên; kết quả đêm này là `6600000.00` mỗi phòng.
- Bảy bản ghi mùa khác hiện có giữ ý nghĩa giá thay thế, không chuyển thành phụ thu. Dữ liệu so sánh toàn bộ bảng giá, giá cơ bản/phòng, booking, booking_room, snapshot, payment, invoice, invoice_item trước–sau giữ nguyên các cột cũ. Nhật ký đối chiếu nằm trong `storage/app/private/pricing-surcharge-conversion/`, không chứa credentials.
- Ngày 31/10/2026 đã qua. Không đổi ngày áp dụng trên DB chính để tạo dữ liệu kiểm tra cho hôm nay.

## Phạm vi đã kiểm chứng

- Trước yêu cầu dừng test: bộ giá trên MySQL test riêng đạt **9 tests / 114 assertions**; JavaScript đạt **10 ca** (runner báo một file thành công). Đã kiểm tra cú pháp các file PHP sửa.
- Kiểm thử bổ sung checkout/hóa đơn chưa chạy thành công: MySQL test riêng đã ngắt kết nối, lỗi kết nối xảy ra trước assertion. Không ghi nhận đây là ca đạt.
- Theo yêu cầu mới nhất của người dùng, dừng chạy thêm kiểm thử; các mục dưới đây dành cho người dùng tự kiểm tra. Ảnh giao diện chỉ là chụp hiển thị, không thay cho kiểm thử chức năng.
- Đã chụp ảnh Admin và khối giá khách qua server loopback chỉ cho đọc/GET; riêng ảnh khách xem lại ngày 31/10/2026 để hiện đúng bản ghi được xác nhận, không sửa ngày DB chính. Ảnh: `artifacts/surcharge-20261101/admin-surcharge.png` và `customer-surcharge.png`, đã gửi trực tiếp trong cuộc trò chuyện. Server chụp tạm đã dừng; không dừng dịch vụ Laragon của người dùng.

## Danh sách tự kiểm tra

Dùng DB test/bản sao riêng cho các thao tác thay giá, tạo đơn và giả lập thanh toán; không sửa giá kinh doanh DB chính chỉ để thử. Chọn ngày nhận phòng tương lai, gọi là D; giá cơ bản fixture 6.500.000 ₫. Các số dưới đây là dữ liệu thử, không phải thay đổi giá kinh doanh.

1. **Admin lưu/sửa/xóa:** thêm phụ thu ngày D = 100.000; ô có nhãn “Phụ thu ngày/mùa (₫/phòng/đêm)”, giải thích cộng vào giá cơ bản; danh sách hiện +100.000 và giá cơ bản vẫn 6.500.000. Sửa/xóa cập nhật danh sách.
2. **Một đêm:** nhận D, trả D+1, một phòng: cơ bản 6.500.000 + phụ thu 100.000 = **6.600.000 ₫**.
3. **Không có phụ thu:** đêm ngoài quy tắc tính **6.500.000 ₫**, hiện phụ thu 0.
4. **Ưu tiên ngày/mùa:** mùa D…D+2 = 200.000, ngày D+1 = 100.000. Đêm D+1 tính **6.600.000 ₫**, không tính 6.800.000.
5. **Nhiều đêm/phòng:** nhận D, trả D+3, hai phòng; ba đêm lần lượt 6.700.000 / 6.600.000 / 6.700.000, tổng **40.000.000 ₫**. Không xuất hiện đêm D+3.
6. **Chặn chồng chéo:** tạo/sửa phụ thu ngày trùng ngày; tạo/sửa mùa giao nhau, kể cả chung ngày biên: báo lỗi, dữ liệu trước đó giữ nguyên. Ngày nằm trong mùa vẫn được phép.
7. **Cập nhật báo giá:** mở tab khách, sửa phụ thu ở Admin. Khách quay lại tab thấy giá mới; nút đặt bị khóa đến khi xác nhận giá mới. Đổi ngày/số phòng cập nhật đúng. Mở sẵn báo giá cũ rồi gửi sau khi giá đổi phải nhận yêu cầu xem lại giá.
8. **Đơn đã tạo:** đặt đơn với phụ thu 100.000; sau đó sửa thành 200.000 hoặc xóa quy tắc. Tổng đơn và giá từng đêm ở checkout/chi tiết đơn phải giữ nguyên.
9. **Hóa đơn:** giả lập thành công DEMO cho đơn thử; hóa đơn/API/PDF có cùng tổng đơn đã lưu và cùng chi tiết cơ bản/phụ thu từng đêm; không nhân giá bình quân gây sai lệch, không cộng phụ thu thêm lần nữa.
10. **Dữ liệu cũ và hồi quy:** bản ghi cũ chưa xác định vẫn ghi “Giá thay thế cũ — chưa chuyển”, không cộng vào giá cơ bản; đơn cũ không bị sửa. Kiểm tra khối giá trên điện thoại và mục yêu cầu thêm, QR Banking, OTP, Google Login vẫn hoạt động.
