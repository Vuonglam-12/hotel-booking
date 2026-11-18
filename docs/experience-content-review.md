# AUREL — Bộ nội dung Trải nghiệm chờ duyệt

**Bản xuất chỉ nguồn Wikimedia Commons:** [ZIP 14 JPEG + 21 bản nháp và bảng ghi công](experience-commons-export.md), đã kiểm chứng ngày 25/10/2026. Gói nhiều nguồn của vòng 2 bên dưới vẫn giữ nguyên; ảnh Flickr ID 14 được bỏ riêng khỏi bản xuất Commons.

**Cập nhật vòng 2, 25/10/2026:** đã bổ sung **8 ảnh**, tổng **15 ảnh**; chuẩn hóa **21/21 danh mục**, rà soát **21/21 nội dung**; 14 hồ sơ đủ tài liệu để duyệt, 7 hồ sơ cần bổ sung. **[Báo cáo hiện tại](experience-content-review-round2.md)** · **[Mở bản duyệt đã cập nhật](C:/Users/ASUS/AppData/Local/AUREL/content-review/experiences-20261025-a1cebecd/review/index.html)**. Chưa nhập/upload/xuất bản; tiến độ module ước tính 87%.

Phần dưới lưu kết quả **vòng 1** để đối chiếu. Số lượng/hash gói cũ thuộc bản lưu `evidence/review-v1.zip`; gói duyệt tại đường dẫn chung đã được cập nhật theo báo cáo vòng 2.

Ngày: **25/10/2026**. Đã chuẩn bị **21 bản nháp và 7 ảnh JPEG**. Không cập nhật dữ liệu, upload vào ứng dụng hoặc xuất bản. Không thay đổi Admin/Customer, Header, Booking, Payment, Login hoặc Lịch trình AI.

**Mở [bản xem trước](C:/Users/ASUS/AppData/Local/AUREL/content-review/experiences-20261025-a1cebecd/review/index.html)** bằng Chrome. Có bộ lọc địa phương, tìm theo tên/ID, lọc tình trạng ảnh, nội dung đầy đủ, đối chiếu database và cửa sổ xem toàn ảnh kèm ghi công.

**[Gói duyệt ZIP](C:/Users/ASUS/AppData/Local/AUREL/content-review/experiences-20261025-a1cebecd/aurel-experiences-owner-review.zip)** có thể giải nén rồi mở `review/index.html`, không cần Laravel/MySQL. Gói chỉ chứa tài liệu duyệt và ảnh đã kiểm tra, không chứa credential, SQL, script tải ảnh hoặc ảnh nguồn đang bị tạm giữ.

## 1. Tiếp nhận và phạm vi an toàn

- ZIP nguồn: `C:\Users\ASUS\Downloads\Goi_noi_dung_Trai_nghiem_cho_duyet.zip`; **9.873 byte**, SHA-256 `f0e79f31dad6e88798de9f431a1eadca2ce7534a8414145eddca9ef3279d1d5a`.
- Giải nén vào `C:\Users\ASUS\AppData\Local\AUREL\content-review\experiences-20261025-a1cebecd\original`, ngoài Git/public. Có 5 file, không có ảnh đi kèm. Đã kiểm tra đường dẫn ZIP, trùng tên, symlink và giới hạn giải nén; giữ nguyên bản gốc.
- Thư mục làm việc giới hạn quyền cho chủ tài khoản Windows và SYSTEM. Ảnh tải gốc, bản nén và bằng chứng nguồn nằm riêng trong `images/source`, `images/optimized`, `evidence`.
- README và script trong ZIP được xem là dữ liệu tham khảo; **không thực thi script đi kèm**. Việc tải ảnh tuân theo yêu cầu của chủ dự án và kiểm tra nguồn độc lập.
- Đối chiếu catalog bằng credential RO đã duyệt. Guard trước truy vấn xác nhận `127.0.0.1:3306 / hotel_booking`, UUID `7880df8a-92cd-11f0-85c1-a841f442ddbd`, account `aurel_catalog_ro@127.0.0.1`, chỉ USAGE + SELECT, roles NONE; xác nhận lại UUID/account cuối connection. Snapshot **17:27:24 +07:00**, kết nối đã đóng.
- Chỉ SELECT/SHOW catalog cần thiết: 8 `location`, 47 `attraction`; không đọc khách hàng/giao dịch, `.env` hoặc credential Admin. Không chạy migration/seed/importer.

## 2. Đối chiếu 21 bản nháp

**PASS:** 21 ID duy nhất, tên khớp database và kế hoạch; địa phương đề xuất đều tồn tại, đúng mapping Phase 5C.1. JSON và CSV trong gói khớp từng trường. Không có ID thiếu/thừa hoặc địa phương tự tạo. [CSV đủ 21 tên/ID](validation/experience-content-review.csv) ghi rõ địa phương hiện tại, đích, danh mục gốc/đề xuất và tình trạng ảnh.

**Khác biệt với database đang dùng:** 21 liên kết cũ vẫn chưa được sửa. Đây là thay đổi đã nằm trong kế hoạch, không phải lỗi ID/tên của gói mới.

| ID | Địa phương đang lưu | Địa phương đúng theo kế hoạch |
|---|---|---|
| 1, 2, 3, 4, 5, 6, 9 | Hồ Chí Minh — 2 | Hà Nội — 1 |
| 10, 11, 12, 13, 14, 15, 18 | Đà Nẵng — 3 | Hồ Chí Minh — 2 |
| 19, 20, 21, 22, 23, 24, 27 | Phú Quốc — 4 | Đà Lạt — 7 |

Sáu legacy hotel 7/8/16/17/25/26 và 20 địa điểm Phú Quốc 28–47 không nằm trong gói; giữ nguyên.

**21 key danh mục trong gói không đúng contract hiện có** tại `app/Support/ExperienceCategory.php`. Chỉ đề xuất chuyển đổi trong bộ duyệt, chưa lưu vào hệ thống:

| Loại bản ghi đang có | Danh mục đề xuất | Số bản nháp |
|---|---|---|
| `attraction` | `tham-quan` — Tham quan | 9 |
| `restaurant` | `am-thuc` — Ẩm thực | 9 |
| `activity` | `dia-phuong` — Hoạt động địa phương | 3 |

Các key như `di_tich`, `bao_tang`, `mua_sam`, `thien_nhien` được đối chiếu theo loại bản ghi, không tạo danh mục mới. Đặc biệt ID 12 Chợ Bến Thành giữ `attraction`, đề xuất `tham-quan`; ID 9/18/27 đề xuất `dia-phuong`. Cần chủ dự án duyệt cách trình bày này.

**Nội dung cần bổ sung/xác minh:** cả 21 bản nháp thiếu nguồn xác minh thông tin địa điểm, địa chỉ, tọa độ và giá; không điền dữ liệu suy đoán. Nguồn ảnh không thay cho `experience_source_url`. Giữ các trường chưa biết trống trong hồ sơ, không dùng chúng để ghi đè dữ liệu cũ. ID 18 du thuyền và ID 27 hái dâu cần chốt đơn vị/vườn cụ thể; ID 24 cần xác nhận cơ sở Quán Gỗ (Ba Toa). Chủ dự án xác nhận nguồn/quyền sử dụng các đoạn văn trước khi công khai.

## 3. Ảnh và ghi công

Đã kiểm tra từng trang Commons, metadata API, tác giả, phiên bản giấy phép và cảnh báo nguồn. Lưu HTML, revision, thời điểm kiểm tra, SHA-1 nguồn và SHA-256 các bản ảnh trong thư mục riêng. Bảy file tải xuống khớp SHA-1 do Commons cung cấp.

| ID | Ảnh / tác giả theo nguồn | Giấy phép công bố | Bản JPEG |
|---|---|---|---|
| 2 | [Văn Miếu — Arjunullas](https://commons.wikimedia.org/wiki/File:Temple_of_Literature_Hanoi.jpg) | CC BY-SA 4.0 | 960×1280; 310.149 B |
| 3 | [Hồ Hoàn Kiếm — Vyacheslav Argenberg](https://commons.wikimedia.org/wiki/File:Hanoi,_Vietnam,_Hoan_Kiem_Lake.jpg) | CC BY 4.0; giữ ghi công © Vyacheslav Argenberg | 1280×853; 162.281 B |
| 9 | [Múa rối — Ian Sewell](https://commons.wikimedia.org/wiki/File:Thang_long_water_puppet.JPG) | CC BY-SA 3.0 | 600×399; 56.091 B |
| 10 | [Dinh Độc Lập — Trung geo](https://commons.wikimedia.org/wiki/File:Independence_Palace,_Ho_Chi_Minh_City.jpg) | CC BY-SA 4.0 | 1280×960; 199.150 B |
| 11 | [Bảo tàng Chứng tích Chiến tranh — Christophe95](https://commons.wikimedia.org/wiki/File:War_Remnants_Museum.jpg) | CC BY-SA 4.0 | 1280×960; 205.730 B |
| 12 | [Chợ Bến Thành — lightwrite](https://commons.wikimedia.org/wiki/File:Ben_Thanh_Market,_Saigon.jpg) | CC BY-SA 2.0; nguồn Flickr được ghi trên Commons | 1280×960; 170.653 B |
| 19 | [Langbiang — Tilamdong](https://commons.wikimedia.org/wiki/File:Langbiang_Mountain.JPG) | Public domain theo tuyên bố của tác giả trên trang nguồn; không gán nhãn CC0 | 1280×960; 199.303 B |

Tổng **1.303.357 byte**, mỗi ảnh dưới 500 KiB. JPEG progressive, cạnh dài tối đa 1280 px, không phóng lớn file, giữ hướng EXIF rồi bỏ metadata, không cắt ảnh hoặc tạo ảnh AI. Đã xem trực tiếp đủ 7 ảnh. Ảnh ID 9 năm 2005, kích thước nhỏ và watermark `IanAndWendy.com` được giữ nguyên; cần duyệt độ phù hợp hiện tại. Ảnh ID 3 minh họa hồ, không mô tả đầy đủ cả đền.

Các bản ảnh đi kèm tên ảnh, tác giả, URL nguồn, URL giấy phép và thông báo thay đổi trong preview, `photo-credits.json`, `ATTRIBUTIONS.md`, từng file `.license.txt`. Chuỗi ghi công dự kiến đều nằm trong giới hạn `image_source` 500 ký tự; caption trong giới hạn 200 ký tự. Khi dùng thực tế phải giữ phần ghi công/giấy phép và các liên kết này. Nghĩa vụ tương ứng xem tại [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/), [CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/), [3.0](https://creativecommons.org/licenses/by-sa/3.0/), [2.0](https://creativecommons.org/licenses/by-sa/2.0/). Đây là xác minh giấy phép được công bố tại nguồn, không tự xác nhận quyền độc lập ngoài những gì nguồn cung cấp.

**Không tải/dùng ảnh ID 20:** [trang Thung lũng Tình Yêu](https://commons.wikimedia.org/wiki/File:The_Valley_of_Love_in_Dalat,_Vietnam.jpg) cảnh báo ảnh chuyển từ Wikivoyage chưa được kiểm tra, thông tin có thể không đáng tin cậy. Không chỉ dựa vào nhãn CC BY-SA để chấp nhận ảnh.

**13 ID chưa được cung cấp nguồn ảnh:** 1, 4, 5, 6, 13, 14, 15, 18, 21, 22, 23, 24, 27. Cộng ID 20 bị tạm giữ: **14 địa điểm chưa có ảnh chuẩn bị**. Không tạo ảnh giả hoặc dùng ảnh không rõ nguồn để lấp chỗ trống.

## 4. Kiểm chứng và file tạo mới

- **PASS — 14 nhóm Chrome:** đủ 21 bản nháp và UTF-8; 7 JPEG đọc được; link nguồn/giấy phép từng ảnh; 3 địa phương mỗi nhóm 7 bản nháp; tình trạng ảnh 7/13/1; tìm tiếng Việt không dấu; empty/reset; đối chiếu nội dung; toàn ảnh/ghi công/Escape/focus; responsive 1440/768/390/320 px không tràn ngang; không lỗi JavaScript/CSP hoặc request ứng dụng/ra mạng từ preview.
- **PASS:** gói duyệt đọc được, CRC ZIP hợp lệ, đúng 20 file gồm 6 tài liệu + 7 JPEG + 7 file ghi công; SHA-256 đủ 7 ảnh khớp manifest. Không chứa script nguồn, credential hoặc ảnh ID 20.
- Gói duyệt: **1.331.441 byte**; SHA-256 `1a079d52d4e244786a687b0022bc7302a402a56b48afbfb53dd134447000c736`.
- Bằng chứng gọn: [JSON kiểm tra](validation/experience-content-review.json), [CSV đối chiếu](validation/experience-content-review.csv). Snapshot catalog, trang nguồn, ảnh gốc và 5 ảnh chụp màn hình chỉ nằm trong thư mục riêng.
- Công cụ mới: `tools/prepare-experience-review-images.php`, `tools/build-experience-content-review.py`, `tools/check-experience-content-preview.cjs`. Các công cụ này chỉ làm việc trên bộ duyệt riêng; không bootstrap Laravel, không có API/SQL cập nhật. Không sửa code ứng dụng hoặc chạy lại các test module đã PASS; không chạy full suite.

## 5. Trạng thái và bước tiếp theo

- **DONE:** giải nén an toàn; đối chiếu 21/21 bản nháp với catalog thật và kế hoạch; kiểm tra 8 nguồn; chuẩn bị 7 JPEG có ghi công; bản duyệt offline và 14 nhóm Chrome PASS.
- **PARTIAL:** 14 địa điểm chưa có ảnh dùng được; 21 bản nháp chưa có nguồn xác minh vận hành; danh mục chuyển đổi và tính phù hợp ảnh/nội dung cần chủ dự án duyệt.
- **BLOCKED:** ảnh ID 20 đang có cảnh báo nguồn; cập nhật/upload/xuất bản main chưa được duyệt và chưa thực hiện. Điều kiện schema/restore-test của kế hoạch tích hợp trước đó chưa được coi là hoàn thành trong nhiệm vụ này.
- **Tiến độ module Trải nghiệm: ước tính 87%, giữ nguyên mốc backup.** Cơ sở: 80 điểm kỹ thuật đã kiểm thử + 5 điểm khảo sát RO + 2 điểm backup kiểm chứng. Việc chuẩn bị bản nháp chưa thay thế duyệt nội dung, restore-test hoặc tích hợp main; tỷ lệ không phải phần trăm dữ liệu đã đúng/đã xuất bản.
- **Bước tiếp theo:** chủ dự án xem `review/index.html`, chốt từng ID/nội dung/địa phương, phương án danh mục và 7 ảnh cùng ghi công; cung cấp nguồn/ảnh còn thiếu hoặc chọn giữ bản nháp. Sau khi duyệt mới chuẩn bị thao tác Admin cụ thể và trình phạm vi ghi/upload riêng, kiểm tra lại điều kiện schema/backup theo runbook. Không tự áp dụng mapping, migration hoặc publish khi chỉ nhận xét bản duyệt.
