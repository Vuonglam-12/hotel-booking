# AUREL — Phase 5C.1: đối chiếu READ-ONLY

**Cập nhật 25/10/2026:** đã nhận credential RO được duyệt và hoàn tất khảo sát database thật. Xem [báo cáo thực tế mới](catalog-live-readonly-phase5c1.md): 8 location, 47 attraction, 24 hotel; 27 dòng lệch địa phương theo source; ba migration catalog chưa áp dụng. **Chưa thực hiện 5C.2, NO-GO triển khai main.**

Phần dưới là **biên bản offline ngày 24/10/2026**, giữ để đối chiếu lịch sử; trạng thái thiếu credential và UNKNOWN ở biên bản này đã được thay bằng kết quả trong báo cáo mới.

Ngày khảo sát: **24/10/2026**. Kết quả: **hoàn thành phần offline; BLOCKED phần database thực tế**. Chưa đủ điều kiện kết luận tích hợp hoặc chuyển sang Phase 5C.2.

Đã đọc [báo cáo 5A](deployment-readiness-phase5a.md), [báo cáo 5B](catalog-data-safety-phase5b.md), migration/model và cấu hình đường dẫn ảnh liên quan. Chưa được cung cấp credential READ-ONLY riêng kèm phê duyệt trong phiên làm việc này. Theo ràng buộc của chủ dự án, **không kết nối `hotel_booking`**, không thử tài khoản mặc định/Laravel, không đọc `.env`, config cache chứa thông tin runtime hoặc kho credential. Không chạy Artisan, MySQL client, migration, seeder, importer, test runner hoặc thao tác ghi vào database.

Chỉ bổ sung báo cáo, bằng chứng offline và một file SQL SELECT chưa thực thi. Không sửa code ứng dụng, migration, ảnh, dữ liệu khách sạn hoặc giao dịch; không tự sang 5C.2.

## 1. DONE / PARTIAL / BLOCKED

| Trạng thái | Nội dung | Phạm vi bằng chứng |
|---|---|---|
| DONE | Đọc 5A/5B, kiểm tra yêu cầu schema, FK và thứ tự ba migration | File nguồn; không phải migration ledger thật |
| DONE | Đối chiếu hash với 5B | 11/11 file mã nguồn/test và 4/4 file được bảo toàn, gồm ba migration và seeder, đều khớp |
| DONE | Kiểm tra đường dẫn ảnh ở workspace bằng thao tác chỉ đọc | Junction `public/storage` trỏ đúng `storage/app/public`; chín ảnh Blog khớp hash 5B |
| DONE | Chuẩn bị truy vấn đối chiếu bổ sung và kế hoạch backup/restore-test | File SQL chỉ SELECT, chưa thực thi; chưa tạo backup hoặc restore |
| PARTIAL | Đối chiếu bốn key địa phương | Có ánh xạ mong đợi từ source; chưa biết ID/name/code thực tế |
| PARTIAL | Chất lượng 47 dòng seed và chín bài Blog | Dùng lại bằng chứng nguồn/synthetic 5B; không thay thế số liệu thực |
| PARTIAL | Kiểm tra storage | Xác nhận cấu trúc workspace; chưa có ảnh tham chiếu từ DB, cấu hình triển khai, kiểm tra HTTP hoặc quyền ghi của runtime |
| BLOCKED | Schema thực tế, migration đã/chưa áp dụng, duplicate/orphan, số lượng location/attraction/hotel/image | Thiếu credential RO riêng đã được phê duyệt |
| BLOCKED | MySQL version, SQL mode, timezone, collation, engine và quyền thực tế của tài khoản audit | Không mở kết nối khi chưa đủ điều kiện |
| BLOCKED | Chốt phạm vi Phase 5C.2 | Cần hoàn tất báo cáo RO thực tế và phê duyệt riêng cho bước tiếp theo |

## 2. Schema và migration: yêu cầu từ code, thực tế UNKNOWN

**Không có kết quả SELECT/SHOW từ database chính. Không xác định được migration nào chưa áp dụng; không suy từ việc có file migration hay thiếu thư mục upload.**

| Migration đầy đủ | Yêu cầu/thay đổi từ source | Ledger/schema thực tế |
|---|---|---|
| `2026_10_23_000001_add_experience_catalog_metadata.php` | Cần location/attraction nền; thêm `location.code` nullable unique, attraction slug/metadata công bố và `attraction_image` | **UNKNOWN — chưa truy vấn** |
| `2026_10_23_000002_create_travel_guides.php` | Cần location nền; tạo `guide_category`, `guide_post` với `legacy_id` unique và FK tới location/category | **UNKNOWN — chưa truy vấn** |
| `2026_10_23_000003_widen_guide_publication_datetime.php` | Phụ thuộc cột từ migration Cẩm nang; trên MySQL nâng `guide_post.published_at` từ TIMESTAMP sang DATETIME | **UNKNOWN — chưa truy vấn** |

Thứ tự dự kiến giữ nguyên **000001 → 000002 → 000003**. Không chạy migration cùng tiền tố `2026_10_23_000002_add_itinerary_trip_management.php`. Cần đối chiếu cả ledger và cấu trúc thật; migration 000003 có thể bỏ qua khi thiếu cột hoặc kiểu không phải TIMESTAMP, nên một dòng ledger không tự chứng minh kiểu cuối đúng.

| Bảng | Cấu trúc code yêu cầu để đối chiếu | Kết quả thực tế/khác biệt |
|---|---|---|
| `location` | ID INT signed; name/region/country; tọa độ nullable; code VARCHAR(80) nullable unique sau 000001. Chưa có cột `is_active`/`status` trong các migration/model đã đối chiếu | **Chưa xác minh** |
| `attraction` | ID BIGINT unsigned; location_id INT signed FK tới location; loại/name/description/address/giá ước tính/lat/lng/is_active. Sau 000001: slug VARCHAR(180) nullable unique, category/summary/duration/source, snapshot code, verified/published/archive | **Chưa xác minh** |
| `attraction_image` | ID/attraction_id BIGINT unsigned, FK cascade; image_url, is_primary, caption, source_note | **Chưa xác minh bảng có tồn tại** |
| `hotel` | ID/location_id INT signed; FK `fk_hotel_location`; name/address/status; code gợi ý chỉ lấy `status='active'` và cùng location_id | **Chưa xác minh** |
| `hotel_image` | ID/hotel_id INT signed, FK tới hotel; image_url/caption/is_primary | **Chưa xác minh** |
| `guide_category` | ID BIGINT unsigned; name/slug unique; is_active | **Chưa xác minh bảng có tồn tại** |
| `guide_post` | ID BIGINT unsigned; legacy_id nullable unique; slug unique; nội dung/ảnh/tác giả/trạng thái; category_id BIGINT unsigned, location_id INT signed nullable; published_at cuối cùng DATETIME trên MySQL; metadata import riêng tư | **Chưa xác minh bảng/cột/kiểu** |

Đối chiếu FK cần giữ nguyên ID dùng chung. Metadata incoming FK có thể đọc để biết quan hệ phụ thuộc mà không đọc row Booking/Payment/hóa đơn. Cần xác minh engine, signedness, index unique, charset/collation và schema drift trước khi đề xuất migration. Các hàm schema-ready của ứng dụng không thay thế việc kiểm tra đầy đủ kiểu cột/FK/index trên target.

**Runtime MySQL chính:** version, SQL mode, timezone session/global/system, collation và engine đều **UNKNOWN**. MySQL 8.0.30/strict PASS ở 5B thuộc instance synthetic đã shutdown, không dùng để xác nhận cấu hình `hotel_booking`. Timezone UTC trong cấu hình nguồn ứng dụng chưa chứng minh timezone connection thực tế.

## 3. Địa phương, địa điểm và khách sạn

| Key nguồn | Tên chuẩn nguồn | Dòng seed mong đợi | ID/code/số địa điểm và khách sạn thực tế |
|---|---|---:|---|
| `vn-ha-noi` | Hà Nội | 9 | **UNKNOWN** |
| `vn-ho-chi-minh` | Hồ Chí Minh | 9 | **UNKNOWN** |
| `vn-da-lat` | Đà Lạt | 9 | **UNKNOWN** |
| `vn-phu-quoc` | Phú Quốc | 20 | **UNKNOWN** |

Source dùng key ổn định, ưu tiên code đã gán; fallback cần đúng một tên/country phù hợp. Không suy ID từ thứ tự seed hoặc ID fixture. Nếu dữ liệu thực dùng tên khác, không tự thêm alias, tạo địa phương hay gán code. Tên trùng hoặc FK hợp lệ chỉ là dữ kiện để người phụ trách đối chiếu địa lý.

**Số location, attraction, hotel, hotel active, image, duplicate và orphan trong database chính: chưa biết.** Không xác nhận production có đúng 47 attraction. Chín dòng `item_type=hotel` trong seed không đồng nghĩa chín row trong bảng hotel; 38 dòng còn lại chỉ là ứng viên theo loại.

Kết quả nguồn/synthetic 5B được giữ nguyên: 47 mô tả; thiếu 47 địa chỉ, 47 cặp tọa độ và 47 gallery; 47 khoảng giá mẫu chưa xác minh, gồm tám khoảng `0–0`. Không công bố là miễn phí, không tự điền thông tin. Chín ảnh Blog có file nhưng quyền sử dụng/tác giả chưa được duyệt. Bài 1 có nội dung, tám bài còn lại rỗng/thiếu; importer mới giữ draft và bảo toàn trạng thái các row đã tồn tại. **Trạng thái chín row trên production vẫn UNKNOWN.**

Sau khi đủ điều kiện RO, đối chiếu riêng attraction và hotel theo location_id bằng các tập tổng hợp độc lập để tránh đếm nhân bản do JOIN. Kiểm tra cùng tên trong/cross địa phương, thiếu mô tả/địa chỉ/tọa độ, khoảng giá bất thường, trạng thái công bố, row ảnh thiếu file/nguồn, ảnh mồ côi hoặc nhiều ảnh chính. Không tự sửa bất kỳ kết quả nào.

## 4. Cấu trúc ảnh đã xác nhận tại workspace

| Đường dẫn | Kết quả chỉ đọc |
|---|---|
| `public/storage` | Junction tới `C:\laragon\www\Hotel-Booking\storage\app\public`, khớp root public disk mặc định trong `config/filesystems.php` |
| `storage/app/public/accommodation` | Có; cấp trực tiếp chứa `hotel_image` và `hotel_room_type_image` |
| `storage/app/public/accommodation/hotel_image` | Có 20 thư mục con trực tiếp; **không dùng con số này làm số khách sạn hoặc số ảnh trong DB** |
| `storage/app/public/accommodation/attraction_image` | Chưa tồn tại ở workspace |
| `storage/app/public/guides` | Chưa tồn tại ở workspace |
| `public/image/L-1.jpg` … `L-9.jpg`, riêng bài 5 là `L5.jpg` | 9/9 file khớp SHA-256 5B; không sửa/xóa |

Code upload Trải nghiệm dùng `accommodation/attraction_image/{id}/...`, Cẩm nang dùng `guides/{id}/...` trên public disk. Thư mục được dùng khi có upload; thiếu thư mục hiện tại chưa chứng minh lỗi upload hoặc thiếu migration. Bài nhập cũ dùng `image/L*.jpg`, không bắt buộc nằm trong `guides`.

Không duyệt đệ quy storage hoặc mở dữ liệu thanh toán/tài khoản; chỉ xem tên thư mục ở các cấp liên quan. Chưa kiểm tra quyền ghi bằng file probe, chưa chạy `storage:link`, chưa fetch ảnh/API hay kiểm tra URL production. Chưa đối chiếu ảnh từ DB vì không có kết nối RO. Cấu hình runtime có thể khác cấu hình mặc định; không đọc `.env`/config cache để suy ra.

## 5. Vấn đề và mức độ ảnh hưởng

| Mức độ | Vấn đề/rủi ro | Xác nhận/phạm vi | Hành động tiếp theo |
|---|---|---|---|
| Chặn P0 | Chưa có credential RO riêng được cấp phép | Xác nhận thiếu thông tin truy cập được cung cấp cho phiên này; chưa kết nối | Chủ dự án cấp thông tin target và vị trí credential an toàn/phê duyệt; không thay bằng tài khoản Laravel |
| Cao P1 | Ledger/schema drift hoặc kiểu/FK/index khác code | **Rủi ro chưa xác minh trên DB thật** | Đọc identity/grants rồi schema/ledger; lập diff, không chạy migration thử |
| Cao P1 | Quan hệ địa phương cũ có thể sai dù FK hợp lệ | **Rủi ro từ lịch sử hardcode; chưa xác nhận row sai thực tế** | Đối chiếu ID thật, code/name/country, nguồn địa lý; trình từng quyết định |
| Cao P1 | Ảnh/nội dung thiếu chứng cứ quyền sử dụng, bài 1 chờ duyệt | Xác nhận ở kiểm kê nguồn; trạng thái production chưa đọc | Người có thẩm quyền duyệt quyền ảnh, tác giả và nội dung trước công bố |
| Cao P1 | Importer cũ có thể đã công bố bài 1; importer mới không tự hạ trạng thái | **Trạng thái thực tế chưa biết** | Chỉ đọc chín legacy row; báo người phụ trách quyết định riêng |
| Vừa P2 | Thiếu ảnh, địa chỉ, tọa độ; giá mẫu chưa xác minh | Xác nhận 47 dòng nguồn/synthetic, không suy ra 47 row production | Lập checklist theo ID thật sau khi được đọc, giữ thông tin thiếu |
| Vừa P2 | Chưa có trạng thái hoạt động riêng cho location trong source | Xác nhận từ cấu trúc nguồn; schema thật chưa đọc | Nếu cần nghiệp vụ này, chốt yêu cầu và ảnh hưởng dữ liệu dùng chung ở giai đoạn sau |
| Thông tin | Chưa có thư mục upload catalog tại workspace | Xác nhận filesystem; chưa phải lỗi hệ thống | Kiểm tra liên kết DB–file và quyền runtime trên môi trường được phép |

Chưa có kết luận về duplicate, orphan, sai location_id hoặc lỗi dữ liệu trên database chính. Các mục ghi rủi ro không phải kết quả truy vấn production.

## 6. Truy vấn chỉ đọc đã chuẩn bị, chưa chạy

- [catalog-readonly-schema.sql](deployment/catalog-readonly-schema.sql): identity, grants, version/mode/timezone, bảng/cột/index/FK và ledger đúng ba migration.
- [catalog-readonly-location-review.sql](deployment/catalog-readonly-location-review.sql): phân nhóm địa điểm, ứng viên địa phương theo tên, duplicate và trang dữ liệu nền.
- [catalog-readonly-publication-review.sql](deployment/catalog-readonly-publication-review.sql): metadata công bố, ảnh, code/slug trùng và chín bài cũ; chỉ dùng khi đủ bảng/cột.
- **Mới:** [catalog-readonly-relations-review.sql](deployment/catalog-readonly-relations-review.sql): exact counts, quan hệ khách sạn/địa điểm theo từng location, orphan, trùng tên, tọa độ/giá và trang đường dẫn ảnh. Gồm 11 block SELECT, tách phần cần code/bảng ảnh mới.

Không chạy cả file tự động. Đầu tiên cần credential RO riêng được phê duyệt, host/port/schema và phạm vi đọc rõ ràng. Dùng connection đó để kiểm tra identity và quyền hiệu lực, kể cả role nếu có; nếu thấy quyền ghi hoặc target không khớp thì dừng. Chỉ chạy từng block sau khi bảng/cột cần thiết đã được xác nhận. Không coi `@@read_only` của server là bằng chứng duy nhất về quyền tài khoản. Không lấy password qua chat/báo cáo hoặc `.env`.

Các truy vấn tổng hợp có thể đọc nhiều row; cần thống nhất thời điểm/giới hạn khảo sát với operator sau khi biết kích thước bảng. Các trang chi tiết giới hạn 500 row theo cursor. COUNT là số đếm tại thời điểm đọc, không dùng `information_schema.TABLE_ROWS` làm số chính xác và không coi nhiều SELECT rời là một bản backup nhất quán. Static check chỉ xác nhận các file chứa SELECT/SHOW, không chứng minh cú pháp/cột chạy được trên database chưa được phép kết nối.

## 7. Kế hoạch backup và restore-test — chỉ lập kế hoạch

1. **Chủ sở hữu và target:** operator xác nhận instance/version/schema/storage thực, cửa sổ backup, nơi lưu được duyệt, người truy cập và thời gian phục hồi mục tiêu. Tài khoản audit RO không mặc nhiên có quyền backup mọi object hoặc quyền restore. Chưa thực hiện dump/backup trong 5C.1.
2. **Phạm vi DB:** backup schema/data nhất quán cùng migration ledger, FK/index, charset/collation và object phụ thuộc thực tế. Giữ nguyên ID dùng chung. Cách backup phải được operator chọn theo engine/version và hoạt động hệ thống sau khi có khảo sát RO; không mặc định chỉ copy hai bảng catalog là đủ.
3. **Phạm vi file:** sao lưu file thật phía sau `public/storage`, thư mục accommodation/guides đang có, ảnh khách sạn liên quan, chín ảnh Blog đúng tên, các file nội dung gốc và metadata/catalog nguồn. Ghi mốc DB–storage, dung lượng, đường dẫn và manifest SHA-256; không chỉ sao lưu junction và không xóa ảnh cũ.
4. **Restore-test cần phê duyệt riêng:** bản sao trên instance/database/storage tách biệt, không có credential hay kết nối tới production. Kiểm tra schema-qualified name, USE/DEFINER và object trong bản backup trước khi restore; không sửa file backup gốc. Runtime chặn email, webhook, giao dịch ngoài và tách cache/session. Không nới guard test hiện có để trỏ sang database chính.
5. **Đối chiếu bản sao:** so row count/ID/FK/index/ảnh/checksum trước mọi thay đổi; sau đó mới diễn tập đúng ba migration theo tên đầy đủ nếu được giao quyền. Kiểm tra no-op repeat, schema lỗi dở dang, timezone/ngày, import draft và bảo toàn bài Admin đã xử lý. Đo thời gian DDL/recovery. Không kéo migration AI hoặc full suite vào phạm vi.
6. **Tiêu chí đạt:** backup phục hồi được, dữ liệu/ảnh/quan hệ khớp, trạng thái công bố an toàn, URL storage hoạt động trên bản sao và thời gian phục hồi được chấp thuận. Ưu tiên kế hoạch giữ schema/dữ liệu catalog khi tạm ngừng phục vụ; rollback 000001/000002 làm mất dữ liệu mới nên không chọn làm mặc định. Không restore toàn DB production để xử lý lỗi catalog khi có giao dịch mới.

## 8. Điều kiện trước Phase 5C.2 và bước tiếp theo

1. Chủ dự án cung cấp/phê duyệt **credential RO riêng** qua cơ chế an toàn, cùng host/port/database và phạm vi bảng; không dùng tài khoản Laravel có quyền ghi. Nếu chỉ cung cấp bản xuất được duyệt, có thể tiếp tục phân tích bản xuất nhưng không coi đó là kiểm tra kết nối/runtime live.
2. Hoàn tất phần RO còn thiếu của **5C.1**: identity/grants, schema/ledger thực tế, exact counts, đối chiếu bốn key, duplicate/orphan, ảnh và chín bài; lập danh sách khác biệt kèm ID thật/mức độ.
3. Chủ dự án chốt phạm vi 5C.2 sau khi xem kết quả RO: dữ liệu cần duyệt, backup/storage, instance bản sao và người vận hành. Nếu bước đó cần ghi bản sao thì phải có quyền riêng cho đúng target; không tự suy quyền ghi `hotel_booking`.
4. Chỉ đề xuất restore rehearsal/migration trên bản sao sau khi backup được phê duyệt. Phê duyệt xuất bản nội dung và phê duyệt triển khai chính thức là các quyết định riêng.

**Tiến độ Trải nghiệm giữ ở ước tính 80%**, chưa tăng so với 5B: bốn trong năm nhóm có trọng số quản lý bằng nhau đã có bằng chứng kỹ thuật (Client, Admin, API/schema/liên kết, kiểm thử/an toàn); nhóm dữ liệu thực/vận hành còn chặn. Đây không phải tỷ lệ bản ghi đã xác minh hoặc độ sẵn sàng production.

**Bước tiếp theo được đề xuất:** bổ sung điều kiện truy cập RO rồi hoàn tất chính Phase 5C.1. Chưa chuyển 5C.2, chưa triển khai production. Bằng chứng offline: [catalog-readonly-phase5c1.json](validation/catalog-readonly-phase5c1.json).
