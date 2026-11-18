# Lịch trình AUREL — giai đoạn 1

Phạm vi: lưu thông tin chuyến đi v2, ngày thực, nền tảng nguồn/chi phí,
quyền sở hữu, ghi lặp và phiên bản chỉnh sửa. Không gọi Geoapify/AI,
không tạo hoạt động, không báo giá phòng, không đổi frontend hiện tại.
Không có endpoint chat v2. Không bật RoomType V2 hoặc catalog trải nghiệm.

## API đã triển khai

Tất cả endpoint dưới `/api/v2/itineraries` dùng `auth:sanctum` và phiên khách
hàng; phiên Admin không được dùng. Khi migration chưa được áp dụng, API
trả 503. Không tự chạy migration trong request.

| Method / đường dẫn | Contract |
| --- | --- |
| GET `/destinations?q=Sài%20Gòn` | Danh sách location được hỗ trợ. Từ khóa Sài Gòn tìm lựa chọn Hồ Chí Minh có ID thật. Không tạo location. |
| POST `/` | Tạo thông tin chuyến đi ở trạng thái `draft`, `schema_version=2`, `revision=1`. |
| GET `/?status=draft&per_page=20` | Chỉ chuyến v2 của chủ sở hữu. `status`: draft/saved; phân trang tối đa 100. |
| GET `/{id}` | Chi tiết chuyến v2 và ngân sách tính từ dữ liệu hiện có. |
| PUT `/{id}` | Thay toàn bộ thông tin bản nháp; gửi các trường bắt buộc như POST và `expected_revision`. Các trường tùy chọn bỏ qua trở về null/rỗng. Không dùng PUT để đổi trạng thái. |
| PATCH `/{id}` | Chỉ đổi `status` giữa draft/saved, kèm `expected_revision`. Saved không đồng nghĩa đã kiểm tra hay đủ tiền. Chuyển về draft trước khi PUT. |
| DELETE `/{id}` | Xóa chuyến v2 của mình, kèm `expected_revision`; trả 204. |

POST/PUT/PATCH/DELETE bắt buộc header `Idempotency-Key`: 1–64 ký tự
`A-Z a-z 0-9 . _ -`. Khóa phân biệt chữ hoa/thường, có phạm vi từng khách hàng, dùng chung cho các
loại thao tác. Cùng khóa + cùng payload/thao tác trả lại snapshot phản hồi
đầu tiên; khác payload/thao tác trả 409. Payload được chuẩn hóa thứ tự
khóa object, nhưng giữ thứ tự array. `Idempotency-Replayed: true/false`
cho biết yêu cầu có được phát lại hay không. Không tái tạo chuyến đã xóa.

Revision không khớp trả 409. Không có quyền sở hữu hoặc ID thuộc lịch v1
trong API v2 trả 404. Dữ liệu đầu vào không hợp lệ trả 422. Không chấp nhận
`customer_id`, `destination`, giá/hoạt động/phương án phòng do client gửi
để ghi các dữ liệu do backend phải xác minh.

## Payload POST/PUT

Các giá trị số nguyên phải là số nguyên JSON, không nhận chuỗi số, boolean
hoặc số thập phân thay thế. Tiền là VND nguyên; giới hạn lưu trữ hiện tại
của DECIMAL(12,2) cho số tiền đầu vào là 9.999.999.999 VND.

| Trường | Quy tắc |
| --- | --- |
| `location_id` | Bắt buộc, INT tồn tại trong location và được bật trong itinerary_destination. |
| `start_date`, `end_date` | Bắt buộc YYYY-MM-DD; kỳ đi 1–30 ngày theo lịch, tính bao gồm hai đầu. Tái sử dụng cột cũ; total_days luôn được service tính và cập nhật cùng transaction. |
| `adults` | Bắt buộc số nguyên 1–8. |
| `children`, `infants` | Bắt buộc là danh sách, có thể rỗng. Mỗi phần tử `{ "age": 8, "unit": "months" }`; age nguyên không âm, unit months/years. Tổng người lớn và hai danh sách tối đa 8. Không tự phân loại lại theo tuổi hoặc coi miễn phí. |
| `stay_mode` | aurel_hotel / own_accommodation / day_trip. |
| `selected_dates` | Bắt buộc danh sách 1–5 ngày khác nhau trong kỳ đi. Đây là lựa chọn chuẩn bị lên lịch, chưa phải ngày đã có hoạt động. |
| `arrival_time`, `departure_time` | HH:mm hoặc null. day_trip bắt buộc cả hai, cùng ngày và giờ kết thúc sau giờ bắt đầu. |
| `activity_window_start`, `activity_window_end` | Khung giờ hoạt động dự kiến HH:mm, có thể chưa biết. Nếu nhập phải có cả hai, giờ kết thúc sau giờ bắt đầu. Không gửi hai trường này cho day_trip: khung giờ lấy từ arrival/departure. |
| `activity_window_confirmed` | Bắt buộc boolean. Qua đêm muốn true phải nhập khung giờ dự kiến; false/null giờ không được tự điền mặc định. |
| `lodging_check_in`, `lodging_check_out` | Chỉ aurel_hotel; nhập thành cặp, nằm trong kỳ đi, checkout > checkin. Khoảng thuê dùng [checkin, checkout), không tính đêm checkout. Có thể để null trong bản nháp. |
| `hotel_id` | Tùy chọn cho aurel_hotel, chỉ một khách sạn đang hoạt động thuộc location đã chọn; nếu chọn khách sạn phải có kỳ thuê. Chọn hotel chưa tạo phương án phòng hoặc bảo đảm đủ phòng. |
| `estimated_budget` | Bắt buộc nguyên dương; tái sử dụng cột hiện có làm tổng ngân sách nhóm. Không có giá trị mặc định. |
| `contingency_amount` | Số tiền cố định, nguyên không âm hoặc null; null khác 0. |
| `free_day_allowances` | Danh sách `{ "date": "2030-01-02", "amount": 0 }`; ngày duy nhất trong kỳ đi, amount nguyên không âm hoặc null. Ngày không có entry được lưu null. |
| `title` | Tùy chọn, tối đa 200 ký tự; mặc định nhãn từ location thật. |
| `transport`, `pace` | Phương tiện dạng chuỗi tối đa 100; pace relaxed/moderate/packed hoặc null. Chưa áp đặt danh sách phương tiện hoặc giả lập định tuyến. |
| `interests`, `food_restrictions` | Hai danh sách độc lập, tối đa 30 phần tử. Không xác nhận khả năng đáp ứng dị ứng. |
| `requirements` | Yêu cầu riêng tối đa 5.000 ký tự. |

Ngày chưa có hoạt động luôn có planning_status=free, kể cả ngày đã chọn.
PUT chưa được dùng khi chuyến đã có item/snapshot/chi phí/phương án phòng:
trả 409 để tránh mất dữ liệu trước giai đoạn triển khai kiểm tra lại lịch.

## Nguồn dữ liệu và migration

Migration mới: `2026_10_23_000002_add_itinerary_trip_management.php`.
Không sửa migration cũ, không drop cột/bảng, không chuyển đổi/xóa lịch cũ.
Migration forward-only: down() chủ động từ chối xóa cấu trúc.

- itinerary: thêm phiên bản cấu trúc, revision, thành phần nhóm, lựa chọn
  lưu trú, giờ/khung giờ và xác nhận, sở thích, dự phòng. Không thêm bản sao
  ngân sách hoặc kỳ đi. `total_days` là giá trị dẫn xuất do service đồng bộ.
- itinerary_destination: chỉ FK location_id và cờ hỗ trợ, không sao chép tên
  địa phương. Khi áp dụng migration, bật các ID location đang tồn tại;
  location được thêm sau đó cần được bật hỗ trợ rõ ràng.
- itinerary_region_mapping: tách provider_region_id khỏi ID nội bộ, có
  trạng thái/bằng chứng xác minh và boundary. Migration không điền mapping.
  Chỉ chấp nhận trạng thái verified + thời điểm + mã vùng + bằng chứng vùng
  hành chính. Evidence radius/nearby không được chấp nhận. Chưa có luồng
  lấy/kiểm tra ứng viên trong giai đoạn này.
- itinerary_calendar_day: đủ mọi ngày của chuyến, unique(itinerary_id,date),
  lựa chọn lên lịch, trạng thái và dự trù. Nguồn duy nhất của selected_dates
  là các dòng này, không lưu thêm JSON selected_dates trên itinerary.
- itinerary_place_snapshot: nguồn, ID địa điểm nhà cung cấp, snapshot,
  mapping và trạng thái xác minh vùng. Có liên kết attraction nullable;
  không tự chép Geoapify vào catalog attraction dùng chung.
- itinerary_lodging_room: nền tảng phối nhiều room_type trong cùng khách
  sạn của chuyến theo từng đêm; có snapshot chính sách và nhóm đã xác minh.
  Chưa có API ghi phương án phòng và chưa có giá/phòng giả.
- itinerary_cost_line: nguồn chính của chi phí v2, có đơn vị, số lượng,
  trạng thái known/estimated/unknown, nguồn và thời điểm giá. Không lưu
  thêm tổng dòng có thể lệch unit_amount × quantity. estimated_cost của
  item cũ chỉ phục vụ dữ liệu legacy; v2 không cộng trường này vào tổng.
  Tích hợp sau này nếu cần projection legacy phải cập nhật nó cùng transaction.
- itinerary_write_request: khóa ghi theo khách, hash request và snapshot
  phản hồi, unique(customer_id,request_key). Khóa khách trước khóa chuyến,
  revision và toàn bộ thay đổi được ghi trong cùng transaction.

FK mới dùng đúng INT signed cho itinerary/customer/hotel/room_type/item;
BIGINT unsigned cho attraction và các ID mới tạo bằng id().

## Ngân sách và độ sẵn sàng

Response tách lodging, activities, free_days, contingency. Không gồm vé
đến/rời điểm đến. Hai chế độ không cần AUREL hotel bỏ chi phí lodging.
Free allowance chỉ cộng cho ngày còn free; khi planned thì thay bằng các
dòng hoạt động của ngày đó, không cộng hai lần. null, thiếu nguồn hoặc
price_status=unknown làm tăng unknown_count; known với amount=0 có nguồn
được phân biệt với chưa có giá. Khoản đã biết vượt ngân sách luôn trả
over_budget dù các khoản khác chưa biết. Không SUM nullable rồi báo đủ tiền.

Các trạng thái: over_budget / incomplete / estimated_within_budget /
within_budget. Nếu số học vượt phạm vi integer, trả over_budget cùng
calculation_overflow=true, tổng null, không trả tổng float sai lệch.
Các tổng known bao gồm khoản dự trù/dự phòng đã nhập, không phải lời khẳng
định đó là giá địa điểm thật. Chi phí ước tính được trả riêng.

Ngày đã chọn chưa có hoạt động vẫn là khoản chưa thể chốt. Đêm chưa có
phương án lưu trú/chính sách đã xác minh được liệt kê, kể cả đã chọn hotel.
30 ngày theo lịch tương ứng 29 đêm khi thuê toàn kỳ. Khoảng thuê ngắn hơn
kỳ đi không tự khiến các đêm còn lại thành đã được bố trí.

readiness.validation_status=not_checked, generation_available=false và
budget.feasibility_checked=false trong giai đoạn này. Chưa xác minh chính
sách tuổi, sức chứa hoặc phụ thu. Lưu saved không thay các trạng thái đó.

Các endpoint ghi legacy update/updateItem/deleteItem/destroy/chat đều qua
WriteGuard: v1 trả 409 legacy_itinerary_read_only; dùng v1 để ghi v2 trả
409 use_itinerary_v2_api. Đọc legacy được giữ. Legacy generate và frontend
hiện tại chưa chuyển sang contract mới; chatbot chung không bị sửa.

## Kiểm thử và DB chính

Suite `phpunit.itineraries.xml` chỉ chạy khi được cấp manifest của server
MySQL cô lập do script workspace tạo. Guard kiểm tra host/port riêng,
server UUID, datadir, tài khoản chỉ có quyền trên schema riêng và ownership
token trong DB. Tên chứa test không phải bằng chứng an toàn.
Bootstrap không đọc .env của dự án và loại bỏ các connection khác.

Tái sử dụng script khởi tạo server MySQL cô lập hiện có; việc này không
kích hoạt bất kỳ provider, route hay migration RoomType V2 nào:

```powershell
$descriptor = & ./tests/Support/start_roomtype_v2_test_server.ps1
$settings = php tests/Support/prepare_itinerary_test_database.php $descriptor
$env:AUREL_ITINERARY_TEST_SETTINGS = $settings
php vendor/bin/phpunit -c phpunit.itineraries.xml --no-progress
php tests/Support/shutdown_roomtype_v2_test_server.php $descriptor
```

Suite dựng baseline đúng kiểu schema đã khảo sát, giữ một lịch/item legacy,
rồi chỉ chạy up() của migration mới trên MySQL riêng. Các test dữ liệu dùng
transaction và rollback trong schema cô lập; không migrate:fresh, không
rollback migration và không migrate toàn bộ. Server được dừng; dữ liệu test
riêng được giữ để xem lại. Migration chưa được áp dụng lên DB chính.

Phần tiếp theo: xác minh mapping vùng và lọc ứng viên, chính sách tuổi/phụ
thu và giá thực, phối phòng/tồn phòng, Places/Routing, AI trên tập ứng viên,
validator toàn lịch và bổ sung ngày, cuối cùng mới nối giao diện 4 bước.

## Kết quả xác minh ngày 2026-10-23

- MySQL 8.0.30 trên server vừa được khởi tạo riêng: 51 tests, 387 assertions,
  tất cả đạt. Migration up() được chạy từ baseline mới trên server này.
- Hai test hồi quy legacy trong CustomerAccountSecurityTest, SQLite :memory:
  với PDO SQLite được bật riêng cho CLI: 2 tests, 26 assertions, tất cả đạt.
- PHP lint 22 file đã sửa/thêm: đạt. Pint đã định dạng 18 file PHP mới;
  git diff --check ở các file tracked bị sửa: đạt.
- Metadata chỉ đọc trên DB chính xác nhận customer/location/hotel/room_type/
  itinerary/item dùng INT signed, attraction BIGINT unsigned. Số cột
  itinerary.schema_version trên DB chính vẫn là 0: chưa áp dụng migration.
- Không có HTTP Geoapify/AI nào trong suite; Http::assertNothingSent kiểm tra
  sau từng test MySQL. Server MySQL riêng đã nhận lệnh shutdown có kiểm tra
  datadir/port; dữ liệu kiểm thử riêng được giữ, không xóa DB/file.
- Chưa chạy toàn bộ test suite dự án, chưa stress test nhiều request đồng
  thời; đã kiểm tra rollback khi lỗi giữa chừng, retry/idempotency, UNIQUE
  của MySQL và hai yêu cầu dùng cùng revision ban đầu.

## File trong phạm vi thay đổi

Các file hiện có được sửa bổ sung, giữ các thay đổi trước đó:

- app/Models/Itinerary.php — thêm casts cho dữ liệu v2.
- app/Http/Controllers/ItineraryController.php — thêm 5 lời gọi WriteGuard.
- routes/api.php — thêm nhóm route v2, giữ các nhóm route khác.
- tests/Feature/CustomerAccountSecurityTest.php — cập nhật đúng hai test
  lịch cũ theo contract chỉ đọc; giữ kiểm tra quyền sở hữu.

Các file mới:

- database/migrations/2026_10_23_000002_add_itinerary_trip_management.php
- app/Http/Controllers/Api/V2/ItineraryController.php
- app/Http/Middleware/ItineraryV2Access.php
- app/Http/Requests/Itinerary/MutationRequest.php
- app/Http/Requests/Itinerary/TripRequest.php
- app/Http/Requests/Itinerary/StatusRequest.php
- app/Http/Requests/Itinerary/DeleteRequest.php
- app/Http/Resources/ItineraryTripResource.php
- app/Rules/ItineraryInteger.php
- app/Services/Itinerary/DestinationService.php
- app/Services/Itinerary/TripService.php
- app/Services/Itinerary/BudgetCalculator.php
- app/Services/Itinerary/IdempotentWriter.php
- app/Services/Itinerary/WriteGuard.php
- tests/Feature/ItineraryV2Test.php
- tests/Support/ItineraryFixture.php
- tests/Support/itinerary_test_bootstrap.php
- tests/Support/prepare_itinerary_test_database.php
- phpunit.itineraries.xml
- docs/itinerary-phase1-api.md

Runtime và thông tin kết nối test nằm dưới storage/app/private, không đưa
credential vào tài liệu hoặc phản hồi API.
