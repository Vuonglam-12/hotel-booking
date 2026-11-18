# AUREL — ảnh Hotel/RoomType trong Admin sau Phase 2D

Hoàn tất ngày 12/10/2026, chỉ trong phạm vi quản lý ảnh Admin. Tái sử dụng form, middleware Admin, `hotel_image`, `room_type_image`, `image_url`, `is_primary`, resolver ảnh local và disk `public`. Không thêm migration/dependency, bật provider V2 production, mở Checkout, chạy thao tác trên database chính hoặc thay luồng thanh toán/đăng nhập.

## Hành vi

- Form sửa Hotel hiển thị gallery hiện tại; form RoomType lấy gallery theo Hotel và Type trong URL. Cùng một partial/editor xử lý thumbnail mới, bỏ từng file, đánh dấu xóa/hoàn tác liên kết cũ và chọn ảnh đại diện.
- Phân biệt “Ảnh đã lưu” / “Ảnh mới · chưa lưu”; có empty/loading/saving/error/success và tải lại gallery. Khóa lưu khi chưa đọc được ảnh; khóa thao tác ảnh trong lúc lưu.
- Chọn/bỏ/đánh dấu xóa/chọn cover không gửi request ghi. Hủy/đóng/chuyển đối tượng giải phóng object URL, bỏ lựa chọn tạm và vô hiệu hóa response cũ bằng AbortController + số phiên form (kể cả mở lại cùng ID).
- Không chọn ảnh mới giữ gallery; thêm ảnh append. Thông tin và thay đổi ảnh lưu trong một transaction. HTTP validation/lỗi storage/lỗi transaction giữ lựa chọn trong giao diện và rollback dữ liệu. Cleanup khi transaction lỗi chỉ xóa các upload mới do chính request tạo.
- Xóa ảnh chỉ xóa liên kết. File đã lưu, file dùng chung và `room_image` legacy giữ nguyên. Nếu xóa cover, ảnh giữ lại đầu tiên trở thành cover khi lưu; vẫn có thể chọn cover cụ thể trước khi lưu.

## API và validation

Giữ endpoint lưu Hotel/RoomType hiện có. Bổ sung `GET /api/admin/hotels/{id}/images`; tái sử dụng `GET /api/admin/hotels/{hotel}/room-types/{type}/images`. Response ảnh thêm `display_url`, `image_validation`; field/storage cũ giữ nguyên. API liên kết/gỡ ảnh RoomType cũ vẫn hoạt động.

Các field bổ sung khi lưu:

| Field | Ý nghĩa |
|---|---|
| `images[]` | File JPG/JPEG, PNG hoặc WebP hợp lệ; tối đa 2 MB/ảnh và 20 ảnh mới/request |
| `image_changes.delete_ids` | ID các liên kết thuộc đúng đối tượng cần gỡ |
| `image_changes.primary_id` | ID ảnh đã lưu, đúng đối tượng, không bị gỡ |
| `image_changes.primary_new` | Vị trí file mới được chọn làm cover |

Không gửi hai lựa chọn cover cùng lúc. JSON không có file giữ nguyên phương thức PUT; có file gửi multipart POST + `_method=PUT`. `adminApi` để trình duyệt tạo multipart boundary, vẫn giữ toàn bộ auth/headers hiện tại. `image_changes` có thể là JSON object hoặc chuỗi JSON trong multipart. Chuẩn hóa `amenities=[]` rỗng trong multipart để không làm mất contract tiện nghi hiện có.

Validation ảnh dùng quy tắc JPG/PNG/WebP/2 MB đã có ở public upload của ứng dụng, thêm kiểm tra ảnh đọc được. Backend kiểm tra Admin role/session ability; khóa Hotel/RoomType đúng quan hệ và xác minh mọi ID ảnh trước khi ghi/upload. Từ chối ID/cover của đối tượng khác, Type sai Hotel, file giả định dạng và file quá lớn.

Giới hạn tổng request của PHP/web server vẫn áp dụng độc lập; không sửa cấu hình máy chủ. Browser test dùng PHP `upload_max_filesize=2M`, `post_max_size=48M` trong process thử nghiệm riêng. Giao diện giữ lựa chọn nếu server từ chối request. Không tuyên bố đã nghiệm thu cấu hình upload của máy chủ thật.

## File của lượt này

8 file sửa:

- `app/Http/Controllers/AdminController.php`
- `app/Http/Controllers/PreparedV2/AdminRoomTypeController.php`
- `app/Services/RoomTypeAdminServiceV2.php`
- `routes/api.php`
- `resources/views/admin/layouts/admin.blade.php`
- `resources/views/admin/hotels.blade.php`
- `resources/views/prepared-v2/admin-hotels.blade.php`
- `resources/views/prepared-v2/admin-room-types.blade.php`

10 file thêm:

- `app/Services/AdminAccommodationImageService.php`
- `resources/views/admin/partials/image-editor.blade.php`
- `resources/views/admin/partials/image-editor-assets.blade.php`
- `tests/Feature/AdminAccommodationImagesTest.php`
- `tests/Frontend/admin-images.browser.cjs`
- `tests/Support/AdminImagesFixture.php`
- `tests/Support/prepare_admin_images_staging.php`
- `tests/Support/admin_images_staging_router.php`
- `phpunit.admin-images.xml`
- `docs/admin-accommodation-images.md`

## Kiểm tra và giới hạn

Reused owned MySQL harness/fixtures, server mới `42b46bc30f21`, port `49674`, principal chỉ có quyền trên owned test schemas. Bootstrap từ descriptor private, kiểm identity/grants trước connection; không dùng `.env` làm database target. Router thử nghiệm chỉ overlay các route Admin ảnh/RoomType, luôn giữ Checkout tắt và không đăng ký V2 production. Public disk/uploads, bản sao CSS build hiện có và browser profile nằm trong runtime private. External browser requests bị chặn.

| Kiểm tra | Kết quả |
|---|---|
| `phpunit.admin-images.xml` | **6 tests / 68 assertions PASS**: giữ ảnh khi không chọn, append/cover, gỡ giữ file/legacy, ownership/atomic rollback, file sai/quá lớn, guest/user/ability, cleanup upload riêng khi transaction lỗi |
| Regression readiness Admin create/status, gallery legacy, role/ability | **3 tests / 27 assertions PASS** |
| Regression Hotel amenities/nullable/filtered KPI | **1 test / 11 assertions PASS** |
| `admin-authentication.test.cjs` | **17 tests PASS**; hành vi auth hiện có giữ nguyên sau thay header multipart |
| Chrome + HTTP + MySQL/storage thử nghiệm | **33 checks PASS, 0 uncaught errors**: mở/xem/chọn/bỏ/xóa/undo/cover/lưu/mở lại; Hủy không ghi; lỗi định dạng/dung lượng, file giả bị backend từ chối; lỗi lưu giữ lựa chọn; khóa trong lúc lưu; multipart boundary; đổi Hotel/Type, response muộn và mở lại cùng Type; tải lỗi/retry; gỡ liên kết giữ file; desktop 1440 px/mobile 390 px không tràn ngang |
| Syntax | 9 PHP files, CJS, PHPUnit XML và `git diff --check` PASS; các Blade/scripts được render/chạy trong browser ở cả Hotel root, Hotel prepared và RoomType |

Browser evidence: `storage/app/private/roomtype-v2-tests/42b46bc30f21/admin-images-browser-results.json` và bốn screenshot `admin-images-{hotel,type}-{desktop,mobile}.png`. Không đưa descriptor/token/credentials private vào Git.

Đã đóng Chrome/PHP của browser script và graceful shutdown đúng MySQL owned. Xác nhận không còn listener trên port MySQL `49674` và HTTP cuối `49276`; giữ runtime/datadir/artifact để review.

Các log FAIL ban đầu giữ riêng: thiếu quyền spawn trong sandbox (đã chạy browser với auto-review cho môi trường biệt lập); lỗi bỏ một thumbnail đã sửa; nhãn tiếng Việt bị shell encoding đã sửa bằng patch UTF-8; đồng thời hai fixture allocator đụng schema (đã chạy lại tuần tự); harness thiếu global FormData đã xử lý tương thích; chờ tải ảnh/resize đã sửa. Một lần PHP staging trả lỗi fetch tạm; retry của giao diện phục hồi. Không hạ assertion hoặc coi các FAIL cũ là PASS.

Đối chiếu source baseline: không mất file đã có. `.env`, auth controllers/middleware, Booking/Payment controllers, config/provider V2 production, migrations và dependency manifests giữ hash. Ngoài 8 file của lượt này, bốn file ngoài phạm vi cũng đổi hash trong thời gian làm việc: `resources/views/Layout.blade.php`, `resources/views/partials/layout/header.blade.php`, `resources/views/partials/layout/header-script.blade.php`, `resources/css/site-shell.css`. Công việc ảnh Admin không ghi hay hoàn tác các file đó; giữ các thay đổi hiện có trong workspace.

Không còn thiếu chức năng trong phạm vi ảnh Admin đã yêu cầu. Chưa kiểm tra dữ liệu/hosting thật và không kích hoạt V2 production; các giới hạn Phase 2D vẫn giữ nguyên. Dừng tại đây.

Chạy lại trên owned test settings (không dùng artisan/default database):

```powershell
$env:AUREL_ROOMTYPE_V2_TEST_SETTINGS=(Resolve-Path '<owned-runtime>/test-environment.json').Path
php vendor/bin/phpunit -c phpunit.admin-images.xml --no-progress
php tests/Support/prepare_admin_images_staging.php $env:AUREL_ROOMTYPE_V2_TEST_SETTINGS
node tests/Frontend/admin-images.browser.cjs '<owned-runtime>/admin-images-session.json'
```

Fixture allocator dùng schema rỗng trong allowlist; chạy các suite tạo fixture **tuần tự**. Browser script tự đóng đúng Chrome/PHP đã mở; MySQL shutdown qua `tests/Support/stop_roomtype_v2_test_server.ps1` với owned `server.json`, giữ datadir/artifact để review.
