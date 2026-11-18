# AUREL — Runbook tích hợp Trải nghiệm: 21 mapping

Ngày 25/10/2026. **Chỉ là kế hoạch, chưa thực thi trên main.** Dùng cùng [kế hoạch tích hợp](../experience-integration-plan.md) và [manifest 21 dòng](../validation/experience-mapping-21-plan.csv). Quyết định dữ liệu đã chốt không phải quyền chạy DDL/UPDATE. Không đọc `.env`, dùng credential quản trị mặc định hoặc tự khởi động lại quy trình Phase 5C.2.

## 1. Phạm vi cố định

- Đúng migration `2026_10_23_000001_add_experience_catalog_metadata.php`; không tạo migration mới hoặc chạy hai migration Cẩm nang/AI.
- Sau khi được phê duyệt ghi: gán `vn-ha-noi` cho location 1, `vn-ho-chi-minh` cho 2, `vn-da-lat` cho 7, `vn-phu-quoc` cho 4, dựa trên tên/quốc gia và identity đã kiểm tra. Không tạo location hoặc đổi mã đã gán khác.
- Chỉ đổi `attraction.location_id`: **1–6,9: 2→1; 10–15,18: 3→2; 19–24,27: 4→7**. Tổng 21 dòng.
- Giữ nguyên sáu attraction hotel **7,8,16,17,25,26**, mọi trường/quan hệ/trạng thái/ảnh. Giữ nguyên cả 20 dòng Phú Quốc **28–47**. Không đổi bảng hotel, mã ID, giá, nội dung hoặc trạng thái để thực hiện mapping; không seed, xóa hoặc tự xuất bản.
- Public loại theo `item_type` phù hợp category, không theo danh sách ID. Ba hotel Phú Quốc **34,35,47** cũng không vào public Trải nghiệm. Gợi ý khách sạn vẫn đọc bảng `hotel` riêng.

Target main dự kiến: **127.0.0.1:3306 / hotel_booking**, server UUID **7880df8a-92cd-11f0-85c1-a841f442ddbd**. Mọi thông tin schema hiện dựa vào khảo sát 5C.1, không phải xác minh mới ở lượt này.

## 2. Sao lưu tối thiểu trước ghi

Gói bảo toàn tối thiểu gồm **một dump đầy đủ schema+dữ liệu database, một snapshot storage bị tác động và manifest phiên bản/khôi phục**. Không coi CSV/JSON khảo sát hoặc chỉ export 21 dòng là backup đủ cho thay đổi DDL ở location/attraction có FK dùng chung. **Cập nhật Phase 5E: gói backup thật đã được tạo và xác minh file/hash**, xem [kết quả cụ thể](experience-backup-phase5e.md); nạp restore-test vẫn cần phê duyệt target riêng. Các bước dưới đây giữ làm quy trình, không yêu cầu làm lại các bước backup đã hoàn tất.

1. **READ-ONLY — xác minh đầu vào.** Dùng RO đã duyệt để kiểm tra lại target/identity/grants, ledger, engine/cột/index/FK, đủ 47 attraction, 8 location và đúng 21 tuple ID+tên+loại+location cũ. Kiểm tra metadata trigger/view/routine/event bởi người có quyền nhìn đủ object; RO SELECT không chứng minh không có các object bị ẩn. Lưu inventory, counts, SQL mode/timezone và mốc thời gian; không đọc row khách hàng/giao dịch vào báo cáo.
2. **Cần xác nhận vận hành và ghi file — tạo backup.** Chủ dự án duyệt người/tài khoản backup phù hợp, đường dẫn riêng ngoài Git/webroot và mốc T0. Phối hợp dừng DDL cùng các tác vụ quản trị/nền thay đổi ảnh/catalog trong thời gian chụp DB+storage. Không tự dừng Booking/Payment hoặc dịch vụ MySQL chính. Nếu không đảm bảo mốc nhất quán, dừng và chốt lại phương án.
3. **Dump database bằng mysqldump 8.0.30**, schema + dữ liệu + FK/index/ledger và object thực sự tồn tại. Không dùng tài khoản admin của chủ dự án hoặc nâng quyền audit RO qua assistant. File có thể chứa dữ liệu cá nhân/giao dịch: operator giữ riêng, không đưa nội dung vào Git/chat/log, không in password. Credential backup phải được cấp riêng và dùng prompt/login-file ngoài Git.
4. **Snapshot `storage/app/public`** tại cùng mốc ổn định, giữ cấu trúc đường dẫn và file hiện có; ghi nhận junction `public/storage`, không xóa/tạo lại junction. Lưu bản copy/hash bộ file Trải nghiệm đang chạy và migration để có thể khôi phục code; loại credential khỏi gói code. Không cần thay hoặc xóa ảnh ở `public/image` trong đợt này.
5. **Kiểm tra và duyệt backup.** Exit code 0, file không rỗng, manifest SHA-256 cho SQL và các file storage, identity nguồn, thời điểm, counts và schema. Hash chỉ chứng minh tính toàn vẹn file, chưa chứng minh restore được. Chủ dự án xác nhận đường dẫn/hash/phạm vi backup trước khi dùng.
6. **Cần phê duyệt ghi riêng — restore-test tối thiểu.** Phục hồi vào database trống, target/tài khoản/storage riêng được duyệt, không phải hotel_booking. Kiểm tra tham chiếu schema/DEFINER trước import, từ chối lệnh trỏ main, không dùng credential có quyền ghi main. Đối chiếu counts, ID, FK/index/ledger, đủ 21 tuple, file/hash và URL ảnh catalog nếu có. Đây mới là bằng chứng phục hồi, chưa cho phép triển khai main; không tự tạo clone hoặc dùng dump chưa được duyệt.

Các tùy chọn backup phải được chốt sau inventory: `--single-transaction --quick` chỉ đảm bảo snapshot nhất quán cho InnoDB, tránh DDL đồng thời; Windows dùng `--result-file`, không redirect `>` qua PowerShell. Quyền đọc bảng, view và trigger khác nhau; không mặc định SELECT hiện tại đủ. [MySQL: mysqldump](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html).

Với target đủ điều kiện, operator xem xét `--no-defaults`, login-path/file riêng, `--no-tablespaces`, `--set-gtid-purged=OFF`, `--hex-blob`, `--default-character-set=utf8mb4`, `--skip-lock-tables`; flag object theo inventory. Không dùng `--databases/--all-databases`, `--force` hoặc bỏ lỗi/object rồi coi backup đầy đủ. Chưa cung cấp lệnh chạy hoàn chỉnh vì chưa xác nhận inventory toàn DB, credential và file đích; không được tự dùng cấu hình mặc định để lấp phần thiếu.

## 3. Trình tự ghi sau khi đủ điều kiện

1. **Preflight RO mới** ngay trước cửa sổ triển khai; identity/grants/schema/ledger không khớp thì dừng. Bằng chứng cũ: code/metadata/gallery chưa tồn tại, INT signed location và BIGINT unsigned attraction phù hợp FK. Nếu schema đã thêm một phần hoặc có migration mới, không chạy lại mù quáng.
2. **DDL được duyệt:** kiểm tra target bằng connection triển khai riêng được cấp rõ ràng rồi chạy duy nhất file 000001. Không bootstrap Artisan bằng connection mặc định chưa xác minh. Kiểm tra toàn bộ 10 cột attraction, location.code/unique, slug/unique, public index, attraction_image/FK và ledger. Số row/ID cũ không đổi; metadata mới vẫn null, không tự publish.
3. **Gán bốn code được duyệt:** đối chiếu đúng location ID+tên+quốc gia; null thì gán, đúng mã sẵn có thì không cần ghi, mã khác/trùng hoặc drift thì dừng. Không gán thêm mã cho bốn địa phương ngoài phạm vi.
4. **UPDATE 21 mapping được duyệt:** một transaction DML riêng, khóa/đối chiếu đúng 21 tuple từ manifest, chỉ cập nhật location_id rồi kiểm tra đủ 21 thay đổi. Bất kỳ precondition/count sai thì rollback transaction này, không bỏ guard để chạy tiếp. Trước commit phải chứng minh 26 row khác, các trường khác trong 21 row, bảng hotel và quan hệ ID được giữ nguyên; dùng baseline mới có cả timestamps sau preflight, không chỉ snapshot rút gọn 5C.1.
5. **Đối chiếu sau sửa, trước nhập mục nghiệm thu:** vẫn 47 attraction, 8 location; không duplicate/orphan; phân bố vật lý Hà Nội 7 / Hồ Chí Minh 9 / Đà Nẵng 2 / Phú Quốc 22 / Đà Lạt 7. Ứng viên theo type là 7/7/0/17/7, tổng 38; không đồng nghĩa đã được công bố. Giữ 6 legacy và 20 Phú Quốc nguyên trạng. Chưa mở công khai các mục thiếu xác minh/quyền ảnh.
6. **Nghiệm thu một nội dung thật được duyệt** theo bảng dưới. Không tự tạo dữ liệu mẫu trên main. Lưu ID mới và file mới để đối chiếu, không xóa khi kết thúc nghiệm thu.

Migration có nhiều câu DDL; không thể coi cả DDL và UPDATE là một transaction có thể rollback chung vì DDL MySQL gây implicit commit. Nếu DDL lỗi giữa chừng, dừng và kiểm tra object/ledger thực tế trước phương án sửa tiếp. [MySQL: implicit commit](https://dev.mysql.com/doc/refman/8.0/en/implicit-commit.html).

## 4. Phục hồi theo điểm lỗi

| Điểm lỗi | Xử lý được đề xuất, không tự thực hiện |
|---|---|
| Chưa có SQL/file ghi trên main | Dừng, giữ hệ thống hiện tại; không cần restore |
| DDL 000001 lỗi giữa chừng | Dừng các thao tác ghi catalog; lưu schema/ledger hiện tại, xác định object đã tạo. Trình phương án hoàn thiện phần thiếu hoặc phục hồi đã diễn tập; không sửa migration lịch sử, ledger hoặc rerun mù quáng |
| Transaction mapping chưa commit | Rollback transaction DML, kiểm tra đủ 21 quan hệ cũ; không rollback DDL kèm theo |
| Mapping đã commit nhưng cần đảo lại | Dùng baseline 21 row và manifest ngược; chỉ đảo location_id khi ID/tên/loại/quan hệ mới và tình trạng chỉnh sửa vẫn khớp. Có drift hoặc chỉnh sửa nghiệp vụ sau đó thì dừng và duyệt xử lý từng mục. Cần quyền UPDATE riêng |
| Lỗi sau upload/xuất bản/nghiệm thu | Ưu tiên bỏ xuất bản mục bị ảnh hưởng, giữ row/file/ID và schema; chỉ khôi phục nội dung hoặc file cụ thể được duyệt. Không xóa record mới hoặc dọn ảnh tự động |
| Cần phục hồi toàn DB/storage | Là quyết định khôi phục khẩn cấp riêng, cần đánh giá giao dịch phát sinh sau T0. Không restore đè main bằng snapshot cũ hoặc ghi đè toàn bộ ledger/storage, vì có thể mất booking/dữ liệu mới |

Không dùng `down()` của 000001 làm rollback mặc định sau khi có dữ liệu: code down xóa bảng gallery và cột metadata. Ưu tiên giữ schema additive, dừng phục vụ/ẩn phần lỗi và xử lý có kiểm soát. Bốn code không tự xóa lại nếu đã được dữ liệu mới sử dụng. Không tắt FK checks hoặc xóa/tạo lại location/attraction để phục hồi nhanh.

## 5. Kịch bản nghiệm thu thực tế — chưa chạy trên main

Điều kiện: backup đã restore-test PASS, schema và 21 mapping đạt kiểm tra, bốn code đúng; credential/quyền Admin và URL upload hợp lệ. Chủ dự án cung cấp **một địa điểm thật chưa có**, tên/địa phương/mô tả/nguồn đúng và hai ảnh A/B có quyền sử dụng; duyệt việc thêm row, upload, sửa và xuất bản mục đó. Không dùng ảnh/địa điểm synthetic trên website chính.

| Bước Admin | Kết quả cần kiểm tra ở DB / Customer |
|---|---|
| Chọn địa phương có sẵn, nhập nội dung và upload ảnh A; lưu nháp | Nhận ID+slug, row/image lưu đúng, nguồn ảnh có; chưa vào danh sách/tìm kiếm, URL chi tiết chưa truy cập được. Location vẫn 8; attraction thành 48 nếu baseline còn đúng 47 |
| Bật hoạt động, xác minh nội dung/quyền ảnh và chọn xuất bản | Customer tải lại thấy đúng card, tên, địa phương, ảnh A và chi tiết trên slug đã tạo; ảnh trả HTTP 200; không có giá/rating giả |
| Sửa nội dung thực, thay A bằng B, chọn đại diện và xác minh/xuất bản lại | Giữ ID+slug; Customer tải lại thấy nội dung mới, ảnh B và đúng nguồn. Link gallery A bị thay nhưng file A giữ nguyên; không có duplicate row |
| Tìm theo tên và lọc địa phương đúng/địa phương khác | Trả đúng mục tại địa phương đã chọn, không xuất hiện ở địa phương khác; khách sạn gợi ý đúng địa phương, legacy hotel không vào kết quả Trải nghiệm |
| Bỏ chọn xuất bản rồi lưu, giữ is_active hiện tại | Customer không thấy mục ở danh sách/tìm kiếm/chi tiết; row/file giữ nguyên. Không cần đổi trạng thái AI dùng chung chỉ để ẩn trên Customer |

Kiểm tra desktop/mobile, kết quả mới ở request/tải lại kế tiếp; không kỳ vọng push realtime. Số lượng 47 là mốc sau sửa mapping, **không** là tiêu chí sau thêm địa điểm thật. Không tự xuất bản lại mục nghiệm thu sau bước ẩn; chủ dự án quyết định trạng thái cuối.

## 6. Các xác nhận thực sự còn cần

1. **Backup:** operator/tài khoản phù hợp, nguồn đúng UUID, thời điểm/mốc ổn định và đường dẫn DB/storage ngoài Git; quyền tạo file và giữ riêng dữ liệu nhạy cảm.
2. **Restore-test:** đích database/instance/storage rỗng được duyệt và tài khoản chỉ ghi đích đó; quyền tạo/restore riêng, không mặc nhiên cấp cho main.
3. **DDL main:** đúng migration 000001, target hotel_booking, connection/người thực hiện và cửa sổ triển khai sau khi điều kiện bảo toàn đạt.
4. **UPDATE main:** gán bốn code và đúng 21 mapping đã chốt. Không hỏi lại chọn 21 hay 27; không sửa sáu legacy hoặc 20 Phú Quốc.
5. **Nghiệm thu main:** duyệt địa điểm/ảnh thật, quyền sử dụng, Admin thực hiện một INSERT cùng upload, cập nhật, xuất bản và ẩn theo kịch bản; các quyền ghi này chưa được dùng trong lượt chuẩn bị.

**DONE:** chốt phạm vi, manifest/mô phỏng offline và test loại trừ legacy SQLite 1/51 PASS. **PARTIAL:** preflight mới, backup/object inventory toàn DB, URL/quyền upload thực tế và nội dung/ảnh. **BLOCKED:** chưa có backup được duyệt, restore-test hoặc quyền thực thi các nhóm ghi main. **Tiến độ ước tính 85%**, giữ nguyên; bước tiếp theo là chốt operator/đích backup và restore-test, chưa triển khai.
