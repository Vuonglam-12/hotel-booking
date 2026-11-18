# Đối chiếu và chỉnh hiển thị giá ngày/mùa — 31/10/2026

## Dữ liệu thực tế và nguyên nhân

DB đang có **một** bản ghi giá riêng: `hotel_room_rate.id=1`, khách sạn `61` **Regent Phu Quoc**, loại `64` **Ocean View Suite**, giá ngày **31/10/2026 = 100.000 ₫**. Giá cơ bản riêng của loại này là **6.500.000 ₫/đêm**. Chưa có bản ghi giá mùa trong DB tại thời điểm kiểm tra.

Mức **400.000 ₫** thuộc khách sạn `1` **MEANDER Sai Gon**, loại `2` **Phòng tập thể nam nữ**; không có quy tắc giá riêng khớp loại phòng đó. Vì vậy 400.000 ₫ là giá cơ bản của một lựa chọn khác, không phải giá ngày đã lưu cho Regent. Backend lọc đúng hotel/type/ngày và chọn đúng quy tắc; không chuyển bản ghi giá giữa các khách sạn.

Phát hiện thêm hai hạn chế giao diện: báo giá được giữ trong bộ nhớ khi ngày/số lượng không đổi nên chưa tự tải lại sau khi sửa Admin; việc vẽ giá chờ các request của mọi loại phòng hoàn tất, dù loại đang chọn đã có kết quả. Đã bổ sung làm mới khi quay lại tab, khôi phục trang, nhận thông báo thay đổi bảng giá ở tab Admin, và vẽ ngay từng báo giá trả về. API dùng `no-store`, bỏ kết quả request cũ và kiểm tra quote hash lúc đặt vẫn giữ nguyên.

## Thay đổi giao diện

- Admin dùng chung một partial cho hai editor đang có. Tách thẻ giá cơ bản, form ngày/mùa và danh sách đã lưu; ngày/tháng định dạng dễ đọc; nút lưu/sửa/xóa rõ. Hiện tên khách sạn/loại phòng từ context backend và link mở đúng phòng/ngày phía khách để đối chiếu.
- Form mùa hiện ngày đầu/cuối, tính cả hai đầu; cùng ưu tiên vẫn chặn chồng chéo. Responsive 390 px không tràn ngang. Không sửa chức năng metadata, quản lý ảnh hoặc logic lưu giá.
- Khách có khối “Chi tiết giá” đóng/mở, bảng đêm → đơn giá × số phòng → thành tiền, tên giá ngày/mùa/cơ bản dễ hiểu, tiền phòng và tổng căn phải. Mục yêu cầu thêm và các phần còn lại giữ nguyên.
- Backend trả thêm `room_subtotal`, bằng tổng tiền phòng đang tính; không thay công thức, snapshots, thanh toán hoặc giá kinh doanh.

## Phụ thu

Source còn migration dịch vụ cũ, nhưng DB hiện **không có** bảng `service` / `booking_service`. Luồng `RoomPricingService → LocalBookingDemoService` chỉ tính giá từng đêm × số phòng; hóa đơn hiện ghi `service_total=0`, `discount=0`, `tax=0`. Không tìm được cơ chế phụ thu đang áp dụng vào luồng này.

Giao diện nói rõ chưa áp dụng phụ thu/giảm giá; không tạo phí, không đưa mã cách tính nội bộ lên UI. Giá ngày/mùa thay thế giá cơ bản cho đúng đêm, không cộng hai lần.

## Kiểm chứng

| Lựa chọn thực tế | Các đêm | Số phòng | Tổng backend và UI |
|---|---|---:|---:|
| Regent / Ocean View Suite | 31/10 | 1 | 100.000 ₫ |
| Regent / Ocean View Suite | 31/10 | 2 | 200.000 ₫ |
| Regent / Ocean View Suite | 31/10 và 01/11 | 2 | (100.000 + 6.500.000) × 2 = 13.200.000 ₫ |
| Regent / Ocean View Suite | 01/11 | 1 | 6.500.000 ₫ |
| MEANDER / Phòng tập thể nam nữ | 31/10 | 1 | 400.000 ₫ |

- Chrome headless: **11/11 kiểm tra đạt**, Admin và khách desktop/mobile 390 px; đổi ngày/số phòng bằng sự kiện UI, đối chiếu với public API, kiểm tra context Admin, sửa/hủy form không lưu, form mùa, làm mới tab, không có JavaScript exception.
- Test JavaScript: **9/9 giá/đối soát**, **7/7 hồi quy editor loại phòng** đạt. PHP syntax của service/controller đạt.
- Browser đọc DB thật qua server riêng loopback chỉ cho GET/HEAD và có SQL fence chặn ghi; dùng danh tính Admin chỉ trong bộ nhớ, không tạo/copy password hay token thật. Không sửa cấu hình auth hoặc route của website chính.
- Không POST/PUT/DELETE API trong lượt browser, không tạo booking/thanh toán và không đổi giá. Fingerprint toàn bộ quy tắc giá và giá cơ bản của hai khách sạn trước/sau khớp. Kiểm tra mùa trên form; không tự nhập giá mùa kinh doanh khi DB chưa có bản ghi.
- Bằng chứng máy đọc: `artifacts/pricing-review-20261031/browser-verification.json`. Server review tạm thời được dừng sau kiểm tra; giữ ảnh và log.

## Ảnh giao diện sau sửa

- [Admin desktop](../artifacts/pricing-review-20261031/admin-pricing-desktop.png)
- [Admin form mùa desktop](../artifacts/pricing-review-20261031/admin-season-form-desktop.png)
- [Admin mobile](../artifacts/pricing-review-20261031/admin-pricing-mobile.png)
- [Khách một đêm desktop](../artifacts/pricing-review-20261031/customer-pricing-desktop.png)
- [Khách hai đêm desktop](../artifacts/pricing-review-20261031/customer-pricing-two-nights-desktop.png)
- [Khách mobile](../artifacts/pricing-review-20261031/customer-pricing-mobile.png)
