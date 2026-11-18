# AUREL — Phase 2D readiness

Chốt delta so với Phase 2C ngày **12/10/2026**. Tiếp tục từ các thay đổi hiện có, giữ toàn bộ 34 file của lượt trước và bổ sung đúng các lỗi/test liên quan. Không reset/stash/commit/push, không đọc/kết nối/ghi `hotel_booking`, không sửa `.env`, không chạy migration chính, không đăng ký provider V2 production hoặc mở Checkout.

**DONE:** phần chuẩn bị và kiểm thử độc lập được nêu bên dưới. **PARTIAL/BLOCKED:** nghiệm thu môi trường thật, external services và một số khoảng trống schema/coverage. **NO-GO:** production cutover và Checkout. Không coi synthetic PASS là production E2E.

## 1. Trạng thái công việc

| Nhóm | Trạng thái | Delta / bằng chứng thực tế |
|---|---|---|
| B1–B5/C đã PASS | DONE lịch sử, giữ lại | Không chạy lại toàn bộ. Chỉ chạy case bị ảnh hưởng; các kỳ vọng HTTP legacy mở Checkout được cập nhật đúng việc đóng entrypoint. [Matrix C](hotel-roomtype-phase2c-test-matrix.md) ghi kết quả tại thời điểm C, không phải full suite hiện tại đã rerun |
| Defensive security | DONE trong phạm vi đã kiểm | Signed Google identity, OTP reset bypass, unsafe legacy financial ingress, entity handler injection, legacy renderer escaping, invoice page limit, cancellation cutoff và secret flash đã xử lý. [Security audit](hotel-roomtype-phase2d-security-audit.md) có severity/code/repro/fix/limits |
| Authorization/validation/error | DONE synthetic / PARTIAL toàn hệ thống | Mass assignment, notification ownership, guest Admin, reset-token boundaries, Google negative claims, 401/422 headers. Admin role/ability/booking ownership cũ giữ PASS. Không tự đổi Sanctum/model/tài khoản |
| Performance | DONE hai bottleneck / PARTIAL load | Same-schema response hash khớp; Catalog 116→11 queries, History 278→21. [Performance report](hotel-roomtype-phase2d-performance-audit.md) ghi p50/p95/memory/EXPLAIN và giới hạn |
| UI/accessibility/SEO | DONE checks chọn lọc / PARTIAL nghiệm thu | Profile empty state thật, labels/dialog focus/Escape, Type aria-selected/gallery stale response, Admin labels, Home chat mobile/labels, public canonical/meta/OG và private noindex. Không redesign layout |
| Bank/QR | BLOCKED | Owner chưa có cấu hình được duyệt; instructions/QR/SLA xác minh thật chưa acceptance |
| VNPay sandbox/public IPN | BLOCKED | Thiếu merchant/config/domain/permission; giữ protocol/receipt/idempotency đã PASS, không gọi gateway |
| External SMTP | BLOCKED | Thiếu endpoint/sender/recipient được duyệt; không gửi email thật. Retry/backoff/uncertain C giữ nguyên |
| Primary preflight | WAITING_APPROVAL | Reuse tool, bổ sung approval/target/TLS/grants/private output. Chưa có approval read-only dùng được, chưa mở PDO primary |
| Backup/restore | DONE local inventory / BLOCKED primary | 155 asset local/hash; C synthetic restore proof giữ lại. Chưa có dump/object/asset-reference/restore proof chính |
| Production backfill/remap | DONE plan / PARTIAL execution readiness | Có [operations/orchestration plan](hotel-roomtype-phase2d-operations.md), không bỏ test-only guard, không chạy main. Cần source/backup/freeze/approval thật rồi review execution riêng |
| Deployment operations | DONE source review / PARTIAL thực tế | Writer map/drain/capture/replay/restart/resume/abort đã chuẩn bị. Root 114 routes, V2 overlay 129; 0 missing methods, overlay 0 duplicates. Cron/Supervisor/ingress thật chưa acceptance |
| Schema/legacy gaps | PARTIAL | DOB/reset table trong source cần đối chiếu schema được phép; phone OTP/Admin email chỉ unavailable 503. Legacy non-HTTP writers phải freeze/fence khi cutover |

## 2. Môi trường và bảo toàn dữ liệu

- Owned MySQL **54c4b321bc5a**, port **58603**, datadir mới trong private workspace; khi 48 schema gần hết tạo thêm instance **b0959b839bf7**, port **61180**, chỉ chạy 1 regression B4. Test principal chỉ có quyền trên owned test schemas, không global write hoặc quyền trên `hotel_booking`. Control bootstrap account chỉ tồn tại trên hai instance mới này, không dùng principal chính.
- Settings private, target regex/port/host/schema allowlist/UUID/credential phải hợp lệ **trước bootstrap/PDO**; sau connection kiểm actual identity và grants. Fixture lấy từ source/mapping, không lấy schema/account/token thật. Google HTTP fake, Mail fake/array, browser chặn external origins, không API live.
- HTTP browser staging `127.0.0.1:58645`, schema `aurel_roomtype_v2_test_e46ebc920864`, generated accounts synthetic. Defensive renderers chỉ ghi DOM/marker, không business DB rows. Browser setup login tạo token synthetic trên owned schema; không sử dụng token thật.
- `.env`, config/app.php/config/database.php, routes, HTTP/Console Kernel, migrations lịch sử/V2, Admin login controller/middleware và Composer/npm manifests/locks giữ hash baseline. D không thêm migration/dependency. Những deletion Staff/topbar đã có được giữ nguyên.
- Baseline trước D có 655 file source, artifact `storage/app/private/phase2d-evidence/source-baseline.json`; final delta có **621 giữ nguyên hash, 34 file sửa, 16 file mới, 0 file mất**. Cả 34 file đã có khi tiếp tục vẫn tồn tại và giữ phần công việc trước; các bổ sung được liệt kê ở mục 5. Protected-source check **85/85 giữ nguyên hash** gồm `.env`, routes/Kernel, Admin Authentication, migrations và dependency manifests/locks.

Các artifacts/credentials dưới private storage bị Git ignore; không công bố personal data. Không dùng restoration proof synthetic để authorize main.

Sau kiểm thử đã đóng đúng Chrome/HTTP staging và graceful shutdown hai MySQL owned. Xác nhận không còn listener trên **58603/58645/61180**; giữ nguyên datadir/log/artifact để review, không xóa thư mục hoặc dừng dịch vụ chính. Flag “no database mutations” trong artifact defensive cũ chỉ nói về renderer; ghi chú private đã làm rõ setup login vẫn tạo/thay token synthetic, không ghi dữ liệu nghiệp vụ hay primary.

## 3. Test matrix D — chỉ những phần thực sự bị ảnh hưởng

Runtime artifact mặc định trong bảng là `storage/app/private/roomtype-v2-tests/54c4b321bc5a/`.

| Kiểm tra hoàn tất | Kết quả | Artifact / giới hạn |
|---|---:|---|
| D security/quality ban đầu + deployment pure guard | **14 tests /81 assertions PASS** | `phase2d-tests-5f72aa0e84d0.txt`: 11 security +3 deployment |
| Private headers/trusted canonical | **1/13 PASS** | `phase2d-tests-ef5a4fa21837.txt` |
| Secret flash + 401/422 middleware order + private header regression | **2/25 PASS** | `phase2d-tests-0184023b4088.txt`; overlap private-header case; lỗi header trước sửa đã xử lý |
| Legacy financial + missing-method unavailable response/no DB/no external call | **1/12 PASS** | `phase2d-tests-6f310afbbcb1.txt`; version cuối thêm phone/email checks, không fake gửi mail |
| Offline inventory output gates | **1/6 PASS** | `phase2d-tests-4f429d95f9b8.txt`; fixture private file riêng, public/existing output bị từ chối |
| Benchmark sau trên đúng schema baseline | **1/9 PASS** | `phase2d-tests-b68b0f42832b.txt`, response digest khớp |
| B2 cancel/expiry/race liên quan cutoff | **3/18 PASS** | Output exec đã quan sát; metadata `storage/app/private/phase2d-evidence/selected-cancellation-result.json`. Wrapper đã ghi đè last-output bằng lần B2 tiếp theo; không giả định còn raw log riêng |
| B2 hai contract legacy bị thay đổi bởi gate | **2/15 PASS** | `last-backend-test-output.txt`; bảo toàn payment/inventory và default HTTP 503 |
| B5 catalog/inventory/history/payment/PDF | **4/46 PASS** | `filtered-final-d8ddfe401d57-test-output.txt` |
| C guard/rehearsal/funded-released reader | **3/21 PASS** | `filtered-acceptance-6845fdaa5e6d-test-output.txt` |
| B4 direct legacy callback sau đóng entrypoint | **1/4 PASS** | `b0959b839bf7/last-integration-test-output.txt` |
| Browser D trước các bổ sung cuối | **11/11 checks PASS**, 0 uncaught | `phase2d-ui-after.json`; 11 trang mobile; baseline 1/11 trước sửa |
| Browser focus/labels/stale-gallery/error headers | **6/6 PASS**, 0 uncaught | `phase2d-ui-delta.json`; selected follow-up, không lặp toàn 11 checks |
| Admin handler defensive renderer | **2/2 PASS**, 0 uncaught | `phase2d-event-defensive-after.json`; root cùng fix được render/lint JS |
| Legacy card/modal/print HTML trơ | **3/3 PASS**, 0 uncaught | `phase2d-legacy-renderer-after.json`; không chạy AI hoặc print thiết bị thật |
| Home labels/mobile popup | **3/3 PASS**, 0 uncaught | `phase2d-ui-home-delta.json`; không gửi chat |

D có **19 PHP case độc lập** đã PASS qua các lượt filtered (14 security,4 deployment,1 performance); regression chọn lọc **13 case** từ B2/B4/B5/C. Không cộng các lượt overlap/những assertions khác version thành một full suite tổng. Browser D tổng 25 checks được xác nhận qua các lượt khác nhau; đây là tự động, không phải nghiệm thu thủ công hoặc E2E external.

Syntax/checks cuối lưu `phase2d-final-syntax.json`: **90 checks PASS** =44 PHP/raw Blade +17 compiled Blade +1 CJS +28 rendered classic inline scripts (5 template root/prepared/Dashboard). Inline scripts được kiểm ở classic-script mode, không nhầm thành ES module do `package.json`; lỗi mode của helper đã được xử lý. Composer validate/platform requirements và `git diff --check` PASS, không install/build dependency. Artifact final source delta ghi danh sách file và preservation. Artisan `route:list` chạy trên owned test bootstrap, không qua `.env` default connection.

**Giới hạn migration inspection:** browser fixture dựng schema bằng `up()` trực tiếp nên không có bảng `migrations`; `migrate:status` trên fixture này báo “Migration table not found”, không phải kiểm primary. Không tạo ledger giả để đổi kết quả. C đã có explicit-path migration/Ran test riêng và giữ PASS; D không thay migration và không thực thi migration nào trên chính.

Các log FAIL reproduction/harness ban đầu vẫn giữ: Google/invoice/cutoff/OTP, fixture active count, fake notification column/HTTP-fake ordering, BOM đọc JSON trong helper và middleware header ordering đã được xử lý. Không hạ assertion để ép PASS. Ba kỳ vọng legacy B2/B4 được đổi vì source nay trả 503 trước writer; thêm assertions no-resurrection/no-payment/no-receipt/stock-preserved. Positive API Checkout của Phase 1 là contract lịch sử khi legacy mở, không dùng để chứng nhận default Checkout hiện bị khóa.

## 4. UI/SEO audit: kết luận thực tế

11 URL: Home, search, Hotel Detail, Payment Summary **not-found state**, Payment Result error state, History, Profile, Admin Hotels, Admin Bookings, owned RoomType, Reconciliation. Snapshot mobile **390px**, document scrollWidth = clientWidth ở cả 11; Profile mobile screenshot đã xem. Valid summary/booking flow đã PASS C, không gọi các error-state checks D là full payment E2E.

Public route allowlist có description/canonical/Open Graph; canonical dùng configured origin, không Host header hay tracking query. User/Admin/private layouts noindex/noarchive; không schema rating/price giả, không tạo sitemap không có nguồn. Real domain/sitemap/robots/HTTPS còn review; `robots.txt` hiện allow-all và không thay authentication/authorization bằng robots.

Profile không demo booking mới/claim verified, label associations và dialog keyboard/focus đã kiểm. RoomType selection có aria-pressed và late gallery không ghi đè Type hiện tại. Admin labels và Home chat input/send/mobile-width đã xác nhận sau sửa. Remaining: Profile không h1 trong snapshot, một số secondary text contrast cần remediation plan, tier/points/vouchers/DOB cần nguồn/policy/schema rõ ràng; chưa manual screen-reader/contrast tất cả state, desktop/tablet toàn bộ trang hoặc CWV. Không tuyên bố WCAG compliance.

## 5. File delta D và lý do

Toàn bộ thay đổi từ Phase D được liệt kê, bao gồm 34 file đã có trước khi tiếp tục. Không xóa file trong lượt này.

| Nhóm | Files sửa/thêm | Mục đích |
|---|---|---|
| Security/auth hiện có | `app/Http/Controllers/AuthController.php`, **new** `app/Services/GoogleIdentityVerifier.php`, `config/services.php`, `resources/views/auth/Login.blade.php`, `resources/views/auth/Register.blade.php`, `app/Exceptions/Handler.php` | Verified credential, reset-token boundary, public audience thống nhất, unavailable phone OTP, không flash secrets |
| Legacy ingress/quality | `app/Http/Controllers/BookingController.php`, `PaymentController.php`, `Webcontroller.php`, `AdminController.php`, `InvoiceController.php` | Đóng legacy financial writer, explicit unavailable email, pagination limit |
| V2 backend measured fixes | `app/Services/BookingLifecycleServiceV2.php`, `BookingReadServiceV2.php`, `HotelCatalogServiceV2.php`, `app/Support/RoomTypeV2Inventory.php` | Cutoff và hai N+1, giữ locks/snapshots |
| Guard/header/preflight | `app/Support/RoomTypeV2Integration.php`, `RoomTypeV2ProductionGuard.php`, **new** `RoomTypeV2PreflightApproval.php`, **new** `RoomTypeV2DatabasePrivileges.php`, **new** `app/Http/Middleware/RoomTypeV2ResponsePolicy.php`, `tools/roomtype-v2-preflight.php` | Before-PDO approval/grants/debug/TLS; guarded response policy bọc errors |
| Admin/legacy renderers | `resources/views/admin/bookings.blade.php`, `admin/hotels.blade.php`, `prepared-v2/admin-bookings.blade.php`, `prepared-v2/admin-hotels.blade.php`, `prepared-v2/admin-room-types.blade.php`, `prepared-v2/reconciliation.blade.php`, `Dashboard.blade.php` | Output/handler escaping, labels, named dialog, gallery stale response |
| Profile/public metadata/UI | `resources/views/Layout.blade.php`, `admin/layouts/admin.blade.php`, `user/layouts/user.blade.php`, **new** `partials/site-metadata.blade.php`, `partials/home/hero-section.blade.php`, `partials/home/chatbot-section.blade.php`, `user/profile/profile-script.blade.php`, `user/profile/profile-section.blade.php` | Honest UI, public/private SEO, safe local activity, keyboard/mobile labels; không redesign |
| Test mới | **new** `phpunit.roomtype-v2-quality.xml`, `tests/Feature/RoomTypeV2SecurityQualityTest.php`, `RoomTypeV2DeploymentPreparationTest.php`, `RoomTypeV2PerformanceTest.php`, `tests/Frontend/roomtype-v2-quality-browser.cjs`, `tests/Support/run_roomtype_v2_quality_tests.php` | Guarded synthetic negatives, benchmark, real browser delta |
| Test bị ảnh hưởng | `tests/Feature/RoomTypeV2AcceptanceTest.php`, `RoomTypeV2BackendTest.php`, `RoomTypeV2IntegrationCompletionTest.php` | Isolate debug guard and verify newly closed legacy ingress; không giảm safety assertions |
| Ops/docs | **new** `tools/roomtype-v2-asset-inventory.php`; bốn `docs/hotel-roomtype-phase2d-{readiness,security-audit,performance-audit,operations}.md` | Offline hashes, delta reports, preflight/external/backup/cutover plan |

Private inspection/syntax/benchmark/screenshot/helper artifacts không là application source/API và không đưa vào Git. Asset/route/fixture evidence giữ riêng.

## 6. GO / NO-GO và blocker còn lại

| Cổng | Quyết định |
|---|---|
| Tiếp nhận code/docs/test delta để review local | **GO trong phạm vi synthetic đã nêu** |
| Primary SELECT-only preflight | **WAITING_APPROVAL / không thực thi**; cần verified exact identity + expiring approval + dedicated read-only credential |
| Primary backup/restore/backfill/remap | **NO-GO**; chưa backup objects/assets/references/financial/source/timezone thật, chưa restore proof, freeze/ingress/execution approval |
| Register V2 provider/deploy/cutover | **NO-GO**; cần orchestration review, real writer/cron/worker/PDO drain/restart/callback acceptance và source/schema proof mới |
| Checkout/payment method production | **NO-GO riêng**; config flags không phải approval; external banking/VNPay/SMTP chưa acceptance, funds reconciliation không có tự refund/restore |

Cần cung cấp sau: cấu hình external được duyệt qua đường dẫn private; read-only primary approval/identity và least-privilege account; backup/restore proof thật; deployment topology/ingress/cron/worker controls; quyết định các schema/profile/SMS/legacy coverage gaps. Không tự xin credentials thật trong test hoặc nối primary để “hoàn tất”. Dừng sau báo cáo này, không chuyển phase tiếp theo.
