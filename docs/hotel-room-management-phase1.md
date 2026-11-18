# AUREL — Hotel & Booking: kết quả Phase 1

Ngày thực hiện: 11/10/2026. Căn cứ: [Hotel & Room Management Audit](hotel-room-management-audit.md) và yêu cầu Phase 1.

Đã sửa các lỗi phù hợp với schema hiện tại và kiểm thử lại. Mô hình lai `room` / `room_type` / `booking_room` được giữ nguyên. Checkout trên Hotel Detail vẫn bị khóa. Không triển khai Phase 2, đổi Authentication/role/Staff, chạy migration/seeder hoặc commit/push.

## 1. Lỗi đã sửa và nguyên nhân

| Mục audit | Nguyên nhân | Kết quả sau sửa |
| --- | --- | --- |
| H05 — thiếu kiểm tra sức chứa | Chỉ kiểm tra `num_guests >= 1`; service không so với capacity và quantity. | Backend kiểm tra `num_guests <= capacity × quantity` ở BookingService và API kiểm tra khả dụng. Chọn loại dùng RoomType.capacity; đường tương thích chọn phòng cụ thể dùng Room.capacity thực tế. Vượt sức chứa trả 422 với lỗi `num_guests`; gọi service trực tiếp cũng không bỏ qua được. |
| H03 — mất tiện nghi khi sửa Hotel | Form thiếu `room_service`, dùng `airport`; server xóa rồi tạo lại danh sách. | Danh mục dùng chung có đủ các key đang hỗ trợ; `airport` được chuẩn hóa thành `airport_shuttle`. Không gửi amenities khi form không đổi. Backend giữ nguyên khi trường vắng; `[]` xóa có chủ đích; `null`/sai kiểu bị từ chối. Khi sửa danh sách chỉ xóa mục bỏ chọn/thêm mục mới, giữ ID của mục còn lại. Tiện nghi legacy đang có vẫn được hiển thị và giữ được. |
| H09 — validation lệch schema | Tên/địa chỉ cho phép dài hơn cột; một số trường thiếu giới hạn/kiểu. | Tên tối đa 200, địa chỉ 300, phone 20, email 100; description giới hạn 65.535 byte theo TEXT, kiểm tra giờ và tọa độ. RoomType.base_price giới hạn theo DECIMAL(12,2). Dữ liệu không hợp lệ bị chặn trước ghi; không tăng độ dài cột. |
| H10 — không xóa được trường nullable | `array_filter` bỏ mọi giá trị null khi cập nhật. | Phân biệt trường vắng với null có chủ đích. Email/phone/description có thể xóa. Latitude/longitude hiện NOT NULL nên null được chuyển về 0 theo cấu trúc đang dùng, không lưu NULL trái schema. |
| H06 — History sai số phòng | Đếm số dòng quan hệ thay cho quantity. | Cộng `booking_room.quantity`, hiển thị từng loại và số lượng; quantity thiếu/sai được hiển thị chưa xác định, không đoán bằng số dòng. Tổng tiền dùng booking.total_price đã lưu; không tính lại bằng giá hiện tại. |
| H12 — Admin Booking thiếu loại/số lượng | API chỉ load phòng vật lý và room_numbers. | Load bookingRooms.roomType, thêm room_quantity và room_type_summary; bảng/modal Admin hiển thị loại và tổng số phòng cho cả booking không gán room_id. Giữ trường cũ để tương thích. |
| H04/H07 — ảnh không đúng nguồn | Admin dùng Picsum; History đọc image_path dù schema dùng image_url. | Dùng cover_image_url/image_url qua helper ảnh có sẵn, ảnh thật hợp lệ và placeholder cố định `public/image/placeholders/accommodation.svg`. Sửa thêm các binding ảnh khách sạn cùng lỗi tại Customer Dashboard, Wishlist và gợi ý khách sạn trong Itinerary. Không sửa dữ liệu ảnh, nhân bản ảnh hoặc thêm upload. |
| H08 — KPI lệch phạm vi | Total lấy toàn bộ nhưng active/inactive/rating lấy trang hiện tại. | Backend aggregate trên toàn bộ query sau cùng bộ lọc search/status/star, trước paginate. Frontend dùng stats của server; closed và other hiển thị riêng, không gộp vào inactive. |
| H01 — trùng số phòng khi thêm loại | Vòng tạo phòng luôn bắt đầu từ các số đã có. | Khi tạo loại/phòng, khóa Hotel và bỏ qua room_number đã dùng, giữ unique `(hotel_id, room_number)`. Không đổi dữ liệu phòng cũ. |
| H02 — ghi dở dang | Tạo Hotel/amenities và RoomType/nhiều Room thiếu transaction bao toàn bộ. | Bao thao tác trong transaction; tạo RoomType thất bại ở một Room sẽ rollback toàn bộ loại và các Room vừa tạo. Update Hotel/amenities khóa Hotel trong cùng transaction. |

Đối chiếu danh mục Admin/API/Hotel Detail: `wifi`, `pool`, `gym`, `spa`, `restaurant`, `bar`, `parking`, `airport_shuttle`, `breakfast`, `room_service`. Không thêm tiện nghi mới hoặc sửa các bản ghi hiện có trên database chính.

### Hợp đồng kiểm tra khả dụng

- `/api/admin/rooms/availability` cần `quantity` (1–10) và `num_guests` (số nguyên dương) bên cạnh Hotel/Type/ngày. Thiếu hai trường trả 422; phòng cụ thể chỉ cho quantity = 1. `available` xét đủ số lượng đã chọn, không chỉ còn ít nhất một phòng.
- `/api/hotels/{id}/rooms` vẫn phục vụ danh sách tồn để duyệt trước khi chọn loại. Khi gửi loại đã chọn và `num_guests`/`quantity`, API kiểm tra sức chứa và trả lỗi rõ ràng; giữ alias `guests` cũ. Việc duyệt tồn không truyền số khách không tạo quyền đặt phòng: POST Booking luôn kiểm tra lại.
- Hotel Detail gửi số khách/số phòng khi kiểm tra loại đã chọn, hiển thị lỗi từ server và giữ nguyên quantity. Không tự thêm phòng, chính sách trẻ em, giường phụ hoặc phụ thu; không mở checkout.

## 2. Lỗi mới đã tái hiện và sửa

Tái hiện bằng dữ liệu fixture trong MySQL riêng, không dùng booking thật:

| Bằng chứng trước sửa | Cách xử lý | Kiểm tra sau sửa |
| --- | --- | --- |
| Khôi phục booking cancelled quantity=2 khi đã có confirmed quantity=2, stock=3: Admin API trả 200, tạo tổng cam kết 4/3. | Khóa Hotel rồi Booking; khi chuyển từ cancelled/expired về trạng thái giữ tồn, kiểm tra lại toàn khoảng lưu trú và phòng cụ thể nếu có. | Trả 422; booking vẫn cancelled. Nếu đủ tồn thì khôi phục được, giá đã lưu không đổi. |
| Job đã scan pending hết hạn; Admin xác nhận trước khi job xử lý; job vẫn ghi cancelled từ dữ liệu cũ. | Job dùng cùng thứ tự khóa Hotel → Booking và đọc lại status/expires_at sau khóa. | Booking đã confirmed không bị hủy. Kiểm thử bằng hai tiến trình và barrier tại thời điểm scan. |
| Job hết hạn cộng available_count từ 3 lên 4 và giảm booked_count dù luồng tạo không trừ ledger. | Bỏ cộng/trừ ledger cũ ở job. Tồn hiện được suy ra từ booking đang giữ chỗ qua RoomAvailabilityService. | Ledger fixture giữ 3/0; booking hết hạn cancelled và tồn tính từ booking được giải phóng đúng. Giữ bảng room_availability. |
| Tổng giá nhân số đêm/số phòng vượt DECIMAL(12,2) gây lỗi SQL/thông báo chung. | Chặn tổng không hữu hạn hoặc vượt 9.999.999.999,99 trước insert. | Trả 422 `total_price`, không tạo Booking. Công thức giá trong giới hạn được giữ nguyên. |

Việc khôi phục đơn đã hủy là một lỗi overbooking riêng đã có bằng chứng. **Không kết luận luồng tạo Booking thông thường đang overbook:** hai request tạo cạnh tranh đã được kiểm thử và không tái hiện vượt tồn.

## 3. File thay đổi trong Phase 1

Backend:

- `app/Http/Controllers/AdminController.php`: Hotel validation/amenities/KPI, tạo loại/phòng an toàn, dữ liệu Admin Booking và kiểm tra tồn khi khôi phục.
- `app/Http/Controllers/HotelController.php`: lọc/kiểm tra sức chứa theo số phòng tại API công khai.
- `app/Http/Controllers/RoomController.php`: kiểm tra khả dụng với quantity/num_guests.
- `app/Http/Controllers/WishlistController.php`: đọc gallery hiện có và trả cover; các action ghi/kiểm tra người dùng giữ nguyên.
- `app/Services/BookingService.php`: validation sức chứa/tổng giá và cover trong History/detail.
- `app/Services/RoomAvailabilityService.php`: bổ sung hàm validateCapacity dùng chung; giữ thuật toán sự kiện tồn hiện tại.
- `app/Console/Commands/CancelExpiredBookings.php`: kiểm tra lại booking sau khóa, bỏ credit ledger không có debit tương ứng.
- **Mới** `app/Support/HotelAmenityCatalog.php`: danh mục và alias tiện nghi dùng chung.

Giao diện, chỉ sửa binding dữ liệu và validation liên quan:

- `resources/views/admin/hotels.blade.php`: danh mục tiện nghi, payload có chủ đích, KPI toàn phạm vi, ảnh thật, giới hạn input.
- `resources/views/admin/bookings.blade.php`: loại phòng/số lượng trong bảng và modal.
- `resources/views/user/bookings/bookings-script.blade.php`: quantity, snapshot tiền và ảnh.
- `resources/views/user/layouts/user.blade.php`: include helper ảnh có sẵn; không sửa auth script.
- `resources/views/partials/hoteldetail/amenities-section.blade.php`: mapping/hiển thị tiện nghi hiện có.
- `resources/views/partials/hoteldetail/rooms-script.blade.php`: nhãn tồn chưa xác định và refresh sau chọn loại.
- `resources/views/partials/hoteldetail/sidebar-script.blade.php`: gửi pax/quantity, lỗi backend và refresh debounce.
- `resources/views/Dashboard.blade.php`: chỉ binding ảnh khách sạn.
- `resources/views/user/itineraries/itineraries-script.blade.php`: chỉ binding ảnh gợi ý khách sạn.

Kiểm thử và báo cáo:

- `tests/Feature/BookingEligibilityTest.php`: cập nhật fixture token Admin để đi qua middleware hiện tại và tham số kiểm tra khả dụng; không nới middleware.
- **Mới** `tests/Feature/HotelBookingPhaseOneTest.php`.
- **Mới** `tests/Frontend/hotel-booking-phase1.test.cjs`.
- **Mới** `tests/Support/run_hotel_booking_phase1.php`.
- **Mới** `tests/Support/hotel_booking_phase1_worker.php`.
- **Mới** `phpunit.hotel-booking-phase1.xml`.
- **Mới** báo cáo này: `docs/hotel-room-management-phase1.md`.

Không xóa file trong Phase 1. Các Models Hotel/RoomType/Room/Booking/BookingRoom, BookingController, routes, Authentication, .env, migration và các thay đổi UI trước đó được giữ nguyên so với lúc bắt đầu. Trong 121 file đã có thay đổi/untracked trước task, 110 khớp SHA-256 ban đầu; 11 file được chỉnh tiếp thuộc phạm vi trên. Không reset/stash hoặc ghi đè công việc trước.

## 4. Kết quả kiểm thử

| Loại kiểm tra | Kết quả | Phạm vi và giới hạn |
| --- | --- | --- |
| PHPUnit trên MySQL tạm | **86 tests, 455 assertions, đạt** | 51 tests hiện có + 35 trường hợp Phase 1, gồm các dataset. Clone schema, tạo fixture chỉ trong database tạm; không copy dữ liệu chính. |
| PHPUnit Authentication | **17 tests, 178 assertions, đạt** | SQLite RAM; login/logout, quyền/token và tách phiên Admin/Customer. Không đăng nhập tài khoản thật. |
| PHPUnit Hotel Detail Presentation | **8 tests, 57 assertions, đạt** | Đọc dữ liệu hiện có bằng connection READ ONLY; kiểm tra render và checkout vẫn khóa. |
| Node frontend | **33 tests, đạt** | 16 Phase 1 + 17 Authentication hiện có. History 1/2/3 phòng, nhiều loại, tiền lưu, thiếu quantity/ảnh, amenities payload, KPI và checkout khóa. |
| Chrome tự động | **12 kiểm tra, đạt** | Render trang thật; API bị chặn/intercept. Admin Hotel dùng response đọc từ dữ liệu thật; History dùng fixture 1/2/3, capacity error dùng response mô phỏng. Không gửi request ghi tới API thật. |
| Blade/JavaScript/PHP | **Đạt** | Render 5 view Admin Hotels, Admin Bookings, Booking History, Itinerary, Customer Dashboard; parse 23 inline scripts. PHP lint các file PHP sửa/thêm; git diff --check đạt. |

Browser kiểm tra KPI 21 Hotel / 11 active / 10 inactive, ảnh thật và form tiện nghi Admin, mobile Admin, History card/detail quantity và tiền, giữ quantity khi Hotel Detail báo vượt sức chứa, checkout disabled; không có uncaught exception trong lượt kiểm tra. Screenshot và kết quả kỹ thuật nằm tại `storage/app/private/hotel-booking-phase1/` (Git ignore). Ảnh fixture History chỉ phục vụ kiểm thử, không phải dữ liệu Hotel đã tạo trên database chính.

Các trường hợp tồn/Booking đã kiểm tra:

| Trường hợp | Kết quả |
| --- | --- |
| Hai request cùng loại/cùng ngày; hai khoảng giao nhau | Hai tiến trình thật, quan sát chờ row lock trong MySQL; chỉ một đơn thắng khi stock=1. Không vượt tồn. |
| Checkout và check-in kế tiếp | Checkout không chiếm đêm kế tiếp; stock được tính đúng tại ranh giới. |
| Pending/confirmed/cancelled | Pending và confirmed giữ tồn; cancelled giải phóng, không thay snapshot quantity/giá. |
| Khôi phục cancelled | Thiếu tồn bị chặn, đủ tồn được phép; đã sửa lỗi mới nêu trên. |
| Job hết hạn cạnh tranh xác nhận/tạo đơn | Xác nhận sau scan không bị job hủy; booking mới và job không làm vượt tồn. Nếu request đến trước giải phóng tồn, có thể phải thử lại. |
| Giảm tồn dưới số đã cam kết | Trong DB test, xóa trực tiếp 2 Room để stock=1 khi committed=3: available=0, booked=3, booking vẫn giữ nguyên. Đây là rủi ro của thao tác giảm tồn ngoài luồng, chưa có Admin API điều chỉnh tồn để áp quy tắc. |

Lệnh chạy lại bộ Phase 1 an toàn:

```powershell
php tests/Support/run_hotel_booking_phase1.php
node --test tests/Frontend/admin-authentication.test.cjs tests/Frontend/hotel-booking-phase1.test.cjs
```

Runner chỉ nhận filter/stop flags; không cho override config/bootstrap. Connection nguồn chỉ đọc schema trong READ ONLY. Connection ghi riêng tạo database tên ngẫu nhiên `holidayviet_booking_test_<12 hex>`, xác minh tên thực trước fixture, từ chối database nguồn và chỉ dọn database do chính lần chạy tạo. Lượt cuối dùng `holidayviet_booking_test_28d717472241`; kiểm tra sau chạy xác nhận database này đã được xóa. Không chạy migration/seeder trên database chính hoặc database test.

Chưa kiểm thử thủ công login/tạo booking bằng tài khoản thật; không dùng hoặc reset thông tin đăng nhập thật. Kiểm thử payment gateway/callback/refund đầy đủ chưa nằm trong kết quả này.

## 5. Database chính và bảo toàn dữ liệu

Đích đã xác minh: **mysql / 127.0.0.1:3306 / hotel_booking / MySQL 8.0.30**. Trước và sau sửa, lấy SHA-256 cho nội dung từng bảng theo id và SHOW CREATE TABLE bằng transaction READ ONLY; kết thúc rollback. Các lệnh sửa/kiểm thử không thực hiện DML/DDL lên database chính.

- **25/25 schema không đổi**; migrations vẫn 66 dòng, Staff không xuất hiện lại.
- **24/25 bảng có dữ liệu khớp hoàn toàn**. Hotel=21, RoomType=6, Room=336, HotelAmenity=190, HotelImage=21, RoomImage=336; Booking/BookingRoom/Payment/Invoice vẫn 0; Customer vẫn 1 và toàn bộ bản ghi tài khoản khớp ban đầu.
- **Ngoại lệ cần nói rõ:** bảng `personal_access_tokens` vẫn 1 dòng nhưng `last_used_at` và `updated_at` tiến từ `2026-10-11 05:46:17` sang `2026-10-11 07:05:48` ở lần đối chiếu cuối. Thay lại hai timestamp này trong bản sao chỉ trong RAM cho SHA-256 trùng snapshot ban đầu: các trường khác, gồm ID, tên, giá trị token, quyền và hạn hết hiệu lực, không đổi. Không ghi/khôi phục timestamp.
- Sanctum hiện có cập nhật last_used_at/save khi token được dùng (`vendor/laravel/sanctum/src/Guard.php`). Thay đổi metadata phù hợp với hoạt động phiên đang dùng; không xác định request/phiên cụ thể đã phát sinh cập nhật. Vì vậy **không tuyên bố mọi byte của toàn bộ database không đổi**; schema, tài khoản và dữ liệu nghiệp vụ được xác nhận không đổi, còn hai timestamp hoạt động token có thay đổi.

## 6. Phần còn lại và Phase 2

1. **H11 — RoomType thuộc từng Hotel:** hiện 5 loại vẫn dùng chung 21 khách sạn. Chưa thêm hotel_id/total_rooms/status hoặc chuyển dữ liệu; cần thiết kế ownership, migration và kế hoạch chuyển đổi riêng.
2. **Giảm tồn an toàn:** trước khi bổ sung chức năng điều chỉnh số lượng, cần kiểm tra cam kết theo từng ngày và khóa cùng luồng tạo/khôi phục. Giảm vật lý dưới cam kết tạo thiếu hụt dù API không nhận thêm booking; không tự hủy hoặc sửa đơn cũ để che thiếu hụt.
3. **Nguồn giá và sức chứa:** room.price/capacity và room_type.base_price/capacity vẫn là cấu trúc lai. Mẫu dữ liệu hiện có khớp; quản lý cập nhật từng khách sạn và bỏ phòng vật lý cần Phase 2. Không tự đồng bộ giá toàn cục hoặc đổi snapshot Booking.
4. **CRUD và ảnh/chính sách:** sửa/xóa/tắt loại, upload/gallery, room amenities/policies và tồn theo ngày cần đặc tả riêng. Không thêm API nghiệp vụ mới trong Phase 1.
5. **Thanh toán và chuyển trạng thái:** giữ cơ chế cash/banking, hạn 15 phút và side effect hiện tại. Chưa chứng minh an toàn mọi race giữa cancel/confirm/payment callback; cần kiểm thử và quyết định state machine/refund riêng. Không mở checkout trước phê duyệt.
6. **UI ngoài phạm vi dữ liệu:** History mobile còn horizontal overflow ở bố cục có sẵn, cần lượt CSS riêng. Picsum còn ở ảnh thành phố trên Customer Dashboard, không phải ảnh Hotel/Room; không thay máy móc các ảnh nội dung khác.

Phase 1 kết thúc tại đây. Chờ phê duyệt trước mọi thay đổi kiến trúc hoặc triển khai Phase 2.
