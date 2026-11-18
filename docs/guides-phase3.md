# AUREL — Giai đoạn 3: Cẩm nang du lịch

Ngày báo cáo: 23/10/2026. Hoàn thành mã nguồn và kiểm thử cô lập; chưa triển khai/chuyển đổi database chính.

## Trạng thái

| Phạm vi | Trạng thái | Kết quả |
|---|---|---|
| Kiến trúc, migration, API, Client, Admin | DONE | Một nguồn bài viết trong `guide_post`; danh mục trong `guide_category` |
| Chuyển đổi 9 bài trong SQLite cô lập | DONE | 1 published, 8 draft; giữ metadata danh sách/chi tiết, ID gốc, đường dẫn ảnh, byte nội dung gốc và SHA-256 |
| Tương thích URL cũ | DONE | `/blog` → `/cam-nang`; `/blog/{id}` ánh xạ bằng `legacy_id`, không dựa vào ID mới |
| Kiểm thử tập trung | DONE | PHP: 14 tests / 318 assertions; Chrome: 26 nhóm chức năng + 3 nhóm bố cục chi tiết |
| Kiểm chứng trên MySQL và database chính | PARTIAL | Chưa thực hiện; chỉ SQLite cô lập được cấu hình trong các bài kiểm thử |
| Xuất bản 8 bài thiếu nội dung | BLOCKED bởi dữ liệu nguồn | 7 file rỗng, 1 file thiếu; cần nội dung thật để biên tập/xuất bản |

Không truy cập hoặc sửa `hotel_booking`; không chạy migration/seed trên database chính. Không chạy full test suite. Không sửa Booking, Payment, Login, Lịch trình AI hay mã module Trải nghiệm/Header/tìm kiếm đã PASS.

## Kiến trúc dữ liệu và quy tắc

Audit mã nguồn không tìm thấy bảng/model/service bài viết có thể tái sử dụng: Blog cũ là hai mảng metadata trong `BlogController` và các Blade nội dung. Vì vậy chỉ bổ sung hai bảng:

- `guide_category`: ID, tên duy nhất, slug duy nhất cố định, trạng thái hiển thị, timestamps.
- `guide_post`: ID mới; `legacy_id` duy nhất để giữ ID Blog gốc; slug duy nhất cố định; tiêu đề đầy đủ, tóm tắt, `body_html`, ảnh bìa, danh mục, `location_id` nullable, tên tác giả nullable, `draft/published`, cờ nổi bật, ngày xuất bản, thời điểm lưu trữ, timestamps. Bản chứng cứ riêng gồm `legacy_metadata`, `legacy_body`, `legacy_content_sha256`, `legacy_import_state`.

Khóa ngoại địa phương dùng signed integer tương ứng `location.id` hiện có. Không tạo bảng địa điểm/hệ thống ảnh song song. Tác giả là tên/bút danh biên tập viên xác nhận; không can thiệp tài khoản/đăng nhập.

Danh sách, bài nổi bật, chi tiết và bài liên quan cùng dùng `GuideCatalogService::publicQuery()`: published, chưa lưu trữ, ngày xuất bản đã tới, có nội dung và danh mục đang hiển thị. API công khai chọn rõ các trường được trả về; không trả bản gốc lưu trữ hay metadata quản trị. Không có rating, bình luận, lượt xem hoặc thời gian đọc giả. Không gửi email/thông báo. Ngày tương lai được hiển thị theo điều kiện truy vấn, không cần tác vụ gửi thông báo.

Slug không thay khi sửa tiêu đề/tên danh mục. Ẩn bài bằng draft; xóa bài trong Admin là lưu trữ và có thể khôi phục về draft. Ẩn danh mục tạm ẩn bài thuộc danh mục; chỉ xóa được danh mục không có bài, kể cả bài lưu trữ.

`GuideHtml` dựng lại HTML bằng DOM và danh sách thẻ/thuộc tính cho phép. Bỏ script, sự kiện, style, iframe, SVG, ảnh chèn tùy ý, biểu mẫu và URL thực thi; giữ các định dạng biên tập cơ bản. Lọc khi lưu, xem trước và đọc công khai. Không render/evaluate Blade/PHP lấy từ nội dung. Bản gốc chứa script/bình luận mẫu chỉ được lưu riêng làm chứng cứ và không được serialize qua API.

Ảnh bìa dùng cột đường dẫn, kiểm tra ảnh JPEG/PNG/WebP thật, tối đa 2 MB. Tái sử dụng giới hạn ảnh và cơ chế transaction/dọn upload thất bại của `AdminAccommodationImageService`. Thay/bỏ ảnh gỡ liên kết khỏi bài; giữ các file ảnh cũ để bảo toàn dữ liệu, bao gồm ảnh Blog gốc.

## Client và Admin

Client `/cam-nang`: giới thiệu gọn, bài nổi bật, danh mục thật, tìm tiêu đề/tóm tắt, phân trang, lưới 3/2/1 cột, skeleton loading, empty/error/retry, giữ bộ lọc trong URL và hỗ trợ Back/Forward. Không dùng banner Blog cũ. Màu #F8F8F8, #142B49, #2563EB; animation nhẹ và hỗ trợ reduced motion.

Chi tiết `/cam-nang/{slug}`: tiêu đề đầy đủ, ảnh cũ/thật nếu có, ngày đăng, tác giả nếu biết, nội dung có định dạng và bài liên quan. Không suy đoán tác giả cũ: hiển thị “Chưa có thông tin tác giả”. Khi Admin xuất bản/biên tập lại bài, yêu cầu tác giả, tóm tắt, danh mục đang hiển thị và ít nhất 100 ký tự nội dung thực sau lọc HTML.

Liên kết địa phương chỉ được gán rõ ràng trong Admin. Khi có quan hệ thật, gợi ý Trải nghiệm đã qua điều kiện công bố của module hiện có và khách sạn active cùng `location_id`. Tái sử dụng `ExperienceCatalogService`, không thay đổi service này. Không tự gán địa phương cho 9 bài cũ; bài đầy đủ số 1 nói tới nhiều địa phương.

Admin `/admin/guides`: thay mục Blog “Sắp có” bằng Cẩm nang; CRUD bài/danh mục, bộ lọc/tìm kiếm/phân trang, trình nhập HTML cơ bản có thanh định dạng và xem trước an toàn, upload/thay/bỏ ảnh, draft/publish/hẹn ngày, bài nổi bật, lưu trữ/khôi phục. Dùng shell và `adminApi` hiện có. Mọi API quản trị nằm trong middleware `auth:admin` + `AdminMiddleware`, kiểm tra role, tên token và ability Admin; validation backend bổ sung kiểm tra quyền trong request ghi bài.

Header tự nhận `guides.index` để dùng `/cam-nang`, không chỉnh component/script/CSS Header. Footer đổi tên hiển thị sang AUREL và link Cẩm nang; metadata có mô tả, canonical và OG article ở chi tiết.

## API và URL

| Phương thức | Đường dẫn | Hành vi |
|---|---|---|
| GET | `/cam-nang`, `/cam-nang/{slug}` | `guides.index`, `guides.show` |
| GET | `/blog` | 301 sang Cẩm nang, giữ q/category/page |
| GET | `/blog/{id}` | 302 tới slug published, hoặc `/cam-nang?legacy={id}` có thông báo rà soát; 9 ID cũ không dẫn vào 404 |
| GET | `/api/guides` | q, category (slug), page, per_page (1–24); data, featured, categories, meta |
| GET | `/api/guides/{slug}` | Bài published và các gợi ý thật; nháp/ẩn/slug không tồn tại trả 404 |
| GET | `/api/admin/guides/meta` | Danh mục, địa phương, giới hạn ảnh |
| GET/POST | `/api/admin/guides` | Danh sách / tạo bài |
| GET/PUT/DELETE | `/api/admin/guides/{id}` | Chi tiết / sửa / lưu trữ |
| POST | `/api/admin/guides/{id}/restore` | Khôi phục bản nháp |
| POST | `/api/admin/guides/preview` | HTML đã lọc cho xem trước |
| POST | `/api/admin/guides/categories` | Tạo danh mục |
| PUT/DELETE | `/api/admin/guides/categories/{id}` | Sửa/ẩn hoặc xóa danh mục trống |

Upload dùng multipart POST, `_method=PUT` khi sửa; trường `cover`, `remove_cover`. Không nhận URL ảnh, slug hoặc metadata gốc do Client tự ghi. Khi schema chưa sẵn sàng, danh sách có error/retry, API đọc trả 503 và chi tiết có trang thông báo 503. URL cũ của bài nháp vẫn quay về Cẩm nang có thông báo, không lộ bản nháp.

## Kết quả 9 bài Blog

| ID Blog gốc | Nội dung nguồn | Trạng thái sau import | Ảnh gốc |
|---|---|---|---|
| 1 | Đầy đủ: mở bài, 4 địa điểm, mẹo, bảng so sánh, kết bài | published | L-1.jpg, có file |
| 2 | File rỗng | draft | L-2.jpg, có file |
| 3 | Thiếu `blog-3-content.blade.php` | draft | L-3.jpg, có file |
| 4 | File rỗng | draft | L-4.jpg, có file |
| 5 | File rỗng | draft | L5.jpg, có file |
| 6 | File rỗng | draft | L-6.jpg, có file |
| 7 | File rỗng | draft | L-7.jpg, có file |
| 8 | File rỗng | draft | L-8.jpg, có file |
| 9 | File rỗng | draft | L-9.jpg, có file |

Giữ nguyên tiêu đề đầy đủ, tag, tag_color, ngày cũ, read_time cũ và metadata chi tiết trong snapshot riêng; không hiển thị read_time như số liệu đã xác minh. Giữ byte gốc kể cả phần nghe bài, script và bình luận mẫu; chỉ trích phần biên tập để công khai. SHA-256 nội dung gốc số 1 trên workspace: `5160478b3133806a2cacb7aa3091cf5608b2ee4668ae86f885734ac0aaaa4254`.

Fingerprint xét duyệt chuẩn hóa BOM/CRLF để chuyển checkout Windows/Linux không khiến bài đủ nội dung thành nháp. Nếu nội dung nguồn số 1 thay đổi ngoài fingerprint đã xét duyệt, chuyển sang `needs_review`/draft. Không tự suy luận file mới là đủ. Import có transaction và chỉ thêm `legacy_id` chưa tồn tại; chạy lại không ghi đè bài đã biên tập. Kiểm thử xác nhận ID mới khác ID cũ vẫn chuyển hướng đúng.

Không sửa/xóa `resources/views/blog.blade.php`, `blog_detail.blade.php`, các file `content/blog-*-content.blade.php` hoặc 9 ảnh gốc.

## File tạo mới

- Migration: `database/migrations/2026_10_23_000002_create_travel_guides.php`.
- Models: `app/Models/GuidePost.php`, `app/Models/GuideCategory.php`.
- Support: `app/Support/GuideSchema.php`, `GuideHtml.php`, `LegacyBlogCatalog.php`.
- Services: `app/Services/GuideCatalogService.php`, `LegacyBlogImporter.php`, `app/Services/Admin/GuideService.php`.
- Controllers/request: `app/Http/Controllers/GuideController.php`, `app/Http/Controllers/Admin/GuideController.php`, `app/Http/Requests/SaveGuideRequest.php`.
- Command: `app/Console/Commands/ImportLegacyGuides.php`.
- Client: `resources/views/guides/index.blade.php`, `index-script.blade.php`, `show.blade.php`, `styles.blade.php`, `card.blade.php`, `unavailable.blade.php`.
- Admin: `resources/views/admin/guides.blade.php`, `resources/views/admin/partials/guides-script.blade.php`.
- Kiểm thử: `phpunit.guides.xml`, `tests/Feature/GuideModuleTest.php`, `tests/Support/guide_test_bootstrap.php`, `guide_browser_setup.php`, `guide_browser_router.php`, `tests/Frontend/guides.browser.cjs`.
- Báo cáo: `docs/guides-phase3.md`.

## File chỉnh sửa

- `app/Http/Controllers/BlogController.php`: bỏ hai nguồn danh sách/chi tiết trùng, dùng chuyển hướng tương thích, snapshot metadata đã đưa sang `LegacyBlogCatalog`.
- `routes/web.php`, `routes/api.php`: thêm riêng routes Cẩm nang; giữ routes module khác.
- `resources/views/admin/partials/sidebar.blade.php`: thay mục Blog placeholder.
- `resources/views/partials/layout/footer.blade.php`: tên AUREL và link Cẩm nang.
- `resources/views/partials/site-metadata.blade.php`: nhận routes Cẩm nang, mô tả và OG article, tránh lặp tên thương hiệu.
- `resources/views/Layout.blade.php`: chỉ thay tên thương hiệu mặc định của title thành AUREL; không refactor layout.

## Kiểm thử và bằng chứng

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit -c phpunit.guides.xml
node tests/Frontend/guides.browser.cjs
node tests/Frontend/guides.browser.cjs --layout
```

- PHP: **14 tests, 318 assertions PASS**. Bao phủ giới hạn kết nối SQLite, schema thiếu, 9 bài/bảo toàn hash, import lặp, command inspection không cần kết nối DB, chặn primary mặc định, URL cũ, slug cố định, xuất bản/hẹn ngày/múi giờ/ẩn, trường riêng tư, quyền Admin, validation, HTML/XSS, preview, ảnh, danh mục, lưu trữ/khôi phục, gợi ý địa phương thật và không gửi email/thông báo.
- Chrome: **26 nhóm PASS**, không có JavaScript exception. Client loading/danh mục/tìm kiếm/empty/error503/retry/phân trang; URL cũ; bài đầy đủ/ảnh thật; liên kết địa phương; Admin danh mục, preview, upload, draft/publish/thay/bỏ ảnh, ẩn/hiện danh mục, ẩn bài, archive/restore. Client ở 1440/1024/768/390/320px; Admin editor ở 1440/768/390/320px.
- Chrome layout: **3 nhóm PASS**: nội dung chi tiết không chồng Header và không tràn ngang ở 1440/768/320px. Phép đo dùng vị trí `.gd-page` sau padding của layout; không sửa Header để phục vụ bài kiểm thử.
- Đã xem ảnh chụp desktop/mobile và kiểm tra syntax, diff whitespace trong các file sửa. Chưa kiểm chứng Safari/Firefox, thiết bị vật lý hoặc MySQL production.

Artifacts chức năng/ảnh chụp: `C:/Users/ASUS/AppData/Local/Temp/aurel-guide-tests-211c63c9ed1d/browser-report.json`.

Artifacts layout chi tiết: `C:/Users/ASUS/AppData/Local/Temp/aurel-guide-tests-847ce42bc1c8/browser-report.json`.

Mỗi lượt kiểm thử dùng thư mục tạm riêng, view/cache/upload riêng, chỉ một kết nối SQLite, không đọc `.env` dự án hay config cache production. Browser server chỉ loopback, kiểm tra đường dẫn SQLite thuộc đúng thư mục tạm. Dữ liệu/ảnh fixture nhân tạo chỉ nằm trong SQLite/thư mục tạm; không được thêm vào Client production. Các module đã PASS không bị chạy lại full suite.

## Điều kiện trước khi bật trên database chính

1. Rà soát migration trên MySQL staging cô lập cùng phiên bản/schema mục tiêu, nhất là kiểu `location.id`, foreign key, index và thứ tự migration; bảo đảm migration địa phương/Trải nghiệm liên quan đã được triển khai nếu muốn có gợi ý Trải nghiệm.
2. Sao lưu dữ liệu/assets theo quy trình triển khai; giữ các Blade và ảnh Blog gốc. Kiểm tra nội dung, các thông tin/giá trong bài cũ số 1, quyền dùng ảnh và tác giả thực trước khi đưa bản import lên môi trường chính. Không suy đoán các thông tin còn thiếu.
3. Kiểm tra PHP DOM/fileinfo, public storage, đường dẫn `/storage`, quyền ghi upload, dung lượng request/ảnh và cấu hình URL/caches trên môi trường triển khai.
4. Xem trước nguồn bằng `php artisan guides:import-legacy` (mặc định chỉ đọc file). Chạy `--apply` trên staging đã duyệt và đối chiếu 9 `legacy_id`, 1 published/8 draft, ảnh và redirect. Import không tự chạy khi truy cập trang hay trong migration.
5. Chỉ người triển khai đã được duyệt mới chạy migration và chuyển đổi trên database chính. Command chủ động từ chối `hotel_booking` nếu không có cờ `--allow-primary`; **không sử dụng cờ này hoặc thực hiện thao tác DB chính trong giai đoạn hiện tại**.
6. Sau triển khai được duyệt: làm mới route/view cache theo quy trình, kiểm tra lại Header/link Cẩm nang, quyền Admin, upload và redirect thật. Hoàn thiện 8 bản nháp bằng nguồn nội dung thật; giữ draft cho tới khi đủ điều kiện xuất bản.
