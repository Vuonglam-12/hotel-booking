# AUREL — Hoàn thiện luồng CRUD Trải nghiệm

Ngày: **25/10/2026**. Phạm vi: Admin nhập/sửa và upload ảnh → Laravel lưu → Customer đọc. **Tạm dừng quy trình backup/clone/deployment rehearsal Phase 5C.2.**

**DONE luồng CRUD trên môi trường cô lập; PARTIAL mức sẵn sàng dùng dữ liệu chính.** Không kết nối `hotel_booking`, không dùng credential RO/admin của hệ thống chính, không đọc `.env`, không chạy seed/importer hoặc triển khai. Không sửa code Header, tìm kiếm khách sạn, Booking, Payment, Login, Lịch trình AI hoặc Cẩm nang.

Bằng chứng: [experience-crud-completion.json](validation/experience-crud-completion.json).

## 1. Kết quả nghiệp vụ

| Trạng thái | Chức năng / điều kiện |
|---|---|
| DONE | Dùng lại `/admin/experiences`, AttractionController, AttractionService và hai model Attraction/AttractionImage; không tạo module/service mới |
| DONE | Thêm và sửa tên, mô tả, địa chỉ, danh mục, địa phương, giờ hoạt động và metadata đã có |
| DONE | Dropdown Admin lấy danh sách từ `/api/admin/locations`; lưu attraction không tạo hoặc sửa location; ID không tồn tại bị từ chối |
| DONE | Upload ảnh, chọn ảnh đại diện, thay ảnh và bỏ ảnh khỏi gallery; thay liên kết ảnh không xóa file cũ |
| DONE | Lưu nháp hoặc xuất bản rõ ràng; tắt hoạt động, lưu trữ và khôi phục; khôi phục về nháp, giữ bản ghi và ảnh |
| DONE | Customer đọc cùng bảng attraction: danh sách, tìm kiếm, lọc địa phương/danh mục, phân trang và trang chi tiết |
| DONE | Sau khi lưu và xuất bản, request/tải lại Customer nhận tên, nội dung, địa phương và ảnh mới trên cùng slug; API công khai đã có `Cache-Control: no-store` |
| DONE | Quyền ghi qua middleware Admin; request không đăng nhập hoặc token Customer bị từ chối; lỗi upload/xuất bản rollback cả dữ liệu và file mới của thao tác thất bại |
| PARTIAL | 47 bản ghi thật mới được đối chiếu lại từ bằng chứng READ-ONLY 5C.1; chưa duyệt nội dung/quyền ảnh hoặc sửa 27 địa phương lệch |
| PARTIAL | Kiểm thử dùng dữ liệu và ảnh synthetic trong runtime tạm; không chứng minh dữ liệu/ảnh thật đã sẵn sàng xuất bản |
| BLOCKED | Chạy module đầy đủ trên schema chính khi chưa áp dụng migration cần thiết và chưa được phê duyệt thay đổi |

Luồng sử dụng giữ đơn giản: chọn địa phương đang có → nhập nội dung và ảnh → lưu nháp; khi đã kiểm tra thì bật hoạt động, xác nhận xác minh và chọn xuất bản. Không thêm cơ chế duyệt hay kiến trúc mới. Mã địa phương ổn định được gán một lần bằng chức năng Admin đã có, không tự sinh/gán lại trong lượt này. Nếu chưa có mã hoặc nguồn xác minh, vẫn lưu nháp được nhưng chưa xuất bản.

Điều kiện công khai hiện có được giữ nguyên: active, chưa lưu trữ, có slug, đã xác minh, đã xuất bản đến hạn, loại/danh mục phù hợp và mã địa phương khớp bản chụp lúc xác minh. Sửa nội dung/ảnh/địa phương cần xác minh lại khi xuất bản. `location` trong schema đã khảo sát không có trạng thái active riêng; không giả lập cột này hoặc tạo địa phương mới.

## 2. Thay đổi thực hiện

Vấn đề UX đã xử lý: nút và thông báo cũ chỉ nói “Lưu trải nghiệm/Đã lưu trải nghiệm”, chưa giúp Admin phân biệt nội dung đã lưu nháp hay đã hiển thị trên website. Hiện nút đổi thành **Lưu bản nháp / Lưu và xuất bản** theo lựa chọn; có hướng dẫn điều kiện hiển thị, validation phía trình duyệt cho danh mục/nguồn khi xuất bản và thông báo kết quả tương ứng dữ liệu server trả về.

Không phát hiện lỗi nghiệp vụ mới cần sửa controller/service trong các kịch bản đã chạy. Giả thuyết về định dạng giờ đã được đối chiếu: `attraction.open_time/close_time` là VARCHAR, dữ liệu khảo sát ở dạng HH:mm; không sửa định dạng giờ, model hoặc migration.

| File sửa | Nội dung |
|---|---|
| `resources/views/admin/experiences.blade.php` | Ghi chú trạng thái lưu và nhãn nút nháp |
| `resources/views/admin/partials/experiences-script.blade.php` | Hướng dẫn/validation theo lựa chọn xuất bản; thông báo nháp hoặc xuất bản sau khi lưu |
| `tests/Feature/CatalogMysqlIntegrationTest.php` | Thêm 6 test CRUD tập trung, giữ nguyên các test catalog trước đó |
| `tests/Support/run_catalog_mysql_integration.cjs` | Thêm chế độ `--experience-crud`: chạy schema tối thiểu, 6 test và Chrome Trải nghiệm trên cùng MySQL riêng; giữ ownership guard và shutdown |
| `tests/Support/catalog_mysql_control.php` | Nhánh `experience-migrations`: kiểm tra schema rồi áp dụng đúng migration Trải nghiệm trên server test mới |
| `tests/Support/experience_browser_setup.php` | Dùng lại fixture cho SQLite hoặc MySQL có guard; ảnh thay thế và browser state tách khỏi descriptor sở hữu MySQL |
| `tests/Frontend/experiences.browser.cjs` | Kiểm thử nháp → xuất bản → Customer → sửa/thay ảnh/địa phương → Customer; chạy được trên cùng MySQL và storage thực của runner |

File tạo: báo cáo này và `docs/validation/experience-crud-completion.json`. Không tạo migration ứng dụng mới hoặc sửa migration lịch sử.

Code được tận dụng: `routes/web.php`, `routes/api.php`, `app/Http/Controllers/Admin/AttractionController.php`, `app/Http/Requests/SaveAttractionRequest.php`, `app/Services/Admin/AttractionService.php`, `app/Services/ExperienceCatalogService.php`, `app/Services/AdminAccommodationImageService.php`, `app/Models/Attraction.php`, `app/Models/AttractionImage.php` và `resources/views/experiences/`.

## 3. Kiểm thử và cách ly

**Lần chạy cuối: PASS — MySQL 8.0.30, 6 tests / 134 assertions; Chrome trên chính MySQL đó: 20 nhóm PASS, 0 JavaScript exceptions.** Có 65 request Laravel được ghi nhận với cùng database/UUID và không có email, notification hoặc HTTP outbound qua ứng dụng.

| Thuộc tính | Giá trị |
|---|---|
| Database test | `aurel_catalog_test_811da79a1c9d` |
| Endpoint riêng | `127.0.0.1:62893` |
| UUID riêng | `b9d013b7-d04b-11f1-b208-89fe61ea7279` |
| Runtime riêng | `C:\Users\ASUS\AppData\Local\Temp\aurel-catalog-mysql-811da79a1c9d` |
| Kết nối ứng dụng | Chỉ `catalog_test`; không có cấu hình connection chính |
| Storage | Upload riêng trong runtime, được phục vụ qua junction `public/storage` của test; không thay junction/storage của dự án |
| Sau kiểm thử | MySQL riêng đã shutdown, exit code 0; PHP server và Chrome kết thúc |

Runner kiểm tra database/port/datadir/UUID/owner marker trước migration, trước ứng dụng và từng request HTTP. Server riêng bind loopback, tắt event scheduler và binlog. Schema kiểm thử dùng migration nền tối thiểu cho location/attraction/hotel/ảnh và tài khoản fixture; catalog chỉ chạy **`2026_10_23_000001_add_experience_catalog_metadata.php`**. Không chạy migration Cẩm nang/AI, seeder hoặc importer. Fixture nằm trong test riêng, không bổ sung bản ghi vào hệ thống thật.

Sáu test xác nhận: upload/sửa/thay ảnh và nội dung Customer; chỉ dùng location tồn tại; lọc/tìm kiếm/phân trang và hotel cùng địa phương; ẩn/lưu trữ/khôi phục; từ chối tài khoản không có quyền; rollback upload thất bại. Dữ liệu hotel fixture được kiểm tra giữ nguyên khi sửa attraction.

Chrome xác nhận ảnh tải được, dữ liệu Admin mới hiện trên trang chi tiết Customer, thay ảnh/địa phương không đổi URL, nháp không hiện công khai, tắt hoạt động ẩn mục, khôi phục giữ ảnh, responsive desktop/tablet/mobile (768/390/320px và form Admin 390px), loading/empty/error/retry. Trạng thái lỗi 503 được giả lập ở HTTP trong browser runner. Không chạy full test suite hoặc kiểm thử lại nghiệp vụ đặt phòng/thanh toán/đăng nhập/AI.

Trước lần chạy cuối: Chrome SQLite cô lập cũng PASS 20 nhóm. Một test MySQL mới ban đầu FAIL do đổi token Customer→Admin trong cùng application test còn cache guard; đã tách thành hai test độc lập. Không sửa hoặc nới quyền đăng nhập để làm test PASS.

Lệnh tái chạy, **chỉ cho server synthetic do runner tự sở hữu**, không dùng cho database thật:

```powershell
node tests/Support/run_catalog_mysql_integration.cjs C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqld.exe --experience-crud
```

## 4. 27 liên kết địa phương: phương án, chưa thực hiện

Nguồn là [đối chiếu 47 dòng READ-ONLY](validation/attraction-live-location-review-phase5c1.csv), không phải truy vấn mới trong lượt này. Xác nhận lại 20 dòng khớp và 27 dòng lệch so với địa phương chuẩn trong source; gồm 21 mục thuộc loại Trải nghiệm và 6 mục legacy loại hotel.

| Attraction ID | Hiện đang trỏ location | Đề xuất location đang tồn tại | Số dòng |
|---|---|---|---:|
| 1–9 | 2 — Hồ Chí Minh | 1 — Hà Nội | 9 |
| 10–18 | 3 — Đà Nẵng | 2 — Hồ Chí Minh | 9 |
| 19–27 | 4 — Phú Quốc | 7 — Đà Lạt | 9 |

Danh sách từng ID: [CSV đề xuất đã chuẩn bị](validation/attraction-clone-mapping-plan-phase5c2.csv). CSV này chỉ là kế hoạch, không được thực thi. ID 28–47 hiện khớp Phú Quốc, không đề nghị đổi theo nguồn này.

Phương án nghiệp vụ: người phụ trách duyệt đúng tên/địa chỉ/địa phương từng mục; khi được phép thay dữ liệu, dùng form Admin chọn location hiện có và lưu nháp theo nhóm nhỏ. Giữ ID và mọi trường không cần sửa, không xóa/tạo lại, không chạy seeder hoặc đổi tên location để chữa mapping. Nếu cần thao tác hàng loạt, phải trình phạm vi và điều kiện kiểm tra ID + tên + loại + location cũ trước, dừng khi dữ liệu đã đổi so với bảng duyệt; không phát hành SQL sửa main trong lượt này.

Sửa `location_id` hoặc `is_active` có thể ảnh hưởng dữ liệu dùng chung cho AI dù không sửa code AI. Sáu mục legacy hotel cũng cần duyệt riêng; không chỉnh bảng hotel để xử lý chúng. Sau mỗi nhóm được phép sửa: kiểm tra còn đủ 47 attraction, không duplicate/orphan, không đổi ID hoặc dữ liệu hotel, vẫn unpublished cho tới khi kiểm tra nội dung và ảnh.

## 5. Schema, việc còn lại và bước tiếp theo

Khảo sát 5C.1 trước đó xác nhận schema main chưa có `location.code`, metadata Trải nghiệm và `attraction_image`; lượt này **không kết nối lại main và không giả định schema đã thay đổi**. Hiện chưa đủ cơ sở bật CRUD đầy đủ trên database chính. Không cần thêm migration mới cho nghiệp vụ đang yêu cầu: migration 000001 đã có đủ cột/bảng ảnh và đã PASS trong test riêng.

Bước tiếp theo trong phạm vi CRUD:

1. Chủ dự án xem lại giao diện và luồng nhập nháp/xuất bản theo kết quả test. Không cần xây lại module hoặc mở thêm kiến trúc.
2. Duyệt danh sách 27 mapping và nội dung/ảnh từng mục; không tự lấp địa chỉ, tọa độ hoặc giá bằng dữ liệu giả. Quyền sử dụng ảnh thật vẫn cần chủ dự án xác nhận.
3. Khi muốn dùng database chính, phải có phê duyệt riêng cho schema/dữ liệu: đối chiếu lại ledger/schema bằng RO, trình phạm vi migration 000001 và mã địa phương cần gán, cùng điều kiện bảo toàn/phục hồi dữ liệu. Không tự khởi động lại quy trình backup/clone đang tạm dừng.
4. Chỉ khi các điều kiện và quyền ghi được xác nhận mới lên bước tích hợp; kiểm tra bằng một mục nội dung thật đã được duyệt trước, sau đó mở rộng theo nhóm nhỏ. Không tự triển khai trong nhiệm vụ này.

**Tiến độ module Trải nghiệm: ước tính 85%, giữ nguyên mốc 5C.1.** Cơ sở: bốn nhóm kỹ thuật đã có bằng chứng (80 điểm) và khảo sát dữ liệu thật RO (5 điểm); các mốc duyệt/sửa dữ liệu, chuẩn bị vận hành và phê duyệt triển khai chưa hoàn thành. Luồng CRUD trong test đã DONE, tỷ lệ này không đồng nghĩa 85% dữ liệu đúng hoặc đã sẵn sàng dùng trên `hotel_booking`.
