# AUREL — Xuất ảnh Wikimedia Commons và nội dung chờ duyệt

Ngày **25/10/2026**. **DONE: 14 JPEG Commons + 21 bản nháp**, đặt tên `images/1.jpg`, `images/2.jpg`… theo đúng `attraction_id`. Không nhập database, upload vào ứng dụng hoặc xuất bản.

**[Mở ZIP ảnh và nội dung](C:/Users/ASUS/AppData/Local/AUREL/content-review/experiences-20261025-a1cebecd/exports/commons-20261025-3f7563ad-anh-va-noi-dung-cho-duyet.zip)** · **[Xem trước](C:/Users/ASUS/AppData/Local/AUREL/content-review/experiences-20261025-a1cebecd/exports/commons-20261025-3f7563ad/review/index.html)** · **[Danh sách nguồn/tác giả/giấy phép](C:/Users/ASUS/AppData/Local/AUREL/content-review/experiences-20261025-a1cebecd/exports/commons-20261025-3f7563ad/sources.csv)**.

Gói nằm ngoài Git/public, trong thư mục duyệt riêng. Giải nén ZIP rồi mở `review/index.html` bằng Chrome; không cần chạy Laravel/MySQL. Bộ duyệt vòng 2 có 15 ảnh vẫn giữ nguyên; gói mới chỉ nhận nguồn Commons theo yêu cầu lần này.

## Kết quả xuất

- **14 ảnh:** ID **1, 2, 3, 4, 5, 6, 9, 10, 11, 12, 13, 19, 20, 21**.
- Tất cả đã tải về máy và nén ở các lượt chuẩn bị trước. Lượt này xác minh lại Commons API: đủ 14 file gốc khớp SHA-1, SHA-256; revision trang và giấy phép không đổi; nguồn không có cảnh báo chưa xử lý trong hồ sơ đã duyệt kỹ thuật. Không tải hoặc nén lại file đã đạt yêu cầu.
- JPEG trong gói là bản sao nguyên byte của bản nén đã kiểm tra. Tổng **2.479.402 byte**; ảnh lớn nhất **310.149 byte**, mọi ảnh **dưới 500.000 byte**, đáp ứng cả cách tính KB thập phân.
- Tên file dùng ID không thêm số 0 phía trước. Mỗi ảnh có `ID.license.txt`; giữ nguồn, tác giả, phiên bản giấy phép và thông báo nén/đổi kích thước/bỏ EXIF. Ảnh ID 5 là tư liệu White House Photo Office/NARA: nguồn ghi PD tại Hoa Kỳ/PDM, giữ lưu ý phạm vi dùng và không ngụ ý bảo trợ AUREL, không tự đổi sang nhãn CC0.
- **21 bản nháp** giữ danh mục đã chuẩn hóa: 9 `tham-quan`, 9 `am-thuc`, 3 `dia-phuong`; mọi dòng vẫn `draft / needs_owner_approval`. Không sửa tên, mapping hoặc nội dung đã rà soát của bộ nguồn. Trong bản xuất, dòng không có ảnh Commons để `image_file=null`, không dùng đó làm chỉ thị xóa ảnh hệ thống.

## Bỏ qua và phần còn thiếu

| ID | Địa điểm | Lý do không kèm ảnh |
|---|---|---|
| 14 | Phở Lệ | Ảnh đã xác minh ở vòng 2 đến từ Flickr, nằm ngoài phạm vi gói Commons; không phải lỗi tải. File Flickr cũ vẫn giữ nguyên |
| 15 | Cơm tấm Ba Ghiền | Chưa có ảnh Commons đúng cơ sở với nguồn/quyền sử dụng xác minh |
| 18 | Ăn tối trên du thuyền sông Sài Gòn | Chưa chốt tàu/đơn vị/bến; không gán ảnh hãng khác |
| 22 | Lẩu gà lá é Tao Ngộ | Chưa chốt đúng cơ sở và nguồn ảnh phù hợp |
| 23 | Bánh căn Lệ | Chưa có ảnh Commons phù hợp; ảnh Flickr tìm được ghi All rights reserved nên không dùng |
| 24 | Lẩu bò Quán Gỗ (Ba Toa) | Chưa có ảnh xác minh đúng cơ sở và quyền sử dụng |
| 27 | Trải nghiệm hái dâu tây tại vườn | Chưa chốt tên vườn; không gán ảnh nông trại bất kỳ |

Không phát sinh lỗi tải mới vì 14 ảnh Commons đã có trên máy và đều vượt kiểm tra lại. Không đưa vào gói ảnh Thung lũng Tình Yêu cũ có cảnh báo nguồn, ảnh Phở Lệ ở Hong Kong hoặc ảnh All rights reserved. Ảnh ID 20 đang xuất là nguồn thay thế Diane Selwyn đã xác minh ở vòng 2.

Ngoài phần ảnh, các điểm chưa đủ cơ sở nội dung ở ID 6/18/22/24/27 vẫn giữ nguyên cảnh báo của [báo cáo vòng 2](experience-content-review-round2.md). Không coi bộ ảnh/nội dung chờ duyệt là đã sẵn sàng xuất bản tất cả 21 địa điểm.

## Thành phần và kiểm chứng

ZIP có **38 file**:

- `images/`: 14 JPEG + 14 file ghi công.
- `sources.csv`, `sources.json`, `ATTRIBUTIONS.md`: nguồn Commons, tác giả/ghi công, giấy phép, ngày ảnh, thay đổi và checksum.
- `content/experiences_21_draft.json`, `.csv`: đủ 21 bản nháp; địa phương/địa chỉ vẫn là dữ liệu tham khảo chờ duyệt.
- `review/index.html`, `README.md`, `skipped.csv`, `manifest.json`, `SHA256SUMS.txt`.

**PASS:** 14 ảnh khớp nguồn, tên file đúng ID, mọi JPEG dưới 500.000 byte, checksum đầu ra, CRC ZIP và đối chiếu lại byte của toàn bộ 38 entry. Hash và mtime của đủ **15 ảnh bản nén trước đó** không đổi, gồm cả ảnh Flickr được giữ ngoài gói. ZIP không chứa credential, SQL hoặc script thực thi.

**Chrome: 9 nhóm PASS** — đúng 21 tên/ID và trạng thái bản nháp, 14 ảnh đọc được, tên file theo ID, ghi công/giấy phép và 7 trạng thái thiếu ảnh, mở nội dung chi tiết, responsive 1440/768/390/320 px, không lỗi JS/CSP hoặc request ứng dụng/ra mạng từ preview. Không chạy lại test chức năng module hoặc full suite.

ZIP: **2.522.269 byte**. SHA-256:

`d487bfc006050aac5c3b09f7da8cbd768fad2f8f73b08c12bd9c4d6506448eb2`

Bằng chứng: [experience-commons-export.json](validation/experience-commons-export.json). Công cụ mới: `tools/export-experience-commons-review.py`, chỉ đọc bộ duyệt sẵn có và tạo bản xuất riêng, không bootstrap Laravel, network hoặc database. Thao tác xác minh Commons API và kiểm tra Chrome được chạy riêng; không đọc `.env` hoặc sử dụng credential MySQL.

- **DONE:** xuất bộ ảnh/nội dung và kiểm chứng đầy đủ.
- **PARTIAL:** 7 dòng không kèm ảnh Commons, có lý do trong `skipped.csv`; thông tin cơ sở còn thiếu giữ nguyên trạng thái chờ xác minh.
- **BLOCKED:** nhập/upload/xuất bản chưa được chủ dự án duyệt và chưa thực hiện.
- **Tiến độ module: ước tính 87%, giữ nguyên**, chưa thay đổi trạng thái tích hợp. Phạm vi ảnh Commons hiện đạt 14/21 (67%); bộ nhiều nguồn trước đó vẫn có 15 ảnh.
- **Bước tiếp theo:** chủ dự án duyệt ZIP; chốt sử dụng ảnh và nội dung theo từng ID, giữ bản nháp cho phần chưa đủ dữ liệu. Không tự nhập hệ thống sau khi xuất gói.
