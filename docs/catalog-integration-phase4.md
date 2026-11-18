# AUREL — Giai đoạn 4: tích hợp Trải nghiệm và Cẩm nang trên MySQL

Ngày kiểm thử: 23/10/2026. **PASS trên MySQL synthetic cô lập. Chưa triển khai vào `hotel_booking`.**

## A. Môi trường và bằng chứng cách ly

| Thuộc tính | Giá trị của lượt PASS |
|---|---|
| Server | MySQL Community Server 8.0.30, InnoDB, strict SQL mode |
| MySQL endpoint | `127.0.0.1:51806` |
| Database duy nhất của ứng dụng test | `aurel_catalog_test_b9bfb9f66452` |
| Server UUID | `fb23d8a1-cec8-11f1-96b5-5428cd8d5b1f` |
| Tài khoản ứng dụng | `aurel_b9bfb9f66452@127.0.0.1` |
| Runtime/datadir | `C:/Users/ASUS/AppData/Local/Temp/aurel-catalog-mysql-b9bfb9f66452/data` |
| HTTP endpoint khi kiểm thử | `http://127.0.0.1:51837` |
| Public storage thật | Junction `runtime/public/storage` → `runtime/uploads` |
| Sau kiểm thử | MySQL shutdown thành công, exit 0; không còn process MySQL/PHP/Chrome của runtime này |

Runner tự khởi tạo datadir trống, chạy `mysqld --no-defaults` với cổng ngẫu nhiên và bind loopback. Không kết nối MySQL đang chạy của dự án, không dùng `.env`/config cache production. Một tài khoản bootstrap riêng với mật khẩu ngẫu nhiên chỉ dùng để chuẩn bị/dừng instance do runner sở hữu; tài khoản Laravel chỉ có quyền trên database test. Tên database trong GRANT được escape dấu `_` để không trở thành wildcard. `SHOW GRANTS` chỉ có USAGE toàn cục và quyền trên database test.

Descriptor bắt buộc phải khớp tên database/username theo nonce, thư mục dưới temp, mã sở hữu trong file và bảng `_catalog_test_owner`. Trước từng migration/rollback có kiểm tra database hiện hành, port, UUID, datadir, bind address và mã sở hữu. Ghi nhận **17 sự kiện migration có guard**. Laravel chỉ được cấu hình kết nối `catalog_test`; gọi kết nối `mysql` bị từ chối. Migrations chỉ chạy qua danh sách `--path` cụ thể, không chạy toàn bộ repository.

Cache, compiled views, upload và Chrome profile đều thuộc runtime riêng. Router test chỉ cho phép hai module, API Admin liên quan và `/api/admin/me`; các luồng Booking/Payment/AI không được đưa vào môi trường kiểm thử. Mail/Notification dùng fake và HTTP client chặn request ra ngoài. Không có bảng booking/payment/itinerary trong schema test.

Bằng chứng bền vững đã loại bỏ password, token và descriptor bí mật: [validation/catalog-mysql-b9bfb9f66452.json](validation/catalog-mysql-b9bfb9f66452.json). File này chứa identity, GRANT, schema/index/FK, kết quả import, 21 nhóm Chrome, SHA-256 nguồn đã kiểm thử và trạng thái shutdown. Không chia sẻ `stage.json`, `bootstrap-secret.json`, `root-init.sql` hoặc `browser-state.json` vì chúng chứa thông tin truy cập synthetic của runner.

## B. Migration và dữ liệu

| Hạng mục | Kết quả |
|---|---|
| Schema nền | PASS: migration gốc location/customer/hotel/hotel_image/attraction và FK liên quan, Sanctum tokens |
| Migration Trải nghiệm `000001` | PASS |
| Migration Cẩm nang `000002` | PASS |
| Bổ sung `000003` sửa ngày xuất bản | PASS: nâng TIMESTAMP → DATETIME và giữ nguyên bài có sẵn |
| Chạy lại Laravel migrate | PASS: không có migration pending, không đổi migration records |
| Rollback/reapply | PASS: rollback các batch đúng thứ tự rồi apply lại; giữ nguyên attraction cũ synthetic |
| Rollback riêng migration mở rộng ngày | PASS: giữ ngày năm 2099, không thu hẹp kiểu dữ liệu gây mất dữ liệu |
| FK | PASS: attraction→location, attraction_image→attraction, guide_post→location/category, hotel→location, hotel_image→hotel |
| Unique | PASS: location code, attraction slug, guide slug, category slug; nullable slug cho dữ liệu attraction cũ vẫn hợp lệ |
| Metadata/trạng thái | PASS: private metadata không đi ra public API; draft, future, archived, inactive, unverified và sai snapshot địa phương bị loại khỏi dữ liệu công khai |

Seeder Attraction chạy **chỉ trong database test**, kiểm tra 47 cặp tên/key từ nguồn với các ID xáo trộn: Hà Nội 72, Hồ Chí Minh 901, Đà Lạt 18, Phú Quốc 405; thêm các địa phương mồi ở ID 2/3/4. Tất cả 47 bản ghi tới đúng địa phương, inactive và chưa published. Chạy lại không nhân bản hoặc ghi đè. Bản ghi attraction legacy synthetic có trước migration vẫn được giữ nguyên.

Import Blog trên MySQL: **9 bài, 1 published và 8 draft**.

| ID gốc | Nguồn nội dung | Kết quả |
|---|---|---|
| 1 | Đầy đủ | published |
| 2 | File rỗng | draft |
| 3 | Thiếu file | draft |
| 4 | File rỗng | draft |
| 5 | File rỗng | draft |
| 6 | File rỗng | draft |
| 7 | File rỗng | draft |
| 8 | File rỗng | draft |
| 9 | File rỗng | draft |

Đối chiếu metadata, `legacy_id`, đường dẫn ảnh, byte gốc và SHA-256; chạy import lần hai không ghi đè bài đã biên tập. `/blog` và cả 9 `/blog/{id}` có chuyển hướng hợp lệ. Không sửa/xóa file hoặc ảnh Blog gốc. Các bài/ảnh minh họa bổ sung cho phân trang và upload chỉ tồn tại trong database/storage synthetic, không đưa vào dữ liệu thật.

## C. Client và Admin

**Backend tích hợp: 8 tests / 376 assertions PASS**, chỉ chạy `phpunit.catalog-mysql.xml`. Bao phủ schema/constraints, seeder/import lặp, MySQL Unicode search, bộ lọc/phân trang, trạng thái công khai, quyền token/role/ability Admin, CRUD bài/danh mục, gallery/ảnh bìa, transaction rollback/dọn upload thất bại, archive/restore, dữ liệu liên module và ngày xuất bản ngoài năm 2038.

**Chrome tích hợp: 21 nhóm PASS**, không JavaScript exception:

- Hai danh sách `/trai-nghiem`, `/cam-nang` cùng đọc instance MySQL; trang chi tiết và gallery hoạt động.
- Bộ lọc địa phương/danh mục, tìm kiếm, phân trang, empty state hoạt động trên dữ liệu MySQL.
- Gây lỗi schema thực trong database test bằng rename tạm bảng module, nhận **503 thật** từ Laravel; khôi phục bảng và retry thành công ở cả hai danh sách. Không mock response lỗi của hai module.
- `/blog`, `/blog/1`, `/blog/3` hoạt động trong Chrome; PHP kiểm tra đủ 9 ID cũ.
- Header/Footer và layout Client kiểm tra desktop 1440, tablet 768, mobile 390/320px. Không tràn ngang hoặc chồng nội dung lên Header. Hai editor Admin được kiểm tra mobile 390px và thao tác chính trên desktop.
- Tạo Trải nghiệm qua Admin, upload hai ảnh, chọn ảnh chính, verify và publish. Tạo Cẩm nang draft với ảnh bìa rồi publish; draft không xuất hiện công khai.
- Sửa tên/nội dung qua Admin phản ánh ngay trong public API/Client; slug giữ nguyên. Bật/tắt Trải nghiệm cập nhật gợi ý trong Cẩm nang. Archive/restore giữ dữ liệu/ảnh và trả về trạng thái cần xuất bản/xác minh lại.
- PHP built-in server dùng document root test thật và junction `public/storage`. Download ảnh qua public URL trả 200, MIME ảnh và SHA-256 khớp file upload; ảnh hai module không trùng đường dẫn/ghi đè nhau.

Các counter dashboard và `/api/hotels` của thanh tìm kiếm chung được stub riêng để không kiểm thử lại các luồng ngoài phạm vi. API Trải nghiệm/Cẩm nang, API quản trị của chúng, gợi ý khách sạn và public storage **không mock**. Không chạy lại các suite riêng của Giai đoạn 1–3.

HTTP boundary ghi nhận **116 application requests**, tất cả tới database test đã xác nhận; 0 vi phạm mail/notification/outbound HTTP. Có 2 response 503 do fault có chủ đích và 404 cho bài draft, không phải lỗi chưa xử lý.

Đã xem ảnh chụp desktop, mobile và editor Admin. Chưa kiểm chứng Safari/Firefox hoặc thiết bị vật lý; các viewport dùng Chrome headless.

## D. Liên kết hai module và khách sạn

PASS: Cẩm nang gán `location_id=72` chỉ gợi ý Trải nghiệm published/verified/active đúng snapshot địa phương và khách sạn active thuộc Hà Nội. Khách sạn Phú Quốc và khách sạn inactive cùng tỉnh bị loại. Trang chi tiết Trải nghiệm dùng cùng khách sạn thực từ fixture.

Một Trải nghiệm mới tạo ở Admin được xuất hiện trong bài Cẩm nang mới xuất bản cùng địa phương. Sửa rồi xác minh lại tên Trải nghiệm được phản ánh trong gợi ý; thay đổi nội dung mà không xác minh lại bị ẩn khỏi gợi ý. Khi tắt/lưu trữ Trải nghiệm, gợi ý biến mất; khôi phục trả về inactive/unverified, không tự published. Ẩn danh mục Cẩm nang không làm mất Trải nghiệm độc lập.

Không tự suy đoán location cho 9 bài Blog cũ. Không thay đổi logic gợi ý hay dữ liệu Lịch trình AI.

## E. Lỗi xác nhận và bản sửa

**Lỗi ứng dụng xác nhận:** API Admin nhận `published_at=2099-01-02T07:30:00+07:00` qua validation nhưng MySQL trả SQLSTATE 22007 / error 1292, khiến response HTTP 500. Kiểm thử SQLite trước đó không phát hiện giới hạn kiểu TIMESTAMP của MySQL. [Tài liệu kiểu ngày giờ MySQL](https://dev.mysql.com/doc/refman/8.0/en/datetime.html) xác nhận phạm vi TIMESTAMP tới năm 2038 và DATETIME có phạm vi rộng hơn.

**Sửa tối thiểu:** thêm `database/migrations/2026_10_23_000003_widen_guide_publication_datetime.php`, chỉ nâng `guide_post.published_at` từ TIMESTAMP sang DATETIME trên MySQL. Không sửa migration cũ, controller/service, Header/search hoặc giao diện đã PASS. Kiểm thử giữ nguyên mọi trường của bài trước/sau upgrade, lưu đúng ngày năm 2099 và giữ bài hẹn ngày khỏi public API. `down()` của riêng migration này chủ động giữ kiểu rộng để tránh mất dữ liệu; rollback migration tạo bảng vẫn hoạt động bình thường.

Các điều chỉnh harness khi thiết lập gồm tài khoản bootstrap loopback riêng và assertion chấp nhận cả mã FK 1216/1452 với SQLSTATE 23000. Không nới quyền ứng dụng và không thay đổi logic module để làm test PASS.

File mới của giai đoạn này:

- Migration: `database/migrations/2026_10_23_000003_widen_guide_publication_datetime.php`.
- PHPUnit: `phpunit.catalog-mysql.xml`, `tests/Feature/CatalogMysqlIntegrationTest.php`.
- Isolation/fixtures: `tests/Support/CatalogMysqlEnvironment.php`, `CatalogMysqlFixture.php`, `catalog_mysql_bootstrap.php`.
- Runner/control/router: `tests/Support/run_catalog_mysql_integration.cjs`, `catalog_mysql_control.php`, `catalog_mysql_browser_router.php`.
- Chrome: `tests/Frontend/catalog-mysql.browser.cjs`.
- Báo cáo và bằng chứng: tài liệu này và `docs/validation/catalog-mysql-b9bfb9f66452.json`.

## F. Điều kiện còn thiếu trước khi triển khai vào hotel_booking

1. Có phê duyệt triển khai riêng, backup dữ liệu/assets và kế hoạch vận hành. Giai đoạn này không kết nối hay thực hiện bất kỳ migration/seed/import nào trên database chính.
2. Đối chiếu schema/version/SQL mode/timezone của môi trường mục tiêu bằng quy trình được phép; kiểm tra schema drift, kiểu FK, dữ liệu duplicate slug/code và các trạng thái cũ. Instance synthetic chứng minh mã/migration hoạt động trên schema từ repo, chưa chứng minh dữ liệu production sạch hoặc đã được xác minh.
3. Duyệt và áp dụng migration theo thứ tự **000001 → 000002 → 000003** trên mục tiêu đã xác nhận. Xác nhận thời gian ứng dụng/session khi chuyển TIMESTAMP sang DATETIME và lưu ý rollback riêng 000003 không thu hẹp kiểu lại.
4. Kiểm tra storage URL/junction hoặc symlink, quyền ghi, dung lượng upload và cấu hình HTTP thực tế. Endpoint loopback test đã dừng, không phải staging public đang phục vụ.
5. Rà soát nội dung, tác giả và quyền dùng ảnh của bài cũ số 1; bổ sung nội dung thật cho 8 bài thiếu. Chỉ import sau khi duyệt target, giữ draft khi chưa đủ. Attraction production cần xác nhận mã địa phương, nguồn và quyền ảnh trước verify/publish; dữ liệu seeder không tự công bố.
6. Sau triển khai được duyệt, smoke test riêng trên môi trường mục tiêu. Không tự mở rộng kiểm thử sang Booking/Payment/Login/AI hoặc chạy full suite.

## Chạy lại trên instance owned mới

```powershell
node tests/Support/run_catalog_mysql_integration.cjs C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqld.exe
```

Runner luôn tạo nonce/datadir/database mới, không nhận database có sẵn làm fallback. Chỉ executable MySQL được truyền rõ ràng; không truyền DSN hay credentials của dự án. Quy trình khởi tạo datadir dựa trên [hướng dẫn MySQL](https://dev.mysql.com/doc/refman/8.0/en/data-directory-initialization.html); mật khẩu synthetic được gán khi server khởi động và không in ra report. Runner dừng đúng process mình tạo, giữ artifacts trong temp để đối chiếu; không xóa thư mục dự án hay assets.

Artifacts đầy đủ của lượt PASS: `C:/Users/ASUS/AppData/Local/Temp/aurel-catalog-mysql-b9bfb9f66452/` (`phpunit.txt`, `isolation.json`, `migration-report.json`, `migration-start-guards.jsonl`, `browser-report.json`, `http-boundary.jsonl`, ảnh chụp và `run-report.json`). Ảnh chụp/temp có thể bị hệ điều hành dọn; bằng chứng JSON đã được lưu trong repository.
