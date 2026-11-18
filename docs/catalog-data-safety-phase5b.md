# AUREL — Phase 5B: an toàn dữ liệu trước tích hợp

Ngày: **24/10/2026**. **PASS phần mã nguồn và kiểm thử synthetic; NO-GO triển khai vào `hotel_booking`.**

Đã đọc [báo cáo 5A](deployment-readiness-phase5a.md) và [47 dòng đối chiếu](validation/attraction-seed-review-phase5a.csv). Không đọc `.env`, không kết nối database chính, không sửa dữ liệu/ảnh gốc, Header, khách sạn, Booking, Payment, Login hoặc Lịch trình AI. Không chạy full test suite. Các thao tác ghi/migration/seed/import chỉ diễn ra trên instance MySQL synthetic do runner tạo và sở hữu hoặc SQLite in-memory. Không công bố bài Blog thật.

## 1. DONE / PARTIAL / BLOCKED

| Trạng thái | Kết quả |
|---|---|
| DONE | Import mới luôn `draft`, `is_featured=false`, `published_at=null`; không có tùy chọn tự publish |
| DONE | Import lại giữ nguyên toàn bộ row đã tồn tại theo `legacy_id`: trạng thái, lịch đăng, lưu trữ, nội dung, ảnh, tác giả, slug, metadata và timestamps |
| DONE | Giữ metadata ID gốc, ngày cũ trong `legacy_metadata`, raw nội dung/hash và đường dẫn ảnh của chín bài; file nguồn và ảnh không thay đổi |
| DONE | Kiểm thử nguồn thay đổi/nguy hiểm, file rỗng/thiếu, ảnh thiếu, xung đột unique và rollback transaction |
| DONE | Đối chiếu đủ 47 seed với CSV; bốn key ổn định, không hardcode ID. Thiếu/trùng địa phương dừng trước khi ghi; chạy lại không tạo trùng hoặc thêm địa phương |
| DONE | Kiểm tra điều kiện active/verified/published/archive, snapshot địa phương và category/type trên danh sách, chi tiết, bộ lọc và gợi ý từ Cẩm nang |
| DONE | MySQL 8.0.30: ba migration apply/repeat/rollback/reapply PASS, 24 tests / 1.134 assertions PASS; SQLite hồi quy tập trung 4 tests / 148 assertions PASS |
| DONE | Lập CSV chất lượng dữ liệu 47 dòng và danh sách chín ảnh cần kiểm tra quyền sử dụng; không khẳng định đã có giấy phép |
| PARTIAL | Bài 1 đủ cấu trúc nội dung theo fingerprint, vẫn chờ duyệt nội dung, giá/thông tin, tác giả và quyền ảnh; tám bài còn lại thiếu nội dung, giữ draft |
| PARTIAL | 47 dòng seed là kiểm kê nguồn và round-trip synthetic; không chứng minh dữ liệu đang dùng có quan hệ đúng hoặc đủ thông tin |
| PARTIAL | Schema `location` trong source không có `is_active`/`status`; chưa có quy tắc bật/tắt địa phương độc lập để kiểm thử. Không tự thêm cột hay suy diễn từ khách sạn |
| PARTIAL | `source_note` là ghi chú nguồn ảnh, không phải chứng cứ quyền sử dụng; ảnh không có ghi chú hoặc file không đọc được bị loại khỏi gallery công khai |
| BLOCKED | Schema/ledger, 47 bản ghi và gallery thực tế của `hotel_booking`: nhiệm vụ này cấm truy cập database chính |
| BLOCKED | Triển khai thật: chưa có đối chiếu target, duyệt dữ liệu/quyền ảnh, backup/restore rehearsal trên bản sao được duyệt và quyền ghi riêng |

## 2. Nguyên nhân và thay đổi importer

Trước 5B, `LegacyBlogImporter::inspect()` đánh dấu bài 1 là `complete` nếu fingerprint nội dung đã chuẩn hóa khớp và nội dung sau sanitize đạt ngưỡng. `apply()` dùng trực tiếp `$published = $row['state'] === 'complete'`, rồi gán `published`, nổi bật và ngày 06/04/2026. Command cũng hiển thị bài này là ứng viên `published`. Đây là kiểm tra tính đầy đủ kỹ thuật, chưa bao gồm phê duyệt biên tập, tác giả hay quyền ảnh.

Đã bỏ việc suy ra quyền xuất bản từ `complete`. `apply()` chỉ tạo bản nháp mới; `inspect()` vẫn nhận diện nguyên vẹn chất lượng và lưu trữ nguồn. Bài 1 có nội dung đã sanitize nhưng không công khai. Tám bài thiếu nội dung không được bù bằng nội dung/ảnh tự tạo. Ngày đăng cũ còn trong metadata, không dùng làm ngày xuất bản mới khi chưa duyệt.

Nhánh tìm `legacy_id` có sẵn vẫn trả `kept`, không gọi save/update. Vì vậy **bài đã published trước đây không bị tự hạ draft**, bài đã draft không bị nâng published, bài có lịch đăng hoặc đã lưu trữ không bị thay đổi. Nếu hệ thống từng import bằng bản cũ, cần người phụ trách đối chiếu và quyết định riêng; thay đổi 5B không tự sửa dữ liệu đã nhập. Unique `legacy_id` tiếp tục ngăn bản trùng; xung đột slug với row khác khiến cả transaction thất bại, không tự đổi slug hay ghi đè. Hai import đồng thời có thể khiến một lượt nhận lỗi unique; chưa bổ sung cơ chế tự retry, cần tránh chạy song song.

Giữ nguyên guard `hotel_booking` và `--allow-primary`; không sử dụng cờ đó. Command mặc định chỉ kiểm kê file, `--apply` mới ghi. Artisan vẫn bootstrap ứng dụng, vì vậy không chạy command thường trên runtime production chưa xác minh. Không thêm đường tắt phê duyệt triển khai hoặc tự xuất bản.

## 3. Chất lượng 47 dòng Trải nghiệm

Nguồn chi tiết: [attraction-data-quality-phase5b.csv](validation/attraction-data-quality-phase5b.csv). ID trong fixture cố ý khác thứ tự cũ; không dùng ID test làm ID thật.

| Key nguồn | Địa phương trong hệ thống nguồn | Số dòng |
|---|---|---:|
| `vn-ha-noi` | Hà Nội | 9 |
| `vn-ho-chi-minh` | Hồ Chí Minh | 9 |
| `vn-da-lat` | Đà Lạt | 9 |
| `vn-phu-quoc` | Phú Quốc | 20 |

Các key là định danh nội bộ đã khai báo, không phải mã hành chính được xác minh. Seeder ưu tiên code đã có; fallback phải khớp đúng một tên/country. Không tạo location mới, không sửa location_id cũ. Chạy seed lại không phải phương án sửa quan hệ sai trong dữ liệu đang dùng.

| Trường/nhóm | Kết quả từ nguồn được chạy trên synthetic | Xử lý |
|---|---|---|
| Mô tả | 47 có mô tả | Chưa đồng nghĩa đã được xác minh |
| Tọa độ `lat/lng` | 47 thiếu | Để null; không lấy tọa độ tâm tỉnh/thành thay địa điểm |
| Địa chỉ | 47 thiếu | Cần nguồn xác nhận, không tự bổ sung |
| Gallery/ảnh | 47 không có ảnh seed | Dùng trạng thái chưa có ảnh; không tạo ảnh giả |
| `price_min/price_max` | 47 có khoảng giá mẫu; 39 khác `0–0`, 8 là `0–0`; không có khoảng min > max | Toàn bộ chưa xác minh. `0–0` không chứng minh miễn phí. API Trải nghiệm không trả giá ước tính |
| Metadata công bố | Chưa có slug/category/nguồn/verified/published mới từ seed | Các sample giữ inactive, chưa xác minh, chưa xuất bản |
| Loại | 15 attraction + 15 restaurant + 8 activity + 9 hotel | 38 ứng viên theo loại; chín hotel không thuộc Trải nghiệm công khai, không chuyển/xóa dữ liệu dùng chung |

Tám khoảng giá `0–0`: Lăng Chủ tịch Hồ Chí Minh, Chợ Bến Thành, Vườn Quốc gia Phú Quốc, Bãi Sao, Dinh Cậu, Bãi Dài Phú Quốc, Nhà thùng nước mắm Khải Hoàn, Làng chài Hàm Ninh. Chỉ ghi nhận dữ liệu nguồn, không xác nhận tính đúng hoặc thời điểm áp dụng.

`ExperienceCatalogService::publicQuery()` yêu cầu attraction active, chưa lưu trữ, có slug, verified, ngày công bố đã tới, location tồn tại/có code khớp snapshot và category đúng item_type. Bộ lọc chỉ lấy location đã tồn tại có attraction đáp ứng các điều kiện này. Địa phương không có dữ liệu đủ điều kiện hoặc mã không tồn tại trả danh sách rỗng; không tạo thêm địa phương. Cẩm nang dùng lại cùng query khi gợi ý Trải nghiệm.

Thiếu ảnh/địa chỉ/tọa độ không phải tất cả đều là điều kiện chặn publish ở query hiện tại; cần checklist duyệt chất lượng. Ảnh có source_note vẫn phải được kiểm tra quyền sử dụng bên ngoài. Không thêm điều kiện ngầm hoặc thay đổi dữ liệu dùng chung với AI trong 5B.

## 4. Danh sách ảnh cần kiểm tra quyền sử dụng

[image-rights-review-phase5b.csv](validation/image-rights-review-phase5b.csv) liệt kê đủ chín ảnh bìa, kèm SHA-256: `public/image/L-1.jpg`, `L-2.jpg`, `L-3.jpg`, `L-4.jpg`, **`L5.jpg`**, `L-6.jpg`, `L-7.jpg`, `L-8.jpg`, `L-9.jpg`.

Chín ảnh khớp hash đã kiểm tra ở 5A, không sửa/xóa/thay thế. Tình trạng quyền dùng của tất cả ảnh là **UNVERIFIED**; chưa có chứng cứ giấy phép, phạm vi sử dụng hoặc người có thẩm quyền cấp quyền. Cần xác nhận nguồn, chủ sở hữu, quyền dùng trên website thương mại và cách ghi credit. Không suy ra giấy phép từ EXIF trống, ảnh đọc được, ghi chú nguồn hoặc câu mô tả trong bài.

47 sample không kèm gallery để duyệt. Ảnh attraction đã được upload ở môi trường thật chưa được kiểm kê vì không truy cập database/storage production; phải bổ sung danh sách từ bản xuất dữ liệu hoặc bản sao được phê duyệt ở 5C. Ảnh synthetic chỉ tồn tại trong thư mục test, không đưa vào dữ liệu triển khai.

## 5. Kiểm thử và bằng chứng cách ly

- MySQL **8.0.30**, `127.0.0.1:62211`, database **`aurel_catalog_test_402113e2cf40`**. Datadir mới: `C:\Users\ASUS\AppData\Local\Temp\aurel-catalog-mysql-402113e2cf40\data`.
- `--no-defaults`, bind loopback, credential ngẫu nhiên, tài khoản Laravel chỉ có quyền trên đúng database synthetic, không có connection mặc định/fallback tới production; không nạp `.env` hoặc config cache dự án.
- UUID/port/datadir/database/owner marker được xác nhận; **17 guard trước migration/rollback** đều đúng instance sở hữu. Mail/Notification fake và chặn HTTP ngoài. Dữ liệu kiểm thử nằm trong transaction rollback.
- Ba file **không thay đổi**: `2026_10_23_000001_add_experience_catalog_metadata.php` → `2026_10_23_000002_create_travel_guides.php` → `2026_10_23_000003_widen_guide_publication_datetime.php`. Apply, no-op repeat, rollback/reapply PASS; giữ row nền và dữ liệu ngày, cột cuối là DATETIME. Không chạy migration AI cùng tiền tố.
- **24 tests / 1.134 assertions PASS**: 16 ca an toàn dữ liệu mới và tám ca tích hợp tập trung đã cập nhật kỳ vọng draft. Bao gồm import lần đầu/lặp lại, draft/published/scheduled/archived đã xử lý, raw nguy hiểm không thực thi, rỗng/thiếu ảnh/file, xung đột unique rollback, guard primary, mapping 47 dòng, địa phương thiếu/trùng và public gates.
- **4 tests / 148 assertions PASS** trên SQLite in-memory: import, bảo toàn dữ liệu, command guard và route Blog tương thích.
- PHP lint/Node syntax checks PASS cho các file mã nguồn/test thay đổi. Không chạy full suite. Không chạy lại Chrome trong 5B vì không sửa giao diện; 21 nhóm Chrome là **bằng chứng lịch sử Phase 4**, không phải kết quả mới của 5B. Fixtures/browser assertions đã cập nhật để không phụ thuộc việc tự publish bài 1; lần chạy browser kế tiếp dùng bài synthetic cho phần nổi bật/chi tiết.
- Lần đầu bộ test mới FAIL do dùng `useResourcePath` không tồn tại trong Laravel 10 và so thứ tự key JSON MySQL; đã sửa fixture/phép so sánh, chạy lại PASS. Không thay đổi ứng dụng để chiều theo các lỗi test này. Instance của lần FAIL và lần PASS đều được shutdown.
- MySQL hoàn tất shutdown, exit **0**; không triển khai sau kiểm thử. Bằng chứng đã loại credentials: [catalog-safety-phase5b.json](validation/catalog-safety-phase5b.json). Không chia sẻ `stage.json`, bootstrap secrets hoặc token test.

Lệnh **đã chạy**, chỉ tạo/ghi môi trường test do runner sở hữu:

```powershell
node tests/Support/run_catalog_mysql_integration.cjs C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqld.exe --data-safety
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit -c phpunit.guides.xml --filter 'test_import_|test_command_default|test_legacy_routes'
```

## 6. File thay đổi

| Nhóm | File sửa/tạo |
|---|---|
| Ứng dụng — sửa | `app/Services/LegacyBlogImporter.php`; `app/Console/Commands/ImportLegacyGuides.php` |
| Test mới | `tests/Feature/CatalogDataSafetyMysqlTest.php`; `phpunit.catalog-safety-mysql.xml` |
| Test hồi quy — sửa kỳ vọng importer | `tests/Feature/CatalogMysqlIntegrationTest.php`; `tests/Feature/GuideModuleTest.php` |
| Runner — thêm chế độ tập trung, giữ guard | `tests/Support/run_catalog_mysql_integration.cjs` |
| Fixture/browser — dùng bài synthetic thay bài nhập tự publish | `tests/Support/CatalogMysqlFixture.php`; `tests/Frontend/catalog-mysql.browser.cjs`; `tests/Support/guide_browser_setup.php`; `tests/Frontend/guides.browser.cjs` |
| Báo cáo mới | `docs/catalog-data-safety-phase5b.md`; `docs/validation/catalog-safety-phase5b.json`; `docs/validation/attraction-data-quality-phase5b.csv`; `docs/validation/image-rights-review-phase5b.csv` |

Không sửa migration lịch sử, seeder, model, schema guard, service Trải nghiệm hoặc dữ liệu nguồn. Báo cáo 5A được giữ nguyên như mốc lịch sử; hành vi auto-publish ghi ở 5A đã được thay thế bởi chính sách draft trong 5B.

## 7. Tiến độ và Phase 5C

**Trải nghiệm: ước tính 80%.** Cơ sở quản lý: năm nhóm có trọng số bằng nhau — Client, Admin, API/schema và liên kết, kiểm thử/an toàn, dữ liệu thật/vận hành. Bốn nhóm đầu có bằng chứng hoàn thành kỹ thuật; nhóm cuối chưa đủ điều kiện. Đây không phải tỷ lệ bản ghi đã xác minh, tỷ lệ test hay cam kết sẵn sàng production. Dữ liệu production chưa được đọc và chưa có quyết định GO.

Phase 5C chia nhỏ, chỉ thực hiện sau khi được giao phạm vi/quyền tương ứng:

1. Nhận bản xuất schema/dữ liệu hoặc connection **read-only được phê duyệt riêng**. Đối chiếu identity, version, SQL mode, timezone, FK/index và ledger đúng ba migration trước mọi thao tác ghi. Không lấy credentials từ `.env` theo mặc định.
2. Lập bảng đối chiếu ID thật → location thực → key ổn định → nguồn xác minh. Rà quan hệ cũ, ảnh thật và các trường thiếu; trình từng sửa location_id nếu cần. Không dùng seed để sửa dữ liệu hiện hữu, không tự tạo địa phương.
3. Duyệt nội dung/tác giả/quyền ảnh, địa chỉ và metadata của từng mục. Bài 1 tiếp tục draft chờ duyệt cuối; tám bài thiếu nội dung giữ draft. Nếu đã có bài published do importer cũ, báo người phụ trách quyết định riêng, không tự hạ/đổi trạng thái. Chốt yêu cầu trạng thái địa phương nếu thật sự cần, đánh giá ảnh hưởng dữ liệu dùng chung trước khi thiết kế.
4. Operator tạo backup DB và storage thật, restore-test trên **bản sao đã được phê duyệt** có cách ly connection/storage/outbound. Diễn tập ba migration theo tên đầy đủ, import draft lặp lại, schema drift, thời gian DDL, khôi phục và bảo toàn dữ liệu. Không rollback xóa bảng catalog sau khi đã có dữ liệu mới làm phương án mặc định.
5. Trên staging/bản sao, kiểm tra URL ảnh qua storage thực, route mới/cũ, quyền Admin, public gates và liên kết cùng location. Chạy lại browser smoke liên quan theo fixtures mới; không dùng kết quả synthetic thay xác minh cấu hình thực.
6. Chốt release, gate riêng hai module, rollback ưu tiên tắt phục vụ/giữ schema và kế hoạch theo dõi. Xin phê duyệt **ghi schema/dữ liệu** riêng cho database chính; duyệt xuất bản nội dung là quyết định riêng. Không tự triển khai hoặc xuất bản sau 5B/5C.
