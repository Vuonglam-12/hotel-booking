# AUREL — Phase 5E: backup thực tế đã xác minh file

Ngày **25/10/2026**. **DONE backup database + ảnh + bản chụp code liên quan; PARTIAL khả năng phục hồi SQL, chờ restore-test được phê duyệt riêng.** Chủ dự án đã xác nhận bằng phpMyAdmin: Trigger 0 / Routine 0 / Event 0. Không truy vấn lại ba loại metadata, không tạo schema/view audit hoặc cấp thêm quyền.

## 1. Bản sao đã tạo

Thư mục ngoài Git/webroot, chỉ ASUS và SYSTEM được truy cập:

`C:\Users\ASUS\AppData\Local\AUREL\backups\experience-phase5e-20261025-057fe2d6`

| Thành phần | Kết quả thực tế |
|---|---|
| Database | `database\hotel_booking-20261025.sql` — **298,645 bytes**, 29 CREATE TABLE; mysqldump 8.0.30 exit 0, không stderr |
| Ảnh/storage | `storage-public` — **447 file, 40,591,954 bytes (38.71 MiB)**; toàn bộ size/SHA-256 khớp nguồn |
| Code liên quan | `code-snapshot` — 25 file Trải nghiệm/routes/migration/script, hash khớp; không có .env hoặc credential. Đây là snapshot phạm vi liên quan, không phải bản sao toàn bộ repository |
| Bằng chứng riêng | `evidence` — schema/index/FK snapshot, manifest ảnh/code, options dump không password và backup-verification.json |
| Tổng gói | 478 file, 41,242,147 bytes; không đưa SQL hoặc ảnh backup vào repository |
| ACL | Đã kiểm tra 586 đường dẫn, không có access rule cho tài khoản ngoài ASUS/SYSTEM |

SQL chứa bản sao dữ liệu đầy đủ của database, gồm dữ liệu nhạy cảm. Không in nội dung INSERT/row khách hàng/giao dịch vào terminal, log hoặc báo cáo. Chỉ công bố số lượng catalog, dung lượng, hash và trạng thái kiểm tra; giữ gói tại thư mục private.

## 2. Hash xác nhận

**SQL SHA-256**

`ba0130dffefcfe4153bb85ed1900ec1faea171a9a2ada166cbe1ed36f88fb875`

**File storage-manifest.json SHA-256** — manifest liệt kê path tương đối, bytes, mtime và SHA-256 của từng file:

`07f201a55a9ffed4f1d9ba610854584b4130b080898802a9214d836a24216824`

**File code-manifest.json SHA-256**

`7ab7ebaf08b44204ed1f00ce3b849c843abd556cef9cade2ac540944ad8c8fd8`

**File backup-verification.json SHA-256**

`fe8e68e5d5fbb989b625c80ff422acb7dee715995b91795e172cbaa92d659a7a`

Hash SQL đã được tính lại độc lập bằng Get-FileHash sau khi script hoàn tất và khớp. SHA-256 của thư mục ảnh được kiểm chứng qua từng file và manifest, không gọi đó là một file archive.

## 3. Source và phạm vi thao tác

- Endpoint `127.0.0.1:3306 / hotel_booking`; UUID **7880df8a-92cd-11f0-85c1-a841f442ddbd**; account **aurel_catalog_ro@127.0.0.1**. Guard target/grants/roles tại thời điểm truy cập backup và ở cuối cùng connection; chỉ USAGE + SELECT, roles NONE. Kết nối đã đóng.
- Mốc source: **2026-10-25 17:01:36 +07:00**. Dump bắt đầu **17:01:38 +07:00**, kết thúc cùng giây. Không dùng credential Admin, cấu hình mặc định hoặc đọc .env.
- Dùng kết quả metadata zero của chủ dự án; mysqldump skip trigger/routine/event theo kết quả đó. Không đề xuất phân quyền hoặc kiểm tra lại các object này.
- Full schema+dữ liệu, InnoDB single-transaction/quick, no-tablespaces, set-gtid-purged OFF, UTF-8/hex-blob và result-file. Không dùng --databases/--all-databases, --force, source LOCK TABLES hoặc SQL ghi dữ liệu.
- Dump dùng một INSERT mỗi row để kiểm chứng số lượng; bỏ DROP TABLE, output LOCK TABLES và DISABLE KEYS. File không có CREATE DATABASE, USE, DEFINER, SET GLOBAL hoặc tham chiếu schema nguồn ngoài các INSERT/ghi chú. Chưa thực thi file này trên MySQL nào.
- Ngoài thao tác mysqldump được yêu cầu, chỉ SELECT/SHOW identity, schema và fingerprint catalog. Không phân tích giá trị row khách hàng/giao dịch; không gọi Laravel, email, webhook, migration, seed hoặc importer.
- Không INSERT/UPDATE/DELETE/ALTER trên main; **21 mapping, 6 legacy hotel, 20 Phú Quốc và bốn code địa phương đều chưa thay đổi**.

Script thực hiện: [tools/backup-experience-phase5e.cjs](../../tools/backup-experience-phase5e.cjs). Có guard --execute, không ghi đè gói đã có. Không chạy lại vào cùng thư mục. Lần chạy thực tế exit code 0.

## 4. Kiểm chứng đã PASS và giới hạn

| Kiểm tra | Kết quả |
|---|---|
| SQL đọc được bằng UTF-8 strict, không NUL, có header/footer hoàn tất | PASS |
| Inventory CREATE TABLE khớp đủ 29 bảng nguồn, không trùng | PASS |
| Dump catalog row counts khớp baseline ổn định | PASS — location 8, attraction 47, hotel 24, hotel_image 142, migrations 69 |
| Fingerprint schema/index/FK và catalog trước–sau | PASS — không đổi |
| Size/SHA ảnh source trước → copy → source sau | PASS — 447/447 |
| Bản chụp code và source code giữ nguyên hash | PASS — 25/25 |
| SQL hash đọc lại và ACL toàn gói | PASS |
| Nạp SQL vào MySQL cô lập rồi đối chiếu bản phục hồi | **NOT RUN — cần phê duyệt riêng target ghi** |
| Nghiệm thu ảnh qua web/storage của môi trường phục hồi | **NOT RUN** |

Không đặt main vào maintenance hoặc dừng dịch vụ Booking/Payment; chỉ ghi nhận không có thay đổi schema/catalog/ảnh qua so sánh trước–sau, không tuyên bố đã áp đặt khóa vận hành. Các kiểm tra file không thay thế restore-test, không khẳng định SQL đã phục hồi thành công.

## 5. Bước tiếp theo: restore-test sau khi được duyệt

Đã chuẩn bị [đề xuất target và tiêu chí restore-test](experience-restore-test-phase5e.md). Đề xuất một **instance MySQL 8.0.30 riêng**, `127.0.0.1:63317`, database `aurel_experience_restore_20261025_057fe2d6`, runtime private `C:\Users\ASUS\AppData\Local\AUREL\restore-tests\experience-phase5e-20261025-057fe2d6`.

Chưa tạo runtime/database, khởi động MySQL, tạo account hoặc nạp dữ liệu. Không dùng credential Admin/main hoặc account RO để ghi. Port không có listener tại lúc lập đề xuất, sẽ phải kiểm tra lại trước khi khởi tạo. Bản backup và target ghi này cần chủ dự án phê duyệt trước khi thực hiện theo runbook.

- **DONE:** backup thực tế + kiểm chứng dung lượng/hash/encoding/table inventory/catalog counts/ảnh/code/ACL.
- **PARTIAL:** tính phục hồi trên MySQL thực và URL ảnh môi trường phục hồi chưa kiểm chứng.
- **BLOCKED:** restore-test chưa có target/tài khoản hoặc quyền khởi tạo instance riêng được phê duyệt; không dùng main để thử.
- **Tiến độ module: ước tính 87%**, tăng từ 85% nhờ hoàn thành bản sao SQL/ảnh có kiểm chứng. Cơ sở: 80 điểm kỹ thuật + 5 điểm khảo sát RO + 2 điểm bước backup; phần restore-test, sửa dữ liệu và phê duyệt triển khai vẫn còn.
- **Bước tiếp theo:** chủ dự án duyệt gói backup có SHA trên và target restore-test cụ thể; sau đó mới nạp thử và đối chiếu. Không tự chạy migration hoặc cập nhật mapping sau backup.
