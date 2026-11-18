# AUREL — Trải nghiệm, giai đoạn 2

Module dùng lại `location` và `attraction`. Không có hệ thống địa điểm, đặt tour hay thanh toán trải nghiệm riêng. Header và tìm kiếm giai đoạn 1 được giữ nguyên; route `experiences.index` tự kích hoạt link đã chuẩn bị.

## Dữ liệu và điều kiện công bố

- Migration mới chỉ thêm cột và bảng ảnh. Không backfill, sửa quan hệ, gán mã hoặc xuất bản bản ghi cũ.
- `location.code` là mã nội bộ duy nhất, có thể gán một lần qua API Admin hiện có. Đây không phải mã hành chính nhà nước. Tên có thể thay đổi mà không đổi mã.
- `attraction` thêm slug cố định, danh mục, mô tả ngắn, thời lượng đã kiểm tra theo phút, nguồn thông tin, bản chụp mã địa phương lúc xác minh, người/thời điểm xác minh, thời điểm xuất bản/lưu trữ.
- Chỉ công bố khi `is_active=true`, đã xác minh, đã xuất bản, chưa lưu trữ, loại khớp danh mục và mã địa phương hiện tại khớp mã được xác minh. Sửa nội dung, quan hệ địa phương hoặc gallery làm mất xác minh/xuất bản cho tới khi Admin kiểm tra lại.
- Không lấy thời lượng công khai từ `duration_hours` vì trường cũ có giá trị mặc định phục vụ AI. Không trả giá ước tính, giá vé hoặc rating từ API công khai.
- `attraction_image` lưu ảnh upload, ảnh đại diện, chú thích và nguồn/quyền sử dụng. Chỉ ảnh thực tồn tại trên storage mới được trả ra Client; thiếu ảnh hiển thị trạng thái rõ ràng.
- Tái sử dụng `AdminAccommodationImageService` cho validation ảnh, quyền sở hữu ảnh và transaction có dọn file mới nếu thất bại. Tối đa 12 ảnh/trải nghiệm, JPG/PNG/WebP, 2 MB/ảnh.
- DELETE được chuyển thành lưu trữ; bản ghi, giá trị cũ và file ảnh không bị xóa vật lý. Khôi phục trả về bản nháp, tắt hoạt động và yêu cầu xác minh lại.
- `is_active` vẫn là trường dùng chung với AI. Tắt/bật hoặc chỉnh thông tin attraction qua Admin có thể ảnh hưởng dữ liệu đầu vào AI dù code AI không đổi; cần xét duyệt nội dung trước thao tác trên môi trường chính.

## Seeder

Seeder cũ gán Hà Nội bằng ID 2, TP.HCM bằng ID 3 và cả Đà Lạt/Phú Quốc bằng ID 4. Bản sửa dùng các khóa nội bộ `vn-ha-noi`, `vn-ho-chi-minh`, `vn-da-lat`, `vn-phu-quoc` theo từng nhóm dữ liệu.

Resolver ưu tiên mã đã gán; fixture cũ chưa có mã chỉ được khớp đúng một tên chuẩn/quốc gia. Thiếu/trùng địa phương sẽ dừng trước khi ghi. Seeder không ghi đè hay tự sửa bản ghi đã có; mẫu mới luôn tắt hoạt động và chưa xuất bản. Seeder từ chối database tên `hotel_booking` và môi trường ngoài local/testing. Không chạy seeder này để sửa dữ liệu thật.

## API và trang

| Phương thức | Đường dẫn | Ý nghĩa |
|---|---|---|
| GET | `/trai-nghiem` | Danh sách, danh mục, tìm kiếm, địa phương, phân trang và các trạng thái tải/rỗng/lỗi |
| GET | `/trai-nghiem/{slug}` | Chi tiết công khai; gallery, thông tin có dữ liệu và khách sạn active cùng `location_id` |
| GET | `/api/experiences` | `q`, `location` (mã ổn định), `category`, `sort=latest/name`, `page`, `per_page<=24` |
| GET | `/api/experiences/filters` | Địa phương có trải nghiệm được công bố và bốn danh mục |
| GET | `/api/experiences/{slug}` | Chi tiết công khai, nguồn ảnh/thông tin và khách sạn liên quan |
| GET | `/admin/experiences` | Giao diện quản trị dùng shell/Admin auth hiện có |
| GET | `/api/admin/experiences/meta` | Danh mục và giới hạn upload |
| GET | `/api/admin/attractions` | Quản trị, lọc `location_id`, `q`, `archived`, `page` |
| GET/POST | `/api/admin/locations/{locId}/attractions` | API cũ: danh sách theo địa phương/tạo mới |
| GET/PUT/DELETE | `/api/admin/attractions/{id}` | Chi tiết/cập nhật/lưu trữ |
| PUT | `/api/admin/attractions/{id}/toggle` | API cũ: bật/tắt hoạt động |
| POST | `/api/admin/attractions/{id}/restore` | Khôi phục thành bản nháp |
| PUT | `/api/admin/locations/{id}` | API cũ, thêm gán mã địa phương một lần |

Mọi API Admin nằm trong nhóm `auth:admin` + `AdminMiddleware` hiện có, kiểm tra role, tên token `admin_token` và ability `admin`. Giao diện web không chứa dữ liệu quản trị; dữ liệu chỉ tải sau xác minh phiên Admin. Không thay đổi Login.

Lưu attraction chấp nhận JSON hoặc multipart. Upload dùng `images[]`, `image_source`, `image_caption` và JSON `image_changes` (`delete_ids`, `primary_id` hoặc `primary_new`). Multipart cập nhật dùng POST + `_method=PUT`. `verify` và `publish` là yêu cầu chủ động; client không được tự gửi thời điểm/người xác minh.

Nếu chưa có schema mới, API Trải nghiệm trả 503 có kiểm soát; landing page vẫn có giao diện và trạng thái lỗi/thử lại. Bản ghi không công bố trả 404, kể cả khi biết slug.

## File sửa/tạo

Backend:

- `app/Http/Controllers/ExperienceController.php` (mới)
- `app/Http/Controllers/Admin/AttractionController.php` (nối API cũ vào service duy nhất)
- `app/Http/Controllers/AdminController.php` (bỏ logic attraction trùng, thêm gán mã địa phương)
- `app/Http/Requests/SaveAttractionRequest.php` (mới)
- `app/Services/ExperienceCatalogService.php` (mới)
- `app/Services/Admin/AttractionService.php`
- `app/Support/ExperienceSchema.php`, `ExperienceCategory.php`, `LocationIdentity.php` (mới)
- `app/Models/Attraction.php`, `Location.php`, `AttractionImage.php` (model ảnh mới)
- `routes/web.php`, `routes/api.php`
- `database/migrations/2026_10_23_000001_add_experience_catalog_metadata.php` (mới, chưa chạy trên DB chính)
- `database/seeders/AttractionSeeder.php`

Giao diện:

- `resources/views/experiences/index.blade.php`, `index-script.blade.php`, `show.blade.php`, `styles.blade.php`, `unavailable.blade.php` (mới)
- `resources/views/admin/experiences.blade.php`, `admin/partials/experiences-script.blade.php` (mới)
- `resources/views/admin/partials/sidebar.blade.php`
- `resources/views/partials/site-metadata.blade.php`

Kiểm thử:

- `phpunit.experiences.xml`
- `tests/Feature/ExperienceModuleTest.php`
- `tests/Support/ExperienceFixture.php`, `experience_test_bootstrap.php`, `experience_browser_setup.php`, `experience_browser_router.php`
- `tests/Frontend/experiences.browser.cjs`

## Kiểm thử cô lập

PHP CLI trên máy hiện tại có SQLite DLL nhưng chưa bật mặc định. Chỉ bật cho tiến trình kiểm thử:

```powershell
php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit -c phpunit.experiences.xml
node tests/Frontend/experiences.browser.cjs
node tests/Frontend/header-search.browser.cjs
```

Bootstrap không đọc `.env` dự án, không dùng config/route cache production và thay toàn bộ danh sách connection bằng SQLite. Feature tests dùng `:memory:`; Chrome dùng file SQLite, upload và browser profile trong thư mục tạm riêng. Ảnh/dữ liệu nhân tạo chỉ tồn tại trong fixture, không được đưa vào dữ liệu hoặc asset của ứng dụng. Các API khách sạn và badge Dashboard ngoài phạm vi được giả lập trong kiểm thử trình duyệt module; bộ kiểm thử Header được chạy riêng.

Chưa kiểm chứng MySQL production, Safari/Firefox hoặc thiết bị thật. Không chạy full test suite.

Kết quả thực tế của giai đoạn này:

- PHP: **14 tests, 143 assertions — PASS** trên SQLite trong bộ nhớ.
- Chrome Client/Admin: **17 nhóm kiểm tra — PASS**, gồm tải/rỗng/lỗi/thử lại, bộ lọc, phân trang, responsive 320/390/768px, gallery/Escape/focus, CRUD, upload, xuất bản, bật/tắt, lưu trữ/khôi phục và form Admin mobile. Không có JavaScript exception.
- Bộ Header giai đoạn 1: **8 kịch bản — PASS lại**, bao gồm các mốc 1023/1024px và route tương thích.
- Đã xem ảnh chụp Client desktop/mobile, chi tiết và form Admin mobile. Ảnh chụp chứa dữ liệu kiểm thử, không phải nội dung công bố.
- PHP lint, JavaScript syntax và kiểm tra whitespace các file liên quan đạt.

## Trước khi bật trên database chính

1. Duyệt migration và sao lưu; đối chiếu schema/phiên bản MySQL trên bản sao cô lập, kiểm tra khóa ngoại và index. Chỉ chạy migration trên database chính bằng đợt triển khai được duyệt riêng.
2. Kiểm tra storage public, quyền ghi, đường dẫn `/storage`/symlink và upload trên môi trường triển khai; không tự tạo symlink trong giai đoạn này.
3. Đối chiếu từng địa phương, gán mã ổn định và lập phương án sửa các quan hệ sai có người duyệt. Không chạy lại DatabaseSeeder/AttractionSeeder trên dữ liệu chính.
4. Bổ sung ảnh thật có quyền sử dụng và nguồn thông tin. Admin xác minh đúng địa phương, loại hoạt động, nội dung, thời lượng/giờ nếu có; sau đó mới bật hoạt động và xuất bản từng mục.
5. Kiểm tra route, quyền Admin, upload/gallery và gợi ý khách sạn trên môi trường triển khai. Giữ nguyên các luồng Booking, Payment, Login và AI.

Không có bước nào ở trên được tự chạy trên `hotel_booking` trong giai đoạn 2.
