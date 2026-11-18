# AUREL — Kế hoạch tích hợp Trải nghiệm vào website hiện tại

Ngày: **25/10/2026**, cập nhật theo quyết định **sửa 21 mapping Trải nghiệm, giữ nguyên 6 legacy hotel và 20 dòng Phú Quốc**. Đây là chốt phạm vi phương án, chưa cấp phép thực thi trên main. Chỉ cập nhật tài liệu, manifest và thêm một test tập trung; không sửa code ứng dụng/migration hoặc dữ liệu chính. Không đọc `.env`, không kết nối MySQL hoặc chạy seed/importer; test mới dùng SQLite `:memory:` với schema/fixture riêng. Không chạy lại full suite. Quy trình backup/clone Phase 5C.2 vẫn tạm dừng.

## 1. Kết luận từ giao diện và code

Dùng lại `/admin/experiences`, `/trai-nghiem`, `/trai-nghiem/{slug}` và các API hiện có; Header/sidebar đã trỏ đúng route, không cần làm lại hoặc đổi route. Admin nhập và Customer đọc cùng bảng `attraction`, gallery dùng `attraction_image`; không cần hệ thống địa điểm hoặc service mới.

- **Admin:** một form thêm/sửa; chọn địa phương từ `GET /api/admin/locations`, chọn danh mục tự điền loại phù hợp; upload có preview, chọn ảnh đại diện, thay/bỏ liên kết ảnh; lưu nháp hoặc xuất bản có nút/thông báo riêng. Tối đa 12 ảnh, JPG/PNG/WebP, 2 MB/ảnh; nguồn/quyền ảnh bắt buộc khi upload. Tắt hoạt động, lưu trữ và khôi phục đã có; file cũ được giữ.
- **Customer:** danh mục, tìm kiếm, lọc, phân trang, card, chi tiết/gallery và khách sạn active cùng `location_id`; loading/empty/error/retry và responsive đã có. Bộ lọc chỉ trả địa phương đang có trải nghiệm đủ điều kiện công khai, không tự thêm địa phương.
- **Cách vận hành đơn giản:** người phụ trách gán mã địa phương ổn định một lần, sau đó Admin thường chỉ chọn địa phương đã có → nhập nội dung/ảnh → lưu nháp → kiểm tra và xuất bản. Sửa nội dung/ảnh cần xác minh lại khi xuất bản; Customer nhận nội dung mới ở request/tải lại, không phải cập nhật đẩy thời gian thực.
- **Điểm cần hướng dẫn:** “Đang hoạt động” là dữ liệu dùng chung với AI; để chỉ ẩn trên Customer có thể bỏ chọn xuất bản. Bản nháp không tự hiện trên website; mã địa phương và nguồn thông tin là điều kiện xác minh.
- Không phát hiện lỗi mới cần sửa trong phần code đã rà soát. Không bỏ điều kiện xác minh để công bố dữ liệu cũ sai địa phương.

Bằng chứng kiểm thử **đã có**, không phải lượt chạy mới: [báo cáo CRUD](experience-crud-completion.md), [JSON kiểm thử](validation/experience-crud-completion.json): **MySQL 8.0.30 — 6 tests / 134 assertions, Chrome trên cùng MySQL — 20 nhóm PASS**. Bảy file UI/test trong danh sách hash vẫn khớp; không chạy lại các chức năng đã PASS.

File đối chiếu giao diện/logic: `resources/views/admin/experiences.blade.php`, `resources/views/admin/partials/experiences-script.blade.php`, `resources/views/experiences/index.blade.php`, `show.blade.php`, `styles.blade.php`, `app/Services/Admin/AttractionService.php`, `app/Services/ExperienceCatalogService.php`, `app/Http/Requests/SaveAttractionRequest.php`, `app/Support/ExperienceSchema.php` và `LocationIdentity.php`.

## 2. Đối chiếu địa phương đã xác minh trong Phase 5C.1

Nguồn: [khảo sát READ-ONLY](catalog-live-readonly-phase5c1.md), [JSON ngày 25/10](validation/catalog-live-readonly-phase5c1-2026-10-25.json), [47 dòng CSV](validation/attraction-live-location-review-phase5c1.csv). Mốc đọc nguồn: **25/10/2026 12:12:01 +07:00**. Đây là dữ liệu đã ghi nhận ở 5C.1, **không phải xác nhận trạng thái main mới trong lượt này**.

Trong bằng chứng có **8 location, 47 attraction, 24 hotel**, không có orphan location được phát hiện. Đã đối chiếu đủ 47 tên + loại + ID + quan hệ với snapshot và 47 dòng seeder bằng thao tác đọc file; 20 khớp, 27 lệch. Việc đọc source seeder không chạy seeder.

| location.id thật | Tên đang có | Mã nội bộ đề xuất, chưa gán |
|---:|---|---|
| 1 | Hà Nội | `vn-ha-noi` |
| 2 | Hồ Chí Minh | `vn-ho-chi-minh` |
| 3 | Đà Nẵng | Ngoài nhóm bốn key hiện tại; giữ nguyên |
| 4 | Phú Quốc | `vn-phu-quoc` |
| 5 | Nha Trang | Ngoài nhóm bốn key hiện tại; giữ nguyên |
| 6 | Hội An | Ngoài nhóm bốn key hiện tại; giữ nguyên |
| 7 | Đà Lạt | `vn-da-lat` |
| 8 | Huế | Ngoài nhóm bốn key hiện tại; giữ nguyên |

Các mã trên là định danh nội bộ, không phải mã hành chính. Gán mã là **UPDATE dữ liệu cần phê duyệt riêng**, không tạo địa phương mới, không đổi tên/ID hoặc tự gán cho cả tám địa phương. Admin vẫn chọn được tất cả địa phương đang có; mục chưa đủ điều kiện có thể lưu nháp. Bảng `location` ở 5C.1 không có cột active, không thêm cột/trạng thái địa phương mới cho đợt này.

## 3. Danh sách 27 dòng đối chiếu: chốt sửa 21, giữ nguyên 6 legacy

“Địa phương đúng” dưới đây là địa phương chuẩn khớp source và location đã có trong Phase 5C.1; chưa thay thế xác minh địa chỉ/đơn vị vận hành thực tế của từng mục.

| attraction.id | Tên nguyên bản | Đang gắn địa phương (ID) | Địa phương đúng trong hệ thống (ID) |
|---:|---|---|---|
| 1 | Lăng Chủ tịch Hồ Chí Minh | Hồ Chí Minh (2) | Hà Nội (1) |
| 2 | Văn Miếu - Quốc Tử Giám | Hồ Chí Minh (2) | Hà Nội (1) |
| 3 | Hồ Hoàn Kiếm & Đền Ngọc Sơn | Hồ Chí Minh (2) | Hà Nội (1) |
| 4 | Phở Gia Truyền Bát Đàn | Hồ Chí Minh (2) | Hà Nội (1) |
| 5 | Bún chả Hương Liên (Bún chả Obama) | Hồ Chí Minh (2) | Hà Nội (1) |
| 6 | Chả cá Lã Vọng | Hồ Chí Minh (2) | Hà Nội (1) |
| 7 * | Hanoi Old Quarter Budget Hotel | Hồ Chí Minh (2) | Hà Nội (1) |
| 8 * | Tirant Hotel Hanoi | Hồ Chí Minh (2) | Hà Nội (1) |
| 9 | Xem Múa Rối Nước Thăng Long | Hồ Chí Minh (2) | Hà Nội (1) |
| 10 | Dinh Độc Lập | Đà Nẵng (3) | Hồ Chí Minh (2) |
| 11 | Bảo tàng Chứng tích Chiến tranh | Đà Nẵng (3) | Hồ Chí Minh (2) |
| 12 | Chợ Bến Thành | Đà Nẵng (3) | Hồ Chí Minh (2) |
| 13 | Bánh Mì Huỳnh Hoa | Đà Nẵng (3) | Hồ Chí Minh (2) |
| 14 | Phở Lệ | Đà Nẵng (3) | Hồ Chí Minh (2) |
| 15 | Cơm tấm Ba Ghiền | Đà Nẵng (3) | Hồ Chí Minh (2) |
| 16 * | Saigonciti Hotel | Đà Nẵng (3) | Hồ Chí Minh (2) |
| 17 * | Liberty Central Saigon Centre | Đà Nẵng (3) | Hồ Chí Minh (2) |
| 18 | Ăn tối trên du thuyền sông Sài Gòn | Đà Nẵng (3) | Hồ Chí Minh (2) |
| 19 | Khu Du lịch Langbiang | Phú Quốc (4) | Đà Lạt (7) |
| 20 | Thung lũng Tình Yêu | Phú Quốc (4) | Đà Lạt (7) |
| 21 | Vườn hoa Thành phố Đà Lạt | Phú Quốc (4) | Đà Lạt (7) |
| 22 | Lẩu gà lá é Tao Ngộ | Phú Quốc (4) | Đà Lạt (7) |
| 23 | Bánh căn Lệ | Phú Quốc (4) | Đà Lạt (7) |
| 24 | Lẩu bò Quán Gỗ (Ba Toa) | Phú Quốc (4) | Đà Lạt (7) |
| 25 * | Tigold Hostel Da Lat | Phú Quốc (4) | Đà Lạt (7) |
| 26 * | Hôtel Colline Đà Lạt | Phú Quốc (4) | Đà Lạt (7) |
| 27 | Trải nghiệm hái dâu tây tại vườn | Phú Quốc (4) | Đà Lạt (7) |

**Dấu *:** ID **7, 8, 16, 17, 25, 26** là sáu row loại `hotel` trong bảng `attraction`, không phải ID của bảng `hotel`. **Giữ nguyên toàn bộ dữ liệu và quan hệ hiện tại của sáu row này**, không áp dụng địa phương nguồn ở cột cuối. Chúng không hiển thị thành Trải nghiệm. Các quan hệ khác nguồn còn tồn tại theo quyết định giữ legacy; không kết luận cả 47 dòng đã đúng địa lý.

**Phạm vi đã chốt để chuẩn bị thực thi sau phê duyệt riêng:** ID **1–6, 9: 2→1**; **10–15, 18: 3→2**; **19–24, 27: 4→7**. Mỗi nhóm 7 dòng, tổng 21. [Manifest 21 dòng có tên/loại/quan hệ cũ và mới](validation/experience-mapping-21-plan.csv) chỉ là kế hoạch chưa chạy. ID **28–47** khớp Phú Quốc, giữ nguyên cả 20 dòng. Không xóa/tạo lại attraction, đổi ID, đổi tên location, sửa bảng hotel hoặc chạy lại seed để chữa mapping.

Trước mỗi sửa phải khớp ID + tên + item_type + location cũ với manifest; dừng khi có drift. Chỉ cập nhật `location_id` của 21 dòng trong một transaction riêng, kiểm tra đủ 21 thay đổi rồi mới commit; không ghép transaction DDL vào bước này. Giữ mọi trường còn lại, không tự xác minh/xuất bản. Sửa `location_id` vẫn ảnh hưởng dữ liệu dùng chung dù không sửa code AI. Chưa tạo hoặc chạy công cụ UPDATE trên main.

Mô phỏng offline từ snapshot: tổng vẫn **47**, 21 dòng chỉ thay `location_id`, 26 dòng giữ nguyên. Phân bố vật lý sau sửa: **Hà Nội 7, Hồ Chí Minh 9, Đà Nẵng 2, Phú Quốc 22, Đà Lạt 7**; ba địa phương khác 0. Các số 9/2/22 gồm legacy giữ nguyên tại chỗ; **không dùng tiêu chí 9–9–9–20 của phương án sửa cả 27 cũ**. Ứng viên theo loại Trải nghiệm: Hà Nội 7, Hồ Chí Minh 7, Đà Lạt 7, Phú Quốc 17, tổng 38; chưa có nghĩa đã xuất bản. Trong 20 dòng Phú Quốc có ba hotel ID **34, 35, 47**, cũng được giữ nguyên và loại khỏi public bằng cùng quy tắc loại.

Kiểm tra loại trừ hotel: `ExperienceCatalogService::publicQuery()` chỉ nhận cặp category/type `attraction`, `restaurant`, `activity`; listing, tìm kiếm, filter địa phương, chi tiết và gợi ý ở Cẩm nang dùng chung query này. Không hardcode danh sách ID, không tắt `is_active` của legacy. `hotels()` đọc model/bảng `hotel` độc lập. Test mới **1 test / 51 assertions PASS** trên SQLite bộ nhớ: sáu hotel có metadata xuất bản giả lập vẫn không vào public; bản ghi kiểm thử ở bảng hotel có cùng ID 7 vẫn được gợi ý và dữ liệu giữ nguyên. Chưa chạy test mới trên MySQL; bằng chứng MySQL CRUD trước đó giữ nguyên.

Chưa xác minh độc lập tên/địa chỉ/hoạt động kinh doanh và quyền ảnh của các mục. Mục mô tả hoạt động chung, ví dụ ID 18 và 27, cần nguồn/đơn vị hoặc địa chỉ cụ thể trước khi công bố. Không tự lấp tọa độ/giá/ảnh hoặc bulk publish.

## 4. Ba migration catalog và schema cần thiết

Đối chiếu ledger và schema **đã lưu tại Phase 5C.1**: cả ba chưa áp dụng; không có dấu hiệu áp dụng dở dang trong các object đã khảo sát. Hash cả ba migration hiện vẫn khớp nguồn 5C.1. Trước khi thực thi trong tương lai vẫn phải đọc lại ledger/schema qua credential RO đã được duyệt; nếu khác bằng chứng thì dừng để đối chiếu.

| File migration | Thay đổi | Cần cho đợt chỉ Trải nghiệm |
|---|---|---|
| `2026_10_23_000001_add_experience_catalog_metadata.php` | Thêm `location.code` nullable/unique; 10 cột attraction cho slug, danh mục, tóm tắt, thời lượng, nguồn, mã địa phương lúc xác minh, người/ngày xác minh, ngày xuất bản/lưu trữ; unique slug, index công bố; tạo `attraction_image` và FK | **Có**, dùng nguyên migration đã PASS; không cần file mới |
| `2026_10_23_000002_create_travel_guides.php` | Tạo `guide_category`, `guide_post` | **Không**, để ngoài đợt Trải nghiệm |
| `2026_10_23_000003_widen_guide_publication_datetime.php` | Đổi `guide_post.published_at` từ TIMESTAMP sang DATETIME trên MySQL | **Không**; chỉ dùng khi tích hợp Cẩm nang, sau 000002 |

`ExperienceSchema::ready()` yêu cầu đầy đủ các object của 000001. Không chỉ thêm bảng ảnh hoặc slug rồi coi CRUD đã sẵn sàng; không rút gọn/sửa migration đã kiểm thử. 000001 không backfill mã, sửa mapping, tạo slug cho 47 row cũ hoặc xuất bản tự động. Bảng/cột/index mới cần đối chiếu trước DDL, vì migration này không tự xử lý schema đã thêm một phần.

Riêng Trải nghiệm không đọc bảng Cẩm nang nên không phụ thuộc 000002/000003. Khi Cẩm nang được giao đợt riêng thì 000002→000003; không chạy thêm migration AI cùng tiền tố 000002 hoặc lệnh migrate toàn bộ repository.

Ở snapshot: nền InnoDB, `location.id` INT signed và `attraction.id` BIGINT unsigned phù hợp FK mới. SQL mode strict và timezone server SYSTEM +07:00 đã ghi nhận; kiểm tra lại cấu hình triển khai/connection khi được phép. Đây không phải quyền tự đổi timezone/SQL mode hoặc chạy DDL.

## 5. Kế hoạch tích hợp ngắn và các xác nhận cần có

Các mục dưới đây **chưa thực hiện**. Target chính chỉ được sử dụng sau khi đối chiếu lại: **`127.0.0.1:3306 / hotel_booking`**, UUID đã duyệt **`7880df8a-92cd-11f0-85c1-a841f442ddbd`**. Không lấy credential từ `.env`, không dùng tài khoản audit SELECT để thực hiện ghi.

| Bước | Nội dung / target | Chủ dự án cần xác nhận trước thao tác |
|---:|---|---|
| 1 | Đọc lại identity/grants, ledger/schema và 47 mapping trên target chính bằng credential RO riêng đã duyệt | Dùng đúng phạm vi RO đang được cấp; nếu target/credential thay đổi phải xác nhận lại. Không ghi dữ liệu |
| 2 | Chuẩn bị biện pháp bảo toàn DB và storage, mốc triển khai, người thực hiện và khả năng phục hồi | **Phê duyệt riêng việc tạo backup và phạm vi dữ liệu** nếu muốn tiếp tục; hiện chưa có backup được duyệt, không tự khởi động lại Phase 5C.2 |
| 3 | Áp dụng duy nhất migration 000001 nếu schema/ledger vẫn thiếu và biện pháp bảo toàn đạt | **Phê duyệt DDL trên hotel_booking**, đúng file đầy đủ, tài khoản/người thực hiện có quyền phù hợp và thời điểm chạy; không mặc nhiên cấp phép cho hai migration Cẩm nang |
| 4 | Gán bốn code cho location 1/2/7/4 và sửa đúng 21 mapping đã chốt; giữ ID/bản ghi | **Phê duyệt thực thi UPDATE riêng** trên main: bốn code và 21 dòng trong manifest; giữ nguyên sáu legacy và 20 Phú Quốc; không đồng nghĩa duyệt xuất bản |
| 5 | Kiểm tra storage/URL và dùng một bản ghi thật đã duyệt để nhập nội dung/ảnh, xuất bản có kiểm soát | **Duyệt nội dung, nguồn/quyền ảnh, ID bản ghi, người Admin thực hiện và quyền upload/ghi file/xuất bản**. Không dùng dữ liệu mẫu trên main |
| 6 | Customer tải lại danh sách/chi tiết, tìm/lọc, đối chiếu khách sạn cùng địa phương, kiểm tra khả năng ẩn | **Xác nhận nghiệm thu và mở cho người dùng** sau khi URL ảnh, dữ liệu và phạm vi ảnh hưởng đạt; không tự mở rộng sang module khác |

Storage local đã có junction **`public/storage → C:\laragon\www\Hotel-Booking\storage\app\public`** (đã kiểm tra chỉ đọc). **Không cần tạo lại link hiện tại.** Chưa xác minh URL trên website chính/quyền ghi của PHP service; chỉ xử lý nếu phát hiện thiếu và có phạm vi được duyệt, không sửa ACL rộng hoặc xóa junction/ảnh đang có.

[Runbook sao lưu tối thiểu, phục hồi và nghiệm thu](deployment/experience-integration-runbook.md) ghi rõ điều kiện dừng và các nhóm ghi cần phê duyệt. Chưa tạo backup, restore target hoặc chạy các bước đó.

Sau các bước được phép: kiểm tra tổng attraction vẫn 47 nếu chỉ sửa dữ liệu hiện hữu, location vẫn 8, không duplicate/orphan, không đổi ID hoặc dữ liệu hotel, row chưa duyệt không hiện công khai, ảnh cũ giữ nguyên. Với mục đã duyệt, ảnh mới và nội dung phải hiện đúng ở request Customer kế tiếp; tắt xuất bản phải ẩn khỏi danh sách/chi tiết.

Không chọn rollback 000001 bằng `down()` sau khi đã có gallery/metadata mới: hàm down xóa bảng ảnh và cột catalog. Ưu tiên dừng phục vụ/ẩn module, giữ schema và dữ liệu để xử lý; mọi khôi phục DB/storage cần kế hoạch/phê duyệt riêng, tránh ghi đè đặt phòng phát sinh.

## 6. DONE / PARTIAL / BLOCKED, tiến độ và bước tiếp theo

- **DONE:** chốt phương án 21 mapping, giữ sáu legacy và 20 Phú Quốc; manifest và mô phỏng offline bảo toàn 47 ID/26 dòng không sửa; xác minh điều kiện lọc public bằng code và test mới 1/51 PASS; xác định migration 000001 cùng runbook sao lưu/phục hồi/nghiệm thu.
- **PARTIAL:** chưa xác nhận schema/ledger mới hơn mốc 5C.1; chưa kiểm chứng URL ảnh/quyền upload trên website chính, địa chỉ/nội dung hoạt động và quyền ảnh thật; phương án đã chốt nhưng 21 mapping và bốn code chưa có phê duyệt thực thi ghi. Test mới chỉ chạy SQLite; không nhầm với kết quả MySQL 6/134 và Chrome 20 nhóm trước đó.
- **BLOCKED:** gói backup đã tạo và kiểm chứng file nhưng chưa có phê duyệt target restore-test; chưa có bằng chứng nạp phục hồi hoặc quyền DDL/UPDATE/upload/xuất bản main theo kế hoạch. Không phát triển lại hoặc chạy lại bộ test đã PASS.

**Tiến độ hiện tại: ước tính 87% sau backup thực tế Phase 5E.** Cơ sở: kỹ thuật có bằng chứng 80 điểm + khảo sát dữ liệu thật RO 5 điểm + backup SQL/ảnh đã tạo và kiểm chứng file 2 điểm; restore-test, sửa dữ liệu và phê duyệt triển khai chưa hoàn thành. [Kết quả backup và hash](deployment/experience-backup-phase5e.md) không thay thế bằng chứng phục hồi trên MySQL.

**Bước tiếp theo:** chủ dự án duyệt gói backup có SHA đã công bố và target restore-test riêng đã đề xuất trong Phase 5E; sau bằng chứng phục hồi mới phê duyệt DDL 000001, gán bốn code, UPDATE đúng 21 mapping và nghiệm thu bằng một nội dung thật. **Không hỏi lại lựa chọn 21 hay 27: phạm vi đã chốt là 21.** Quyết định phương án không thay thế xác nhận riêng trước từng nhóm ghi database/storage chính. Chưa thực hiện triển khai.

