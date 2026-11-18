# AUREL — Đề xuất restore-test backup Phase 5E

Ngày 25/10/2026. **CHƯA PHÊ DUYỆT / CHƯA THỰC HIỆN.** Chỉ chuyển sang bước này sau khi [backup thực tế](experience-backup-phase5e.md) đã kiểm chứng file; bước trước đã đạt điều kiện đó, nhưng quyền ghi target vẫn cần phê duyệt riêng theo [runbook](experience-integration-runbook.md).

## Target và nguồn cụ thể để chủ dự án xem xét

| Thuộc tính | Đề xuất |
|---|---|
| Nguồn SQL | `C:\Users\ASUS\AppData\Local\AUREL\backups\experience-phase5e-20261025-057fe2d6\database\hotel_booking-20261025.sql` |
| SHA-256 nguồn | `ba0130dffefcfe4153bb85ed1900ec1faea171a9a2ada166cbe1ed36f88fb875` |
| Dung lượng SQL | 298645 bytes |
| Dữ liệu ảnh nguồn | `C:\Users\ASUS\AppData\Local\AUREL\backups\experience-phase5e-20261025-057fe2d6\storage-public`; 447 file/40591954 bytes, từng hash trong manifest |
| Máy chủ test | **MySQL 8.0.30 instance mới, chỉ loopback 127.0.0.1:63317**, không dùng instance chính :3306 |
| Database mới | `aurel_experience_restore_20261025_057fe2d6` |
| Runtime/datadir mới | `C:\Users\ASUS\AppData\Local\AUREL\restore-tests\experience-phase5e-20261025-057fe2d6`, nhánh `data` riêng |
| Ảnh đích mới | Runtime trên, nhánh `public\storage`/upload riêng, không sửa junction/storage dự án |
| Credential | Chỉ dành cho instance/DB test; do chủ dự án cấp hoặc phê duyệt thiết lập trên instance mới. Không dùng tài khoản Admin/main hoặc nâng quyền aurel_catalog_ro |
| Kết quả hiện tại | Runtime chưa tồn tại, port chưa có listener khi kiểm tra; chưa tạo/khởi động/nạp dữ liệu |

## Những thao tác ghi cần duyệt

Chủ dự án xác nhận **bản backup có SHA ở trên** được dùng làm nguồn, việc khởi tạo instance/datadir private mới, tạo database/tài khoản test riêng hoặc cung cấp target/credential clone-only đã có, và nạp nguyên SQL + copy ảnh vào đúng đích. Đây là dữ liệu thật trong bản sao riêng; không công khai instance, không đưa SQL/ảnh/data directory vào Git, không chia sẻ payload nhạy cảm ra log.

Nếu dùng target khác, cần tên/host/port/database, danh tính server và đường dẫn credential clone-only ngoài Git. Không gửi mật khẩu hoặc credential Admin. Không tạo object test trên hotel_booking. Chưa lập/chạy GRANT hoặc phương án thay đổi quyền cho database chính.

## Trình tự sau phê duyệt

1. Kiểm tra lại SHA SQL/manifest. Chuẩn bị folder/ACL private, khởi tạo MySQL riêng bằng --no-defaults và datadir mới; bind loopback, tắt event scheduler/binlog/mysqlx và external file loading. Không đọc cấu hình Laragon main hoặc .env.
2. Xác minh server test: host/port, UUID mới khác **7880df8a-92cd-11f0-85c1-a841f442ddbd**, datadir và marker sở hữu, đúng tên DB không phải hotel_booking, account chỉ có phạm vi đã duyệt. Dừng nếu trùng source/đích có sẵn hoặc scope quyền sai.
3. Giữ file backup nguyên trạng; không đổi USE/DEFINER bằng replace tùy tiện. File đã kiểm chứng không có USE/CREATE DATABASE/qualified schema source. Nạp bằng connection tường minh vào DB trống; dừng ở lỗi đầu tiên, không --force. Chỉ các session SET tiêu chuẩn của dump hoạt động trong session test; không thay biến global của main.
4. Đối chiếu **29 bảng**, cấu trúc cột, unique/index, PK/FK và ledger với source-schema.json. So FK schema theo tên database test được remap; không chấp nhận tham chiếu ngược main.
5. COUNT từng bảng so với insert_rows_by_table trong backup-verification.json. Xác nhận catalog **8/47/24/142/69**, đủ ID, 21 mapping vẫn giữ trạng thái gốc, legacy/Phú Quốc không đổi, không duplicate/orphan. Không in row khách hàng hoặc giao dịch ra log.
6. So fingerprint catalog với baseline riêng, có cùng cách biểu diễn timezone của TIMESTAMP; kiểm tra SQL mode/timezone mà không đổi main. Các count/hash là dữ liệu kiểm chứng, không khẳng định nội dung nguồn đã đúng địa lý.
7. Phục hồi 447 file ảnh sang storage test, so size/SHA từng file; nếu bật HTTP kiểm tra ảnh thì chỉ server test/loopback, không bootstrap app tới main, không gửi email/webhook hoặc giao dịch thật.
8. Ghi báo cáo PASS/FAIL và dừng đúng instance do runner sở hữu. Giữ gói nguồn và bằng chứng; không DROP hoặc restore đè main, không dọn file ảnh/source, không tự triển khai.

**Không chạy migration 000001/Guides/AI, seed, importer, sửa 21 mapping hoặc xuất bản nội dung trong restore-test baseline này.** Mục tiêu chỉ chứng minh khôi phục đúng bản sao hiện tại. Việc tích hợp module và sửa dữ liệu là bước được duyệt riêng sau đó.

## Trạng thái

**DONE:** đề xuất nguồn/hash/đích và tiêu chí kiểm tra cụ thể. **PARTIAL:** chưa kiểm chứng import SQL, FK/row equivalence hoặc ảnh phục hồi. **BLOCKED:** chờ chủ dự án duyệt target ghi/khởi tạo và account test phù hợp. Không đánh dấu restore PASS dựa trên SHA hay header/footer.
