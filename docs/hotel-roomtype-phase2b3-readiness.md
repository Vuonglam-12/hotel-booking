# AUREL — Phase 2B.2.1 / 2B.3 readiness

Ngày thực hiện: 12/10/2026. Đối chiếu [Phase 2A](hotel-roomtype-phase2a-plan.md), [mapping](hotel-roomtype-phase2a-mapping.csv), [B1](hotel-roomtype-phase2b1.md), [B2](hotel-roomtype-phase2b2.md) và [Phase 1](hotel-room-management-phase1.md).

**Đã sửa và kiểm thử các handler chuẩn bị, migration và tích hợp V2 trên MySQL riêng: readiness 45 tests / 283 assertions PASS, B1 36 / 1.310 PASS, B2 51 / 336 PASS. Chưa áp dụng các sửa đổi lên route/controller/database production. Chưa sẵn sàng cutover.** Không mở Checkout, gọi gateway thực, thay `.env`/Auth/UI hoặc commit/push.

## 1. Ba blocker và phạm vi FIXED / PARTIAL / BLOCKED

| Blocker | Root cause | Kết quả trong môi trường chuẩn bị | Production |
| --- | --- | --- | --- |
| A1 — late callback overbooking | WebController xác nhận Booking chỉ theo response code, không kiểm amount/status hiện tại/tồn dưới khóa; Return URL còn ghi trạng thái. | **FIXED trên test:** Return handler read-only; IPN kiểm HMAC/merchant/amount/transaction, khóa Hotel → Type tăng dần → Booking → Payment; receipt late/double payment để đối soát, không tự restore/refund. | **PARTIAL, chưa triển khai:** Webcontroller/PaymentController legacy được giữ nguyên; các lỗi của luồng đang chạy chưa được thay bằng handler mới. |
| A2 — unique status collision | Unique key gồm status nên nhiều lịch sử cancelled/expired cùng ngày va chạm; pending/confirmed khác status lại chưa bị DB chặn chung. | **FIXED trên test:** migration mới dùng generated key chỉ cho pending/confirmed và unique có phạm vi khách/Hotel/ngày. Hủy → đặt lại → hủy giữ đủ lịch sử; application lock/duplicate check cùng DB constraint. | **PARTIAL, chưa triển khai:** migration Pending trên main; index cũ chưa thay. |
| A3 — missing payment methods | Route trỏ vnpayReturn/retry và createPayment gọi builder không có; PaymentService duplicate còn builder placeholder. | **FIXED trong route overlay test:** PreparedV2 PaymentController có createPayment/retry/vnpayReturn/IPN/manual; URL builder theo contract sandbox, không stub success. | **PARTIAL/BLOCKED deploy:** route/root controller chưa đổi; thiếu cấu hình merchant/secret/Return URL để chứng minh kết nối merchant sandbox thực. |

Part B chỉ bắt đầu sau suite Part A đạt **34 tests / 155 assertions**. “FIXED” ở đây có nghĩa implementation/migration đã kiểm thử trong module cách ly, **không có nghĩa lỗi live đã được triển khai khắc phục**. Giữ nguyên code đang chạy để tuân thủ cổng phê duyệt thay đổi có tác động production.

## 2. Migration mới và chính sách duplicate

### `2026_10_12_000005_replace_booking_status_unique.php`

- Preflight từ chối nếu đã có nhiều pending/confirmed cho cùng customer_id + hotel_id + check_in + check_out.
- Một ALTER TABLE MySQL thay index cũ bằng `active_duplicate_key TINYINT GENERATED ALWAYS AS (CASE WHEN status IN ('pending','confirmed') THEN 1 ELSE NULL END) STORED` và `uq_booking_active(customer_id,hotel_id,check_in,check_out,active_duplicate_key)`.
- NULL ngoài pending/confirmed cho phép nhiều cancelled/expired/completed. Rule chống duplicate giữ đúng tập trạng thái của luồng create hiện tại; completed vẫn giữ tồn theo interval, không suy diễn là checkout.
- Application create và restore kiểm duplicate dưới Hotel/Type/Booking locks; database chặn cả ghi trực tiếp/concurrent bỏ qua application.
- down() từ chối nếu history có tổ hợp trùng mà index cũ không biểu diễn được. Không xóa history để rollback. Đã kiểm down/up khi dữ liệu còn biểu diễn được, từ chối down sau hủy/rebook/hủy và từ chối up từ legacy cross-status active conflict.

### `2026_10_12_000006_create_payment_receipts_and_effects.php`

- `payment_gateway_receipt`: payment FK INT signed, provider transaction/reference, DECIMAL amount, response/status, outcome, payload đã bỏ signature, event hash, timestamp. Unique payment/event hash và provider/success transaction ID.
- `booking_effect`: Booking/Payment/Invoice FK signed, event_key unique, state/attempt/lease, invoice reference, delivery timestamp/error marker. Không sửa schema Invoice hoặc Payment hiện có.
- down() từ chối khi có receipt/effect, để bảo toàn thông tin tài chính. Failed callback có provider transaction NULL để không va chạm giá trị gateway “0”.
- Đây là hai migration **mới**, không sửa lịch sử và không chạy trên main. MySQL DDL không rollback nguyên chuỗi; migration receipts/effects có hai CREATE nên vẫn cần kiểm tra partial DDL/backup/restore trước thực thi thật.

Create retry hiện chặn duplicate active bằng application/DB; không có response replay cache cho POST Booking. Payment retry tái dùng đúng pending VNPay attempt, giữ failed attempt và chỉ tạo attempt mới khi cần; không tạo Booking/BookingRoom mới, không gia hạn 15 phút hoặc khôi phục cancelled/expired.

## 3. Payment contract và lifecycle

[Tài liệu chính thức VNPay PAY](https://sandbox.vnpayment.vn/apis/docs/thanh-toan-pay/pay.html) phân biệt Return URL hiển thị và IPN cập nhật giao dịch. Module dùng v2.1.0, HMAC-SHA512, tham số sort/URL encoding, amount ×100 dưới dạng integer cents và timestamp GMT+7.

`VnpayProtocol` chỉ nhận settings explicit, fail nếu thiếu cấu hình; builder chỉ hỗ trợ URL sandbox đã xác định. Không đọc credential để tạo fixture hoặc gửi HTTP/gateway thật. Bộ test dùng merchant/secret tổng hợp. Kiểm scalar/format/merchant/signature hash_equals, amount và TxnRef lấy từ Payment snapshot; callback không được dùng booking_id frontend để quyết định chủ đơn.

`VnpayPaymentServiceV2` dùng `RoomTypeV2Writer` cho legacy và owned Type:

1. Guard test DB trước connection; tìm Payment để xác định Booking.
2. Khóa Hotel → Type tăng dần → Booking/BookingRoom → Payment, đọc lại dữ liệu.
3. Sai signature/merchant/amount/reference bị từ chối trước cập nhật. Unknown Booking status fail closed.
4. Callback success đúng hạn xác nhận pending; confirmed/completed không bị đổi ý nghĩa. Kiểm inventory và active duplicate trước tăng cam kết.
5. Released Booking hoặc pending quá deadline nhận receipt `late_reconciliation`; giữ trạng thái Booking, không cấp lại phòng. Payment success ghi nhận **tiền đã nhận**, không đồng nghĩa xác nhận Booking. Không tự refund.
6. Nếu một attempt khác đã success, ghi `duplicate_payment_reconciliation` thay vì phát thêm confirmation/invoice. Không che mất giao dịch tài chính thứ hai.
7. Receipt/event hash chống xử lý lại callback giống nhau. IPN ACK 00/02 cho đã ghi/lặp; 97 signature, 04 amount, 01 reference không biết, 99 lỗi chưa commit/cần retry. Không ACK success nếu transaction lỗi.

Thông tin giao dịch được giữ private trong receipt; chưa có UI/flow đối soát hoặc chính sách giải quyết tiền đến trễ/double payment. Conflicting callback sau một success đã ghi được từ chối, không overwrite financial state; cần đối soát riêng.

`RoomTypeV2Writer` tập trung discovery/recheck/locking/ownership/commitment/duplicate; BookingLifecycleServiceV2 đã bỏ phần code lặp và gọi writer này. Legacy Type được xác minh có Room đúng Hotel, không tự coi là owned Type. Legacy thiếu hụt tồn bị chặn; V2 vẫn chỉ tính total_rooms + quantity. Writer legacy direct của root app chưa được chuyển, do chưa phê duyệt deployment.

## 4. Side effects sau commit

Successful timely IPN ghi một `booking_effect` atomic cùng Booking/Payment/receipt. Không email/PDF/gateway trong transaction này. `BookingEffectServiceV2` claim lease dưới cùng khóa, tạo/đọc một Invoice từ snapshots, commit rồi mới gọi sender với `event_key` ổn định.

Retry sender failure giữ một Invoice/InvoiceItem, tăng attempts và dùng cùng key; delivered event không gửi lại. Nếu Booking đã released trước delivery, event bị blocked, không gửi confirmation hoặc tạo invoice mới. Không xóa invoice/receipt cũ hoặc phát refund.

**Chưa cài sender SMTP/PDF production.** Test sender chứng minh gọi ở transactionLevel=0, retry/key/idempotency. Exactly-once gửi mail không thể được suy ra từ database lease khi process chết sau gửi nhưng trước ACK; cần sender có idempotency hoặc chính sách chấp nhận at-least-once được duyệt. New invoice adapter dùng `INV-V2-<event_key>` deterministic; invoice cũ được giữ nguyên, không renumber. Format và handoff production phải được duyệt trước cutover. Manual banking giữ completed/Payment pending như code hiện tại; side effects riêng cho manual/cash cần đối chiếu và hoàn thiện, chưa tự đổi policy để ép test PASS.

## 5. API RoomType V2 và Admin backend

Chỉ route overlay/provider test đã guard được phép đăng ký:

| Contract chuẩn bị | Kết quả |
| --- | --- |
| GET `/api/hotels/{hotel}/room-types` | Chỉ owned Type active của Hotel active; giá DECIMAL string, capacity, total, gallery, availability segments/available_rooms và bookable. Không room_number/id giả. |
| Date/quantity/num_guests | Date pair phải đầy đủ/hợp lệ; quantity 1–10, khách dương. Không có date thì available=null/bookable=false, yêu cầu chọn ngày; thiếu capacity chỉ đánh dấu Type không đáp ứng, không làm giả tồn. |
| Booking API overlay | Một Type, quantity 1–10, room_id mới có giá trị bị từ chối; đọc lại ownership/status/tồn/giá; giữ cash/banking và 15 phút. |
| Admin Type create/edit | Reuse `auth:admin` và AdminMiddleware role/session/ability; Type thuộc Hotel, không sinh Room; validation đúng VARCHAR/TEXT/DECIMAL/INT. |
| Ownership | Không nhận bất kỳ hotel_id body, gồm null/empty/array; URL quyết định Hotel. Không đổi owner qua mass assignment. |
| Total/status | Chặn thấp hơn peak ongoing/all future, kể cả năm 2035. Update metadata + total atomic. Inactive giữ Booking/history, DELETE API chuẩn bị thực hiện ngừng bán, không hard delete Type. |
| Gallery | Link local asset tồn tại theo lựa chọn Admin, dedup trong Type, primary có thứ tự; image ID phải thuộc Type. Không upload/copy file, lấy tự động ảnh Hotel khác, xóa RoomImage hoặc file nguồn. Chưa xác minh nội dung ảnh đúng cơ sở. |
| History adapter | Reuse BookingService getDetail/myBookings cho room_id nullable và Type remap; giữ quantity/giá/total. Remap trên test giữ 336 Room và lịch sử theo map, không thay Invoice/email/PDF source. |

API invalid input trả 422, owner/permission chặn qua middleware/validation, inactive Hotel không public 404; inventory chưa remap/thiếu hụt báo 409 để đối soát, không âm thầm bỏ legacy Booking.

## 6. Kích hoạt có kiểm soát và patch review

- `config/roomtype-v2.php`: `prepared_routes=false`, không lấy flag từ `.env`.
- `RoomTypeV2PreparedProvider` chưa được thêm vào config/app.php. Chỉ khi flag explicit true, guard owned server hợp lệ và schema prerequisite đầy đủ mới cài overlay.
- `RoomTypeV2Integration` ưu tiên static routes để IPN không bị `/payments/{booking_id}` che. Tái dùng middleware hiện có, không đổi Auth config.
- Patch [roomtype-v2-prepared-provider.patch](patches/roomtype-v2-prepared-provider.patch) chỉ thêm optional provider, **chưa áp dụng**, git apply --check đạt. Test đăng ký provider thật với flag test; default-off không nối V2 vào app chính.
- Patch hiện chỉ chuẩn bị wiring **test**, không phải lệnh bật production. Guard vẫn từ chối hotel_booking/3306. Muốn cutover phải có bước production gate được review riêng, không đơn giản bỏ guard hoặc đổi flag.
- `bookings:v2-expire-prepared` là command test riêng, dùng shared writer/recheck. Scheduler cũ chưa đổi. Cửa sổ cutover phải dừng scheduler/queue/callback/legacy writers và chuyển cả expiry command; không chỉ đổi các HTTP route.

Root Webcontroller/PaymentController/BookingController/AdminController/BookingService/CancelExpiredBookings/routes/PaymentService giữ nguyên. Chưa xóa duplicate services. Nếu sau này cho bất kỳ writer legacy nào hoạt động sau V2 cutover, nó có thể đi vòng logic mới: đây là **deployment blocker**, không được coi route overlay test là maintenance barrier.

Browser `/payment/result` trong overlay hiện trả DTO read-only; **chưa hoàn thiện HTML adapter cho view cũ**. View cũ ghi “email đã gửi” và thiếu pending/reconciliation state; không sửa Blade trái phạm vi. Production HTML route vẫn nguyên. Phải duyệt adapter phù hợp trước cutover, không thay giao diện live bằng JSON một cách âm thầm.

## 7. Kiểm thử thực tế

Suite cuối readiness và B1/B2 đã chạy trên các server riêng:

| Suite/lượt | Tests / assertions | Kết quả |
| --- | --- | --- |
| **Readiness full cuối, sau ownership-null/Register** | **45 / 283** | **PASS**, 01:57.274, 0 failure/error. |
| Part A trước khi bắt đầu Part B | 34 / 155 | PASS, 01:29.463. |
| API/Admin standalone trước bổ sung ownership-null/Register | 11 / 122 | PASS, 00:48.865. |
| Readiness full đầu tiên | 45 / 277 | PASS, 02:52.189; sau đó bổ sung kiểm ownership null và Register, chạy full lại. |
| B1 nguyên suite | 36 / 1.310 | PASS, 01:17.495. |
| B2 nguyên suite | 51 / 336 | PASS, 02:59.022. |
| PHP lint | 21 PHP files mới/sửa | PASS. |
| Diff / patch | diff --check, patch --check | PASS; patch chưa áp dụng. |

Không cộng các targeted/debug runs vào distinct totals. Lỗi phát hiện lúc chạy: route:list flush middleware groups, IPN bị route động che, rule array chứa `sometimes|required` sai cú pháp và ownership-null có thể vượt rule prohibited; đã sửa trong module test/controller mới và kiểm thử lại. Source/docs B2 khớp audit, không mặc định lỗi cũ ngoài bằng chứng.

Readiness có 4 bài concurrency độc lập, mỗi bài parent giữ Hotel row lock, hai worker CONNECTION_ID khác nhau và quan sát **2 InnoDB row-lock waiters**. Lượt full cuối: create/create 45/46; cancel/IPN 49/50; expiry/IPN 53/54; restore/IPN 57/58. Một duplicate create thắng; IPN vẫn ghi receipt khi cancel/expiry thắng, không resurrect. Restore kiểm tồn dưới khóa. Artifact `readiness-concurrency-*.json` giữ kết quả cụ thể, không tuyên bố đã vét cạn mọi interleaving.

B2 regression thêm 7 race thật: phòng cuối cùng, giao ngày, giảm tồn, cancel/callback adapter, expiry/callback, restore/create, expiry/create. Như vậy có 11 concurrency cases được chạy trong hai suites, không dùng mock/SQLite để chứng minh InnoDB. Chưa có race riêng sender crash-after-send hoặc manual payment versus expiry.

Tests còn kiểm migration preflight/down, callback đúng/sai/late/lặp/amount/signature và lỗi giữa transaction; nhiều cancelled/expired, duplicate active, retry attempts; effect retry; API/Admin CRUD/inventory/gallery; remap/history; Sanctum login/Register/Logout/role/ability và tách phiên bằng account/token tổng hợp trên MySQL test.

Phase 1 regression có chọn lọc qua B2: legacy capacity/cash/banking/invoice/mail fake, amenities/KPI/nullable clear/API default. **Không chạy nguyên runner Phase 1 hoặc suite Auth SQLite**, do yêu cầu lần này dùng MySQL riêng và runner Phase 1 đọc schema chính. Không test browser/tài khoản thật hay merchant sandbox end-to-end; không báo PASS cho phần chưa chạy.

## 8. Database chính và cách ly

Không mở connection tới hotel_booking, kể cả bootstrap/schema/FK đọc. Fixture lấy migrations/CSV/JSON mã nguồn, Sanctum vendor migration và dữ liệu tổng hợp; không token/secret/account thật, không gọi API live hoặc gateway.

Mỗi instance MySQL 8.0.30 dùng --no-defaults, datadir mới trong storage private, cổng loopback khác 3306, account test chỉ có USAGE global và grants namespace allowlist. Runtime guard trước DB::connection chặn main/port/root/URL/socket/read-write override; sau connection xác minh UUID/port/database/grants. Negative tests dùng spy xác nhận main bị từ chối trước connection.

| Runtime | Port | Mục đích |
| --- | --- | --- |
| `23e8ac169bc2` | 61019 | Debug/Part A full. |
| `7fbe8e3522c6` | 61456 | API/debug và readiness full đầu. |
| `2c17a297d17b` | 54114 | B2 regression. |
| `5cac77b74f7e` | 55133 | B1 regression. |
| `db6931a38bb0` | 55999 | Readiness full cuối. |

Artifacts nằm dưới `storage/app/private/roomtype-v2-tests/<runtime>/`; credentials không đưa vào report. Instances shutdown sau kiểm thử, giữ data/log. Không fingerprint DB chính do cấm kết nối, không tuyên bố kiểm mọi byte main hoặc kiểm soát hoạt động ngoài task.

Audit `.env` chỉ trả metadata hiện diện: VNPAY_TMN_CODE/hash secret/Return URL không có; URL có. Process env của tiến trình kiểm tra cũng không có ba key; không có config cache mặc định. Không in giá trị hoặc sửa `.env`. Không suy ra cấu hình của PHP runtime khác; cần merchant config/sandbox verification riêng trước deployment.

## 9. File mới/sửa và bảo toàn

Sửa duy nhất file có sẵn: `app/Services/BookingLifecycleServiceV2.php`, dùng shared writer/duplicate guard và bỏ import không dùng. Đây là module B2 chưa được production bind.

File mới:

1. Hai migration 000005/000006 ở mục 2.
2. `app/Support/RoomTypeV2Writer.php`, `VnpayProtocol.php`, `RoomTypeV2Integration.php`.
3. `app/Services/VnpayPaymentServiceV2.php`, `BookingEffectServiceV2.php`, `RoomTypeAdminServiceV2.php`.
4. Bốn controllers dưới `app/Http/Controllers/PreparedV2/`: Payment, Booking, RoomType, AdminRoomType.
5. `app/Console/Commands/ExpirePreparedV2Bookings.php`.
6. `app/Providers/RoomTypeV2PreparedProvider.php`, `config/roomtype-v2.php`.
7. `phpunit.roomtype-v2-readiness.xml`.
8. `tests/Feature/RoomTypeV2PaymentReadinessTest.php`, `RoomTypeV2ApiReadinessTest.php`.
9. `tests/Support/RoomTypeV2ReadinessFixture.php`, `roomtype_v2_readiness_worker.php`, `run_roomtype_v2_readiness_tests.php`.
10. Provider patch và báo cáo này.

Tổng **23 file mới**, **1 file V2 sửa**. Trong 551 đường file có trước task, **550 khớp SHA-256 ban đầu**, gồm `.env`, UI/Auth/controllers/routes production/migrations lịch sử và trạng thái xóa chưa commit. Không reset/stash/commit/push.

## 10. Điều kiện cutover / rollback / forward-fix

1. Duyệt deployment patch/gate/route/controller adapter và scheduler cùng một đợt; rà hết writer trực tiếp/duplicate/queue để legacy không bypass. Chưa được bỏ test guard tự phát.
2. Duyệt merchant sandbox config, IPN URL registration và verification end-to-end. Return URL giữ read-only; hoàn thiện HTML pending/reconciliation adapter mà không báo email đã gửi khi chưa có delivery.
3. Duyệt quy trình xử lý late/double payments, sender idempotency/SMTP/PDF handoff, manual/cash side effects và invoice numbering adapter. Không tự refund hay thay 15 phút/cash/banking/completed semantics.
4. Refresh count/FK/source/history/ledger và manifest trong task được cho phép; xử lý active conflicts/ambiguous history trước migration. Có backup schema/data/assets với restore proof trên DB khác và cửa sổ ngừng mọi writer.
5. Chỉ chạy đúng các migration/backfill/remap được phê duyệt; không chạy tất cả Pending hoặc fresh/refresh/wipe. Schema B1, active unique và receipt/effect là prerequisite riêng.
6. Down unique phải từ chối nếu old schema không biểu diễn history; down receipt/effect phải từ chối có tài chính. Ưu tiên forward-fix, giữ receipt/history/delta mới. Không restore backup cũ làm mất Booking/Payment phát sinh.
7. Tắt flag chỉ đổi route, **không phải rollback đủ** khi V2 đã đổi total/price/capacity hoặc tạo đơn mới: code Room-based có thể khác tồn mới. Cần maintenance, reconciliation delta và phương án được duyệt. Giữ Room/RoomImage/legacy Type/mapping trong giai đoạn này.

**Dừng tại readiness. Không chạy migration/backfill/remap/cutover database chính, không mở Checkout và không tự triển khai bước tiếp.**
