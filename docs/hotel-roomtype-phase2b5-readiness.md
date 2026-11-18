# AUREL — Phase 2B.5: Final Integration, Payment Policy & RoomType V2 Readiness

Ngày thực hiện: 12/10/2026. Đã đối chiếu plan/mapping Phase 2A, B1–B4 và source thực tế. **Phương án C đã triển khai trong module/overlay MySQL test riêng. Không cutover chính hoặc mở Checkout.**

Kết luận: **GO cho tiếp tục nghiệm thu kỹ thuật trên test; NO-GO cho triển khai thật** khi chưa có bank/merchant được xác minh, VNPay sandbox E2E, SMTP/retry policy, backup/restore chính và production wiring/maintenance được phê duyệt.

## 1. QR Banking và VNPay theo Phương án C

### QR Banking

- Booking và Payment mới đều **pending**, kể cả khách chọn banking. Hạn vẫn 15 phút, quantity giữ toàn khoảng `[check_in, check_out)`.
- POST `/api/payments/manual` chỉ ghi/tái dùng pending attempt của QR Banking. Nhấn “đã chuyển khoản”, gửi `payment_status=success` hoặc bấm lại không xác nhận booking, không kéo dài expiry, không tạo invoice/mail.
- Final API và service chỉ cho booking mới dùng banking/vnpay; cash chỉ còn trong prototype compatibility và lịch sử, không phải lựa chọn đặt mới trong final overlay.
- Admin dùng POST `/api/admin/bookings/{booking}/payments/{payment}/verify-banking`. Guard/role/ability giữ nguyên. Service đọc lại role Admin trong DB và Payment thuộc Booking sau khóa.
- Input: `reference`, amount dạng chuỗi DECIMAL chính xác, `received_at` ISO có timezone, `funds_received=true`, `evidence_source=bank_statement`. UI yêu cầu Admin xác nhận đã kiểm tra tiền thực nhận. Không chấp nhận screenshot làm nguồn xác minh, không nhận booking_id/payment_id body để thay URL.
- Amount phải trùng Payment và Booking snapshot. Reference chuẩn hóa uppercase, giới hạn 100 ký tự chữ/số/underscore/hyphen và unique toàn hệ thống. Thời điểm nhận được chuyển UTC, không tương lai hoặc trước Payment. Việc đối chiếu tiền thực nhận là trách nhiệm Admin; không có kết nối ngân hàng để tự chứng minh sao kê.
- Khóa Hotel → Type tăng dần → Booking/BookingRoom → Payment, kiểm lại commitment và duplicate active. Payment success và Booking confirmed được commit cùng verification audit/effect; không có email/PDF trong transaction xác minh.
- Xác nhận lặp cùng dữ liệu trả duplicate, giữ verified_by/time/reference gốc. Dữ liệu xác nhận mâu thuẫn hoặc reference đã dùng cho Payment khác bị từ chối; không ghi đè lịch sử.

### VNPay

Tái sử dụng protocol và service B3/B4: HMAC-SHA512, merchant/reference/amount snapshot, receipt/effect idempotent, Return read-only. Đã đối chiếu lại [tài liệu chính thức VNPay PAY](https://sandbox.vnpayment.vn/apis/docs/thanh-toan-pay/pay.html), trong đó IPN cập nhật giao dịch và Return hiển thị kết quả.

Chỉ URL sandbox được builder chấp nhận. Test dùng merchant/secret **synthetic**, không gọi gateway. Không coi `TEST0001` là credentials thật. Nút VNPay trên page test chỉ sẵn sàng khi có flag merchant đã xác minh và cấu hình hợp lệ; mặc định tắt. API vẫn kiểm ownership/deadline trước tạo URL, không xác nhận từ frontend.

## 2. Expiry, late funds và reconciliation

| Tình huống | Kết quả |
|---|---|
| Admin xác minh hợp lệ trước deadline | Payment success, Booking confirmed, một audit/effect |
| Pending qua deadline nhưng job chưa chạy | Ghi tiền/late_reconciliation, giữ pending thận trọng đến khi expiry writer chạy; không gia hạn hoặc xác nhận |
| Booking cancelled/expired | Ghi tiền/late_reconciliation; giữ trạng thái đã giải phóng, không tự restore |
| Khách trả trước deadline nhưng Admin xác minh sau | Vẫn late_reconciliation; received_at cũ không kéo dài hiệu lực giữ phòng |
| Banking và VNPay cùng thanh toán thành công | Giữ cả hai financial attempts; attempt thứ hai duplicate_payment_reconciliation, chỉ một confirmation effect |
| Duplicate verification/callback | Không invoice/effect/notification trùng |
| Amount/reference/evidence sai | Không xác nhận, không thay Payment/Booking; lỗi validation rõ ràng |
| Lỗi SQL trước commit | Toàn bộ Payment, Booking, audit/effect của lượt xác minh rollback |

Payment success thể hiện **tiền đã ghi nhận**, không đồng nghĩa Booking đang hợp lệ. Payment DTO thêm `display_status=reconciliation`, `verification_outcome` và danh sách attempts; giữ enum payment_status hiện có, không xóa receipt hay tự refund.

Admin không được dùng status endpoint để chuyển pending thành confirmed hoặc restore released order nhằm né verification. Completed chỉ từ confirmed; cancel/completed qua shared lifecycle và audit. Prototype unsigned `verifiedPaymentSuccess()` bị chặn trong final integration.

**Giữ 15 phút:** QR thủ công đòi hỏi Admin kiểm tra kịp hạn. Phải nghiệm thu SLA vận hành trước deployment; nếu không khả thi, cần quyết định thời hạn/UX riêng, không âm thầm giữ phòng vô hạn. Không triển khai workflow giải quyết/refund reconciliation khi chưa được duyệt.

## 3. Reader đã chuyển sang RoomType V2

`HotelCatalogServiceV2` là nguồn read chung, lấy MIN(base_price) từ owned Type active, SUM(total_rooms) cho tổng inventory. Giữ alias `rooms_min_price`, `rooms_count`, `min_price` cho consumer cần tương thích; không fallback `room.price`.

| Consumer | Cách chuyển trong final overlay |
|---|---|
| Home/list/search/filter | Catalog query, giữ Blade Home, giá mới sau Admin edit; max_price dùng owned Type active |
| HotelController API index/show | Prepared CatalogController; trả owned room_types/gallery và alias giá |
| HotelService | Binding bridge đúng namespace legacy, toàn bộ getList/getDetail/getRooms/getLocations dùng catalog/type inventory |
| Location/map | Join Hotel/RoomType trực tiếp, không phụ thuộc v_map_hotels hoặc relation Location.rooms; radius/lat/lng có validation |
| Deals | Giữ bố cục và phép chọn nhóm cũ, đổi nguồn giá/location sang catalog; featured/min-price đã kiểm tra |
| Similar Hotels/Hotel Detail | Owned Types/base_price/images và availability V2; giữ date/guest picker, selector một Type/quantity, Checkout disabled |
| Admin Hotels/service | Tổng từ inventory V2, không chạy parent withCount physical trong final mode; CRUD Type/gallery không sinh Room |
| Admin Bookings/customer detail/service | BookingReadServiceV2: Type × quantity, snapshot tiền, attempts và reconciliation; không load Booking.rooms |
| Customer History/Payment detail | Nested summary: Hotel, Type, quantity, nights, unit price, total, method/status; giữ room_id nullable trong raw history, không resolve phòng vật lý |
| Invoice/Email/PDF | Template V2 giữ style, bỏ đoạn đọc `$br->room`; tiền lấy price_at_booking × nights × quantity |

`HotelReadV2` chặn relation Room và minPrice dùng owned Type. Global catalog guard từ chối unmapped/mismatched history hoặc Type fields không hợp lệ. Date inventory dùng SUM quantity theo trạng thái và checkout exclusive; overcommit trả lỗi/conflict, không làm giả số 0. Type inactive không được hiển thị đang bán.

Legacy source/model relation vẫn được giữ để compatibility/offline remap, không bị xóa. Trong **final overlay**, route/service bindings đã chuyển; ngoài overlay hệ thống chính vẫn chạy source legacy. `/api/hotels/{id}/rooms` và `/booking/{id}` tiếp tục 409 cho contract vật lý/checkout cũ, không giả Room IDs. `Room`/`RoomImage` vẫn tồn tại để bảo toàn lịch sử.

Rà soát gián tiếp: Wishlist/Chat/Itinerary không có reader Room.price/minPrice cần thay trong các controller đã kiểm tra; giá điểm tham quan của Itinerary không phải giá khách sạn. Map adapter hiện là reader, không ghi map_search_log như controller legacy; cần review contract telemetry riêng nếu vận hành dùng log này.

**Vấn đề có sẵn ở Deals:** controller legacy tạo discount/original_price ngẫu nhiên. Bản prepared giữ phép chọn/metadata này để không tự đổi nghiệp vụ; giá bán canonical vẫn base_price V2. Không có chứng cứ promotion thật cho các badge đó. Cần nguồn/quy tắc ưu đãi được duyệt trước phát hành UI thật; không coi fixture hay badge random là dữ liệu thương mại đã xác minh.

## 4. API, Admin và Customer contract

- POST `/api/bookings`: một owned Type, quantity 1–10, capacity × quantity, date hợp lệ, room_id bị cấm, không tin price/total frontend. Final default banking; chỉ banking/vnpay. Booking summary trả nested data từ snapshot.
- GET `/api/hotels`, `/{id}`, `/map`, `/api/locations`: catalog V2. GET `/api/hotels/{hotel}/room-types`: price/capacity/gallery/stock; thiếu ngày available=null và bookable=false.
- GET `/api/bookings/my`, `/{id}` và GET `/api/payments/{booking}`: Sanctum ownership; chọn đúng funded/latest attempt, không dùng hasOne Payment đầu tiên để che VNPay/banking attempt mới.
- GET `/api/admin/bookings` hỗ trợ Hotel/Customer/status/search/dates; GET `/{booking}/payment-audit` có verification/state audit/effect status, chỉ Admin.
- POST Admin verify-banking như mục 1; PUT status vẫn qua lifecycle, không vượt xác nhận Payment. Actor ID lấy từ guard, không từ body.
- Các CRUD/gallery/inventory B3/B4 giữ ownership URL, chống giảm dưới peak commitment và không hard-delete lịch sử. Đã kiểm bank confirmation của đơn giữ chỗ khi Type sau đó ngừng bán: không coi ngừng bán là tự hủy cam kết cũ.
- Prepared `/admin/bookings` giữ layout, thêm input sao kê/attestation và trạng thái reconciliation/delivery. Không còn option UI confirmed/pending override.
- Prepared `/payments/bookings/{booking}` là trang summary/payment cho **đơn đã có**. SSR không đưa Customer/Booking PII ra page; JS gọi API có token/ownership. Không mở luồng tạo Checkout production.
- QR/bank instruction chỉ hiển thị khi config đã có bank/account/name. QR chỉ nhận local image helper hợp lệ, không tự tạo ảnh hoặc tài khoản ngân hàng. Config hiện null nên UI báo chưa sẵn sàng và khóa acknowledgement; gateway action mặc định khóa đến khi merchant được xác minh.

`route:list --json` đã chạy trong app bootstrap test và lưu `b5-route-list.json`. Không thay routes production, guard hoặc Authentication.

## 5. Email, notification, invoice và PDF

- Confirmation chỉ cho Booking confirmed/completed và Payment success. Claim/effect/invoice là transaction sau transaction xác nhận đã commit; sender/PDF chạy ngoài lock/transaction.
- Invoice/InvoiceItem dùng price_at_booking, quantity, nights và tổng snapshot. Invoice cũ được reuse, không renumber hay xóa.
- Notification booking_success tạo một lần, có marker riêng theo effect. Nội dung không nói email đã gửi khi chưa gửi. `notification_id` có FK SET NULL: Customer được xóa notification qua API hiện có, marker vẫn giữ để retry không tạo lại.
- Delivery trả state/attempts/last_error/delivered_at, DTO có display_state=failed khi pending có lỗi sender; retry vẫn dùng event key/invoice/notification ổn định.
- Mailable/PDF V2 giữ template style và bỏ Room lookup. Transport test **array**, chỉ `@example.test`, remote PDF off. Memory transport acceptance không chứng minh gửi đến hộp thư thật.
- Đã mô phỏng sender crash **sau** transport nhận: retry có thể tạo **hai email memory**, nhưng vẫn một invoice và một notification. Đây là test xác nhận giới hạn exactly-once, không phải lỗi đã giả vờ giải quyết. Stable Message-ID không làm SMTP exactly-once; cần sender/provider idempotency hoặc policy retry đã duyệt trước production.
- Không tự refund, xóa receipt, hoặc đánh dấu email sent từ IPN. Sender lỗi không xóa sự thật tài chính.

## 6. Migration mới

`database/migrations/2026_10_12_000007_add_manual_payment_verification_audit.php` — không sửa migration lịch sử:

| Bảng mới | Nội dung |
|---|---|
| manual_payment_verification | INT signed ID/FK Booking/Payment/Admin(Customer); unique payment_id và bank_reference; received_amount DECIMAL(12,2), received_at/verified_at, evidence_source, outcome, trạng thái Booking/Payment trước và sau |
| booking_state_audit | Signed Booking/actor FK nullable, actor_kind, status before/after, reason và timestamp; append qua writer cho create/cancel/expiry/admin/payment confirmation |
| booking_effect_notification | Signed effect_id PK/FK; notification_id BIGINT unsigned nullable phù hợp schema hiện có, unique, FK SET NULL để không phá delete notification của Customer |

Không thêm/chuyển enum business status, không đổi Customer/Hotel/Room/Booking money fields hay historical account data. down() từ chối khi có audit/marker cần giữ, chỉ bỏ schema mới khi rỗng. MySQL DDL gồm ba CREATE, có thể commit từng phần: phải preflight và forward-fix phần thiếu theo source đã duyệt; không đánh dấu Ran khi schema chưa đủ.

Đã chạy **đúng path migration** bằng Artisan trên owned schema test, xác nhận Ran, kiểm signed/FK, thử Payment FK sai bị MySQL 1452 và giữ history có room_id/quantity/snapshot. Các fixture integration khác gọi up() trực tiếp sau guard để dựng schema; không mô tả chúng như migration log production.

Thứ tự deployment dự kiến: expand 000002–000004 → backfill/remap/verify → constraint 000005 → receipts/effects 000006 → audit policy 000007 (customer_notifications phải tồn tại) → wiring cuối. Không chạy migrate toàn bộ Pending.

## 7. Test thực tế

| Suite | Kết quả |
|---|---|
| B1 nguyên suite | PASS **36 / 1310 assertions**, MySQL riêng port 64931 |
| B2 regression | PASS **51 / 336**, port 59489 |
| B3 regression | PASS **45 / 283**, port 49362 |
| B4 regression/dry-run/dump-restore | PASS **23 / 418**, port 49752 |
| B5 full suite | PASS **28 / 253**, port 64515, 02:56.801 |
| B5 follow-up sau tinh chỉnh | PASS: 3/40 (audit time/map/Deals/templates); 1/14 (missing merchant/UI gate); 3/39 (notification deletion/FK/route list); 1/15 (strict final methods/customer acknowledgement) |
| Node Admin Auth / Phase1 history/UI / B4 prepared | PASS **17 + 16 + 7** |
| Node B5 banking/merchant/SSR scripts | PASS **9** (7 functional + 2 rendered-script syntax) |
| VNPay sandbox E2E/SMTP/cron/browser manual thật | **CHƯA CHẠY / BLOCKED**, không tính PASS |

Tổng **183 PHP test cases khác nhau** trong các lượt full và **49 frontend tests** đã chạy. Follow-up là recheck case có sẵn, không cộng như case mới. Lượt full B5 có trước những tinh chỉnh cuối; các case liên quan đã được chạy lại như bảng, không tuyên bố full cuối có số assertion khác khi chưa chạy.

Đã cập nhật **ba kỳ vọng B2** vì policy C supersede prototype cũ: banking create pending thay confirmed/success; manual acknowledgement pending thay completed; VNPay nay là method được hỗ trợ (test unsupported method đổi sang giá trị không hỗ trợ). Không nới capacity/date/stock/ownership checks để ép PASS. Test legacy Phase1 vẫn giữ source/hành vi cũ ngoài overlay; PASS đó **không chứng minh production legacy đã có policy C**.

## 8. Concurrency và lỗi đã xử lý

Năm race B5 dùng hai PHP process/CONNECTION_ID khác nhau, parent giữ Hotel lock, quan sát **2 InnoDB waiters** rồi cho cạnh tranh:

| Race | Connection IDs trong lượt full | Kết quả |
|---|---|---|
| Hai Admin verification cùng Payment | 58 / 57 | Một verification, lượt còn lại duplicate; một effect |
| Verification versus cancel | 61 / 62 | Booking cuối cancelled; tiền giữ, không resurrection |
| Verification versus expiry | 65 / 66 | Booking cancelled, late_reconciliation, không effect |
| Banking versus VNPay IPN | 69 / 70 | Hai financial successes, một confirmation effect |
| Reference trùng ở hai Hotel | 73 / 74 | Một thắng, một bị từ chối reference; không payment success giả/lặp |

Artifact `b5-concurrency-*.json` trong runtime private lưu waiters/schema/results. B2/B3/B4 regression còn kiểm race last-room/duplicate active/create-vs-stock/expiry/cancel/restore/IPN/delivery. Không coi các interleaving đã quan sát là vét cạn mọi lịch chạy.

Đã phát hiện **MySQL 1213 deadlock** trong lần đầu cross-Hotel verification do current reads Payment cạnh tranh. Shared writer và create transaction được retry giới hạn **3 attempts**; callback chỉ chứa DB work, nên không phát mail/PDF khi retry. Case lỗi đã chạy lại PASS và full B5 PASS. Không retry validation/sai tiền để che lỗi.

Một lỗi dựng fixture: DB CURRENT_TIMESTAMP và clock giả Laravel không cùng thời điểm khiến receipt time bị coi trước Payment. V2 Booking/Payment mới ghi created_at từ clock Laravel để phù hợp expiry/verification; không sửa timestamp lịch sử, timezone hoặc `.env`. Audit create time cũng explicit. Trước cutover phải xác minh timestamp/session timezone của dữ liệu legacy, không tự chuẩn hóa main.

## 9. Cách ly và evidence

Không kết nối `hotel_booking`, kể cả lấy schema/fingerprint. Giữ B1 guard APP_ENV testing, own datadir/server UUID/loopback port khác 3306, schema allowlist và test account không có quyền main/global write. Guard spy xác nhận final reader/writer/provider từ chối main **trước DB::connection**. Schema/fixtures từ source và tài liệu; không chạy seeder, không tài khoản/token thật, không API live/gateway/email thật.

Runtime full B5: `storage/app/private/roomtype-v2-tests/99c63527e170`, port **64515**, MySQL **8.0.30**. Evidence: `full-final-test-output.txt`, các filtered output, `b5-route-list.json`, `b5-concurrency-*.json`, rendered `b5-*.html`, source-baseline và summary. Runtime khác có output B1–B4/restore proof; tất cả server test dừng sau hoàn tất, dữ liệu/evidence giữ private.

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tests/Support/start_roomtype_v2_test_server.ps1
php tests/Support/prepare_roomtype_v2_test_databases.php <owned-runtime>/server.json
php tests/Support/run_roomtype_v2_final_tests.php <owned-runtime>/test-environment.json
node tests/Frontend/roomtype-v2-final.test.cjs <owned-runtime>
php tests/Support/shutdown_roomtype_v2_test_server.php <owned-runtime>/server.json
```

Không chạy runner Phase1 có copy schema từ main. Descriptor/credential test nằm storage private/Git ignore, không đưa vào report/commit. Baseline **597 paths**: 17 file V2/test thay đổi có chủ đích, **580 giữ nguyên byte**, gồm `.env`, auth, production routes/provider registration/kernel, Home/Hotel Detail/Admin Blade gốc, các migration cũ và dirty edits.

## 10. File thay đổi

Chỉnh 17 file có sẵn:

- `app/Http/Controllers/PreparedV2/{AdminHotelController,BookingController,HotelDetailController,InvoiceController,PaymentController}.php`.
- `app/Providers/RoomTypeV2PreparedProvider.php`.
- `app/Services/{BookingEffectServiceV2,BookingLifecycleServiceV2,BookingServiceV2,VnpayPaymentServiceV2}.php`.
- `app/Services/PreparedV2/{BookingBridge,BookingMailSender}.php`.
- `app/Support/{RoomTypeV2Integration,RoomTypeV2WriteFence,RoomTypeV2Writer}.php`.
- `config/roomtype-v2.php`; `tests/Feature/RoomTypeV2BackendTest.php`.

Thêm:

- `app/Http/Controllers/PreparedV2/{BankingController,CatalogController,DealsController}.php`.
- `app/Mail/BookingConfirmedV2.php`; `app/Models/HotelReadV2.php`.
- `app/Services/{BookingReadServiceV2,HotelCatalogServiceV2,ManualBankingPaymentServiceV2}.php`.
- `app/Services/PreparedV2/{AdminBookingServiceBridge,AdminHotelServiceBridge,HotelServiceBridge}.php`.
- `app/Support/RoomTypeV2BookingAudit.php` và migration 000007 tại mục 6.
- `resources/views/prepared-v2/{admin-bookings,banking-admin-script,booking-payment,email-booking-confirmed,invoice-pdf}.blade.php`.
- `phpunit.roomtype-v2-final.xml`; `tests/Feature/{RoomTypeV2FinalMigrationTest,RoomTypeV2FinalReadinessTest}.php`.
- `tests/Frontend/roomtype-v2-final.test.cjs`; `tests/Support/{RoomTypeV2FinalFixture,roomtype_v2_final_worker,run_roomtype_v2_final_tests}.php`.
- Báo cáo này. Không xóa source/schema/data, không commit/push/reset/stash.

## 11. Blocker còn lại

1. Chưa có bank/account/QR đã xác minh hoặc credentials merchant sandbox thật; cấu hình null/verified=false có chủ đích, không đoán thông tin.
2. Chưa test VNPay E2E, IPN qua public ingress/retry thực tế, SMTP/hộp thư/PDF thực tế. Synthetic protocol tests không thay thế các proof này.
3. Exactly-once email chưa giải quyết qua transport: crash sau send trước ACK có thể gửi lặp. Cần sender idempotency hoặc policy retry đã duyệt.
4. Chưa xác minh backup/restore và manifest dữ liệu chính hiện tại. Counts 21/105/336 ở test không chứng minh main còn như Phase2A; phải refresh preflight được phê duyệt.
5. Guard/prepared provider hiện chỉ cho test, chưa có production connection/write barrier/rollout đã duyệt. Routes/API legacy chính vẫn giữ hành vi cũ; phải chuyển đủ writer/callback/scheduler/worker, không chỉ bật flag hoặc áp provider patch.
6. Chưa nghiệm thu manual browser/cron thực tế và SLA Admin banking 15 phút; reconciliation/refund workflow cần quyết định riêng. Historical timestamp/timezone và compatibility alias/telemetry cần review khi chuẩn bị rollout thật.
7. Promotion metadata random có sẵn ở Deals chưa có nguồn thương mại thật; cần duyệt nguồn/rules, không tự phát hành badge như dữ liệu đã xác minh.

## 12. GO / NO-GO và thứ tự tiếp theo

**GO test:** Phương án C, owned Type readers, quantity/capacity/stock, Admin verification/audit, funds reconciliation, snapshot History/Invoice, memory Email/PDF, FK/notification compatibility và độc lập MySQL concurrency đã có implementation/test.

**NO-GO main/Checkout** cho đến khi tất cả gate sau đạt:

1. Xác nhận banking/merchant cấu hình thật an toàn tại máy; kiểm sandbox E2E và SMTP, quyết định retry/reconciliation/SLA và nguồn promotion.
2. Phê duyệt production-safe wiring/connection guard và các alias/consumer contract; browser/cron/worker staging proof.
3. Refresh manifest/FK/money/images/history/financial audit, full backup và restore thử thành công trên server riêng.
4. Maintenance: dừng/drain toàn bộ writer, scheduler/queue/long-lived worker; gate/capture IPN hoặc đảm bảo provider retry, không ACK success trước commit.
5. Chạy đúng explicit path/order ở mục 6 sau phê duyệt; checkpoint/verify từng bước. DDL partial inspect/forward-fix, không fresh/refresh/wipe hoặc migrate toàn Pending.
6. Đối chiếu data/ledger/audit/stock sau chuyển, restart worker với code mới, replay callbacks idempotent, kiểm auth/ownership/expiry và resume từng writer.
7. Nếu chưa có writes mới, restore backup chỉ theo phê duyệt riêng và bảo toàn callback delta. Sau writes V2/nhận tiền, forward-fix; không rollback/restore cũ làm mất financial/history/audit.
8. Mở Checkout là phê duyệt riêng sau nghiệm thu, không thuộc task này.

**Dừng sau Phase 2B.5. Không tự thực thi main migration/backfill/remap hoặc triển khai production.**
