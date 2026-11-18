# AUREL — Phase 2D operations delta

Bổ sung cho [cutover runbook C](hotel-roomtype-phase2c-cutover-runbook.md), không thay thế các cổng ở đó. **Không phải phê duyệt đọc/ghi primary hoặc deploy.** Không command nào của tài liệu này đã được chạy trên `hotel_booking`.

## 1. External acceptance còn thiếu

| Nhóm | Owner cần cung cấp qua cấu hình riêng tại máy | Checklist cần nghiệm thu sau cấp phép |
|---|---|---|
| Bank/QR **BLOCKED** | Bank name/code, account holder/number được xác minh, local QR image hoặc payload thực, instructions; reference phải là `payment.transaction_id`; xác nhận SLA nhân sự xác minh | Kiểm QR người nhận/tài khoản/amount/reference; khách bấm chuyển không xác nhận tiền. Admin có đúng role/ability, bằng chứng statement riêng, amount/reference/received_at chính xác, audit/idempotency. Xác minh trước expiry 15 phút; late/double vào reconciliation, không phục hồi tồn hoặc tự refund |
| VNPay sandbox **BLOCKED** | `services.vnpay`: merchant terminal, secret private, URL sandbox, public HTTPS Return và IPN; merchant verification từ operator; quyền tạo giao dịch sandbox cụ thể | Success/failure/cancel thật; public ingress reachable, merchant/reference/amount/HMAC; duplicate/late IPN, replay/idempotency/receipt. Browser Return không xác nhận nhận tiền. UI readiness/configuration không thay chứng cứ E2E |
| SMTP **BLOCKED** | `roomtype-v2.staging_mail`: approved endpoint/port/TLS/peer verify, auth private, sender/from identity, allowlist recipient đã được duyệt; phạm vi gửi thử | TLS/SMTP thật, MIME/template/PDF, sender identity và mailbox nhận thật. `delivered` chỉ transport acceptance; uncertain phải review, không gửi lại tự động. Không dùng customer thật làm recipient hoặc gọi local capture là inbox E2E |

Không gửi secret vào chat, document hoặc CLI argv. Không tự tạo credentials. Phone OTP và Admin send-email hiện trả 503 rõ ràng, chưa có SMS/SMTP implementation tương ứng được nghiệm thu. Google E2E/domain/audience/key rotation cũng chưa được thử bên ngoài.

## 2. Preflight read-only: WAITING_APPROVAL

Tái sử dụng `tools/roomtype-v2-preflight.php`. D bổ sung validator **trước PDO**, private report path, TLS cho remote, exact schema privilege check. Account đọc riêng chỉ SELECT trên schema literal, không global/role/FILE/GRANT OPTION. Không nâng quyền để đọc objects ngoài phạm vi. `_` và `%` phải được xử lý đúng `@@partial_revokes`, không suy ra chỉ từ tên schema.

Owner/operator xác nhận host, port, database, UUID, username, thời hạn <=24h; sau phê duyệt mới lưu artifact riêng dưới `storage/app/private/roomtype-v2-deployment`. Ví dụ **không sử dụng được để kết nối**, không phải approval:

```json
{
  "action": "roomtype_v2_preflight",
  "approved_read_only": false,
  "expires_at": "<reviewed UTC ISO time +00:00>",
  "server_uuid": "<operator verified UUID>",
  "target": {
    "host": "<approved host>", "port": "<approved port>",
    "database": "<approved database>", "username": "<dedicated SELECT-only user>",
    "password": "<private credential, never committed>", "ssl_ca": "<required CA file for remote>"
  }
}
```

Sau approval đọc riêng, operator dùng đúng target confirmation:

```powershell
php tools/roomtype-v2-preflight.php --approval=<private-approved-read-target.json> --confirm-target=<host:port/database> --output=<new-private-report.json>
```

READ ONLY snapshot/rollback của tool xuất schema definitions/fingerprint, business count/checksum, FK/orphan, price/quantity/nights/money conflicts, timestamps/source groups và migration ledger, không xuất personal rows. Report phải đối chiếu Hotel/Type/Room/RoomImage, BookingRoom/Booking/Payment/Invoice/items và audit/effect/delivery. Kiểm legacy assigned-room history, active duplicate, inventory ledger, unmapped/wrong-Hotel Type, status lạ và mapping drift. D chưa thêm đánh giá automatic cho mọi trường hợp lịch sử; invoice mismatches/timestamp interpretation cần operator review theo schema thực.

Không mặc định primary còn 21/105/336 hay chưa có booking. Đối chiếu TIMESTAMP/DATETIME và Laravel/MySQL session/system timezone; không tự cộng 7 giờ. Schema/business hash có thể thay khi writer còn chạy: report ngoài freeze chỉ là baseline đọc, không đủ làm migration authority.

## 3. Backup objects/assets và restore proof

Rehearsal synthetic C không đổi nên giữ PASS, không chạy lại. Delta D:

- Tool offline `tools/roomtype-v2-asset-inventory.php` không load Laravel/.env/PDO; fixed roots `public/image`, `storage/app/public`, `public/build`, `public/fonts`, `public/vendor`; bỏ duplicate `public/storage`. Refuse linked/escaping/unreadable entries và output public/đã có. Không copy/delete file.
- Inventory thực tế local: **155 files, 108.733.817 bytes**, SHA-256 mỗi file và manifest. Artifact private: `storage/app/private/phase2d-evidence/local-asset-inventory.json`. Không xác nhận đủ DB references, remote S3 hoặc backup/restore thật; source hỗ trợ disk remote nhưng runtime main chưa được đọc.
- Backup inventory phải gồm mọi BASE TABLE, `migrations`, Hotel/Room/RoomImage/legacy Type, Booking/lines, Payment/receipt/effect/Invoice/items/manual/state/notification/delivery audit; liệt kê VIEW, TRIGGER, ROUTINE, EVENT riêng với definitions/DEFINER/charset/sql_mode/schedule/enable state/dependencies. Preflight hiện đánh dấu view manual, **không tự export đủ routine/trigger/event**.
- DBA được phép export objects với account backup riêng tối thiểu; không cấp thêm quyền cho preflight SELECT-only/runtime. Không claim “0 objects” vì chưa được kiểm primary. Event scheduler phải tắt/kiểm state ở target restore để không tự chạy writer.
- Freeze asset upload/DDL và writer liên quan, archive release/build, asset hashes, DB dump SHA-256, schema fingerprint, ledger, financial totals/checksums, cutoff/UTC/binlog coordinates nếu có. ACL private, backup mã hóa theo vận hành, ghi người giữ backup. Dump primary cần approval riêng.
- Restore vào **server/database khác đã xác minh**, kiểm count/hash/schema/FK/quantity/nights/price_at_booking/amount/financial histories/assets/object definitions. Chỉ proof thật khớp mới có `synthetic_only=false`, `primary_backup_verified=true`. C proof 29 bảng/5 fixture assets không thể đổi nhãn thành proof chính.

Tool inventory là chuẩn bị và local evidence, không authorize backup đọc primary hay restore overwrite.

## 4. Production backfill/remap orchestration plan

Giữ nguyên test-only guard của `PrepareRoomTypeV2`, `RemapRoomTypeV2` và services. D chọn **orchestration plan**, chưa tạo công cụ production chạy được. Cần review riêng source thật/target/approval trước implementation hoặc execution:

1. **Authorize/inspect:** approval theo action read/backup/write/deploy tách biệt, pin exact target/UUID/release SHA, operator/time/expiry. Read report và restore proof phải cùng nguồn và đủ mới; ảnh/timestamps/mapping bất thường cần quyết định người dùng.
2. **Freeze/drain:** hoàn tất writer map ở mục 5, callback capture, snapshot assets. Lấy fresh manifest trong freeze: schema/ledger/source group/checksum/raw legacy IDs, price/capacity/status/image provenance/history. Dừng nếu nguồn khác plan đã ký; không điều chỉnh count cho vừa fixture.
3. **Dry-run:** plan per Hotel mô tả clone/new Type, source IDs và gallery link, real inserted IDs chưa giả định từ 7, totals/snapshots/target mappings và expected postconditions. Operator review diff/hash rồi confirm exact target/action/manifest SHA tại terminal. Dry-run không ghi business rows.
4. **Expand DDL:** allowlist đúng từng path 000002 → 000003 → 000004. Inspect trước/sau mỗi statement. Không chạy mọi Pending; không coi DDL được rollback trọn transaction. Chỉ ghi Ran khi schema đủ và ledger đúng.
5. **Backfill per Hotel:** dưới freeze, kiểm source checksum ngay trước transaction; clone/map/images bằng IDs database sinh; giữ 6 legacy Type, Presidential 6 không tự clone. Mỗi Hotel DML một transaction, checkpoint commit sau postconditions. Không nhân bản file, tự đổi ảnh/giá/capacity hay sinh room numbers.
6. **Remap per Hotel:** dùng manifest **gốc bất biến** map đúng `(hotel_id, old_type_id)`; Room và BookingRoom chỉ thay type FK đã duyệt. Giữ physical IDs/room_id/quantity/nights/price snapshots/Booking/Payment/Invoice/history. Resume chỉ nhận FK cũ hoặc FK mới đúng map, checksum mọi cột còn lại; drift/ambiguous assigned history → stop.
7. **Validate/finish schema:** đối chiếu toàn source và per-Hotel checks; sau xử lý conflict mới exact-path 000005, rồi 000006/000007/000008 theo [runbook C](hotel-roomtype-phase2c-cutover-runbook.md). Không DROP Room/RoomImage/legacy Type, không ledger fake-Ran.
8. **Runtime review:** fresh read report/schema hash/source proof/restore proof/checkpoints, production manifest hạn <=24h, UTC/history approval, no-debug, least privileges, Checkout false. Provider registration/cold restart/smoke có approval deploy riêng. Runtime guard chỉ nhận schema đã hoàn tất, **không dùng guard này để cấp quyền expand/backfill**.

Checkpoint private cần `run_id`, action/operator/time, target identity, release/manifest SHA, hotel ID, source hash, inserted mapping IDs, row counts, money/image/history post-hashes, transaction commit marker, phase/state và error class. Audit không có passwords/tokens/customer rows. Resume phải đối chiếu actual committed DB với checkpoint; thiếu checkpoint không tự rollback Hotel đã commit. Source/history đổi ngoài kế hoạch hoặc manifest hết hạn → giữ freeze và review.

DDL partial: inspect columns/index/FK/SHOW CREATE/ledger, forward-fix phần thiếu theo approval; không lặp mù migration, rollback hàng loạt, fresh/refresh/wipe. Sau có financial delta mới không restore snapshot cũ để mất receipts/invoices; bảo toàn delta và forward-fix/reconcile. Chưa có production dry-run/DDL/backfill/remap thực tế.

## 5. Writer/deployment acceptance map

| Writer / ingress | Source đã audit | Freeze và resume yêu cầu, chưa thực thi |
|---|---|---|
| HTTP Customer | Root create/payment legacy đóng 503; V2 BookingController/Gate/services dưới approved overlay | Maintenance/gate new mutation, drain in-flight; resume read trước, Checkout vẫn false |
| Admin | API `auth:admin` + `AdminMiddleware`; Type/inventory, banking verification/reconciliation | Tạm ngừng CRUD/status/verify; drain transactions; resume từng API sau audited role/ability và correct V2 route |
| Scheduler | `Console\Kernel` expiry mỗi phút; overlay bind expiry alias + delivery schedule | Dừng cron/task, xác minh không còn instance cũ; cold-load guarded config/provider; bounded tick rồi resume |
| Queue/long-lived | Delivery commands/service, potential external Supervisor/process manager | Inventory processes/config/release tại deployment thật; drain lease/uncertain, stop và cold restart toàn worker; không mặc định queue restart đã đủ |
| Callback/IPN | Public route có signature/merchant/amount/receipt; Return không writer authority | Ingress durable capture raw signed params/query/body với ACL/encryption, dedup/hash/time/processing state. Không ACK success trước commit. Replay nguyên trạng qua verifier, ghi receipt, late/double vào queue; không sửa signed fields hoặc refund |
| Direct PDO/scripts | Preflight read-only; preparation/test workers/restore control còn PDO; writer ngoài repo chưa biết | Allowlist operator tasks, ngừng scripts/SQL sessions/old credentials; Laravel fence không chặn PDO/process ngoài app. Thu hồi DDL cho runtime chỉ trong approved ops |

Route source audit cuối: root **114**, acceptance overlay **129**, 0 missing methods, overlay 0 duplicates. Private artifact `54c4b321bc5a/phase2d-route-audit.json`; `phase2d-artisan-base-route-list.json` là Artisan route list trên môi trường owned. Phone/email stub 503 không tương đương tính năng external DONE.

Provider production vẫn vắng trong `config/app.php`; source flags default null/false, Checkout không được kích hoạt. Không sửa cron/Supervisor/queue/live server. Rehearsal provider/expiry/delivery C giữ PASS; production controls do operator triển khai mới có thể nghiệm thu.

Abort khi sai identity/approval/proof, source drift, object backup thiếu, schema partial, orphan/conflict/stock sai, timestamps chưa hiểu, old writers còn sống, callback có thể mất hoặc financial total thay không giải thích. Trước ghi có thể hủy an toàn sau xử lý ingress; giữa DDL/backfill giữ maintenance và forward-fix từ checkpoint. Restore cũ chỉ sau phê duyệt và chứng minh không mất delta mới. Resume theo thứ tự đọc → callback replay → expiry → Admin → delivery đã verified → Customer writers được duyệt; Checkout là cổng riêng.

**DONE:** source review và runbook/orchestration preparation. **PARTIAL:** least-privilege/TLS/object backup/capture/restart acceptance trên môi trường thật. **WAITING_APPROVAL:** primary read/backup/write/deploy. **NO-GO:** cutover/Checkout.
