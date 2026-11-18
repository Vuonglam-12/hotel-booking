# AUREL — Giai đoạn 5A: đánh giá sẵn sàng triển khai

Ngày đánh giá: **23/10/2026**. Kết luận: **NO-GO cho triển khai/ghi dữ liệu vào `hotel_booking` ở thời điểm hiện tại**.

Chỉ đọc file liên quan, kiểm tra ảnh/hash tại workspace và bằng chứng MySQL synthetic Giai đoạn 4. Không đọc `.env`, không dùng credentials mặc định, không kết nối database chính hoặc khởi động test runner/database. Không chạy migration, seed, import, full suite; không sửa mã ứng dụng/migration hoặc dữ liệu. Các file thêm trong 5A chỉ là báo cáo và SQL chỉ đọc chưa thực thi.

## 1. DONE / PARTIAL / BLOCKED

| Trạng thái | Điều kiện | Bằng chứng/giới hạn |
|---|---|---|
| DONE | Kiểm thử tích hợp synthetic MySQL 8.0.30 | 8 tests / 376 assertions, 21 nhóm Chrome; dùng lại bằng chứng 4, không chạy lại |
| DONE | Đối chiếu nguồn đã kiểm thử | 6/6 SHA-256 trong bằng chứng 4 khớp hiện tại, gồm đủ ba migration; không suy rộng thành toàn bộ release đã được đóng gói |
| DONE | Đánh giá phụ thuộc, FK và rollback | Đã đọc migration/model/service và triển khai Migrator của Laravel đang cài |
| DONE | Kiểm kê nguồn Trải nghiệm | 47 bản ghi seeder, bốn key địa phương; CSV đối chiếu đủ 47 dòng |
| DONE | Kiểm kê 9 bài và ảnh Blog | Bài 1 có nội dung, 7 file rỗng, 1 file thiếu; 9/9 ảnh là JPEG hợp lệ 1024×572; lưu hash |
| DONE | Kế hoạch backup, rehearsal, rollback, smoke test và phân loại lệnh | Tài liệu này và ba SQL chỉ đọc mẫu; chưa thực thi SQL |
| PARTIAL | Tương thích database/storage mục tiêu | Chưa biết version, SQL mode, timezone, engine, schema drift, dung lượng/DDL lock hoặc storage runtime của hệ thống chính |
| PARTIAL | Độ đúng của dữ liệu địa phương | Đã biết ánh xạ mong đợi trong source; chưa biết location/attraction thực tế, orphan/duplicate hay bản ghi từng dùng ID hardcode |
| PARTIAL | Release và phương án vận hành | Chưa có release bất biến, backup được phục hồi thử, thời gian phục hồi mục tiêu và cơ chế tạm chặn riêng hai module được xác nhận |
| BLOCKED | Đối chiếu dữ liệu thực | Chưa được cung cấp kết nối read-only được phê duyệt; không có quyền tự lấy credentials từ `.env` |
| BLOCKED | Rehearsal trên bản sao thực | Chưa có bản sao/backup được phê duyệt và quyền ghi dành riêng cho bản sao đó |
| BLOCKED | Công bố bài 1 | Chờ duyệt cuối nội dung/tác giả/quyền ảnh; importer hiện tự publish bài đầy đủ nếu chạy `--apply` |
| BLOCKED | Công bố bài 2–9 | Thiếu nội dung thật; giữ draft. Điều này không tự chặn triển khai schema khi các điều kiện vận hành khác đã đạt |
| BLOCKED | Migration/import trên hotel_booking | Chưa được phê duyệt ghi dữ liệu; kết quả synthetic PASS không cấp quyền ghi production |

Bằng chứng nguồn và ảnh: [catalog-readiness-phase5a.json](validation/catalog-readiness-phase5a.json). Bằng chứng kiểm thử trước: [catalog-mysql-b9bfb9f66452.json](validation/catalog-mysql-b9bfb9f66452.json).

## 2. Tương thích và thứ tự migration

| File đầy đủ | Phụ thuộc | Thay đổi / xử lý dữ liệu cũ | Rollback |
|---|---|---|---|
| `2026_10_23_000001_add_experience_catalog_metadata.php` | `location.id` INT signed; `attraction.id` BIGINT unsigned và các trường gốc đã tồn tại | Thêm code/slug nullable, metadata công bố và bảng ảnh. Không sửa location_id, không gán code, không tự xác minh/xuất bản attraction cũ | **Mất dữ liệu catalog mới:** drop bảng attraction_image, metadata/slug và location.code; ảnh vật lý không được xóa nhưng mất liên kết |
| `2026_10_23_000002_create_travel_guides.php` | Bảng location gốc; tự tạo guide_category trước guide_post | Tạo hai bảng, giữ ID cũ bằng legacy_id unique. Không tự import 9 bài | **Mất toàn bộ bài/danh mục trong bảng mới** nếu dùng down sau khi có dữ liệu |
| `2026_10_23_000003_widen_guide_publication_datetime.php` | `guide_post.published_at` từ 000002, driver mysql | Chỉ nâng TIMESTAMP → DATETIME. Skip nếu cột không có hoặc đã không còn TIMESTAMP; phải kiểm tra kiểu thật, không chỉ tin migration ledger | down là **no-op**, giữ DATETIME để tránh mất ngày sau năm 2038 |

Thứ tự triển khai đã kiểm thử: **000001 → 000002 → 000003**. Về DDL, 000002 cần location gốc, không cần các cột mới của 000001; về tích hợp, gợi ý Trải nghiệm cần schema 000001. 000003 phụ thuộc trực tiếp 000002.

Nguồn: [migration 1](../database/migrations/2026_10_23_000001_add_experience_catalog_metadata.php), [migration 2](../database/migrations/2026_10_23_000002_create_travel_guides.php), [migration 3](../database/migrations/2026_10_23_000003_widen_guide_publication_datetime.php), [Attraction](../app/Models/Attraction.php), [Location](../app/Models/Location.php), [GuidePost](../app/Models/GuidePost.php), [GuideCategory](../app/Models/GuideCategory.php).

Các điểm phải đối chiếu trên target được phép:

- MySQL 8.0.30/InnoDB/strict đã PASS; chưa xác nhận server chính giống môi trường này. FK cần đúng engine, kiểu INT/BIGINT và signedness. Rà cả FK đi vào location/attraction để giữ nguyên ID dùng chung với khách sạn và chức năng khác.
- Unique code/slug mới nullable nên không buộc backfill dữ liệu cũ trên schema sạch. Nếu cột/bảng/index đã có do drift hoặc migration dở dang, up() của 000001/000002 không phải thao tác idempotent độc lập; Laravel ledger chỉ bảo vệ lượt migrate đã hoàn tất.
- MySQL atomic DDL không biến nhiều ALTER/CREATE trong một migration thành transaction chung. Khi fail giữa chừng, có thể còn schema đã thay đổi dù ledger chưa ghi; dừng và đối chiếu schema, không chạy lặp mù hoặc drop để làm sạch. [MySQL atomic DDL](https://dev.mysql.com/doc/refman/8.0/en/atomic-ddl.html).
- `config/app.php` đặt timezone UTC; GuideService chuẩn hóa input về timezone ứng dụng. TIMESTAMP có chuyển đổi theo timezone session, DATETIME không có cùng cơ chế đó. Phải chốt timezone của connection và so sánh các ngày cũ trước/sau 000003 trên bản sao. Các TIMESTAMP khác của Trải nghiệm không được mở rộng bởi 000003. [MySQL date/time](https://dev.mysql.com/doc/refman/8.0/en/datetime.html).
- ALTER có thể cần metadata lock/rebuild tùy bảng thực tế. Chưa đo được thời gian, dung lượng hay ảnh hưởng trên dữ liệu thật; phải đo trên bản sao cùng phiên bản/schema.
- Có migration khác cùng tiền tố `2026_10_23_000002_add_itinerary_trip_management.php`. Chỉ dùng **ba tên file đầy đủ**; không chạy migrate toàn bộ hoặc lọc bằng tiền tố timestamp.

## 3. Đối chiếu location và attraction

**Đây là báo cáo nguồn seeder, không phải kết quả SELECT từ hotel_booking.** Tất cả production_location_id/production_match trong CSV được ghi UNKNOWN/UNVERIFIED, không dùng ID của fixture MySQL làm ID thật.

| Key nội bộ | Địa phương chuẩn trong source | Tham quan | Ăn uống | Hoạt động | Loại hotel cũ | Tổng |
|---|---|---:|---:|---:|---:|---:|
| vn-ha-noi | Hà Nội | 3 | 3 | 1 | 2 | 9 |
| vn-ho-chi-minh | Hồ Chí Minh | 3 | 3 | 1 | 2 | 9 |
| vn-da-lat | Đà Lạt | 3 | 3 | 1 | 2 | 9 |
| vn-phu-quoc | Phú Quốc | 6 | 6 | 5 | 3 | 20 |
| Tổng | | 15 | 15 | 8 | 9 | 47 |

Có **38 ứng viên theo item_type** cho Trải nghiệm; 9 dòng hotel không thuộc danh mục Trải nghiệm công khai. Không tự chuyển chúng sang bảng khách sạn hoặc xóa dữ liệu dùng chung. Các key là định danh nội bộ, không phải mã hành chính được cơ quan nhà nước xác nhận.

[LocationIdentity](../app/Support/LocationIdentity.php) ưu tiên code, fallback phải có đúng một tên/country phù hợp. [AttractionSeeder](../database/seeders/AttractionSeeder.php) resolve đủ key trước khi ghi, firstOrCreate và để inactive; chặn hotel_booking. Cơ chế này không sửa quan hệ sai đã tồn tại. Chạy lại seed không phải phương án sửa location_id cũ và có thể tạo thêm một bản ghi ở địa phương khác thay vì sửa bản sai.

Kiểm kê source: **47/47 có description**, nhưng **47/47 không khai báo address, gallery, slug hoặc metadata experience mới**. Có mô tả không đồng nghĩa nội dung đã được xác minh. Đây không phải kết luận rằng 47 bản ghi production đều thiếu các trường đó.

Trước khi chọn một attraction để xuất bản cần lập dòng đối chiếu: ID thật → location_id/name/country thật → key được duyệt → nguồn chứng minh đúng địa phương. Rà ảnh thật/caption/nguồn/quyền ảnh, mô tả, địa chỉ, summary, category khớp item_type, URL nguồn, slug, verified_by/at và snapshot location code. Thời lượng/giờ hoạt động chỉ bổ sung khi có nguồn; không chuyển duration_hours ước tính cũ sang thời lượng đã xác minh.

Code công khai thực tế yêu cầu active, slug, đúng category/type, verified, snapshot địa phương khớp, ngày published đã tới và chưa archived. Ảnh/địa chỉ/mô tả không phải tất cả đều là hard gate ở query: có thể hiển thị fallback khi thiếu. Vì vậy cần quyết định chất lượng nội dung của người duyệt, không dựa riêng vào việc API cho publish.

CSV đủ 47 dòng: [attraction-seed-review-phase5a.csv](validation/attraction-seed-review-phase5a.csv). Đối chiếu dữ liệu thật đang **BLOCKED** vì chưa có quyền read-only.

## 4. Chín bài Cẩm nang và quyết định giữ chờ duyệt

| ID gốc | Nội dung nguồn | Ảnh | Quyết định 5A, chưa ghi database |
|---|---|---|---|
| 1 | Đầy đủ, fingerprint trùng bản đã xét duyệt kỹ thuật | L-1.jpg, JPEG hợp lệ | **Chờ duyệt cuối; giữ draft nếu nhập trước khi duyệt, chưa cho hiển thị chính** |
| 2 | File rỗng | L-2.jpg, JPEG hợp lệ | draft |
| 3 | Thiếu file | L-3.jpg, JPEG hợp lệ | draft |
| 4 | File rỗng | L-4.jpg, JPEG hợp lệ | draft |
| 5 | File rỗng | L5.jpg, JPEG hợp lệ | draft |
| 6 | File rỗng | L-6.jpg, JPEG hợp lệ | draft |
| 7 | File rỗng | L-7.jpg, JPEG hợp lệ | draft |
| 8 | File rỗng | L-8.jpg, JPEG hợp lệ | draft |
| 9 | File rỗng | L-9.jpg, JPEG hợp lệ | draft |

Đây là trạng thái phê duyệt trong báo cáo, **không phải khẳng định các row đó đã tồn tại hoặc đã đổi trạng thái trên database chính**. Không thêm một giá trị status mới vào schema; trạng thái chờ duyệt được lưu bằng draft nếu sau này có thao tác nhập được phê duyệt.

9 ảnh tồn tại tại `public/image/`, đúng MIME JPEG 1024×572. Không có trường tác giả trong LegacyBlogCatalog; các tag EXIF attribution đã kiểm tra và các sidecar `.license`, `.license.txt`, `.source.json` cạnh từng ảnh không cung cấp chứng cứ quyền sử dụng. Không suy đoán tác giả từ bình luận mẫu hoặc câu “mình chụp” trong bài. Cần đơn vị/người có quyền xác nhận nội dung, ảnh và cách ghi tác giả. Việc ảnh tồn tại/đọc được không chứng minh bản quyền.

Bài 1 còn thông tin giá/thời gian di chuyển cần duyệt biên tập. Bản gốc có mức giá Vĩnh Hy khác nhau giữa phần thân bài và script đọc bài; importer bỏ player/script khỏi phần công khai, nhưng việc đó không xác minh giá trong thân bài. Giữ nguyên file gốc/hash, không tự sửa hoặc tạo nội dung bù.

**Điểm chặn cần biết:** [LegacyBlogImporter::apply](../app/Services/LegacyBlogImporter.php) hiện tạo bài đầy đủ thành published/featured với ngày 06/04/2026 và không cần tác giả; [command](../app/Console/Commands/ImportLegacyGuides.php) chỉ có `--apply`, `--allow-primary`, không có draft-only hoặc `--database`. Đây là hành vi đã PASS theo yêu cầu các giai đoạn trước; nó không đáp ứng việc nhập bài trước khi duyệt cuối mà vẫn giữ draft. Không chạy `--apply` vào hệ thống chính lúc này.

Hai phương án cần duyệt: **(a)** hoãn import đến khi bài 1 đã được duyệt cuối rồi mới dùng hành vi hiện có; hoặc **(b)** nếu cần nhập 9 bài sớm, chuẩn bị và kiểm thử chế độ draft-only/import có kiểm soát trong giai đoạn được phép thay đổi. Chưa triển khai phương án b ở 5A. Không dùng “import published rồi UPDATE draft sau” vì có khoảng thời gian bài chưa duyệt có thể lộ ra.

Đường dẫn mới giữ slug cố định; legacy_id là khóa nối với `/blog/{id}`. Khi bài còn draft, URL cũ của 9 ID quay về Cẩm nang có thông báo, không lộ nội dung. Không gán một location cho bài 1 vì nội dung nói tới nhiều địa phương. Hash nội dung gốc bài 1: `5160478b3133806a2cacb7aa3091cf5608b2ee4668ae86f885734ac0aaaa4254`.

## 5. SQL chỉ đọc đã chuẩn bị, chưa thực thi

- [catalog-readonly-schema.sql](deployment/catalog-readonly-schema.sql): identity, quyền hiện tại, version/mode/timezone, bảng/cột/index/FK và ledger ba migration.
- [catalog-readonly-location-review.sql](deployment/catalog-readonly-location-review.sql): counts theo địa phương/loại, orphan/duplicate, ứng viên tên địa phương và trang chi tiết tối đa 500 attraction; chỉ dùng cột nền đã xác nhận.
- [catalog-readonly-publication-review.sql](deployment/catalog-readonly-publication-review.sql): thiếu ảnh/metadata, snapshot địa phương, chín legacy row và duplicate; **chỉ dùng khi schema metadata mới đã tồn tại** trên bản sao hoặc target được phép đọc.

SQL chỉ gồm SELECT/SHOW, không có USE, DML, DDL hay gán session variable. Không chạy cả file mù: xác nhận identity/grants ở block đầu trước, rồi mới dùng các block phụ thuộc bảng/cột. Match theo tên chỉ là ứng viên, collation có thể bỏ phân biệt hoa/thường/dấu. `TABLE_ROWS` trong information_schema chỉ là ước tính; rowcount thật cần SELECT COUNT được cho phép. Không dùng server `read_only` làm bằng chứng duy nhất rằng tài khoản là read-only.

## 6. Backup và diễn tập trên bản sao được phê duyệt

Trước lần ghi thật cần có backup database nhất quán do người vận hành được phép tạo, gồm schema/data, migration ledger, FK/index, charset/collation và các object phụ thuộc cần phục hồi. Ưu tiên quy trình backup hiện có đã được restore-test. Nếu cần dump, xác nhận engine trước: `--single-transaction` phù hợp InnoDB, không đảm bảo snapshot nhất quán cho mọi engine; không có DDL đồng thời trong thời gian dump. Tài khoản backup cần các quyền tương ứng object/options, không mặc định đồng nhất với quyền SELECT được cấp để audit. [mysqldump](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html).

Backup storage phải chứa **file thật**, không chỉ symlink `public/storage`: source mặc định trỏ `storage/app/public`; cần xác nhận root/runtime thực. Giữ thư mục ảnh attraction, guides, ảnh khách sạn liên quan, chín `public/image/L*.jpg` đúng tên (đặc biệt L5.jpg), các Blade nội dung Blog, LegacyBlogCatalog và manifest SHA-256. Ghi nhận thời điểm/ranh giới backup đồng bộ DB–file. Không xóa ảnh cũ.

Diễn tập: restore backup được phép lên instance tách biệt không có credentials/quyền tới production; kiểm tra dump không tham chiếu ngược schema gốc qua USE, qualified object name hay DEFINER. Chặn email/webhook/giao dịch và tách storage/cache. Chạy đúng ba migration trên bản sao, đo DDL/lock, đối chiếu trước/sau, thử lỗi giữa chừng và recovery. Test mới chỉ tập trung schema/data thật và việc giữ bài 1 chưa duyệt ở draft; không lặp full suite đã PASS. Chốt người thực hiện, thời gian phục hồi mục tiêu và bằng chứng restore trước GO.

## 7. Kế hoạch rollback và kiểm tra sau triển khai

Ưu tiên rollback việc phục vụ **riêng hai module** bằng gate vận hành được diễn tập, giữ schema additive và dữ liệu. Không hạ toàn bộ ứng dụng hoặc khôi phục toàn database để xử lý một lỗi catalog khi Booking/Payment/Login/AI vẫn hoạt động.

- Nếu chưa có dữ liệu mới, có thể xem xét down theo thứ tự phụ thuộc sau khi đọc ledger/batch thực và có phê duyệt riêng. `--path` giới hạn file nhưng Laravel vẫn chọn các migration rollback theo batch/step; không giả định `--step=1` luôn là migration 000003.
- Nếu đã có bài/ảnh/metadata mới, không dùng down của 000001/000002 làm rollback mặc định. Chặn công bố, giữ/export dữ liệu mới, ưu tiên sửa tiến. Chỉ hoàn tác cấu trúc khi có kế hoạch khôi phục dữ liệu và quan hệ đã diễn tập.
- 000003.down không đưa cột về TIMESTAMP; giữ DATETIME là hành vi có chủ đích, không phải rollback schema hoàn toàn.
- Nếu cần lấy dữ liệu từ backup, restore vào instance phục hồi riêng rồi đối chiếu phần catalog; không ghi đè toàn bộ location/attraction hoặc giao dịch sau backup. Không tự đổi location_id, xóa location/attraction hay làm mất ID đang được chức năng khác tham chiếu.

Sau triển khai được phép: kiểm tra kiểu DATETIME/FK/index/ledger; số row/base field trước/sau; public query chưa lộ bài/attraction chờ duyệt; 9 legacy URL; hình ảnh qua APP_URL/public storage thực; quyền Admin, một luồng chỉnh sửa có kiểm soát và gợi ý cùng location_id; 503/error logs, Header/Footer và viewport trọng tâm. Dùng release/cache riêng, không flush cache chung một cách mặc định. Chỉ thử ghi bằng dữ liệu được phê duyệt; không tự tạo test booking, payment, tài khoản hoặc lịch trình trên production.

## 8. Phân loại lệnh — tất cả lệnh vận hành dưới đây CHƯA chạy

| Lệnh/thao tác | Đọc/ghi | Điều kiện |
|---|---|---|
| `rg`, `Get-Content`, `Get-FileHash`; PHP độc lập đọc hằng metadata/ảnh, không bootstrap Laravel | Chỉ đọc file | Đã dùng trong 5A; không đọc .env hoặc DB |
| `mysql` với SELECT/SHOW trong ba file trên | Đọc DB, có thể ghi file output nếu redirect | Chỉ khi có kết nối RO đã phê duyệt và identity khớp; chưa chạy |
| `php artisan guides:import-legacy` không `--apply` | Thân command chỉ đọc file | Artisan vẫn bootstrap ứng dụng; chỉ dùng sau khi runtime/connection an toàn đã xác nhận; chưa chạy |
| `php artisan migrate --pretend` | **Không coi là read-only tuyệt đối** | Laravel chuẩn bị migration repository trước pretend, có thể tạo bảng migrations nếu thiếu; không dùng để né điều kiện cấp quyền |
| `mysqldump ... --result-file=...` | Đọc DB + ghi backup local | Cần phê duyệt backup/quyền riêng; chưa chạy |
| Restore SQL vào bản sao | Ghi database bản sao | Phải có quyền ghi và identity/cách ly của bản sao được duyệt; chưa chạy |
| `php artisan migrate --database=APPROVED_CLONE ...` | Ghi schema + ledger | Connection alias phải được bootstrap/đối chiếu tới clone, không dựa cấu hình mặc định; chưa chạy |
| `guides:import-legacy --apply` / `--allow-primary` | Ghi data, hiện tự publish bài 1 | Chặn trước duyệt cuối/target approval; command không hỗ trợ `--database` |
| Verify/publish, gán location.code, UPDATE/seed | Ghi dữ liệu | Không được thực hiện trong 5A; không xuất bản/sửa location hàng loạt |
| `migrate:rollback`, restore, DDL | Ghi và có thể mất dữ liệu | Kế hoạch riêng, target/batch rõ ràng; chưa chạy |
| `storage:link`, ghi/sao chép backup, cache release | Ghi filesystem/runtime | Không phải lệnh chỉ đọc dù không nhất thiết sửa DB; chưa chạy |

Ví dụ mẫu **chưa thực thi**, chỉ sau khi được cấp đúng tài khoản RO (password nhập ở prompt, không lấy từ .env):

```powershell
mysql --no-defaults --protocol=TCP --host=APPROVED_HOST --port=APPROVED_PORT --user=APPROVED_RO_USER --password --database=hotel_booking
```

Khi đã vào session được phép, chạy block identity/grants của SQL schema rồi dừng đối chiếu trước các SELECT tiếp theo. Không tự tạo tài khoản/grant hoặc đổi quyền một connection đang có.

Mẫu backup cho operator được phép, **không phải lệnh được phê duyệt chạy ở 5A**:

```powershell
mysqldump --no-defaults --protocol=TCP --host=APPROVED_HOST --port=APPROVED_PORT --user=APPROVED_BACKUP_USER --password --single-transaction --quick --hex-blob --no-tablespaces --set-gtid-purged=OFF --routines --events --triggers --result-file=APPROVED_BACKUP_PATH hotel_booking
```

Các option/GTID/quyền cần operator xác nhận với server thật. Dùng `--result-file` để tránh đổi encoding qua redirect PowerShell. Không thêm khóa toàn server/flush logs một cách mặc định.

Mẫu rehearsal **ghi clone**, chỉ trong runtime được duyệt và đã chặn mọi connection khác:

```powershell
php artisan migrate --database=APPROVED_CLONE --path=database/migrations/2026_10_23_000001_add_experience_catalog_metadata.php --path=database/migrations/2026_10_23_000002_create_travel_guides.php --path=database/migrations/2026_10_23_000003_widen_guide_publication_datetime.php
```

Không cung cấp lệnh import production để chạy ngay: phương án chờ duyệt bài 1 và target bootstrap chưa được chốt. Không chạy migrate không --path, migrate:fresh/reset, seed toàn bộ hoặc rollback theo tiền tố timestamp.

## 9. Các bước bắt buộc trước GO

1. Cấp thông tin target/kết nối RO được phê duyệt hoặc bản xuất dữ liệu được phép; không chia sẻ password trong báo cáo/chat. Chưa có thì giữ bước này BLOCKED.
2. Đọc schema/ledger/runtime và lập đối chiếu location↔attraction thật bằng SQL đã duyệt; đưa từng sai lệch vào danh sách quyết định, không tự sửa.
3. Người phụ trách nội dung xác nhận tác giả/quyền ảnh/nội dung bài 1. Giữ bài 1 chờ duyệt; 8 bài còn lại draft. Chốt hoãn import hay chuẩn bị draft-only được kiểm thử nếu cần nhập sớm.
4. Tạo backup DB/storage qua operator được phép; restore-test lên bản sao riêng đã được phê duyệt và đối chiếu checksum/quan hệ.
5. Diễn tập ba migration và recovery trên bản sao; xác nhận timezone, kiểu DATETIME, locks, dữ liệu cũ, draft/public gates và legacy URL. Không kéo migration AI vào phạm vi.
6. Chốt release bất biến, cửa sổ vận hành, gate riêng hai module, người theo dõi và quy trình rollback không ảnh hưởng dữ liệu giao dịch đang phát sinh.
7. Có phê duyệt ghi schema/data riêng cho hotel_booking sau khi đạt các điều kiện trên. Quyền RO không thay thế quyền ghi, cũng không thay thế duyệt xuất bản nội dung.
8. Chỉ sau đó mới triển khai có kiểm soát, thực hiện smoke test đã duyệt và mở công bố từng nội dung đã xác minh.

**Kết luận 5A: NO-GO cho production hiện tại.** Mã đã có bằng chứng PASS trên synthetic; điều kiện chưa đạt nằm ở quyền truy cập, dữ liệu thực, duyệt nội dung và vận hành/khôi phục. Không có thao tác database hoặc thay đổi module nào được thực hiện để bỏ qua các điều kiện này.
