# AUREL — Phase 2B.2: RoomType V2 Inventory & Booking Backend

Ngày thực hiện: **12/10/2026**. Căn cứ [Phase 2A](hotel-roomtype-phase2a-plan.md), [mapping](hotel-roomtype-phase2a-mapping.csv), [B1](hotel-roomtype-phase2b1.md) và [Phase 1](hotel-room-management-phase1.md).

**Đã chuẩn bị module backend V2 riêng và kiểm thử trên MySQL riêng. Chưa cutover production.** Backend V2: **51 tests / 336 assertions đạt** trong một lượt đầy đủ; B1 chạy lại: **36 tests / 1.310 assertions đạt**. Có 7 bài cạnh tranh bằng hai tiến trình thật. Không mở connection tới `hotel_booking`, chạy migration/backfill/remap chính, sửa UI/Auth/PaymentService, mở Checkout hoặc commit/push.

**Chưa đủ điều kiện cutover:** callback legacy đã tái hiện vượt tồn trên fixture; unique index Booking có collision khi hủy rồi đặt lại cùng ngày. Hai vấn đề vẫn tồn tại trong luồng production, không được âm thầm thay đổi trong B2.

## 1. Service, Model và Command

Tất cả là **file mới**, không sửa file có sẵn:

| Thành phần | Vai trò |
| --- | --- |
| `app/Support/RoomTypeV2Inventory.php` | Session DB có guard; ownership, trạng thái, DECIMAL và ngày; khóa Hotel/Type; event sweep từ quantity. |
| `app/Services/RoomAvailabilityServiceV2.php` | Đọc tồn theo khoảng `[check_in,check_out)` của Type thuộc Hotel. |
| `app/Services/BookingServiceV2.php` | Tạo một Booking với một Type và quantity 1–10; một BookingRoom có room_id null; Payment local trong cùng transaction. |
| `app/Services/BookingLifecycleServiceV2.php` | Prototype cancel, expire, Admin transition/restore, verified-payment adapter và manual payment; cùng cơ chế khóa. |
| `app/Services/RoomTypeInventoryServiceV2.php` | Chuẩn bị tăng/giảm tổng tồn dưới lock, kiểm peak ongoing/future. Không có editor Admin production. |
| `app/Services/RoomTypeV2RemapService.php` | Manifest bất biến và remap offline theo từng Hotel; dry-run/rerun/resume/checksum. |
| `app/Models/RoomTypeV2.php` | Kế thừa model legacy, vẫn dùng bảng `room_type`; field V2 và quan hệ Hotel/gallery. Connection bị guard. |
| `app/Models/RoomTypeImage.php` | Dùng bảng `room_type_image` đã tạo ở B1; connection bị guard. |
| `app/Console/Commands/RemapRoomTypeV2.php` | Command `room-types:v2-remap`, chỉ hoạt động trên test descriptor hợp lệ. |

Không tạo bảng RoomType khác hoặc migration mới. Giữ nguyên Room, RoomImage, RoomAvailability, các Type legacy, Model/Service production, route và frontend contract. Không binding/alias V2 trong service provider, controller hoặc scheduler. Việc có command mới được Artisan tự đăng ký không kích hoạt nghiệp vụ V2.

## 2. Tính tồn từng đêm

Nguồn duy nhất: `room_type.total_rooms` và `booking_room.quantity` theo ngày/status Booking. Không đếm Room, dedup room_id, phân công phòng vật lý hoặc đọc counter `room_availability` để tính tồn V2.

Với mỗi đêm d thuộc `[check_in,check_out)`:

```text
used(d) = SUM(quantity của các Booking đang giữ chỗ ở đêm d)
remaining(d) = total_rooms - used(d)
available_for_stay = MIN(remaining(d)) = total_rooms - MAX(used(d))
```

Service gom delta `+quantity` ở check-in, `-quantity` ở checkout; cắt interval vào khoảng được hỏi, gộp arrivals/departures cùng ngày rồi tính peak. Kết quả có các segment `from/until/used/remaining` thay vì tạo một row database cho mỗi ngày. Segment thể hiện các đêm có cùng mức tồn.

Ví dụ total=5, A quantity=2 ngày 15–17/10 và B quantity=2 ngày 16–18/10: remaining 15/10=3, 16/10=1, 17/10=3; đặt quantity=2 cho 15–18/10 bị từ chối. Hai đơn quantity=4 ở hai đêm kế tiếp chỉ tạo peak=4, không cộng thành 8.

`pending`, `confirmed`, `completed` giữ chỗ. `cancelled`, `expired` giải phóng. Pending quá expires_at vẫn giữ tới khi writer đổi status. Status lạ, quantity/nights/ngày sai, ownership sai hoặc cam kết vượt tổng tồn làm service **từ chối rõ ràng**, không sửa/cắt bớt Booking để che lỗi. Hotel còn BookingRoom legacy chưa map cũng bị chặn; không âm thầm bỏ lịch sử khỏi phép tính.

## 3. Transaction và lock

Thứ tự: **Hotel → RoomType ID tăng dần → Booking liên quan → BookingRoom/Payment**. Hotel lock được giữ tới commit; các writer V2 dùng cùng cơ chế. Candidacy/type discovery đọc trước transaction chỉ để tìm khóa, trạng thái và các dòng được đọc lại sau khóa.

Tạo Booking khóa Hotel và Type, đọc cam kết bằng `FOR UPDATE`, kiểm đủ tồn rồi insert Booking/BookingRoom/Payment trong cùng DML transaction. Không dùng SKIP LOCKED hoặc snapshot tồn từ frontend/cache. Test REPEATABLE READ tạo snapshot cũ bằng connection thứ hai và chứng minh locking read vẫn thấy Booking vừa commit, trong khi ordinary SELECT còn thấy 0.

Cancel, expiry, restore, manual/verified-payment adapter và chỉnh total cùng khóa. Restore có lịch sử nhiều Type khóa toàn bộ Type tăng dần để bảo toàn đơn cũ; tạo đơn V2 mới vẫn chỉ cho một Type. Không có retry tự động phát lại side effect. Lỗi SQL rollback transaction hiện tại.

Module tạo Booking không gọi notification/mail/PDF/gateway. Nó chỉ ghi các row local; Payment tạo atomic để không có row mồ côi. Caller nhận kết quả sau commit. **Đường side effect production sau commit chưa được tích hợp trong B2**; cần adapter/handoff có idempotency và kiểm thử riêng trước cutover. Không coi việc chưa gửi email trong prototype là luồng đặt phòng production hoàn chỉnh.

## 4. Validation và tiền snapshot

- Hotel tồn tại/active; Type có owner đúng Hotel/active, total_rooms không âm và capacity hợp lệ.
- Quantity là integer 1–10, num_guests integer dương và không vượt capacity × quantity. Không thêm chính sách người lớn/trẻ em.
- Ngày chuẩn YYYY-MM-DD, check-in từ hôm nay, checkout sau check-in; checkout không là đêm ở.
- room_id có giá trị và danh sách room_types bị từ chối; không triển khai cart nhiều Type.
- Giữ special_request tối đa 500 ký tự, cash/banking và hạn 15 phút theo luồng hiện tại; payment_method null/vắng dùng cash.
- Không tin base_price/total frontend. Giá lấy từ Type sau lock, chuyển DECIMAL string thành integer cents, kiểm overflow trước nhân quantity × nights; không dùng float.
- Cash create: Booking pending / Payment pending. Banking create: Booking confirmed / Payment success, như BookingService hiện tại. Giữ rule chống đơn pending/confirmed trùng ngày của cùng khách/Hotel.

Đã kiểm từng quantity 1–10, sức chứa tương ứng, một dòng/Booking, room_id null, đúng số đêm/giá/tổng tiền/15 phút. Test `9.999.999.999,97` một đêm giữ đúng giá tới hai chữ số; hai đêm bị chặn trước insert. Đổi Type giá/capacity của Hotel A không đổi Type Hotel B hoặc snapshot đơn A đã tạo.

## 5. Concurrency MySQL thực tế

Suite backend dùng MySQL 8.0.30 riêng, port **53503**, datadir `storage/app/private/roomtype-v2-tests/73bb7f078e84/data`. Parent giữ Hotel row lock để hai PHP worker thực sự phải chờ. Mỗi case có hai CONNECTION_ID khác nhau và quan sát `Innodb_row_lock_current_waits = 2`; sau đó mở khóa để cả hai cạnh tranh. Không dùng SQLite/mock để chứng minh InnoDB.

| Case | Connection ID | Kết quả quan sát trong lượt đầy đủ |
| --- | --- | --- |
| Hai create tranh phòng cuối cùng | 54 / 55 | Một create thắng; create còn lại lỗi quantity. Booking/BookingRoom/Payment đều 1; held=1; orphan=0. |
| Hai create giao ngày một phần | 58 / 59 | Một thắng, một thiếu tồn; không overbook. |
| Create cạnh tranh giảm total xuống 0 | 62 / 63 | Create thắng; giảm tồn bị chặn, total=1 vẫn bảo vệ đơn. |
| Cancel cạnh tranh callback adapter | 66 / 67 | Cancel thắng; callback bị từ chối, Booking không resurrect. |
| Expiry cạnh tranh callback adapter | 70 / 71 | Expiry thắng; callback bị từ chối, Payment còn pending. |
| Restore cạnh tranh create | 74 / 75 | Restore thắng; create thiếu tồn; một đơn giữ chỗ. |
| Expiry cạnh tranh create | 78 / 79 | Expiry giải phóng trước; create thành công, tổng giữ vẫn 1. |

Artifact từng case: `storage/app/private/roomtype-v2-tests/73bb7f078e84/concurrency-*.json`, chứa DB test, request tổng hợp, waiter count và kết quả. Không chứa token/tài khoản thật. Các thứ tự thắng trên là **đã quan sát**, không phải tuyên bố đã vét cạn mọi interleaving. Có test tuần tự xác nhận expiry bỏ qua Booking đã confirmed và callback success lặp idempotent. Chưa có bài race riêng manual-payment versus expiry.

## 6. Lifecycle và audit các writer production

| Đường ghi hiện tại | Đối chiếu và trạng thái B2 |
| --- | --- |
| `BookingController` → `BookingService::create` | Legacy còn nguyên, tiếp tục Room-based. V2 module độc lập và test API legacy xác nhận room_type_id cũ vẫn được lưu. |
| `BookingService::cancel` | Legacy đọc trạng thái trước transaction, chưa dùng khóa đầy đủ V2. V2 cancel recheck dưới lock, giữ ownership/quy tắc 24h và idempotency cùng đơn. |
| `CancelExpiredBookings` + scheduler | Legacy Phase 1 khóa Hotel → Booking và recheck, đổi pending quá hạn thành cancelled/system; không cộng ledger. Prototype expire V2 giữ cùng status semantics và thêm Type locks; chưa sửa scheduler. |
| `AdminController::updateBookingStatus` | Restore legacy dùng RoomAvailabilityService. V2 transition kiểm tổng quantity/tồn dưới locks, không sửa giá/số đêm/tổng tiền. Chưa nối Admin API vào V2. |
| `PaymentController::createPayment` | Expiry inline cập nhật cancelled từ model đã đọc, chưa khóa/recheck chung. Đây là writer phải xử lý trước cutover. |
| `PaymentController::createManual` | Có thể ghi completed ngay khi banking, Payment pending; V2 manual prototype giữ hành vi thông thường này và completed vẫn giữ tồn. Chưa thay controller/invoice/mail. Prototype từ chối ghi đè payment đã success hoặc nhiều giao dịch cần đối soát; cách xử lý production cần duyệt riêng. |
| `Webcontroller::paymentResult` | Callback signature hợp lệ có thể ghi confirmed cho Booking cancelled mà không khóa Hotel/Type hoặc kiểm tồn. **Đã tái hiện trên test DB**, xem bên dưới. |
| `PaymentService` và `Services/Admin/BookingService` | Writer duplicate có thể đổi status ngoài locks; không tự đưa vào route hoặc sửa. Nếu tái sử dụng phải qua adapter được duyệt. |

V2 cancel/expiry lặp không cập nhật counter hai lần. Restore cancelled/expired thiếu tồn bị chặn, đủ tồn được phép, snapshots giữ nguyên. Tắt Type/Hotel chặn đơn mới/restore nhưng không tự hủy hoặc giải phóng đơn đang giữ. Manual banking prototype ghi completed; expiry không hủy completed.

`verifiedPaymentSuccess()` chỉ là **adapter internal trên test**, nhận giao dịch đã được caller xác minh; kiểm Payment thuộc Booking, transaction ID/amount và snapshot. Không thực hiện signature validation/gateway thực tế. Callback lặp không ghi success lần hai; released Booking bị chặn mà không thay Payment hay tự hoàn tiền. Chính sách nhận/đối soát tiền đến trễ phải được phê duyệt trước khi nối adapter vào production.

### Blocker đã tái hiện

1. **Late callback legacy:** tạo Booking A, hủy A, Booking B chiếm phòng cuối cùng; gọi trực tiếp `WebController::paymentResult` với chữ ký tổng hợp/test secret và Mail::fake trên DB test. Controller hiện có đổi A về confirmed, tạo cam kết **2/1**. V2 availability phát hiện vượt tồn và từ chối. Không gọi API live/gateway, không dùng secret thật và không sửa callback production.
2. **Unique status collision:** giữ index lịch sử `uq_booking_prevent_duplicate(customer_id,hotel_id,check_in,check_out,status)`. Hủy A rồi đặt B cùng khách/Hotel/ngày; hủy B gặp MySQL **1062**, vì đã có dòng A cancelled. Transaction B rollback, cả hai lịch sử và quantity còn nguyên. Đây là test **xác nhận blocker**, không phải khẳng định quy tắc hủy lại đã được sửa. Cần phương án constraint/idempotency và phê duyệt migration riêng; không xóa A để né lỗi.
3. **Route payment trỏ method thiếu:** `/api/payments/vnpay-return` trỏ `PaymentController::vnpayReturn`, `/api/payments/retry` trỏ `retry`, nhưng class hiện không có hai method; `createPayment` cũng gọi `buildVNPayUrl` không hiện diện. Code/method_exists xác nhận method thiếu; không gọi các API đó trên live hoặc tuyên bố đã kiểm provider end-to-end. Đây là blocker hiện có cần task thanh toán riêng.

## 7. Tăng/giảm tổng tồn

Service khóa Hotel/Type rồi sweep từ ngày hiện tại theo timezone ứng dụng tới cuối phạm vi DATE MySQL. Tính cả đơn đang lưu trú và đơn xa tới năm **2035** trong fixture; không giới hạn 90 ngày. Chỉ nhận `new_total >= peak_reserved_quantity`, total INT signed không âm. Không tự hủy Booking để giảm tổng.

Test ongoing quantity=4 và future quantity=3: giảm xuống 3 bị chặn; giảm xuống 4 được; tăng lên 10 được. Test create cạnh tranh giảm tồn dùng cùng locks. Dữ liệu đã overcommit bị báo lỗi trước chỉnh; chưa xây cơ chế sửa dữ liệu thiếu hụt ngoài quy tắc này. Giữ timezone ứng dụng hiện tại, không đổi `.env`.

## 8. Remap và reconciliation

Command mới, chỉ chạy trong environment/descriptor test hợp lệ:

```text
room-types:v2-remap --database=mysql --manifest=<owned-private-remap-file> --prepare
room-types:v2-remap --database=mysql --manifest=<same-file> --dry-run
room-types:v2-remap --database=mysql --manifest=<same-file>
```

**Không chạy những lệnh này từ cấu hình ứng dụng bình thường hoặc trên database chính.** Test gọi command sau khi bootstrap đã force connection test. Tên manifest là `remap-<12 hex>.json`, nằm đúng runtime private của descriptor test.

Prepare yêu cầu backfill đủ 105 nhóm và checksum B1 còn đúng, rồi lưu manifest gốc gồm server UUID/database/plan checksum và snapshot các bảng có liên quan. Không overwrite manifest khi đã có. Source Room phải còn legacy lúc prepare; nếu đã remap một phần mà mất manifest gốc, dừng để đối chiếu thủ công.

Run lấy named lock tương thích backfill, preflight toàn bộ snapshot, cho phép mỗi Room/BookingRoom đang ở **ID cũ hoặc đúng ID mới của cặp gốc**. Chuẩn hóa riêng FK về ID cũ để so checksum; mọi field còn lại phải khớp manifest. Per-Hotel transaction khóa Hotel → Type tăng dần → Booking, chỉ UPDATE room_type_id; không delete/recreate/sửa snapshots hoặc nhân bản file ảnh.

Đã kiểm:

- **336 Room** và **3 BookingRoom** của **hai Hotel** được remap theo hotel_id + old_type_id. Hai Hotel cùng Type cũ nhận hai ID mới khác nhau.
- Booking 901 có room_id 1/null, quantity 1/3, giá 500000.00/500111.25 và total 4000667.50; Booking 902 giữ completed, quantity=2, giá 61728.39 và total 123456.78. Mọi ID, room_id, quantity, nights/giá/tổng tiền/status được giữ.
- Hotel, RoomImage, HotelImage, Type gallery/map, Payment và dữ liệu Invoice/InvoiceItem fixture giữ nguyên; Room giữ số/giá/capacity/status/floor/ID. Không xóa legacy 6 Type hoặc Presidential.
- Dry-run không remap; rerun tạo 0 thay đổi. Trigger tại Hotel 3 làm Hotel 1–2 commit **32 Room**, Hotel 3 rollback. Giả lập thêm 1 Room đã chuyển đúng ID rồi resume: remap **303** còn lại, tổng đủ 336; không phát sinh duplicate.
- Drift floor/manifest checksum bị chặn trước ghi; assigned quantity>1, room_id dangling/wrong Hotel, assigned duplicate/overlap và ledger RoomAvailability có dữ liệu đều bị chặn. Không tự dedup để đổi nghĩa lịch sử.

Artifact đối chiếu chuẩn: `storage/app/private/roomtype-v2-tests/73bb7f078e84/phase2b2-reconciliation.json`, DB `aurel_roomtype_v2_test_58568aa4fc78`. Sau remap: **21 Hotel / 105 owned Type + 6 legacy / 336 Room / 105 map / 105 gallery links**, source quantity/money bảo toàn.

**Giới hạn:** đây là tool offline khi không có writer chạy song song. Advisory lock chỉ phối hợp các tool preparation/remap, không tự tạo maintenance barrier cho mọi API/queue/callback production. Trước production phải chặn toàn bộ writer và refresh manifest. Sau khi có Booking V2 mới hoặc editor làm đổi target, manifest cũ sẽ từ chối; không dùng nó để ghi đè delta mới. Command backfill B1 vẫn cố ý từ chối nguồn đã remap; dùng remap manifest để resume, không chạy lại backfill như một lệnh sửa dữ liệu.

## 9. Kiểm thử và cách ly

| Lượt cuối | Kết quả |
| --- | --- |
| Backend V2 + guard + regression có chọn lọc | **51 tests / 336 assertions / 0 failure / 0 error**, 02:48.093, một lượt đầy đủ. Gồm 13 guard cases + 38 integration tests. |
| B1 schema/backfill/preparation, chạy lại nguyên suite | **36 tests / 1.310 assertions / 0 failure / 0 error**, 01:11.340. |
| PHP lint 13 file PHP mới | Đạt. |
| Whitespace / preservation | Các file có trước task không sửa; diff check đạt. |

Không cộng lại các targeted/debug runs vào tổng distinct test. Lượt đầu gặp xung đột tên helper `seed()` với Laravel TestCase và assertion strict so MySQL SUM string với int; đã sửa helper/cast trong test mới, sau đó chạy full suite trên server mới đạt. Không có failure chưa xử lý trong suite cuối; các expected blocker tests ở mục 6 vẫn phản ánh lỗi thật chưa sửa.

Regression Phase 1 có chọn lọc: giữ legacy DI/API Booking, cash/banking/invoice/mail fake, capacity, physical availability, nullable clear, amenities/alias/ID, validation tên và KPI toàn query. **Không chạy lại nguyên suite 86 tests Phase 1**, vì runner cũ đọc schema chính; không dùng runner đó. Không test browser/UI/Auth thực hoặc tài khoản thật trong B2. Main UI/routes/Auth files được kiểm fingerprint nguyên vẹn.

Ba server mới đều dùng executable MySQL 8.0.30 với `--no-defaults`, datadir riêng, loopback, MySQL X/binlog tắt:

| Runtime private | Port | Mục đích |
| --- | --- | --- |
| `5b4b864fc59a` | 64619 | Debug/targeted backend tests. |
| `73bb7f078e84` | 53503 | Full backend V2 suite cuối. |
| `63be94492d5c` | 52526 | Full B1 regression suite. |

Mỗi server có 48 schema mới và account test tự sinh, chỉ có USAGE toàn cục + quyền namespace test, không có quyền hotel_booking hoặc ghi toàn cục. Account control có quyền cao **chỉ trên instance mới** để setup/shutdown, không dùng account ứng dụng. Fixture lấy migrations/CSV/JSON nguồn; Customer/history/email/signature/token object là tổng hợp, không đọc schema/tài khoản/token chính.

Tái sử dụng guard B1 trước DB::connection: APP_ENV testing, MySQL host/port/database/user/password khớp descriptor owned và allowlist; chặn 3306/hotel_booking/URL/socket/read-write override; sau mở xác minh UUID/port/database/grants. Feature spy xác nhận các entry V2 bị từ chối khi config hotel_booking **mà DB::connection không được gọi**.

Ba server đã graceful SHUTDOWN, xác nhận cả ba port đóng; giữ data/log/artifacts private để đối chiếu. Wrapper PowerShell stop bị sandbox chặn Get-CimInstance; dùng helper PHP shutdown sẵn có, xác minh datadir/port/control identity trước SHUTDOWN. Không tắt hoặc kết nối server chính.

**Không có fingerprint DB chính trong B2**, vì không được mở connection đó. Bảo đảm phạm vi đến từ server/credentials/guard và thao tác test cách ly; không tuyên bố đã kiểm mọi byte main hoặc ngăn hoạt động bên ngoài của người dùng khác.

Chạy lại suite bằng instance mới, không dùng `.env` ứng dụng để kết nối DB:

```powershell
$aurelTestServer = powershell -NoProfile -ExecutionPolicy Bypass -File tests/Support/start_roomtype_v2_test_server.ps1
$aurelTestSetup = php tests/Support/prepare_roomtype_v2_test_databases.php $aurelTestServer | ConvertFrom-Json
php tests/Support/run_roomtype_v2_backend_tests.php $aurelTestSetup.settings_file
php tests/Support/shutdown_roomtype_v2_test_server.php $aurelTestServer
```

Suite B1 dùng runner `run_roomtype_v2_tests.php` với một instance mới khác. Không chạy hai suite đầy đủ chung 48 schema vì mỗi integration case giữ schema riêng, không truncate/drop để làm lại. Runner B2 cố định bootstrap/config, chỉ nhận filter/stop flags.

## 10. File mới và bảo toàn thay đổi cũ

9 file ứng dụng ở mục 1, cộng:

1. `phpunit.roomtype-v2-backend.xml`.
2. `tests/Feature/RoomTypeV2BackendTest.php`.
3. `tests/Support/RoomTypeV2BackendFixture.php`.
4. `tests/Support/roomtype_v2_backend_worker.php`.
5. `tests/Support/run_roomtype_v2_backend_tests.php`.
6. `docs/hotel-roomtype-phase2b2.md`.

Tổng **15 file mới**, **0 file có sẵn được chỉnh/xóa**. SHA-256 **536 đường file có trước task** khớp, gồm `.env`, migration/code/UI/Auth, file untracked và trạng thái xóa trước đó. Runtime/artifacts nằm trong storage private đã ignore; không reset/stash/commit/push.

## 11. Điều kiện trước Phase 2B.3

1. Duyệt riêng phương án callback/writer payment: signature/amount/idempotency, late payment đối soát, khóa chung và side effects sau commit; không tự refund hoặc đổi trạng thái business.
2. Duyệt xử lý unique Booking status collision, chuẩn bị migration/test phù hợp nếu cần; giữ mọi lịch sử.
3. Hoàn thiện adapter production cho create/cancel/expiry/Admin restore/payment/manual và loại đường duplicate không được sử dụng, vẫn giữ contract/UI và Checkout khóa theo phạm vi duyệt.
4. Test thêm signature/gateway callback end-to-end trên sandbox, side effect retry, manual-payment races và regression Auth/Booking/API đầy đủ trên DB riêng. Các bộ test đạt hiện tại không thay thế kiểm thử cutover.
5. Refresh nguồn thật chỉ trong task được cho phép, kiểm count/FK/ảnh/Booking/ledger; xử lý ngoại lệ lịch sử. Có backup với restore proof, maintenance cho mọi writer/queue/scheduler/callback và phương án reconcile delta.
6. Phê duyệt riêng đúng migration expand/backfill/remap chính và cutover có kiểm soát. Không xóa Room/RoomImage/Type legacy/RoomAvailability trong bước đó nếu chưa có phê duyệt cleanup.

**Dừng sau Phase 2B.2. Không dùng báo cáo này làm phê duyệt migration/backfill/remap/cutover chính hoặc tự bắt đầu Phase 2B.3.**
