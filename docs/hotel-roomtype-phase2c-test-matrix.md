# AUREL — Phase 2C test matrix

Ngày đối chiếu: 12/10/2026. Chỉ MySQL/HTTP/browser staging **cục bộ, cách ly**, dữ liệu và tài khoản synthetic. Không kết nối `hotel_booking`, không gọi API live hoặc gateway/mailbox bên ngoài.

## Kết quả regression đã giữ lại

Các log dưới đây có kết quả hoàn tất; không chạy lại toàn bộ suite khi tiếp tục sau usage limit. Đường dẫn artifact tương đối với repository, nằm ngoài public và không được đưa lên Git.

| Suite | Kết quả hoàn tất | Bằng chứng |
|---|---:|---|
| B1 — expand/backfill | PASS 36 tests / 1.310 assertions | `storage/app/private/roomtype-v2-tests/35229bd073fa/last-test-output.txt` |
| B2 — inventory/booking | PASS 51 / 336 | `storage/app/private/roomtype-v2-tests/4e94c6db90e7/last-backend-test-output.txt` |
| B3 — payment/API readiness | PASS 45 / 283 | `storage/app/private/roomtype-v2-tests/e5246afa5c8b/last-readiness-test-output.txt` |
| B4 — integration/cutover rehearsal | **PASS 23 / 418** | `storage/app/private/roomtype-v2-tests/90797be9be7c/last-integration-test-output.txt` |
| B5 — final integration/banking | PASS 28 / 266 | `storage/app/private/roomtype-v2-tests/bf758f6ec976/full-final-test-output.txt` |
| C — toàn bộ 22 case lúc chạy full UTC | PASS 22 / 210 | `storage/app/private/roomtype-v2-tests/09e607acb564/full-acceptance-test-output.txt` |
| C — case mới paid → cancelled reconciliation, production provider, prepared views, preflight | PASS 4 / 78 | `storage/app/private/roomtype-v2-tests/09e607acb564/filtered-acceptance-de9806b14065-test-output.txt` |
| C — kiểm tra cuối bị ảnh hưởng: preflight schema definitions + financial/asset restore + UTC | **PASS 3 / 68** | `storage/app/private/roomtype-v2-tests/09e607acb564/filtered-acceptance-c11300bd377b-test-output.txt` |
| B5 — reader History/Admin/Payment/PDF bị ảnh hưởng bởi DTO cuối | **PASS 1 / 17** | `storage/app/private/roomtype-v2-tests/09e607acb564/filtered-final-06bffe5f191f-test-output.txt` |

B1–B5 có 183 test độc lập. C hiện có **23 case độc lập đã PASS qua full 22 + follow-up**, chưa chạy một lần full 23; các lượt filtered có overlap, không cộng assertions hoặc lượt chạy thành số test độc lập. Tổng phạm vi PHP là 206 case độc lập qua các lần chạy trên, không phải một lần suite tổng 206.

Lượt B2/B4 chạy đồng thời ban đầu hết timeout wrapper 600 giây, **không được tính PASS**. Rerun tuần tự đã hoàn tất với assertions giữ nguyên như bảng trên. B4 không còn dang dở. Lượt tiếp tục chỉ chạy ba case C và một case B5 thực sự liên quan, không chạy lại B1–B4 hoặc toàn bộ B5/C.

## Tiếp tục 19/10/2026 — fixture ảnh readiness

| Suite | Kết quả | Phạm vi |
|---|---:|---|
| `RoomTypeV2PaymentReadinessTest` | PASS 21 / 152 | Backfill với PNG synthetic hợp lệ; hotel_image snapshot không đổi; VNPay, timeout, retry, rollback, inventory/booking concurrency |
| `SePayWebhookTest` + `AdminRoomTypeSourceTest` | PASS 19 / 205 | SePay/Checkout→invoice/email array, VNPay callback và Admin legacy RoomType |
| Tổng lượt regression cuối | **PASS 40 / 357** | MySQL server test riêng, account chỉ có quyền trên owned schemas; không truy cập database chính |

Nguyên nhân lỗi trước đó: URL trong prepared mapping trỏ tới ảnh legacy không có trong worktree; `localUrl()` từ chối đúng ảnh thiếu. Fixture readiness dùng mapping tạm trỏ đến PNG raster synthetic hợp lệ, vì vậy validation nội dung ảnh vẫn chạy. Guard và mapping production không đổi; ảnh synthetic được dọn sau test. Các ảnh legacy trong worktree đang được đánh dấu xóa nên tính sẵn có của assets deployment vẫn **BLOCKED** cho đến khi được xác minh độc lập.

## Acceptance kỹ thuật C

| Nhánh | Case / điều kiện xác nhận | Trạng thái, giới hạn |
|---|---|---|
| Deadline | Payment 15 phút; verification window là thông tin hành chính; nhận tiền muộn không phục hồi tồn; xác nhận trước deadline 1 giây; duplicate chỉ một event | DONE trên MySQL synthetic; SLA ngân hàng thật chưa chốt |
| UTC | Session `+00:00`; `UNIX_TIMESTAMP(created_at)` khớp epoch UTC; expiry − created = 900 giây; xác minh đúng hạn | DONE; không đổi config/timezone hoặc lịch sử database chính |
| Retry email | Pre-send failure có backoff, attempt cap; timeout/post-acceptance crash/lease cũ vào uncertain; không tự gửi lại; manual review có role/attestation/reason/audit | DONE; SMTP exactly-once không được bảo đảm |
| Idempotency | Giữ effect/invoice/items/notification marker khi retry; xóa notification không tạo lại marker | DONE |
| Concurrency | Hai PDO/MySQL worker khác connection ID, ít nhất hai InnoDB waiters; một claim effect; expiry cạnh tranh bank không hồi sinh booking | DONE; artifact `phase2c-concurrency-*.json` trong runtime UTC |
| Configuration | Bank chưa verified, QR thiếu, merchant/endpoints không hợp lệ, SMTP thiếu allowlist/TLS đều fail closed và không xuất secret | DONE validators; cấu hình thật BLOCKED |
| SMTP transport | Local TCP capture thực sự nhận MIME + PDF, synthetic recipient được phép | DONE loopback; chưa chứng minh inbox hoặc external SMTP |
| Cancel/reconcile | Cancel trước delivery không gửi confirmation; paid sau delivery rồi cancelled vẫn vào queue, giữ outcome gốc, Payment và Invoice | DONE; không refund hoặc restore tồn |
| Guards | Primary từ chối trước connection; production cần lifecycle đủ, manifest pin/expiry/identity/backup/schema/UTC; source drift bị từ chối; Checkout production 403 | DONE trên target test sở hữu; production thật chưa kích hoạt |
| Migration 000008 | Exact-path Artisan ghi Ran; signed FK; down từ chối xóa delivery history | DONE test; chưa áp dụng chính |
| Preflight | Hai READ ONLY inspection giữ nguyên checksum; đủ schema definitions/hash, FK/count/money/source/ledger; không xuất personal rows | DONE test; primary preflight chưa được phép chạy |
| Backup/restore | mysqldump thật, import schema mới riêng, 29 bảng; financial/effect/manual/delivery audit có dữ liệu; count/checksum/schema/FK/migration khớp; 5 asset hash khớp | DONE synthetic; `primary_backup_verified=false` |
| Prepared views | Không promotion giả; staging checkout gated; admin reconciliation/owned Type/gallery; dead physical/cash modal và quick-confirm button được bỏ trong adapter | DONE test; view gốc và production route giữ nguyên |

## Trình duyệt và frontend

Chrome headless thực sự truy cập HTTP staging + MySQL riêng: **29 checks PASS, 0 uncaught JavaScript errors**. Đây là kiểm thử trình duyệt tự động, không phải nghiệm thu thủ công của chủ website.

Bằng chứng trong `storage/app/private/roomtype-v2-tests/09e607acb564/`:

- `phase2c-browser-results.json`: 29 checks, `synthetic_only=true`, `real_gateway_e2e=false`, `real_mailbox_e2e=false`.
- `phase2c-mobile-detail.png`: viewport 390px, không overflow ngang trong case đã kiểm.
- `phase2c-staging-timezone-check.json`: đọc lại 2 booking của browser; session UTC, 0 epoch mismatch, 0 deadline 900 giây mismatch.
- `staging-http.stderr.log`: không có Fatal/Uncaught/SQLSTATE/Exception khi scan cuối.

Browser đã kiểm Home, customer login, Admin Login/Dashboard guard, ngày checkout exclusive, guests/quantity 2, summary, booking snapshot không room_id vật lý, bank chưa cấu hình, Admin statement verification, scheduler tick, PDF `%PDF`, History, cancel/expiry, reconciliation page, owned Type CRUD/gallery/disable, Deals, logout tách token Admin/Customer và mobile overflow.

External origins fonts/CDN/maps bị chặn có chủ đích; không suy ra các dịch vụ đó đã nghiệm thu. Ngày browser fixture là 15–19/10/2026; khi tái chạy sau các ngày này cần dùng clock/date fixture phù hợp trong test cách ly.

Node frontend đã có **49 PASS** trước khi tiếp tục (17 Admin Auth + 16 Phase 1 + 7 B4 + 9 B5, gồm 4 checks inline scripts từ HTML rendered). Kết quả được giữ từ output công cụ của lần chạy; không có file TAP tổng lưu riêng. Không chạy lại chỉ để tạo log. Các script C mới đã được `node --check` và browser HTTP kiểm trực tiếp.

## Syntax, dependency và preservation

| Kiểm tra cuối | Kết quả | Evidence |
|---|---|---|
| PHP source + raw Blade | 48 files `php -l` PASS = 37 PHP + 11 Blade | `phase2c-final-syntax-checks.json` |
| Blade compiled PHP | 11 templates compile + lint PASS | `phase2c-compiled-blade-checks.json` |
| New CJS | 3 files `node --check` PASS | `phase2c-final-syntax-checks.json` |
| New PS1 | Parser PASS | `phase2c-final-syntax-checks.json` |
| Composer | `validate --no-check-publish` hợp lệ; `check-platform-reqs` tất cả success; no scripts/plugins/network install | Output công cụ lần tiếp tục; PHP 8.2.31, Node 22.22.3 |
| Git whitespace | `git -c core.safecrlf=false diff --check` exit 0 | Output công cụ lần tiếp tục |
| Baseline bảo toàn | 621 file có sẵn: 598 hash không đổi, 23 file V2 thay đổi đúng phạm vi, 0 file mất | `phase2c-final-source-delta.json`; baseline tại runtime `88bbf2a0f8e5` |

Không thay Composer/npm manifest hoặc lock; không cài thêm package. `.env`, Auth controllers/middleware, production routes/Kernel, config/app.php/config/database.php, view gốc và migrations lịch sử có trong baseline vẫn giữ byte ban đầu. Những deletion chưa commit từ phase trước được giữ, không tạo lại Staff/topbar.

## BLOCKED ngoài môi trường synthetic

1. Bank/account/QR và SLA xác minh chưa được owner cung cấp/xác nhận; chưa quét QR/chuyển khoản thật.
2. VNPay merchant sandbox/public Return/IPN và E2E thật chưa có; protocol synthetic không thay merchant acceptance.
3. External SMTP sender/recipient được duyệt và inbox E2E chưa có.
4. Primary source/UTC history/backup/restore proof chưa được phép đọc hoặc xác minh.
5. Offline production backfill/remap orchestration vẫn cần review riêng; các command hiện có cố ý chỉ nhận test.
6. Real deploy, worker/cron, writer drain, public callback gate/replay và recovery drill chưa nghiệm thu.
7. Ảnh legacy `public/image/img_room` và `public/image/img_hotel` đang thiếu trong worktree; cần khôi phục/đối chiếu asset production đúng mapping trước backfill.

Không có assertion hoặc syntax failure còn mở trong phạm vi test đã hoàn tất. Các mục BLOCKED trên không được đánh dấu PASS và không cho phép mở Checkout/cutover chính.
