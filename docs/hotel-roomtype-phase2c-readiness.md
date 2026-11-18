# AUREL — Phase 2C readiness

Chốt trạng thái ngày 12/10/2026, tiếp tục từ các thay đổi/test đã có sau usage limit. Đã kiểm tra baseline và log gần nhất, không triển khai lại Phase 2C.

**DONE: nghiệm thu kỹ thuật synthetic trên MySQL và HTTP/browser staging cục bộ. NO-GO: V2 production cutover. NO-GO riêng: Checkout chính.** Chưa kết nối `hotel_booking`; không thể dùng báo cáo này để khẳng định dữ liệu/timezone hiện tại của database chính hoặc backup chính đã được xác minh.

## 1. Công việc hoàn tất khi tiếp tục

- Xác nhận B4 đã hoàn tất: **23 tests / 418 assertions PASS**; log `storage/app/private/roomtype-v2-tests/90797be9be7c/last-integration-test-output.txt`. Timeout lượt chạy đồng thời trước đó đã được giải quyết bằng rerun tuần tự, không còn regression B4 dang dở.
- Chỉ bổ sung ba assertions cho contract `schema_definitions` trong acceptance preflight; chạy đúng ba case bị ảnh hưởng ở preflight/backup/UTC: **3 / 68 PASS**.
- Chạy một case reader History/Admin/Payment/PDF do DTO payment/reconciliation cuối thay đổi: **1 / 17 PASS**.
- Đọc lại staging browser database bằng guarded READ ONLY: session `+00:00`, **2 bookings**, epoch UTC khớp và cả hai deadline đúng **900 giây**.
- Lint PHP/CJS/PowerShell, compile/lint Blade, kiểm Composer/platform và Git whitespace; đều PASS.
- Hoàn thiện báo cáo này, [test matrix](hotel-roomtype-phase2c-test-matrix.md) và [cutover runbook](hotel-roomtype-phase2c-cutover-runbook.md). Các artifact riêng, gồm log/restore proof/screenshot, giữ trong private storage.

Không chạy lại toàn bộ B1–B5/C, không remap/activate main, không mở Checkout chính. Source logic đã PASS không được triển khai lại. Lượt tiếp tục ban đầu chỉ sửa assertion/tài liệu; phần 19/10 được ghi riêng bên dưới và chỉ đổi test fixture cùng tài liệu liên quan, không tạo API hoặc command production.

### Tiếp tục 19/10/2026 — fixture ảnh readiness

- Nguyên nhân BLOCKED trước đó là fixture lấy nguyên URL ảnh trong mapping chuẩn, trong khi file phòng tương ứng không có trong worktree. `AccommodationImage::localUrl()` đã từ chối đúng ảnh thiếu; không sửa guard/backfill hoặc dữ liệu production.
- Fixture readiness giờ tạo PNG synthetic hợp lệ riêng trong storage public disk của test, tạo plan tạm chỉ đổi URL nguồn ảnh cho fixture, rồi dọn ảnh sau test. Production mapping và fixture chung mặc định không đổi.
- Kết quả lượt regression cuối trên MySQL server cô lập, account chỉ-grant schema synthetic: **40 tests / 357 assertions PASS** cho Payment Readiness, SePay/Checkout và Admin legacy RoomType. Kiểm thử concurrency/expiry/stock/booking nằm trong phạm vi này; snapshot hotel_image cũng không đổi qua backfill. Server đã graceful shutdown; dữ liệu test riêng được giữ lại.
- Worktree hiện đánh dấu xóa các ảnh legacy dưới `public/image/img_room` và `public/image/img_hotel`. Đây không phải lỗi payment/backfill logic, nhưng cần xác minh/khôi phục đúng asset được duyệt trước khi triển khai: nếu ảnh nguồn của database thật không tồn tại hoặc sai mapping, backfill vẫn phải fail closed.
- Không sửa RoomType, hotel image, Checkout, tồn kho, Booking, service production hay migration; không kết nối database chính.

## 2. DONE / PARTIAL / BLOCKED

| Hạng mục | Trạng thái | Kết quả thực tế / phần còn thiếu |
|---|---|---|
| Regression B1–B5 | DONE | B1 36/1310; B2 51/336; B3 45/283; B4 23/418; B5 28/266 PASS; giữ các log hoàn tất |
| C acceptance | DONE synthetic | Full UTC 22/210 + case thứ 23 và follow-up PASS; **không nói đã chạy full 23 một lượt** |
| Payment/inventory deadline | DONE synthetic | 15 phút giữ nguyên; verification deadline chỉ hành chính, không kéo dài hold hoặc hồi sinh booking |
| Timezone | DONE staging / BLOCKED primary | Session UTC từ trước fixture/connection; test epoch và browser bookings đúng; lịch sử primary chưa được đọc/review |
| Delivery safety | DONE kỹ thuật / PARTIAL external | Backoff/cap/lease/uncertain/manual audit PASS; chưa có external SMTP/inbox acceptance |
| Bank/QR | PARTIAL | Validator/instructions/transfer reference/deadline/late queue xong; dữ liệu ngân hàng/QR thật chưa verified |
| VNPay | PARTIAL | Protocol/receipt/ownership/public endpoint validator xong; merchant sandbox E2E và public IPN thật BLOCKED |
| Staging Customer/Admin UI | DONE synthetic | 29 browser checks PASS; Checkout chỉ staging, owned Type CRUD/gallery, real fixture snapshots/PDF/history/logout |
| Funds reconciliation | PARTIAL | Queue late/double/funded-released và email review có auth/audit; **chưa có workflow refund/resolve/khôi phục tồn** |
| Migration 000008 | DONE test | Exact-path Artisan Ran, signed FK, down không xóa audit đã có; chưa chạy chính |
| Preflight tooling | DONE chuẩn bị / BLOCKED primary | READ ONLY report count/hash/DDL/FK/money/ledger; primary CLI cần approval riêng, chưa chạy |
| Backup/restore | DONE synthetic / BLOCKED primary | Dump/import thật riêng, 29 tables + financial/audit + 5 images hash khớp; proof có `primary_backup_verified=false` |
| Production guard/provider | DONE chuẩn bị / PARTIAL deployment | Owned-target `APP_ENV=production` rehearsal PASS; provider chưa đăng ký và manifest production mặc định null |
| Payment readiness fixture ảnh | DONE synthetic | 21/152 Payment Readiness + 19/205 SePay/Checkout/Admin legacy RoomType; combined 40/357 PASS trên server cô lập |
| Offline production migration/backfill/remap | BLOCKED | Exact migration order đã ghi; backfill/remap vẫn test-only; cần source thật được duyệt và orchestration maintenance riêng |
| Legacy image assets trong worktree | BLOCKED xác minh | Ảnh phòng/khách sạn được đánh dấu xóa; cần xác nhận asset package/deployment và đối chiếu mapping trước backfill |
| Real operations | BLOCKED | Writer drain, cron/Supervisor restart, public callback capture/replay và recovery drill chưa nghiệm thu tại deployment thật |

Không có test/syntax failure chưa xử lý trong phạm vi đã chạy. PARTIAL/BLOCKED thể hiện thiếu nghiệm thu, cấu hình hoặc quyền thao tác thực tế; không phải một nhánh được mặc định hoạt động trên production.

## 3. Deadline và UTC đã xác nhận

`RoomTypeV2BankingPolicy` tách `payment_deadline`, `verification_deadline`, inventory state và eligibility. `received_at`/khách bấm đã chuyển tiền không xác nhận Booking. Với default verification 15 phút, deadline review trùng payment; test cấu hình 30 phút chỉ mở thời gian xem xét, không tiếp tục giữ chỗ. Pending hết hạn vẫn phải có expiry job chuyển trạng thái; việc tính stock không được tự giải phóng trước transaction/job.

Staging router/control/worker và acceptance fixture đặt MySQL session `+00:00` **trước khi mở connection**. Case UTC so `UNIX_TIMESTAMP(created_at)` với Carbon UTC và chênh deadline 900 giây. READ ONLY kiểm lại hai booking từ browser cũng không có epoch/deadline mismatch. Evidence: `phase2c-staging-timezone-check.json` tại runtime `09e607acb564`.

`config/app.php` và `config/database.php` gốc không bị đổi. Production guard/provider yêu cầu manifest `session_timezone=+00:00`, `historical_timestamps_verified=true`, kiểm cả timezone session thực tế. Không tự cộng/trừ 7 giờ hoặc normalize TIMESTAMP/DATETIME/pending/payment history trên chính. Review lịch sử primary vẫn là blocker.

## 4. Email và tiền: giới hạn chính xác

Nhánh delivery safety dùng pending → processing → delivered với lease/backoff/attempt cap; pre-send failure đã biết chưa handoff mới được retry tự động. Timeout hoặc crash sau transport có thể đã nhận thư chuyển uncertain, không tự gửi lại. Admin review cần quyền Admin mới, lý do, kiểm tra provider và chấp nhận nguy cơ duplicate khi retry; audit được lưu. Invoice/items/effect/notification marker giữ nguyên; notification bị xóa không tái tạo marker.

`delivered` ở đây là transport acceptance, **không phải inbox delivered**. Stable Message-ID không biến SMTP thành exactly-once. Local TCP SMTP capture đã nhận template và PDF cho synthetic recipient, không relay external; production SMTP chưa verified. Các compatibility fixtures B3–B5 vẫn có mode cũ để regression; production manifest không cho dùng lifecycle/delivery flags chưa đủ.

Queue reconciliation đọc các funds late/double và **Payment success + Booking cancelled/expired**, kể cả audit cũ là success. DTO hiển thị reconciliation, giữ original outcome, Invoice và tiền. Không tự refund, không phục hồi booking/tồn. Queue hiện giới hạn 100 bản ghi mỗi nhóm, chưa có phân trang hoặc workflow đánh dấu xử lý funds; cần quyết định nghiệp vụ riêng khi đi vào vận hành.

## 5. Migration, dependency và production guard

Migration mới duy nhất của 2C: `database/migrations/2026_10_12_000008_add_booking_delivery_safety.php`:

- `booking_effect.next_attempt_at`, `transport_started_at`: nullable DATETIME; index `(state, next_attempt_at)`.
- Bảng `booking_delivery_audit`: signed INT `id`, `effect_id`, nullable `actor_id`; action/state/reason/created_at; FK effect → booking_effect, actor → customer; index `(effect_id,id)`.
- `down()` từ chối khi có audit/attempt hoặc metadata delivery; không xóa history đã sử dụng. MySQL ALTER/CREATE có thể commit từng phần: runbook yêu cầu inspect/forward-fix khi DDL partial, không rollback hàng loạt.

Migration chạy đúng path trên schema sở hữu trong test. Không thay migrations lịch sử, không xóa Room/RoomImage/legacy Type. Composer/npm manifests và locks giữ byte baseline, không dependency mới; package/platform hiện có và PHP 8.2.31/Node 22.22.3 đáp ứng các bước kiểm.

Production provider **vắng trong config/app.php**. Config mặc định: `production_manifest=null`, `staging_checkout=false`, `integration_complete=false`, `final_integration=false`, `delivery_safety=false`. Test overlays chỉ bật trong tiến trình đã qua owned-test guard. Production path cần private manifest pin SHA, expiry tối đa 24 giờ, exact target/UUID/default connection, source/restore proof phù hợp, schema fingerprint, mapping count, frozen writers/callback ready, UTC, và runtime account không DDL/global/cross-schema. Khi approval hết hạn runtime fail closed; renewal là bước vận hành có review, không dùng manifest vô thời hạn.

Synthetic production rehearsal chỉ nhận credentials/UUID/schema allowlist của owned MySQL test. Proof synthetic không thể thay proof restore chính. `RoomTypeV2CheckoutGate` luôn từ chối tạo Booking khi có production manifest, kể cả ai bật nhầm staging flag; mở Checkout sẽ cần approval và thay đổi riêng được review.

Không suy ra MySQL migrations có guard độc lập theo tên database: các migration Laravel dùng connection được operator chọn, vì vậy tuyệt đối chưa chạy Artisan default/whole pending trên main. Các tool backfill/remap hiện còn guard test-only; production runtime guard yêu cầu schema hoàn tất, không phải quyền sửa schema chuyển tiếp.

## 6. Phụ thuộc đọc/ghi và cutover

Xem [dependency map](hotel-roomtype-phase2c-dependency-map.md) và runbook. Runtime overlay đã bridge create/cancel/type/stock/manual banking/IPN/PDF/expiry/delivery và readers catalog/History/Admin/payment. Root controller/service legacy vẫn tồn tại, được route/binding/fence khi overlay được kích hoạt trong test; chưa sửa production routes/Kernel.

Trước activation thật phải kiểm mọi writer: HTTP/Admin, expiry scheduler, mail worker, callback, long-lived PHP và PDO/script ngoài Laravel. Cần durable callback gate/replay, drain request và cold restart theo release hash. Laravel fence không đảm bảo chặn worker cũ hoặc raw PDO bên ngoài app. Backup trước cutover phải bảo toàn financial/audit/asset và delta; sau khi có Booking/Payment mới, không restore snapshot cũ làm mất đơn/tiền.

Backfill/remap production orchestration chưa được triển khai: source phải refresh SELECT-only sau approval, mapping/checksum phải được duyệt, rồi mới chuẩn bị maintenance adapter/authority riêng. Không bỏ TestDatabase guard để chạy lại commands hiện có trên `hotel_booking`.

## 7. File thay đổi trong Phase 2C

So baseline trước 2C: **23 file có sẵn được sửa, 34 file mới (gồm 4 docs), 0 file có sẵn bị xóa**. Giữ các thay đổi/deletion chưa commit từ phase trước. Danh sách dưới đây chỉ delta 2C, không phải toàn bộ Git dirty tree.

### File có sẵn được sửa

| Nhóm | Files |
|---|---|
| Commands/Mail | `app/Console/Commands/DeliverPreparedV2Effects.php`; `app/Mail/BookingConfirmedV2.php` |
| Models | `app/Models/RoomTypeV2.php`; `app/Models/RoomTypeImage.php` |
| Services | `app/Services/BookingEffectServiceV2.php`; `BookingReadServiceV2.php`; `BookingServiceV2.php`; `ManualBankingPaymentServiceV2.php`; `app/Services/PreparedV2/BookingMailSender.php` |
| Support/config | `app/Support/RoomTypeV2Integration.php`; `RoomTypeV2Inventory.php`; `RoomTypeV2WriteFence.php`; `config/roomtype-v2.php` |
| Prepared controllers | `app/Http/Controllers/PreparedV2/AdminRoomTypeController.php`; `BankingController.php`; `BookingController.php`; `DealsController.php` |
| Prepared views | `resources/views/prepared-v2/admin-hotels.blade.php`; `admin-bookings.blade.php`; `banking-admin-script.blade.php`; `booking-payment.blade.php`; `hotel-detail.blade.php`; `sidebar-script.blade.php` |

### File mới

| Nhóm | Files |
|---|---|
| Runtime | `app/Http/Controllers/PreparedV2/ReconciliationController.php`; `app/Providers/RoomTypeV2ProductionProvider.php` |
| Support | `app/Support/BookingDeliveryPreSendFailure.php`; `RoomTypeV2BankingPolicy.php`; `RoomTypeV2CheckoutGate.php`; `RoomTypeV2Database.php`; `RoomTypeV2PaymentConfiguration.php`; `RoomTypeV2Preflight.php`; `RoomTypeV2ProductionGuard.php`; `RoomTypeV2SchemaReadiness.php` |
| Migration/tool | `database/migrations/2026_10_12_000008_add_booking_delivery_safety.php`; `tools/roomtype-v2-preflight.php` |
| Prepared views | `resources/views/prepared-v2/admin-room-types.blade.php`; `checkout-staging.blade.php`; `checkout-staging-script.blade.php`; `deals.blade.php`; `reconciliation.blade.php` |
| PHPUnit | `phpunit.roomtype-v2-acceptance.xml`; `tests/Feature/RoomTypeV2AcceptanceTest.php` |
| Test support PHP | `tests/Support/RoomTypeV2AcceptanceFixture.php`; `RoomTypeV2BackupRehearsal.php`; `prepare_roomtype_v2_staging.php`; `roomtype_v2_acceptance_worker.php`; `roomtype_v2_staging_control.php`; `roomtype_v2_staging_router.php`; `run_roomtype_v2_acceptance_tests.php` |
| Browser/SMTP support | `tests/Frontend/roomtype-v2-browser.cjs`; `tests/Support/roomtype_v2_smtp_capture.cjs`; `close_roomtype_v2_browser.cjs`; `start_roomtype_v2_browser_staging.ps1` |
| Docs | `docs/hotel-roomtype-phase2c-dependency-map.md`; `hotel-roomtype-phase2c-cutover-runbook.md`; `hotel-roomtype-phase2c-test-matrix.md`; `hotel-roomtype-phase2c-readiness.md` |

## 8. Evidence và bảo toàn trạng thái

[Test matrix](hotel-roomtype-phase2c-test-matrix.md) ghi exact logs, phạm vi và số assertions. Các bằng chứng chính tại runtime `storage/app/private/roomtype-v2-tests/09e607acb564/`:

- `full-acceptance-test-output.txt`, filtered acceptance/final logs; `phase2c-browser-results.json` và mobile screenshot.
- `phase2c-staging-timezone-check.json`, `phase2c-owned-preflight.json`, `phase2c-route-list.json`, concurrency evidence.
- `backup-6489ceb054a9/restore-proof.json`: restore mới nhất 29 tables + 5 assets; source/restore khác schema, synthetic only, primary proof false.
- `phase2c-final-syntax-checks.json`, `phase2c-compiled-blade-checks.json`, `phase2c-final-source-delta.json`.

Baseline 621 file được hash trước 2C tại runtime `88bbf2a0f8e5`. Kiểm cuối: 598 byte không đổi, đúng 23 file V2 được sửa, 0 file mất. `.env`, Auth, main routes/Kernel, config/app.php/config/database.php, view gốc và lịch sử migrations được giữ. Không reset/stash/commit/push. Không xem mật khẩu/token thật hoặc dùng account thật làm fixture.

Mọi connection test được ràng buộc descriptor/UUID/datadir/host localhost/port ngẫu nhiên/account grants chỉ owned schemas; từ chối primary trước connection. Không có thao tác đọc/ghi schema chính trong Phase 2C này. Những artifact private có credentials synthetic/dump không được public hoặc commit. Đã dừng browser/HTTP/test MySQL sở hữu sau kiểm tra, giữ log/data/artifact để review.

## 9. Blocker còn lại và quyết định

1. **Bank/QR/SLA:** cần thông tin được owner xác minh và quyết định verification/late-payment handling; default vẫn 15 phút, chưa có refund policy tự động.
2. **VNPay thật:** cần merchant sandbox hợp lệ, public Return/IPN, approved E2E success/failure/late/duplicate/callback replay. Synthetic protocol PASS không đáp ứng mục này.
3. **SMTP thật:** cần sender/recipient được phép, TLS config và inbox acceptance; chốt vận hành uncertain/manual retry. Không hứa exactly-once SMTP.
4. **Primary source/timezone/backup:** cần approval read-only riêng, identity/current counts/mapping/FK/money/history/TIMESTAMP review; backup + restore chính thật trước mọi ghi.
5. **Offline cutover authority:** cần preparation riêng cho guarded production backfill/remap, fresh plan/checkpoints và review partial DDL; commands test hiện có không chạy main.
6. **Deployment/operations:** cần phê duyệt provider/config/wiring, runtime least privilege, writer drain/restart/cron, public ingress durable callback replay và recovery rehearsal thực tế.
7. **Legacy assets:** xác minh các ảnh `public/image/img_room` và `public/image/img_hotel` trong release được duyệt khớp với URL nguồn; không dùng ảnh synthetic của test thay cho asset production.

**Dừng Phase 2C tại đây.** Báo cáo không cấp quyền cutover hoặc mở Checkout; không bắt đầu phase tiếp theo. Các quyết định/policy/credentials và approval thao tác primary nói trên cần hoàn tất riêng trước khi thay đổi production.
