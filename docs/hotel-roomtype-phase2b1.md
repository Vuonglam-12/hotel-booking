# AUREL — Phase 2B.1: schema V2 và kiểm thử migration/backfill

Thực hiện ngày 11–12/10/2026, theo [Phase 2A](hotel-roomtype-phase2a-plan.md), [mapping](hotel-roomtype-phase2a-mapping.csv) và [Phase 1](hotel-room-management-phase1.md).

**Đã chuẩn bị 3 migration, command backfill chỉ chạy trên môi trường test cách ly và bộ kiểm thử MySQL thực tế. Chưa áp dụng bất kỳ bước nào lên database chính.** Không remap Room/BookingRoom, đổi BookingService, API/UI, Checkout, Payment Callback hoặc Authentication.

## 1. Migration mới

| File | Nội dung |
| --- | --- |
| `database/migrations/2026_10_11_000002_expand_room_type_for_hotel_inventory.php` | Thêm hotel_id INT signed nullable, total_rooms INT signed nullable, status VARCHAR(20) nullable; index idx_room_type_hotel; FK fk_room_type_hotel → hotel.id NO ACTION; CHECK tổng >= 0 và status active/inactive hoặc null. |
| `database/migrations/2026_10_11_000003_create_room_type_image_table.php` | id INT signed auto-increment, room_type_id cùng kiểu FK Type, image_url VARCHAR(500), is_primary, created_at; unique(type,url), FK ảnh CASCADE. |
| `database/migrations/2026_10_11_000004_create_room_type_legacy_map_table.php` | Hotel/Type cũ/Type mới INT signed; unique cặp nguồn, unique Type mới; FK NO ACTION; plan_checksum, source_checksum, target_checksum CHAR(64), created_at. |

Giữ nguyên migration lịch sử, PK/field/giá DECIMAL(12,2)/FK/index cũ. Không thêm adult/child policy hoặc bảng daily counter. Các Type legacy tiếp tục có ba field V2 null.

down() của cả ba migration từ chối nếu có dữ liệu/checkpoint V2 cần bảo toàn. Không xóa dữ liệu hoặc sinh phòng giả để rollback. MySQL DDL vẫn có thể commit từng statement: chưa có cơ chế tự khôi phục mọi DDL khi một file lỗi giữa chừng. Trước production phải inspect schema thực, backup/restore thử và duyệt bước xử lý riêng như Phase 2A; không tự dùng fresh/refresh/wipe.

## 2. Command và cơ chế backfill

- Command: `room-types:v2-prepare --database=mysql`; `--dry-run` chỉ kiểm tra và trả kết quả, không tạo dữ liệu V2.
- Handler: `app/Console/Commands/PrepareRoomTypeV2.php`.
- Service: `app/Services/RoomTypeV2BackfillService.php`.
- Parser/plan checksum: `app/Support/RoomTypeV2Plan.php`.
- Guard: `app/Support/RoomTypeV2TestDatabase.php`.
- Nguồn kế hoạch: CSV Phase 2A và `docs/hotel-roomtype-phase2b1-source-types.json`. Artifact Type gồm 6 bản ghi business được đối chiếu từ mã seeder hiện có; không gọi seeder hoặc truy vấn database chính để lấy dữ liệu/schema.

Command chỉ hoạt động với APP_ENV=testing, settings private của server test đã tạo, host 127.0.0.1, cổng khác 3306, database thuộc allowlist và tài khoản test đúng descriptor. Runtime kiểm config trước DB::connection; sau kết nối xác nhận database/port/server UUID và quyền tài khoản. Không thể chạy command này bằng cấu hình production hiện tại; đây chưa phải command backfill production.

Quy trình:

1. Đọc/kiểm plan: 21 Hotel, 105 nhóm, 336 Room ID và 336 RoomImage ID duy nhất; Type 6 chỉ nằm ở phần chưa gán Hotel; không chấp nhận plan giả ID mới.
2. Lấy advisory lock riêng theo database, kiểm toàn bộ nguồn và checkpoint trước khi tạo bất kỳ nhóm mới nào. Sai count/Hotel/status/ID/giá/capacity/Type business fields hoặc ảnh nguồn thì dừng.
3. Mỗi Hotel một transaction: khóa Hotel, Type nguồn, Room và ảnh; kiểm lại snapshot so với preflight. Copy 5 Type thuộc Hotel, giá dưới dạng DECIMAL string, capacity/name/description/bed_type/created_at nguyên nguồn.
4. Tạo đúng một liên kết ảnh hiện có của từng cặp; không copy file, không lấy ảnh của Hotel khác. Dữ liệu đã chuẩn bị chỉ có một URL phân biệt mỗi cặp.
5. Lấy ID thật từ insertGetId; tạo mapping với checksum kế hoạch, snapshot nguồn đầy đủ và snapshot Type/ảnh target. Mapping đã commit đủ 5 nhóm của Hotel chính là checkpoint.
6. Resume xác minh cả source và target checksum. Cùng nguồn thì bỏ qua; checksum thay đổi, mapping lạ/không đầy đủ hoặc target không có map thì dừng, không tự overwrite/sửa editor mới.
7. So sánh hash của Hotel, Room, RoomImage, Booking, BookingRoom và 6 Type legacy trước/sau. B1 không có nhánh remap hay update các bảng nguồn này.

Khi lỗi SQL ở Hotel thứ N, toàn bộ Type/ảnh/map của Hotel đó rollback; những Hotel đã commit vẫn giữ checkpoint. Lần chạy sau chỉ làm phần thiếu. Auto-increment có thể có khoảng trống sau rollback; không giả định ID bắt đầu từ 7 hoặc liền nhau.

CSV không chứa floor/room_number/tất cả timestamp nguồn. Các field đó được giữ nguyên và đưa vào checksum snapshot lần chạy; drift về sau bị chặn. Name/price/capacity/bed/description có thêm kiểm tra với artifact business fields đã chuẩn bị. Nếu kế hoạch/nguồn cần thay đổi phải refresh và duyệt lại; không nới điều kiện bằng cách âm thầm sửa artifact.

## 3. Database test cách ly

Lượt kiểm thử dùng **MySQL 8.0.30, host 127.0.0.1, port 58065**, server riêng có datadir mới tại:

`storage/app/private/roomtype-v2-tests/c2c524a01fe6/data`

- Start với `--no-defaults`, bind loopback, tắt MySQL X/binlog và không dùng my.ini/data của Laragon chính.
- Account control được tạo chỉ trên server riêng để chuẩn bị schema và shutdown. Account chạy tests/backfill là account khác, có USAGE toàn cục và quyền trên 48 namespace test mới; không có quyền ghi toàn cục hoặc hotel_booking. Không lấy username/password tài khoản ứng dụng thật.
- Runner/bootstrap không lấy connection/schema từ database chính và không dùng runner Phase 1. Laravel được ép toàn bộ DB settings/config-cache về test trước bootstrap; URL/socket/read-write override bị từ chối. Test APP_KEY/APP_URL/session/cache/mail cũng tách riêng; không gọi API live.
- Source schema fixture: core migrations hiện có được thực thi trực tiếp chỉ trên connection test đã guard, với các bảng parent tối thiểu phù hợp kiểu FK. Không chạy migration/seeder trên database chính; không chạy các seeder dự án.
- Fixture: Hotel name/status/Room/Image IDs và URL từ CSV; Type business fields từ artifact mã nguồn. Room nguồn dùng nhãn LEG-* để kiểm chứng giữ nguyên nhãn, không tạo số 101/102 trong command. Booking history/customer là dữ liệu tổng hợp @example.test, không có account/token thật.
- Mỗi integration case chọn một schema test còn rỗng. Không xóa/truncate các source fixture sau test; dữ liệu/log/checkpoint giữ private để kiểm tra. Khi hết schema rỗng, phải tạo server test mới.

Server test đã shutdown sau kiểm thử, giữ dữ liệu/log. Instance khởi tạo đầu tiên không kết nối được account bootstrap qua TCP khi skip-name-resolve bật; đã dừng và giữ data, sửa bootstrap control account riêng rồi dùng instance mới ở trên. Không ảnh hưởng server chính.

Cách chạy lại bằng server mới:

```powershell
$aurelTestServer = powershell -NoProfile -ExecutionPolicy Bypass -File tests/Support/start_roomtype_v2_test_server.ps1
$aurelTestSetup = php tests/Support/prepare_roomtype_v2_test_databases.php $aurelTestServer | ConvertFrom-Json
php tests/Support/run_roomtype_v2_tests.php $aurelTestSetup.settings_file
powershell -NoProfile -ExecutionPolicy Bypass -File tests/Support/stop_roomtype_v2_test_server.ps1 -Descriptor $aurelTestServer
```

Không đưa credentials vào command line/báo cáo. Các descriptor/credentials tự sinh nằm dưới storage private (Git ignore), không đặt trong public hoặc .env. Runner chỉ nhận filter/stop flags, không cho override PHPUnit bootstrap/config. Không dùng lệnh migrate/backfill trên cấu hình bình thường để chạy lại.

## 4. Kết quả kiểm thử thực tế

**36 tests / 1.310 assertions đạt:** suite chính 34 tests / 1.304 assertions; hai case bổ sung cuối 2 tests / 6 assertions. Hai case cuối được chạy riêng sau suite chính, không tuyên bố một lượt full suite 36 khi thực tế là hai lượt. PHP lint toàn bộ file PHP mới và git diff --check đạt.

| Kiểm tra | Kết quả |
| --- | --- |
| Expand schema | 3 migration Ran trong schema test; ID/hotel_id/total_rooms là INT signed; field V2 legacy nullable; index/FK cũ và dữ liệu legacy giữ nguyên. |
| Backfill | 105 owned Type + 6 legacy = 111 dòng; tổng V2 336; 105 mapping, 105 ảnh; Presidential id=6 vẫn legacy/null và không có map. |
| Bảo toàn nguồn | Tất cả 21 Hotel, 336 Room, 336 RoomImage và 6 Type legacy khớp snapshot; Room.room_type_id vẫn Type cũ, không remap. |
| Giá/capacity | Copy đúng field của mọi nhóm; test riêng 9.999.999.999,97 giữ chuỗi DECIMAL chính xác; không qua phép tính float. |
| ID | Đặt AUTO_INCREMENT=127 trên schema test; mapping dùng các ID thật từ 127, 105 ID mới duy nhất. |
| Ảnh/ownership | Link đúng cặp và nguồn URL; ảnh bị chuyển room_id sang Hotel khác bị từ chối trước tạo target. Target cùng tên ở hai Hotel có ID độc lập, sửa Type của A không đổi B. |
| Dry-run/rerun | Dry-run không tạo dữ liệu; rerun tạo 0, skip 105; Type/ảnh/map không đổi. |
| SQL failure/resume | Trigger test gây lỗi insert ảnh tại Hotel 3: 10 nhóm của Hotel 1–2 giữ lại, Hotel 3 rollback toàn bộ; resume tạo 95/skip 10, cuối cùng đủ 105 ảnh/map. |
| Drift | Source price/capacity/description/status/ảnh, source floor/checksum, target price/primary ảnh, plan checksum hoặc checkpoint bất thường đều chặn; không ghi đè target. |
| FK/CHECK/unique | Hotel/type FK sai, total âm, status lạ, ảnh trỏ Type thiếu, mapping trùng bị từ chối. Không xóa Type legacy có FK. |
| History fixture | Booking 901, BookingRoom 1901/1902, room_id 1/null, quantity 1/3, nights và price_at_booking/tổng tiền giữ nguyên; Eloquent đọc lại Room/Type lịch sử được. |
| Guard trước kết nối | Database hotel_booking, port 3306, app không testing, root, host remote, URL/socket/read-write override, driver/port không hợp lệ và schema không thuộc allowlist bị chặn. Runtime spy xác nhận DB::connection không được gọi với database chính hoặc endpoint bị đổi. |
| Cạnh tranh backfill | Một session giữ named lock làm lượt chạy thứ hai từ chối trước ghi. Không phải kiểm thử concurrency của Booking V2 ở giai đoạn này. |
| Guarded down | Sau backfill, cả 3 down() từ chối; không mất Type/map/ảnh. |
| Command đăng ký thật | Artisan dry-run/backfill/rerun đạt; lỗi test do đọc BufferedOutput hai lần đã sửa, không phải lỗi backfill. |

Artifact đối chiếu của một case chuẩn: `storage/app/private/roomtype-v2-tests/c2c524a01fe6/phase2b1-reconciliation.json`, schema `aurel_roomtype_v2_test_6dc647727045`. Artifact giữ count và hash bảo toàn nguồn. Output lượt test cuối lưu trong cùng runtime; credentials không nằm trong report JSON đối chiếu.

## 5. Rủi ro và phần chưa xác minh

- **Không kết nối database chính**, nên không chạy fingerprint/migration:status ở đó để xác minh trạng thái mới. Bảo đảm của bước này đến từ server/port/datadir tách biệt, quyền account, guard trước connection và toàn bộ thao tác chỉ trên fixture test.
- Command hiện cố ý chỉ dành cho test; chưa có đường production. Không bỏ guard để chạy ngay trên main. Schema/backfill thật cần preflight mới, backup restore thử, maintenance và phê duyệt riêng.
- Prepared plan cố định 21/105/336 và business fields cũ. Dữ liệu tăng/đổi sau Phase 2A phải dừng để lập kế hoạch khác; không tự lấy min/avg/first để gộp variant.
- Ảnh chỉ được xác minh file hợp lệ/provenance kỹ thuật. Các Hotel hiện dùng chung 5 file theo Type; chưa chứng minh ảnh chụp đúng từng cơ sở. Không lấy ảnh ngoài nhóm để lấp gallery.
- MySQL DDL không rollback cả chuỗi. Test expand thành công và down bảo vệ dữ liệu; chưa thực hiện restore backup production hoặc xử lý mọi loại partial DDL.
- B1 chưa remap, chưa hỗ trợ tiếp tục từ nguồn Room đã remap sang Type V2: command sẽ từ chối vì nguồn không còn khớp CSV legacy. Đường remap/resume sau cutover thuộc B2, không nới điều kiện B1.
- Chưa kiểm thử Booking V2/concurrency checkout/callback/stock giảm với service mới vì BookingService và callback được giữ nguyên. Không suy ra các kiểm thử preparation là chứng minh toàn bộ Phase 2 đã sẵn sàng.

## 6. File mới trong Phase 2B.1

Không chỉnh file có sẵn. File mới:

1. Ba migration tại mục 1.
2. `app/Console/Commands/PrepareRoomTypeV2.php`.
3. `app/Services/RoomTypeV2BackfillService.php`.
4. `app/Support/RoomTypeV2Plan.php`.
5. `app/Support/RoomTypeV2TestDatabase.php`.
6. `docs/hotel-roomtype-phase2b1-source-types.json`.
7. `phpunit.roomtype-v2.xml`.
8. `tests/Feature/RoomTypeV2PreparationTest.php`.
9. `tests/Unit/RoomTypeV2ConnectionGuardTest.php`.
10. `tests/Support/RoomTypeV2LegacyFixture.php`.
11. `tests/Support/roomtype_v2_test_bootstrap.php`.
12. `tests/Support/run_roomtype_v2_tests.php`.
13. `tests/Support/start_roomtype_v2_test_server.ps1`.
14. `tests/Support/prepare_roomtype_v2_test_databases.php`.
15. `tests/Support/shutdown_roomtype_v2_test_server.php`.
16. `tests/Support/stop_roomtype_v2_test_server.ps1`.
17. Báo cáo này: `docs/hotel-roomtype-phase2b1.md`.

Tổng 19 file mới (mục 1 gồm ba file). Runtime/log/data test chỉ nằm trong storage private đã được Git ignore. Fingerprint các file có trước task, kể cả .env/migration lịch sử/UI/auth và các thay đổi chưa commit, được đối chiếu lúc kết thúc. Không reset/stash/commit/push.

Kết quả đối chiếu cuối: **517 đường file có trước task khớp fingerprint SHA-256 ban đầu**, gồm các trạng thái xóa có sẵn. Chỉ 19 file mới trong danh sách trên. Launcher có bước đợi readiness và graceful shutdown đã được smoke test bằng một instance test trống mới; instance đó cũng đã dừng, giữ data private. Không mở kết nối tới database chính để kiểm tra lại dữ liệu.

## 7. Công việc Phase 2B.2 cần phê duyệt tiếp

1. Trên DB riêng: xây source inventory từ total_rooms + BookingRoom.quantity, ownership/status guard và tất cả writer cùng thứ tự khóa; kiểm thử đủ hold/cancel/expiry/restore và giảm tồn.
2. Thiết kế/test remap theo map, bảo toàn Booking/BookingRoom/snapshot/FK/ảnh và cơ chế resume khi FK đã chuyển; xử lý mọi trường hợp lịch sử chưa map hoặc duplicate assigned trước cutover.
3. Kiểm tra payment/callback races và semantics completed/cash/banking theo phạm vi được duyệt; B1 không sửa callback hoặc chính sách.
4. Chốt adapter/API/binding dữ liệu Admin/Hotel Detail, vẫn giữ checkout khóa cho đến phê duyệt riêng; không xóa Room/RoomImage/legacy Type trong đợt chuẩn bị.
5. Chỉ sau các cổng preflight/backup/restore/maintenance và phê duyệt ghi mới đề xuất áp schema/backfill trên main. Các migration mới trong source không phải lệnh được cho phép chạy toàn bộ Pending.

**Dừng sau Phase 2B.1. Không tự bắt đầu B2 hoặc thực thi trên database chính.**
