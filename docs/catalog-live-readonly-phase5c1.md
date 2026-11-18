# AUREL — Phase 5C.1: kết quả đối chiếu database thật

Ngày: **25/10/2026**. **DONE phần khảo sát READ-ONLY trong phạm vi được duyệt; NO-GO triển khai/công bố catalog hiện tại.** Chưa thực hiện Phase 5C.2.

Đã dùng đúng file RO do chủ dự án cung cấp, login-path `aurel_catalog_ro`. Không đọc `.env`, không dùng credential quản trị, không bootstrap Laravel, không gọi API ứng dụng, migration, seed hoặc importer. Không đọc row khách hàng, lịch trình, đặt phòng, thanh toán hoặc hóa đơn. Chỉ SELECT/SHOW catalog và metadata liên quan; không có SQL ghi dữ liệu. File mới/cập nhật chỉ là báo cáo và bằng chứng dưới `docs/`.

Bằng chứng: [JSON khảo sát thật](validation/catalog-live-readonly-phase5c1-2026-10-25.json), [đối chiếu đủ 47 dòng](validation/attraction-live-location-review-phase5c1.csv), [kiểm tra ảnh khách sạn](validation/hotel-image-readonly-review-phase5c1.csv). Đây là các quan sát tại thời điểm đọc, không phải backup snapshot nhất quán.

## 1. DONE / PARTIAL / BLOCKED

| Trạng thái | Kết quả |
|---|---|
| DONE | Target, UUID, identity, grants và role khớp trước khi đọc catalog; kiểm tra lại trong cùng connection ở cuối khảo sát |
| DONE | Đọc schema/cột/index/FK và ledger đúng ba migration; xác nhận cấu trúc catalog mới chưa áp dụng |
| DONE | Đếm chính xác 8 location, 47 attraction, 24 hotel, 142 hotel_image; lấy đủ row cần thiết, không bị giới hạn mẫu |
| DONE | Đối chiếu 47/47 tên + item_type với CSV nguồn, xác định 27 dòng lệch địa phương và 20 dòng khớp |
| DONE | Kiểm tra duplicate/orphan, trường thiếu, khoảng giá; kiểm tra file ảnh tham chiếu tại workspace bằng thao tác chỉ đọc |
| PARTIAL | 27 quan hệ lệch chưa được sửa/duyệt; mô tả, giá, giờ hoạt động, tọa độ và quyền ảnh chưa được xác minh nội dung thực tế |
| PARTIAL | HTTP/public URL, quyền upload runtime và Client/Admin trên schema mới chưa smoke-test; không chạy ứng dụng trên DB chính trong bước này |
| PARTIAL | Cẩm nang: xác nhận chưa có bảng guide_category/guide_post, chưa có row mới để đối chiếu draft/published/legacy_id; file Blog nguồn vẫn được bảo toàn |
| BLOCKED | Bật/công bố module trên database hiện tại: thiếu schema catalog, dữ liệu địa phương lệch và chưa đủ điều kiện nội dung |
| BLOCKED | Ghi dữ liệu/migration/restore trên database chính hoặc bản sao: cần phê duyệt riêng; hiện chỉ có quyền khảo sát RO |

## 2. Target và quyền thực tế

| Thuộc tính | Kết quả |
|---|---|
| Endpoint | `127.0.0.1:3306`, database `hotel_booking` |
| Server host / version | `QuocAnh` / **MySQL 8.0.30** |
| UUID | **`7880df8a-92cd-11f0-85c1-a841f442ddbd`**, khớp giá trị được chủ dự án xác nhận |
| CURRENT_USER | **`aurel_catalog_ro@127.0.0.1`** |
| USER | `aurel_catalog_ro@localhost`; tiêu chí account được đối chiếu bằng CURRENT_USER |
| Grants | Chỉ USAGE trên `*.*` và SELECT trên schema pattern đã escape `hotel\_booking`; không có GRANT OPTION |
| Privilege metadata | USER_PRIVILEGES chỉ USAGE; SCHEMA_PRIVILEGES chỉ SELECT, IS_GRANTABLE=NO; TABLE/COLUMN_PRIVILEGES không có cấp quyền riêng |
| Role | CURRENT_ROLE=NONE; mandatory_roles rỗng; applicable/enabled roles đều 0 |
| partial_revokes / FK checks | 0 / 1 |
| Server read_only | 0; tài khoản audit vẫn chỉ SELECT, không dựa vào biến toàn server để kết luận quyền |

Các MySQL client của lần khảo sát đã đóng. Không thử lệnh ghi để kiểm chứng bị từ chối. Không sao chép, giải mã hoặc in nội dung credential bằng script; chỉ MySQL client sử dụng file được cấp.

## 3. Schema và ba migration chưa áp dụng

Ledger có **69 migration**, nhưng **không có cả ba tên catalog** dưới đây. Bảng/cột thực tế cũng chưa có các thay đổi tương ứng, không thấy dấu hiệu áp dụng dở dang trong các object catalog đã kiểm tra.

| Migration | Ledger thực tế | Schema thiếu thực tế |
|---|---|---|
| `2026_10_23_000001_add_experience_catalog_metadata.php` | Chưa áp dụng | `location.code`; 10 cột attraction mới gồm slug, category/summary/duration/source, snapshot code, verified_by/at, published_at, archived_at; unique code/slug, index công bố; bảng `attraction_image` |
| `2026_10_23_000002_create_travel_guides.php` | Chưa áp dụng | Cả hai bảng `guide_category`, `guide_post` |
| `2026_10_23_000003_widen_guide_publication_datetime.php` | Chưa áp dụng | Chưa có `guide_post.published_at`; chỉ có thể nâng kiểu sau khi 000002 tạo bảng |

Điều kiện cấu trúc nền đã đạt: các bảng liên quan hiện là **InnoDB**; `location.id`, `hotel.id/location_id` là INT signed, `attraction.id` là BIGINT unsigned và `attraction.location_id` INT signed. Các kiểu này khớp yêu cầu FK của migration mới. FK nền attraction→location, hotel→location và hotel_image→hotel đều hiện diện. Chưa thấy xung đột tên object mới trong tập bảng/cột/index đã đối chiếu.

Các bảng nền dùng `utf8mb4_unicode_ci`; database mặc định `utf8mb4_0900_ai_ci`. Cấu hình kết nối trong source đặt `utf8mb4_unicode_ci`; cần kiểm tra kết quả tạo bảng thật trên clone, không tự đổi collation ở main. `location` thực tế không có code hoặc cột trạng thái hoạt động riêng.

Thứ tự **đề xuất để diễn tập trên bản sao được duyệt**: 000001 → 000002 → 000003, dùng tên file đầy đủ. Không bao gồm migration AI cùng tiền tố. Chưa chạy bất kỳ migration nào trên main. Không có kết luận PASS về DDL lock/thời gian chạy trên dữ liệu thật.

## 4. Địa phương, attraction và khách sạn

| ID location thật | Tên | Attraction hiện tại | Hotel | Hotel active |
|---:|---|---:|---:|---:|
| 1 | Hà Nội | 0 | 3 | 3 |
| 2 | Hồ Chí Minh | 9 | 5 | 5 |
| 3 | Đà Nẵng | 9 | 3 | 1 |
| 4 | Phú Quốc | 29 | 2 | 1 |
| 5 | Nha Trang | 0 | 2 | 1 |
| 6 | Hội An | 0 | 5 | 5 |
| 7 | Đà Lạt | 0 | 2 | 2 |
| 8 | Huế | 0 | 2 | 2 |
| **Tổng** | | **47** | **24** | **20** |

Cả bốn địa phương trong source đã tồn tại, mỗi tên/country có một ứng viên phù hợp. Không cần tạo thêm location. Key là định danh nội bộ, chưa gán vào DB vì cột code chưa tồn tại.

| Key nguồn | ID thật theo tên/country hiện có | ID attraction đang trỏ tới | Dòng attraction | Kết luận đối chiếu source |
|---|---:|---|---|---|
| `vn-ha-noi` | **1** | 2 — Hồ Chí Minh | **ID 1–9**, 9 dòng | **Lệch cả 9** |
| `vn-ho-chi-minh` | **2** | 3 — Đà Nẵng | **ID 10–18**, 9 dòng | **Lệch cả 9** |
| `vn-da-lat` | **7** | 4 — Phú Quốc | **ID 19–27**, 9 dòng | **Lệch cả 9** |
| `vn-phu-quoc` | **4** | 4 — Phú Quốc | **ID 28–47**, 20 dòng | **Khớp 20** |

Ví dụ: Lăng Chủ tịch Hồ Chí Minh (ID 1) đang gắn Hồ Chí Minh; Dinh Độc Lập (ID 10) gắn Đà Nẵng; Khu Du lịch Langbiang (ID 19) gắn Phú Quốc. Đây là **27 sai khớp với địa phương chuẩn trong source**, không phải kết quả xác minh độc lập toàn bộ địa điểm/hoạt động còn đang kinh doanh.

Đã khớp đủ 47/47 tên + item_type, mỗi dòng có đúng một bản ghi thực tế. Không có dòng source thiếu hoặc bản ghi attraction thừa trong tập hiện tại. **20 dòng khớp, 27 dòng lệch**; 27 dòng lệch gồm **21 ứng viên Trải nghiệm** và 6 dòng legacy loại hotel. Toàn bộ 47 attraction đang `is_active=1`, khác với chính sách sample inactive của seeder mới.

Không phát hiện duplicate theo location/type/tên đã trim, hoặc cùng type/tên ở nhiều location; không có attraction/hotel mồ côi location hay hotel_image mồ côi hotel. Các kết quả này không thay thế kiểm tra trùng ngữ nghĩa/fuzzy hoặc đúng địa lý: FK đều hợp lệ trong khi 27 dòng vẫn sai khớp địa phương.

**Ảnh hưởng:** nếu công bố các dòng lệch, gợi ý khách sạn theo location_id sẽ lấy sai địa phương. Metadata FK còn cho thấy `itinerary_item.attraction_id` tham chiếu attraction và `customer.preferred_location_id` tham chiếu location; chỉ đọc định nghĩa FK, không đọc row hai bảng này. Vì vậy không đổi ID/đổi tên location để chữa hardcode, không xóa rồi tạo lại attraction. Việc sửa quan hệ cần được duyệt theo ID đang có.

Không chạy lại AttractionSeeder để sửa dữ liệu: ba nhóm mới sẽ resolve về ID 1/2/7, có thể thêm 27 row đúng địa phương trong khi 27 row cũ vẫn sai. Seeder hiện đã chặn database chính; không bỏ guard. Chín dòng hotel trong attraction vẫn là dữ liệu legacy dùng chung, không tự chuyển sang bảng hotel hoặc xóa.

## 5. Trường thiếu và ảnh

- **47/47 attraction có mô tả**, nhưng **47/47 thiếu địa chỉ và cặp lat/lng**. Chưa có bảng `attraction_image` hay metadata công bố để xác minh gallery. Không dùng tọa độ tỉnh/thành thay cho địa điểm cụ thể.
- Có đủ khoảng giá min/max, không có khoảng min > max. **47/47 khoảng giá khớp dữ liệu nguồn 5B**, nhưng toàn bộ vẫn là giá mẫu chưa được xác minh; **8 dòng `0–0` không chứng minh miễn phí**. Không đưa giá này vào Client Trải nghiệm.
- Chưa có slug, category trải nghiệm, URL nguồn, thời lượng phút, verified/published/archive. Không suy `is_active=1` là đã được duyệt công bố. Migration additive không tự xác minh hoặc xuất bản các dòng cũ; cần tiếp tục giữ các gate công bố.
- `hotel_image` có **142 row**, không có orphan FK. Đối chiếu file ở workspace hiện tại: **122 ảnh hợp lệ** qua getimagesize, **20 tham chiếu thiếu file**, đều thuộc nhánh legacy `image/img_hotel/...`. Không xóa row/file.
- Cả **20 hotel active** đều có ảnh đọc được và đúng một ảnh chính đọc được. Bốn hotel inactive ID **9, 11, 13, 15** không có ảnh hợp lệ trong các tham chiếu hiện tại. Đây là mục bảo trì ảnh khách sạn riêng, không sửa module Hotel trong khảo sát này.
- Chín ảnh bìa Blog vẫn khớp hash 5B, quyền sử dụng chưa được duyệt. `guide_category/guide_post` chưa tồn tại nên chưa có trạng thái import/draft/published để xác minh tại main; không tự chạy importer.

Kiểm tra ảnh chỉ giải quyết đường dẫn local trong root `public` hoặc `storage/app/public` của workspace. Không fetch URL từ DB, không gọi HTTP/app, không thử upload, không đọc `.env` để xác định runtime override. Kết quả file hợp lệ chưa chứng minh quyền sử dụng hoặc URL hoạt động trên một deployment khác.

## 6. MySQL mode và timezone

SQL mode session/global cùng là:

```text
ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION
```

Session/global timezone đều **SYSTEM**; system timezone **SE Asia Standard Time**. Quan sát cùng query: `NOW() = 2026-10-25 12:12:01`, `UTC_TIMESTAMP() = 2026-10-25 05:12:01`, chênh **+07:00**. Source Laravel đặt timezone **UTC** và không khai báo timezone riêng trong connection MySQL đã đọc.

Đây là rủi ro tương thích cần diễn tập, chưa phải kết luận đang có lỗi lịch công bố: chưa có bảng/ngày công bố catalog mới trên main. Trên clone cần chốt timezone connection, kiểm tra TIMESTAMP Trải nghiệm và DATETIME Cẩm nang, ngày cũ/ngày tương lai và gate hiển thị. Không chạy SET timezone, đổi SQL mode hoặc sửa app trong 5C.1.

Một truy vấn duplicate của công cụ khảo sát đã bị ONLY_FULL_GROUP_BY từ chối; đã đổi cách SELECT bằng derived table rồi hoàn tất. Không nới SQL mode hoặc sửa code ứng dụng. Kết nối hoàn tất ghi nhận 30 truy vấn nghiệp vụ SELECT/SHOW cùng SELECT marker phục vụ phân tách output; JSON lưu SQL đã đọc, không chứa password. Không chạy full test suite hoặc kiểm thử ghi trên main.

## 7. Rủi ro và quyết định

| Mức độ | Phát hiện | Hệ quả / điều kiện xử lý |
|---|---|---|
| **P0 — chặn công bố** | 27/47 dòng lệch địa phương theo source, trong đó 21 mục thuộc loại Trải nghiệm | Cần duyệt bảng đối chiếu theo ID; giữ unpublished; không seed/đổi location để chữa |
| **P1 — chặn tích hợp runtime** | Cả ba migration chưa áp dụng; thiếu code/metadata/gallery và bảng Cẩm nang | Chuẩn bị backup/clone trước; không coi mã đã PASS synthetic là schema main đã sẵn sàng |
| **P1 — chất lượng nội dung** | Thiếu địa chỉ/tọa độ/ảnh catalog và nguồn xác minh; quyền ảnh Blog chưa duyệt | Checklist từng mục, không tự bịa dữ liệu hoặc publish hàng loạt |
| **P1 — cần diễn tập** | Timezone server +07:00 so với source app UTC | Kiểm tra và chốt quy ước connection trên clone trước migration/import |
| **P2 — ảnh legacy** | 20 hotel_image tham chiếu file không có tại workspace | Lập danh sách bảo trì riêng; 20 hotel active vẫn có ảnh hợp lệ, không thay dữ liệu Hotel hiện tại |
| **P2 — an toàn quan hệ** | Có incoming FK từ customer và itinerary_item | Bảo toàn location/attraction IDs; không đọc/sửa dữ liệu cá nhân hoặc lịch trình để xử lý catalog |

Điều kiện nền thuận lợi: RO guard đạt, types/engine/FK nền phù hợp, không thấy duplicate/orphan hoặc object catalog áp dụng dở dang, không có sai khác khoảng giá so với source. **Những điều này chưa cấp quyền ghi hoặc kết luận GO.**

## 8. Kế hoạch Phase 5C.2 — chỉ đề xuất, chưa thực hiện

1. Chủ dự án duyệt phạm vi bản sao, operator, quyền backup/restore riêng và danh sách 27 dòng cần xem xét. Giữ tài khoản audit chỉ SELECT, không nâng quyền tài khoản này.
2. Operator tạo backup DB và storage thật với mốc nhất quán, ledger/FK/index/charset/collation, file Blog và manifest SHA-256. Restore-test trên instance/storage/cache tách biệt, chặn email/webhook/giao dịch và credential tới production. Kiểm tra tham chiếu schema/DEFINER trong backup trước khi restore.
3. Trên clone đã được cấp quyền, diễn tập ba migration theo tên đầy đủ; đo DDL/lock, no-op repeat, xử lý lỗi dở dang và recovery. Không chạy migration AI hoặc full suite. Kiểm tra collation và timezone thật của clone.
4. Trình phương án sửa **ID 1–9: location 2→1; ID 10–18: 3→2; ID 19–27: 4→7**, chỉ áp dụng trên clone sau khi được duyệt. Giữ ID attraction/location, không tác động bảng hotel, không xóa/tạo lại dữ liệu hoặc dùng seed để sửa. Xác nhận tác động của thay đổi địa phương tới dữ liệu dùng chung trước khi đề nghị ghi main.
5. Duyệt gán bốn code ổn định cho location **1/2/7/4** trên clone, không tạo địa phương mới. Giữ thiếu ảnh/metadata là thiếu; không bulk verify/publish. Nhập chín bài bằng importer draft-only đã PASS, giữ nguyên bài đã được Admin xử lý nếu clone có dữ liệu đó.
6. Kiểm thử tập trung trên clone: public gates, legacy Blog URL, ảnh/storage thật, hotel active cùng địa phương, quyền Admin, lịch công bố và khôi phục. Bảo trì 20 ảnh legacy là đầu việc riêng cần phê duyệt.
7. Đánh giá kết quả clone, backup phục hồi được, người thực hiện và rollback ưu tiên giữ schema/dữ liệu. Xin quyền triển khai main và duyệt công bố riêng nếu đủ điều kiện; không tự tiếp tục sau khảo sát này.

## 9. Tiến độ và phạm vi file

**Phase 5C.1: DONE khảo sát RO được duyệt**, kèm giới hạn nội dung/HTTP/runtime nêu trên. **Module Trải nghiệm: ước tính 85%**, tăng từ 80% nhờ hoàn tất mốc đối chiếu dữ liệu thật. Cơ sở: bốn nhóm kỹ thuật giữ 80 điểm; nhóm dữ liệu/vận hành 20 điểm gồm bốn mốc 5 điểm, mới đạt mốc inventory RO, còn duyệt dữ liệu/nội dung, backup/clone rehearsal và phê duyệt release. Không phải tỷ lệ bản ghi đúng hoặc độ sẵn sàng production; **NO-GO hiện tại**.

Chỉ tạo/cập nhật: báo cáo này, liên kết cập nhật trong báo cáo 5C.1 cũ, JSON bằng chứng và hai CSV. **15 hash source/test/migration/seeder và chín ảnh Blog vẫn khớp 5B**; không sửa ứng dụng, migration, seed hoặc ảnh. Bước tiếp theo là chủ dự án xem xét báo cáo và quyết định phạm vi/quyền cho Phase 5C.2, không tự triển khai.
