# Bổ sung kiểm thử Admin giá ngày/mùa — 31/10/2026

Giữ nguyên code giao diện/backend sản phẩm. Chỉ bổ sung test, cấu hình test và công cụ staging cô lập. Không ghi giá hoặc dữ liệu vào DB chính.

## Môi trường và an toàn

- MySQL mới riêng trên loopback, cổng **59398**, 48 schema test mới. Tài khoản test không có quyền ghi DB ứng dụng hoặc quyền ghi global. Bootstrap kiểm tra host/port/database/user/UUID/grants trước khi chạy.
- Browser dùng một schema test mới riêng với dữ liệu được ghi rõ là kiểm thử. Chạy đúng Admin/customer controllers và Blade hiện tại, không bật V2 hoặc cổng thanh toán thật.
- Router staging chỉ nhận thay đổi giá của hotel/type test; từ chối ghi bảng booking/payment/invoice và các bảng không liên quan. Mail giả lập, chặn HTTP ngoài. Không sửa cấu hình hoặc token của website chính.
- Kiểm tra fingerprint trước/sau các bảng giá `hotel_room_rate`, `hotel_room_type`, `room_type`, `room`: **không thay đổi**. Bằng chứng không chứa credentials: `storage/app/private/pricing-admin-crud-verification/verification.json`.

## API Admin — đã chạy đạt

Lệnh chạy với biến môi trường trỏ tới owned `test-environment.json`:

```powershell
php vendor/bin/phpunit -c phpunit.room-pricing.xml --no-progress
```

**7 tests / 100 assertions đạt**, gồm các test có sẵn và hai test mới:

- Lưu/sửa/xóa cả giá ngày và giá mùa qua endpoint Admin có auth, đọc lại DB và public quote sau từng thay đổi.
- Giá ngày nằm trong mùa được phép và ưu tiên cao hơn giá mùa; các đêm khác dùng mùa hoặc cơ bản, không tính đêm checkout.
- Từ chối HTTP 422 khi tạo giá ngày trùng, mùa chồng lấn hoặc trùng biên; từ chối sửa ngày/mùa sang khoảng đã bị chiếm. Các bản ghi được giữ nguyên sau yêu cầu lỗi.
- Giữ kiểm tra auth, quan hệ hotel/type, giá chính xác từng đêm và snapshot bất biến của bộ test cũ.

## Chrome — đã chạy đạt

**12/12 kiểm tra đạt**, hai tab Admin/customer mở đồng thời trên DB test. Các thao tác tạo/sửa/xóa thực hiện bằng form và nút Admin thực tế; tab khách tự cập nhật từ tín hiệu thay đổi, không nạp lại trang hoặc giả response API.

Giá giả lập chỉ trong test: cơ bản **400.000 ₫**, mùa ban đầu **600.000 ₫**, ngày ban đầu **900.000 ₫**. Lưu trú 30/12/2030–02/01/2031, 2 phòng:

| Thao tác Admin | Tổng UI khách và API |
|---|---:|
| Chưa có giá riêng | 2.400.000 ₫ |
| Lưu giá mùa 600.000 | 3.200.000 ₫ |
| Lưu giá ngày 900.000, ngày ưu tiên mùa | 3.800.000 ₫ |
| Sửa mùa thành 650.000 | 3.900.000 ₫ |
| Sửa ngày thành 950.000 | 4.000.000 ₫ |
| Xóa giá ngày, quay về mùa | 3.400.000 ₫ |
| Xóa giá mùa, quay về cơ bản | 2.400.000 ₫ |

Browser cũng kiểm tra lỗi chồng chéo 422 giữ nguyên dữ liệu/tổng, đổi số phòng/ngày, checkout exclusive và không tràn ngang ở 390 px. Không có JavaScript exception. Các request ghi chỉ tới API giá của DB test.

## Bằng chứng và ảnh

- `tests/Feature/RoomPricingTest.php`: test API.
- `tests/Frontend/pricing-admin-crud.browser.cjs`: kiểm thử Chrome.
- `tests/Support/prepare_pricing_admin_staging.php`, `pricing_admin_staging_router.php`: fixture và router có guard.
- `artifacts/pricing-admin-test-20261031/browser-verification.json`: kết quả browser và trạng thái các request ghi.
- Ảnh Admin desktop/mobile và khách desktop/mobile ở cùng thư mục artifacts. Ảnh đã được gửi trực tiếp trong cuộc trò chuyện; giá trong ảnh là dữ liệu test, không phải thay đổi giá kinh doanh.
- Sau kiểm thử dừng riêng HTTP/MySQL test, giữ nguyên file và dữ liệu test; không dừng website, MySQL Laragon chính hoặc Scheduler.
