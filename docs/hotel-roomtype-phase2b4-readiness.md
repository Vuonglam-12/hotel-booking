# AUREL — Phase 2B.4 Integration Completion & Cutover Preparation

Ngày kiểm tra: 2026-10-12. **NO-GO cho database chính và mở Checkout.** Phần chuẩn bị được triển khai bằng overlay chỉ chạy trên MySQL test riêng. Chưa đăng ký provider V2 vào `config/app.php`, chưa sửa routes/kernel production, chưa đổi `.env`, Authentication hoặc migration lịch sử.

## 1. Integration đã chuẩn bị

- `RoomTypeV2PreparedProvider` mặc định tắt. Khi test bật cả `prepared_routes` và `integration_complete`, kiểm tra schema, active Booking constraint, 105 mappings và ownership sau remap trước khi đổi wiring. Guard từ chối `hotel_booking` trước `DB::connection()`.
- Route V2 được ưu tiên và loại trùng theo method/domain/URI đã chuẩn hóa tên tham số. Route `/api/admin/hotels/{hotelId}/room-types` cũ không còn cạnh tranh với `{hotel}` trong overlay.
- `BookingBridge` giữ các reader History cũ, chuyển create/cancel qua V2. `AvailabilityBridge` tính từ `total_rooms` và quantity, từ chối chọn phòng vật lý. Không tạo Room mới.
- `RoomTypeV2WriteFence` chặn DML vào Booking/Payment/Invoice/inventory nếu không đến từ service V2 đã đăng ký. Có test raw Query Builder, Eloquent bulk update, service Admin cũ, Payment service cũ và Web callback cũ gọi trực tiếp. Đây là lớp phòng vệ ở Laravel cho rehearsal; không phải firewall MySQL và không bảo vệ câu SQL qua PDO trực tiếp hoặc một tiến trình cũ chưa nạp overlay.
- Prepared create Booking trả lại nested history contract (`hotel`, `booking_rooms`, `payment`) bằng reader hiện có, quantity và giá snapshot được giữ nguyên.
- Hotel Detail dùng owned RoomType, ảnh RoomType và giá DECIMAL; giữ AUREL layout, card, modal, quantity 1–10 và date picker. Checkout vẫn disabled. API availability dùng `/api/hotels/{hotel}/room-types`; response vật lý không được giả lập.
- Admin Hotels có bản Blade test riêng: giữ layout, bỏ tầng/sinh số phòng, gửi `base_price` dạng chuỗi chính xác và `total_rooms`; list hiển thị tổng inventory V2. Các API CRUD, status, inventory và gallery tái sử dụng B3.
- Prepared payment result HTML có pending/success/reconciliation/failed/invalid; không báo email gửi xong trước khi effect được transport xác nhận. Return chỉ đọc.
- PDF download được chuyển sang adapter chỉ render/download, không cập nhật `invoice.pdf_url` như endpoint GET legacy.

Các bản Blade mới chỉ được route overlay test gọi; Home, Hotel Detail và Admin UI production giữ nguyên file hiện có. Bản copy cần đối chiếu khi UI gốc thay đổi về sau.

## 2. Audit route, writer và contract

| Đường hiện tại | Production hiện tại | Khi bật complete overlay trên test |
|---|---|---|
| POST `/api/bookings`, POST `/{id}/cancel` | BookingController → BookingService legacy | PreparedV2 BookingController → V2; bridge bảo vệ caller dùng container |
| PUT `/api/admin/bookings/{id}/status` | AdminController writer legacy | Shared V2 lifecycle writer; admin guard/role giữ nguyên |
| POST `/api/payments/create`, `/retry`, `/manual` | PaymentController legacy; `retry` chưa có method ở controller gốc | Prepared PaymentController; Sanctum ownership và shared lock |
| GET `/api/payments/vnpay-return` | Route gốc tham chiếu method chưa có | Read-only V2 result JSON |
| GET `/payment/result` | WebController callback ghi Payment/Booking, invoice/mail trong transaction | Prepared HTML read-only; callback gốc gọi trực tiếp bị fence chặn |
| GET `/api/payments/vnpay-ipn` | Chưa có | Verified V2 IPN; public có HMAC, không cần token Customer |
| Admin hotel RoomType POST/GET | Tạo type chung và physical Rooms | Owned type V2; route normalized không trùng |
| GET `/api/admin/rooms/availability` | Physical inventory/capacity | Controller giữ nguyên, dependency AvailabilityBridge dùng V2 |
| GET `/api/invoices/{booking_id}/pdf` | Render rồi ghi `pdf_url` | Prepared read-only PDF; auth/ownership giữ nguyên |
| GET History, list Invoice, Payment detail | Reader snapshot hiện có | Giữ reader; tests quantity, nullable room_id, money snapshot |
| `bookings:cancel-expired` | Kernel schedule mỗi phút, command legacy | Cùng tên được bind sang expiry V2; lặp lại không ghi thêm |
| `Services/Admin/BookingService::updateStatus` | Writer không shared lock; controller phụ không được routes chính gọi | Direct invocation bị fence chặn |
| PaymentService, InvoiceService ở root Services | Namespace `/Payment`, `/Invoice` không khớp PSR-4 path; không thấy route chính gọi | Giữ code, không tự xóa; DML legacy bị fence nếu class được nạp thủ công |
| Admin HotelService::createRoomType, RoomController legacy | Có logic physical riêng; không thấy route writer Room CRUD trong route list hiện tại | DML Room/RoomType bị fence; create RoomType endpoint chính đã overlay |
| GET `/api/hotels/{id}/rooms`, `/booking/{id}` | Contract/UI physical/checkout cũ | 409 rõ ràng, chỉ dẫn Type API; không cấp ID phòng giả, không mở checkout |

**Reader/contract chưa chuyển toàn bộ:** Home và HotelController/HotelService list, map, filter và detail JSON vẫn có truy vấn `room.price`/Room. Có thể hiển thị giá/tồn cũ khi Admin sửa V2; đây là blocker cho cutover website tổng thể. Không sửa Home theo phạm vi task. Admin Bookings vẫn có relation physical `rooms`; tiền/quantity history được giữ, nhưng UI trình duyệt đầy đủ và contract manual payment/notification cần review riêng.

Không tìm thấy `app/Jobs` hay caller Booking/Payment dùng `dispatch`, `queue`, `later`, `ShouldQueue` trong source đã rà soát. Mail legacy đang gửi đồng bộ; `Queueable` trên Mailable không đồng nghĩa đã enqueue. Không suy ra hệ thống triển khai không có worker/cron bên ngoài: bắt buộc kiểm tra vận hành trước cutover.

## 3. VNPay, Invoice, Email và scheduler

Đối chiếu với [tài liệu chính thức VNPay PAY](https://sandbox.vnpayment.vn/apis/docs/thanh-toan-pay/pay.html): URL sandbox, version 2.1.0, VND ×100, query sort/encoding và HMAC-SHA512, timestamp GMT+7, Return hiển thị và IPN cập nhật. Test chỉ dựng query/HMAC tổng hợp trong database fixture, không gọi gateway hay dùng tài khoản/thẻ thật.

- IPN xác minh signature/merchant/reference/amount, khóa Hotel → Type → Booking → Payment, ghi receipt/effect cùng transaction. Signature sai không ghi; lỗi transaction trả retry, không giả nhận thành công.
- Exact duplicate và signed redelivery thêm metadata nhưng giữ cùng giao dịch tài chính trả duplicate. Giao dịch khác sau khi đã success bị từ chối để đối soát; không ghi đè receipt thành công.
- Late success ghi nhận tiền nhưng không restore booking/chiếm lại inventory, không phát confirmation. Hai payment attempt thành công giữ ledger và đánh dấu reconciliation; không tự refund.
- Invoice/effect claim trong transaction; mail/PDF thực hiện **sau commit**. Lỗi trước gửi giữ effect pending, invoice và event key ổn định. Hai delivery worker chỉ một claim thành công. Leases hết hạn được command quét lại.
- `BookingMailSender` dùng Mailable/Blade/PDF hiện có, chỉ cho transport `array`, recipient `@example.test`, remote PDF off. Có test MIME/PDF binary và download giữ nguyên invoice. PASS ở bộ nhớ không chứng minh SMTP hay hộp thư nhận thật.
- Crash sau khi SMTP nhận nhưng trước ack vẫn có nguy cơ gửi trùng. Stable Message-ID không chứng minh exactly-once SMTP. Chưa có provider gửi email có idempotency key, chưa giải quyết chính sách/manual reconciliation cho trường hợp này.
- Expiry alias có tên command riêng khi production auto-discover; chỉ complete overlay mới đăng ký dưới tên scheduler cũ. Overlay thêm poll `bookings:v2-deliver-prepared` mỗi phút trong Schedule test. Không sửa `app/Console/Kernel.php`. Test gọi command và kiểm tra event binding; chưa chạy cron/Supervisor thật.
- Banking/cash semantics B2 được giữ nguyên: create với `banking` hiện coi local Payment success, manual banking lại có Payment pending. Chưa thống nhất chứng cứ xác nhận chuyển khoản và khi nào phát mail/invoice. Không tự sửa chính sách; **NO-GO cho rollout payment tổng thể** đến khi chủ dự án quyết định và kiểm thử luồng này.

Chưa có merchant credentials sandbox được người dùng cung cấp/xác minh. Không đọc secret `.env` để thử, không dùng bộ fixture `TEST0001` làm merchant thật. VNPay end-to-end, IPN public callback/retry thực tế và SMTP delivery: **BLOCKED / chưa chạy**.

## 4. Dry-run trên MySQL riêng

Fixture/schema lấy từ migration source, plan/mapping tài liệu và dữ liệu tổng hợp, không truy vấn database chính. Mỗi case dùng schema owned đang trống; không wipe/fresh/refresh, không copy tài khoản/token thật.

Thứ tự đã kiểm thử:

1. Chạy từng migration expand `000002` → `000003` → `000004` bằng `--database=mysql --path=<đúng file>` trên fixture riêng; có migration log.
2. Backfill 105 nhóm: giữ 21 Hotel, 6 Type legacy, 336 Room, 336 RoomImage; tạo 105 owned Types, 105 mappings, 105 image links, SUM(total_rooms)=336. Chạy lại trước remap không nhân bản.
3. Chuẩn bị immutable manifest, remap 336 Room FK và 2 BookingRoom history fixture; chỉ đổi Type FK, giữ ID, room_id assigned/null, quantity, nights, price_at_booking, total và financial rows. Verify/rerun checkpoint trước đổi constraint.
4. Migration active duplicate `2026_10_12_000005...`.
5. Migration receipts/effects `2026_10_12_000006...`.
6. Activate route/service/scheduler overlay, kiểm tra API, Admin, History, Invoice và Hotel Detail.

**Dependency đáng chú ý:** remap snapshot gồm toàn bộ Booking fields. Thêm generated `active_duplicate_key` làm snapshot cũ không còn cùng shape; archive manifest và đánh dấu remap đã verified trước step 4. Không chạy lại backfill từ nguồn Room đã remap, không dùng manifest cũ để remap lại sau DDL/writes V2. Post-cutover đối chiếu dữ liệu phải xét mapping và delta mới.

Sự cố đã mô phỏng: backfill lỗi Hotel 2 giữ checkpoint 5 nhóm Hotel 1, resume 100 nhóm; remap lỗi Hotel 2 giữ 16 Room Hotel 1, resume 320 Room; DDL lỗi sau CREATE receipt giữ receipt nhưng migration chưa Ran. Forward-fix rehearsal tạo **chỉ bảng effect còn thiếu** từ SQL của migration chính xác, giữ SHOW CREATE receipt và kiểm tra ba FK effect trước ghi migration log ở batch tiếp theo. Đây là test minh họa, **không phải script tự sửa database chính**; không đánh dấu Ran khi chỉ có nửa schema.

`mysqldump --single-transaction --no-tablespaces --set-gtid-purged=OFF` trên server test riêng, restore bằng mysql vào schema owned trống khác; so sánh dữ liệu/checksum mọi bảng. Đã chạy bổ sung với Payment, receipt, effect, invoice và invoice_item đều không rỗng (giao dịch fixture tổng hợp, mail array). Không có `CREATE DATABASE` hay `hotel_booking` trong dump. **Chưa có proof backup/restore database chính**, không dùng proof fixture thay cho backup thật.

## 5. Kết quả kiểm thử

| Bộ kiểm thử | Kết quả thực tế |
|---|---|
| B1 migration/backfill/guard | PASS 36 tests / 1310 assertions |
| B2 inventory/lifecycle/remap, independent MySQL races | PASS 51 tests / 336 assertions |
| B3 Payment/API/auth/admin/history/concurrency | PASS 45 tests / 283 assertions |
| B4 integration/dry-run/forward-fix/restore | PASS full suite 23 tests / 411 assertions; sau chỉnh nhỏ chạy lại forward-fix (1/19), Admin stopped-selling (1/47) và restore nonempty ledger (1/42), đều PASS |
| Frontend history/Admin/Auth regression | PASS 16 + 17 Node tests |
| Frontend prepared Type availability/Admin payload + rendered scripts | PASS 7 Node tests, gồm parse toàn bộ inline scripts của Hotel Detail và Admin Hotels đã render |
| VNPay sandbox giao dịch thật, SMTP, cron, browser manual | Chưa chạy / blocked như mục 3 |

Lỗi phát hiện trong khi dựng test đã sửa: namespace PaymentService legacy không khớp path (test nạp đúng class để kiểm tra fence), fixture thiếu customer.created_at/review.status khi gọi Dashboard (bổ sung từ schema source), assertion MIME phải đọc binary thay vì base64. Không coi các lỗi fixture/assertion đó là lỗi database chính. Không còn lỗi trong các lượt regression PASS nêu trên.

Các case B4 bao gồm auth thật với tài khoản/token **synthetic**, quantity 1–10, inventory chống giảm dưới commitment, giá snapshot sau sửa Type, Hotel Detail không query Room, invalid availability fail closed, Return pending/success/late, invoice/PDF/memory-mail retry, duplicate IPN metadata, hai tiến trình IPN/delivery dùng connection ID khác và thực sự cùng chờ Hotel lock. B3/B2 chạy riêng các cuộc đua create/expiry/cancel/restore. Không tuyên bố đã kiểm thử UI bằng trình duyệt thật.

## 6. Cách chạy lại an toàn và evidence

```powershell
# Chỉ tạo MySQL instance/datadir mới trong private test runtime.
powershell -NoProfile -ExecutionPolicy Bypass -File tests/Support/start_roomtype_v2_test_server.ps1
php tests/Support/prepare_roomtype_v2_test_databases.php <owned-runtime>/server.json
php tests/Support/run_roomtype_v2_integration_tests.php <owned-runtime>/test-environment.json
node tests/Frontend/roomtype-v2-integration.test.cjs <owned-runtime>
php tests/Support/shutdown_roomtype_v2_test_server.php <owned-runtime>/server.json
```

Runner bootstrap ép APP_ENV testing, random non-3306 loopback port, schema allowlist, server UUID, credentials restricted chỉ owned schemas; guard trước connection và kiểm tra SHOW GRANTS sau mở. Không gọi API live. Không chạy các runner Phase 1 cũ có lấy schema từ database chính.

Evidence private runtime: `last-integration-test-output.txt` (runner lưu lượt gần nhất, kể cả filter), `cutover-dry-run.json`, `complete-route-list.json`, `b4-concurrency-*.json`, immutable `remap-*.json`, `owned-backup.sql`, `owned-backup-restore-proof.json`, HTML render; `phase2b4-test-summary.json` tổng hợp các lượt thực thi. Các file test-environment/client config chứa credential test, không đưa vào Git hoặc báo cáo công khai.

Lượt B4 cuối: runtime `storage/app/private/roomtype-v2-tests/c27be29cc063`, MySQL 8.0.30, loopback port **65519**, test account chỉ có quyền trên 48 owned schemas. B1/B2/B3 regression lần lượt dùng instance riêng port 64688 / 64016 / 49673. Không kết nối port/database chính để kiểm tra schema hoặc dữ liệu. Servers test được shutdown sau khi hoàn tất; giữ evidence private để kiểm tra.

## 7. File thay đổi

Chỉnh 7 file có sẵn, đều thuộc module V2 chuẩn bị: `app/Http/Controllers/PreparedV2/BookingController.php`, `PaymentController.php`; `app/Providers/RoomTypeV2PreparedProvider.php`; `app/Services/VnpayPaymentServiceV2.php`; `app/Support/RoomTypeV2Integration.php`, `RoomTypeV2Inventory.php`; `config/roomtype-v2.php` (thêm flag vẫn false).

Thêm:

- `app/Console/Commands/DeliverPreparedV2Effects.php`, `PreparedV2CancelExpiredBookings.php`.
- `app/Http/Controllers/PreparedV2/AdminHotelController.php`, `HotelDetailController.php`, `InvoiceController.php`, `LegacyContractController.php`.
- `app/Services/PreparedV2/AvailabilityBridge.php`, `BookingBridge.php`, `BookingMailSender.php`; `app/Support/RoomTypeV2WriteFence.php`.
- `resources/views/prepared-v2/{admin-hotels,hotel-detail,payment-result,sidebar-script,similar-section}.blade.php`.
- `phpunit.roomtype-v2-integration.xml`; `tests/Feature/RoomTypeV2IntegrationCompletionTest.php`, `RoomTypeV2CutoverDryRunTest.php`; `tests/Frontend/roomtype-v2-integration.test.cjs`; `tests/Support/RoomTypeV2CutoverFixture.php`, `roomtype_v2_integration_worker.php`, `run_roomtype_v2_integration_tests.php`.
- Báo cáo này. Private runtime/evidence chỉ phục vụ test, bị Git ignore.

Baseline đầu task 574 paths: 567 không đổi byte, 7 file V2 kể trên thay đổi có chủ đích. `.env`, auth, routes production, Console Kernel, migration và các UI gốc/dirty edits được giữ. Không commit/push/reset/stash.

## 8. Checklist trước khi được phép cutover chính

| Gate | Điều kiện PASS | FAIL/abort |
|---|---|---|
| Phê duyệt | Người dùng phê duyệt phạm vi, maintenance và write plan cụ thể | Không tự kích hoạt từ báo cáo này |
| Connection | Xác nhận host/port/server UUID/database/user đích, khả năng backup và quyền operator | Khác đích/không xác minh được → dừng |
| Manifest mới | Refresh read-only được phê duyệt, đối chiếu toàn bộ giá/capacity/ảnh/status/count/FK; xử lý ambiguity/history mới | Source lệch plan, dangling/cross-Hotel FK, booking/status/stock không giải thích được → dừng |
| Backup | Full schema+data+migration ledger+receipts/effects+triggers; dump hash, restore vào server riêng, kiểm tra FK/count/money/checksum | Chưa restore thật/backup không nhất quán → không ghi |
| Reader/UI | List/search/map/Home/Admin contract và manual payment được thống nhất/test đầy đủ | Giá/tồn/UI còn physical hoặc banking confirmation chưa rõ → NO-GO |
| Payment/mail | Merchant sandbox thật xác minh, IPN public và retry/timeout tests; mail sender/retry/duplicate policy duyệt | Không credentials/không có sender policy → NO-GO |
| Freeze writer | Chặn create/cancel/restore/Admin inventory/payment; drain request đang chạy, dừng cron/worker/long-lived PHP; chưa mở Checkout | Không xác định đủ writer hoặc worker cũ còn chạy → abort |
| Callback downtime | Gate IPN ở ingress độc lập; giữ raw signed query an toàn hoặc trả retry để VNPay retry; không ACK 00 trước khi commit | Không chứng minh replay/retry không mất callback → NO-GO |
| SQL order | Explicit path allowlist đúng 5 migration V2, backfill/remap giữa expand và active constraint; verify sau mỗi step | Không chạy migrate toàn bộ Pending; DDL nửa chừng phải inspect rồi forward-fix |
| Reconciliation | Giữ 21 Hotel/336 Room/RoomImage legacy theo refreshed source; mapping bijection, qty/money/history, nullable room_id và ledger nguyên vẹn; 105 theo plan nếu source không đổi | Sai checksum, thiếu receipt/invoice/ảnh hoặc giảm committed inventory → không resume traffic |
| Activate/resume | Deploy approved production-safe wiring (không dùng guard test để kết nối chính); cold restart mọi worker, route audit/auth/API/expiry tests; replay callback rồi resume từng writer | Có route legacy writable/lock sai/oversell → giữ maintenance |
| Checkout riêng | Chỉ mở sau phê duyệt và E2E đầy đủ | Phase này vẫn khóa |

## 9. Maintenance và rollback/forward-fix

Không đặt thời lượng maintenance theo fixture nhỏ; operator phải rehearsal với backup thật để đo thời gian và đặt deadline. `artisan down` một mình không dừng cron, queue hay bảo đảm callback không mất. Callback phát sinh phải có cơ chế ingress durable/retry đã duyệt, signature/amount/merchant/TxnRef được verify lại khi replay; idempotence dựa receipt, không tự thay PayDate/deadline hoặc refund late payment.

- **Trước SQL đầu tiên:** có thể hủy maintenance, khôi phục writer cũ nếu chưa đổi schema/data và callback gate đã kiểm tra.
- **Giữa backfill/remap, chưa mở traffic:** giữ maintenance; sửa nguyên nhân và resume từ checkpoint/manifest gốc, không xóa mapping/Type để chạy lại. MySQL DDL có auto-commit, không giả định transaction rollback được toàn bộ schema. DDL partial phải kiểm tra SHOW CREATE/index/FK và migration log; tạo phần thiếu theo source đã duyệt, không drop financial tables.
- **Trước V2 writes:** khôi phục backup đầy đủ chỉ khi được phê duyệt riêng, callback downtime đã giữ được và không có ledger delta sau backup.
- **Sau Booking/Payment V2 mới hoặc nhận callback:** ưu tiên forward-fix; không restore backup cũ hay rollback hàng loạt làm mất lịch sử/giao dịch. Đối chiếu delta, mappings, receipts/effects, invoice và stock; không remap bằng snapshot cũ.
- Down của active constraint/receipt migrations có guard và có thể từ chối rollback lịch sử không biểu diễn được hoặc financial tables không rỗng. Không dùng fresh/refresh/wipe.

**Kết luận:** GO cho tiếp tục rehearsal và hoàn thiện các blocker độc lập. **NO-GO cho cutover `hotel_booking`, đăng ký provider production hoặc mở Checkout.** Chưa có backup/restore chính được xác minh, sandbox merchant/SMTP proof, approved production connection/writer deployment, reader/list/map compatibility và quyết định manual banking/email retry. Dừng ở Phase 2B.4, không thực hiện cutover chính.
