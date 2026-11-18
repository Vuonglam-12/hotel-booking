# AUREL — Phase 2C cutover runbook

Tài liệu chuẩn bị ngày 12/10/2026. **Không phải phê duyệt thao tác trên database chính.** Provider production chưa đăng ký; Checkout production khóa cả UI V2 và service tạo Booking. Tool preparation/backfill/remap hiện chỉ dành cho test và phải tiếp tục giữ guard.

## 1. Các cổng bắt buộc

1. Người vận hành được phê duyệt riêng phạm vi đọc, backup và sau đó phạm vi ghi/deploy. Xác nhận host/port/database/server UUID/account; không suy ra từ tên connection `mysql`.
2. Chạy preflight SELECT-only được phê duyệt, refresh nguồn/mapping; xử lý orphan, giá/capacity khác nhau, BookingRoom assigned không rõ nghĩa, conflict pending/confirmed, ledger availability khác 0 và trạng thái lạ. Không mặc định 21/105/336 còn đúng. Plan hiện tại phải từ chối nguồn lệch; lập và duyệt artifact mới khi cần.
3. Đối chiếu timezone: Laravel UTC, MySQL session/system timezone, TIMESTAMP và DATETIME, created_at/expires_at/paid_at/received_at. Phân biệt dữ liệu do CURRENT_TIMESTAMP tạo với chuỗi UTC do ứng dụng tạo. Không cộng/trừ 7 giờ hàng loạt hoặc tự sửa deadline lịch sử. Chốt cách xử lý pending/history trước khi dùng session UTC.
4. Backup schema/data/migration ledger/financial/audit/objects/assets; hash và restore thật vào server riêng, đối chiếu schema, FK, count, quantity và tiền. Proof fixture không thay thế proof chính.
5. Kiểm tra bank/QR đã xác minh, merchant sandbox + public IPN/Return, SMTP sender/recipient/transport và SLA xác minh ngân hàng. Chốt chính sách email uncertain/manual retry. Không mở các phương thức chưa đạt nghiệm thu.
6. Freeze toàn bộ writer: HTTP, Admin, cron, queue, long-lived PHP, worker ngoài app/PDO và script thủ công. Drain request đang chạy; xác nhận release/code hash. Fence Laravel không thay thế bước này.
7. Gate callback tại ingress: durable capture raw signed request được bảo vệ hoặc cơ chế retry được merchant nghiệm thu. Không ACK thành công trước transaction commit. Không ghi secret vào access log hoặc đưa signed callback lên tài liệu công khai.

## 2. Preflight được chuẩn bị, chưa chạy trên chính

`tools/roomtype-v2-preflight.php` không đọc `.env`. Không có artifact phê duyệt thì từ chối trước PDO. Artifact đặt dưới `storage/app/private/roomtype-v2-deployment`, chứa action `roomtype_v2_preflight`, approved_read_only, expires_at tối đa 24 giờ, server_uuid và target/credentials SELECT-only. Tạo và bảo quản credentials tại máy; không đưa vào repository hay command line.

Sau phê duyệt đọc, operator mới dùng:

```powershell
php tools/roomtype-v2-preflight.php --approval=<private-approved-read-target.json> --confirm-target=<host:port/database> --output=<private-report.json>
```

Tool kiểm identity/grants, dùng READ ONLY consistent snapshot và rollback, xuất count/hash/DDL fingerprint/FK/orphan/source groups/money conflicts/timestamps/migration ledger; không xuất hàng Customer/password/hash/token. Không dùng report này để tự quyết định normalize dữ liệu. Nguồn có view/cross-schema FK cần review thủ công; không tự đọc dữ liệu schema bên ngoài.

## 3. Backup và restore trước ghi

- Ngừng DDL và writer liên quan; ghi cutoff/timezone/binlog coordinates nếu hạ tầng dùng binlog. Inventory/pending callbacks phải nằm trong kế hoạch bảo toàn delta.
- Dump toàn bộ schema/data cùng migrations, Payment/receipt/effect/manual verification/delivery audit/Invoice/items; kiểm view/trigger/routine/event trước chọn quyền và tùy chọn dump. Account backup riêng, hạn chế quyền; account runtime không có DDL/global/cross-schema rights.
- Bảo quản client config và dump ngoài public, với ACL phù hợp. Dùng consistent dump InnoDB; không chứa CREATE DATABASE ngoài đích đã xác minh. SHA-256 cho dump và manifest.
- Backup toàn bộ uploaded assets thực tế (`storage/app/public`, public image/upload directories theo cấu hình đã kiểm tra) cùng release/build artifact cần thiết. Không chỉ backup 5 ảnh fixture.
- Restore vào server/database khác, xác nhận target restore trước import. Kiểm schema/column/index/CHECK/FK rules, tất cả count và business checksums, quantity/nights/price_at_booking/tổng tiền; đối chiếu asset hashes. Charset có thể được mysqldump ghi tường minh dù collation đã xác định nó; chuẩn hóa chi tiết dư này, giữ đầy đủ thuộc tính cấu trúc.
- Proof chính phải ghi identity nguồn và `restored_matching=true`, `synthetic_only=false`, `primary_backup_verified=true`. Không gán các cờ này cho rehearsal fixture.

## 4. Explicit allowlist và thứ tự

Chỉ sau phê duyệt ghi, operator áp từng path được review, không chạy toàn bộ Pending:

| Bước | Artifact / kiểm tra |
|---|---|
| Expand 1 | `2026_10_11_000002_expand_room_type_for_hotel_inventory.php` |
| Expand 2 | `2026_10_11_000003_create_room_type_image_table.php` |
| Expand 3 | `2026_10_11_000004_create_room_type_legacy_map_table.php` |
| Backfill | Approved fresh source plan, clone/map/gallery; kiểm từng Hotel/checksum/checkpoint |
| Remap | Immutable original manifest; Room/BookingRoom chỉ đổi type FK, giữ IDs/room_id/quantity/nights/money/history |
| Constraint | `2026_10_12_000005_replace_booking_status_unique.php` sau xử lý active conflicts và archive verified remap manifest |
| Receipts/effects | `2026_10_12_000006_create_payment_receipts_and_effects.php`, then `2026_10_19_000001_create_payment_gateway_unmatched_events.php` |
| Banking audit | `2026_10_12_000007_add_manual_payment_verification_audit.php` |
| Delivery safety | `2026_10_12_000008_add_booking_delivery_safety.php` |
| Runtime | Approved production manifest + provider registration, cold restart, route/service/scheduler audit |

Mẫu lệnh migration cho **từng path đã được duyệt**, không thực thi trong Phase 2C:

```powershell
php artisan migrate --database=mysql --path=database/migrations/<approved-exact-file.php>
```

**Backfill/remap commands hiện có vẫn test-only.** Không chạy chúng trên main hoặc bỏ guard. Sau preflight nguồn thật phải review/duyệt orchestration maintenance có guard theo từng bước và artifact nguồn được xác minh; runtime production guard chỉ nhận schema đã hoàn tất, không phải quyền chạy backfill trên schema chuyển tiếp. Đây là cổng còn lại trước cutover, không phải một lệnh đã sẵn sàng chạy mặc định.

## 5. DDL partial và checkpoint

MySQL DDL có thể commit từng statement. Mỗi bước lưu inspection/migration ledger và checksum riêng. 000008 gồm ALTER effect rồi CREATE audit; lỗi sau ALTER không có nghĩa ALTER rollback. So SHOW CREATE/columns/index/FK với source, forward-fix đúng phần thiếu theo review, xác minh đủ schema rồi mới ghi migration ledger. Không dùng `fresh`, `refresh`, `wipe`, rollback hàng loạt hoặc đánh dấu Ran khi schema chưa đủ.

Backfill per-Hotel DML rollback Hotel đang lỗi; phần đã commit giữ checkpoint. Remap resume chỉ nhận FK cũ hoặc FK mới đúng cặp trong manifest gốc. Sau DDL active key hoặc writes V2, không dùng snapshot remap cũ để overwrite data mới.

## 6. Runtime activation được chuẩn bị

`RoomTypeV2ProductionProvider` chưa nằm trong config/app.php. Registrar chạy sau mọi provider route legacy. `RoomTypeV2Database` chỉ cho deployment khi integration_complete/final_integration/delivery_safety đều true; unsigned prototype và sender retry cũ không được dùng.

Private deployment manifest được pin SHA-256 trong config được review, còn hạn tối đa 24 giờ, target host/port/db/user + UUID, mapping_count từ nguồn thật, exact normalized schema SHA, restore/source proof files và hashes, frozen-writer/callback checkpoints, `checkout_enabled=false`, `session_timezone=+00:00`, `historical_timestamps_verified=true`, state ready/active. State ready yêu cầu maintenance. Mọi connection/default Eloquent phải trỏ đúng target; runtime user không root/DDL/global/cross-schema. Cache proof theo PDO + approval hash, kiểm lại khi reconnect/artifact đổi; DDL/ops drift vẫn cần kiểm soát vận hành.

Synthetic rehearsal manifest chỉ được mở own test descriptor, credential/UUID/allowlist và grants test. Nó không cho quyền main. Proof restore synthetic bị từ chối để duyệt production.

Scheduler expiry alias và delivery phải nạp toàn bộ guarded wiring. Không chạy cron với code/config cũ. Rehearsal dùng bounded in-process tick ở `roomtype_v2_staging_control.php`; nó không spawn default artisan theo `.env`. Production cron/Supervisor thật chưa được nghiệm thu trong task này.

## 7. Reconcile và resume

- Đối chiếu count/FK/stock/giá/ảnh/history/financial/audit với nguồn đã duyệt và delta; không dùng số fixture làm tiêu chí nguồn thật.
- Route list không duplicate/missing method; create/cancel/expiry/type/stock/manual/IPN/PDF writers đều đi V2, legacy direct calls bị fence. Audit quyền Admin/Customer bằng account được phép.
- Replay callback nguyên trạng qua verifier, idempotent receipts; late/double payments vào queue, không restore/refund. Tick expiry/delivery theo wiring mới, kiểm uncertain/retry/invoice/notification.
- Cold restart mọi worker và kiểm code hash. Resume từng writer được duyệt; Checkout vẫn khóa. Production SMTP phải cấu hình/recipient policy riêng, không dùng array acceptance làm bằng chứng hộp thư.

## 8. Abort / forward fix / restore

Trước ghi có thể hủy maintenance khi chưa thay dữ liệu và callback gate đã an toàn. Trong DDL/backfill/remap giữ maintenance, inspect và resume/forward-fix từ checkpoint. Chỉ restore backup cũ khi được duyệt và chứng minh chưa có Booking/Payment/callback delta mới. Sau phát sinh ledger mới, giữ receipt/invoice/audit, reconcile delta và ưu tiên forward fix; không mất tiền/đơn mới để quay về trạng thái cũ.

NO-GO nếu sai đích/proof, schema partial, source drift chưa giải thích, active conflict/orphan, inventory shortage, timezone chưa review, worker cũ còn chạy, callback có thể mất, hoặc phương thức thanh toán chưa nghiệm thu. Phê duyệt Checkout là bước riêng, không suy ra từ cutover readiness.

## 9. Trạng thái rehearsal sau khi tiếp tục

- B4 hoàn tất **23 tests / 418 assertions PASS**; không còn regression B4 dang dở. Lượt timeout đồng thời ban đầu không được tính PASS; log rerun tuần tự là evidence.
- C full UTC **22/210 PASS**, case thứ 23 và follow-up **4/78 PASS**; lần kiểm cuối preflight/backup/UTC **3/68 PASS**, reader History/Payment/PDF **1/17 PASS**. Không chạy lại các suite không bị ảnh hưởng.
- Browser HTTP/MySQL staging cục bộ **29 checks PASS**; READ ONLY kiểm lại 2 booking có session `+00:00`, epoch khớp, deadline 900 giây. Không chứng minh timezone/history primary.
- Restore rehearsal mới nhất: private runtime `09e607acb564/backup-6489ceb054a9/restore-proof.json`, 29 tables và 5 assets khớp, `synthetic_only=true`, `primary_backup_verified=false`. Không dùng proof này để ký production manifest.
- Source hiện tại có đủ validators/guard chuẩn bị; chưa có real bank/VNPay/SMTP acceptance, primary preflight/backup hoặc offline production backfill/remap authority. Provider chưa đăng ký, Checkout chính giữ khóa.

Chi tiết file, evidence và DONE/PARTIAL/BLOCKED: [readiness](hotel-roomtype-phase2c-readiness.md), [test matrix](hotel-roomtype-phase2c-test-matrix.md). Manifest runtime hết hạn sau tối đa 24 giờ sẽ fail closed; kế hoạch vận hành phải có review/renewal tương ứng.
