# Bổ sung dữ liệu loại phòng — 31/10/2026

**Trạng thái mới nhất 04/11/2026:** đã bổ sung 702 phòng quản lý và điền 104 giá cơ bản có nguồn theo quyết định tiếp nối của người dùng. Báo cáo dưới đây là lịch sử đợt danh mục ban đầu. [Xem số liệu hiện tại, nguồn giá và các mục còn thiếu](room-admin-update-2026-11-04.md).

Đợt bổ sung danh mục ban đầu đã cập nhật database DEMO: **40 khách sạn, 128 → 362 bản ghi danh mục**, thêm **234** hạng, **88** lượt bổ sung trường trống, gắn **109** ảnh gốc và lưu **330** bản ghi nguồn.

**Cập nhật 01/11/2026 theo yêu cầu mới:** 362 loại chưa archived đã active; điền 133 sức chứa còn trống (4 từ nguồn, 129 DEMO), bổ sung tiện ích và 7 giá bình quân tham khảo đúng kỳ 15–17/11 từ tổng giá thật. Các cột thiếu dưới đây mô tả độ đầy đủ nguồn thực tế của đợt ban đầu, không đồng nghĩa ô dữ liệu DEMO hiện tại còn trống. [Báo cáo hiện tại](room-catalog-update-2026-11-01.md).

**Lượt bổ sung ảnh tiếp theo đã hoàn tất:** gắn thêm **125 ảnh**, hiện **362/362 loại phòng chưa archived** có ít nhất một ảnh. Lưu thêm 125 bản ghi nguồn ảnh (tổng 455 bản ghi bằng chứng). Không còn thiếu ảnh; các phần dữ liệu còn thiếu khác vẫn giữ nguyên. [Bảng ảnh và nguồn từng khách sạn](room-type-photos-2026-10-31.md).

Số trước/sau đếm `hotel_room_type` chưa archived (bao gồm inactive và các tên cũ chưa khớp nguồn), không phải số hạng thực tế duy nhất đã xác minh. Các hạng cũ trùng/chung không bị xóa hoặc gộp ID. 324 hạng nguồn được chọn để đối chiếu; nhiều tên cũ có thể cùng ánh xạ về một hạng nguồn.

**Phòng vật lý giữ nguyên 417 bản ghi `room`, trong đó 332 chưa `catalog_retired`.** Đây là số bản ghi dự án, không xác nhận tồn kho thực tế hay lượng còn bán cho một ngày lưu trú. Không tăng phòng, không ghi giá hay tạo khách sạn.

Tại thời điểm hoàn tất đợt danh mục ban đầu, tất cả 234 loại mới: `inactive`, giá `NULL`, không có `room` và không có dòng `hotel_room_rate`. Giá/cấu hình DEMO cũ giữ nguyên. Lúc đó chưa tìm được báo giá có ngày lưu trú và đầy đủ điều kiện đủ chắc để đưa vào bảng giá; các mức “từ…”, gói ăn sáng/hủy và giá quảng bá đều không được nhập.

## Bảng kết quả thực tế

Tất cả còn thiếu **giá theo ngày/điều kiện** và **dữ liệu phân bổ phòng vật lý theo hạng**. Cột dưới ghi thêm các phần còn thiếu ở ít nhất một hạng đã rà; không có nghĩa mọi hạng tại khách sạn đều thiếu trường đó. URL/ngày kiểm tra được lưu riêng từng hạng trong database và manifest.

| Khách sạn | Loại trước/sau | Phòng vật lý | Nguồn | Thiếu thêm |
|---|---:|---:|---|---|
| MEANDER Sai Gon (ID 1) | 5 → 10 | 16 | [Nguồn](https://staymeander.com/meandersaigon/en/rooms) | sức chứa, đối chiếu tên/dữ liệu |
| The Hanoi Signature Hotel (ID 2) | 3 → 9 | 16 | [Nguồn](https://thehanoisignaturehotel.com/rooms) | sức chứa, giường, đối chiếu tên/dữ liệu |
| La Siesta Classic Ma May (ID 3) | 4 → 10 | 16 | [Nguồn](https://lasiestahotels.com/mamay/rooms) | giường, tiện ích riêng |
| Hanoian Central Hotel & Spa (ID 4) | 3 → 7 | 16 | [Nguồn](https://www.hanoiancentralhotel.com/) | đối chiếu tên/dữ liệu |
| Hotel Majestic Saigon (ID 5) | 3 → 7 | 16 | [Nguồn](https://www.majesticsaigon.com/our-deluxes-suites/) | diện tích |
| La Siesta Premium Saigon Central (ID 6) | 4 → 8 | 16 | [Nguồn](https://lasiestahotels.com/saigoncentral/rooms-suites) | giường, tiện ích riêng |
| La Siesta Premium Saigon (ID 7) | 3 → 7 | 16 | [Nguồn](https://lasiestahotels.com/saigon/rooms-suites) | tiện ích riêng |
| Hotel Grand Saigon (ID 8) | 4 → 9 | 16 | [Nguồn](https://www.hotelgrandsaigon.com/rooms-suites/) | sức chứa, giường, diện tích, tiện ích riêng |
| InterContinental Danang Sun Peninsula (ID 9) | 5 → 15 | 16 | [Nguồn](https://www.danang.intercontinental.com/accommodation/) | đối chiếu tên/dữ liệu |
| Pullman Danang Beach Resort (ID 10) | 3 → 10 | 16 | [Nguồn](https://www.pullman-danang.com/room/) | sức chứa, diện tích |
| Brilliant Hotel Danang (ID 11) | 5 → 15 | 16 | [Nguồn](https://brillianthotel.vn/en/rooms/) | sức chứa, giường, diện tích, đối chiếu tên/dữ liệu |
| WorldHotels Long Beach Resort Phu Quoc (ID 12) | 2 → 12 | 16 | [Nguồn](https://worldhotelslongbeach.com/room/) | đối chiếu tên/dữ liệu |
| Vinpearl Resort & Spa Phú Quốc (ID 13) | 5 → 12 | 16 | [Nguồn](https://vinpearl.com/en/hotels/vinpearl-resort-spa-phu-quoc/rooms) | giường, tiện ích riêng, đối chiếu tên/dữ liệu |
| Brilliant Bay Nha Trang Hotel (ID 14) | 1 → 6 | 18 | [Nguồn](https://brilliantbaynhatrang.com/) | sức chứa, giường, tiện ích riêng, đối chiếu tên/dữ liệu |
| Sunrise Nha Trang Beach Hotel (ID 15) | 5 → 10 | 16 | [Nguồn](https://sunrisenhatrang.com.vn/rooms-suites/) | sức chứa, giường, diện tích, tiện ích riêng, đối chiếu tên/dữ liệu |
| Four Seasons Resort The Nam Hai (ID 16) | 3 → 11 | 16 | [Nguồn](https://www.fourseasons.com/hoian/accommodations/) | tiện ích riêng, đối chiếu tên/dữ liệu |
| Anantara Hoi An Resort (ID 17) | 4 → 6 | 16 | [Nguồn](https://www.anantara.com/en/hoi-an/rooms) | giường, tiện ích riêng |
| Dalat Palace Heritage Hotel (ID 18) | 5 → 11 | 16 | [Nguồn](https://www.dalatpalacehotel.com/en/roomssuites/) | sức chứa, giường, diện tích, tiện ích riêng, đối chiếu tên/dữ liệu |
| Khách sạn Le Récit Boutique Đà Lạt (Le Recit Boutique Hotel de Dalat) (ID 19) | 3 → 4 | 16 | [Nguồn](https://www.booking.com/hotel/vn/le-recit-boutique-de-dalat.fr.html) | sức chứa, tiện ích riêng, đối chiếu tên/dữ liệu |
| Azerai La Residence Hue (ID 20) | 3 → 11 | 17 | [Nguồn](https://azerai.com/azerai-la-residence-hue/accommodations/) | tiện ích riêng, đối chiếu tên/dữ liệu |
| Pilgrimage Village Boutique Resort (ID 21) | 2 → 9 | 16 | [Nguồn](https://pilgrimagevillage.com/home) | — |
| The Pearl Hoi An (ID 43) | 3 → 10 | 10 | [Nguồn](https://thepearlhoian.vn/en/hotel) | giường, tiện ích riêng, đối chiếu tên/dữ liệu |
| NHAAN Resort & Spa Hội An, Tapestry Collection by Hilton (ID 44) | 2 → 10 | 4 | [Nguồn](https://www.booking.com/hotel/vn/nhaan-resort-spa-hoi-an-tapestry-collection-by-hilton.html) | sức chứa, diện tích, tiện ích riêng, đối chiếu tên/dữ liệu |
| Vinpearl Resort & Golf Nam Hoi An (ID 45) | 2 → 8 | 8 | [Nguồn](https://vinpearl.com/en/hotels/vinpearl-resort-golf-nam-hoi-an/rooms) | giường, diện tích, tiện ích riêng, đối chiếu tên/dữ liệu |
| Pan Pacific Hanoi (ID 46) | 3 → 10 | 3 | [Nguồn](https://www.panpacific.com/en/hotels-and-resorts/pp-hanoi/rooms.html) | sức chứa, giường |
| Silk Path Hotel Hanoi (ID 47) | 3 → 6 | 4 | [Nguồn](https://silkpathhotel.com/accommondationhanoi/) | sức chứa, giường, diện tích, tiện ích riêng |
| Ana Mandara Villas Dalat Resort & Spa (ID 48) | 3 → 7 | 3 | [Nguồn](https://anamandara-resort.com/rooms-suites/) | — |
| Terracotta Hotel & Resort Dalat (ID 49) | 3 → 8 | 4 | [Nguồn](https://dalat.terracottaresort.com/phong-nghi/khach-san-17/) | sức chứa, giường, diện tích, tiện ích riêng |
| Swiss-Belresort Tuyen Lam Dalat (ID 50) | 3 → 6 | 3 | [Nguồn](https://samtuyenlamhotel.com.vn/category-stay/accommodation/?lang=en) | sức chứa, giường, tiện ích riêng |
| Hyatt Regency Danang Resort and Spa (ID 51) | 3 → 10 | 4 | [Nguồn](https://www.hyatt.com/hyatt-regency/en-US/danhr-hyatt-regency-danang-resort-and-spa/rooms) | sức chứa, giường, tiện ích riêng |
| Novotel Danang Premier Han River (ID 52) | 3 → 10 | 3 | [Nguồn](https://all.accor.com/hotel/8287/index.en.shtml) | sức chứa, tiện ích riêng |
| Silk Path Grand Hue Hotel & Spa (ID 53) | 3 → 8 | 4 | [Nguồn](https://silkpathhotel.com/slphueaccommonation/) | sức chứa, tiện ích riêng |
| Meliá Vinpearl Hue (ID 54) | 3 → 7 | 3 | [Nguồn](https://www.expedia.com/Hue-Hotels-Melia-Vinpearl-Hue.h29267758.Hotel-Information) | sức chứa, giường, diện tích, tiện ích riêng, đối chiếu tên/dữ liệu |
| Senna Hue Hotel (ID 55) | 3 → 9 | 4 | [Nguồn](https://sennahue.com/) | sức chứa, giường, diện tích, tiện ích riêng |
| InterContinental Nha Trang (ID 56) | 3 → 7 | 3 | [Nguồn](https://nhatrang.intercontinental.com/rooms/) | sức chứa, giường, diện tích, tiện ích riêng, đối chiếu tên/dữ liệu |
| Amiana Resort Nha Trang (ID 57) | 3 → 10 | 4 | [Nguồn](https://amianaresortsandhotels.com/amianaresortnhatrang/) | sức chứa, giường, tiện ích riêng |
| Sheraton Nha Trang Hotel & Spa (ID 58) | 2 → 9 | 3 | [Nguồn](https://www.marriott.com/en-us/hotels/nhasi-sheraton-nha-trang-hotel-and-spa/rooms/premium-rooms/) | sức chứa, giường, diện tích, tiện ích riêng |
| Salinda Resort Phu Quoc Island (ID 59) | 3 → 8 | 4 | [Nguồn](https://salindaresort.com/accommodation/) | tiện ích riêng |
| InterContinental Phu Quoc Long Beach Resort (ID 60) | 3 → 10 | 3 | [Nguồn](https://phuquoc.intercontinental.com/resort-collection/) | sức chứa, giường, đối chiếu tên/dữ liệu |
| Regent Phu Quoc (ID 61) | 2 → 10 | 4 | [Nguồn](https://www.ihg.com/regent/hotels/us/en/phu-quoc/pqcrr/hoteldetail/rooms-and-suites) | sức chứa, tiện ích riêng |

## Phần giữ lại hoặc chưa nhập

- **ID 1:** Hai bản ghi phòng tập thể nam nữ cũ cùng tên được giữ nguyên ID; chỉ xác minh 1 hạng Mixed Dorm.
- **ID 2:** Rooms và Rates trên website có sức chứa khác nhau; không ghi đè số cũ. Hai tên Superior cũ cần đối chiếu/gộp thủ công có xem booking.
- **ID 3:** Chọn 10 hạng có nguồn; nguồn còn Connecting Room và Club Duplex Premier ngoài phạm vi mục tiêu.
- **ID 4:** Executive Suite cũ chưa khớp danh mục chính thức hiện tại; giữ nguyên.
- **ID 9:** 5 hạng cũ Standard/Superior/Deluxe/Suite/Family chưa khớp tên chính thức; giữ nguyên. Bổ sung 10 hạng đã xác minh.
- **ID 11:** 5 hạng cũ chưa khớp tên chính thức; giữ nguyên. Nguồn có nhãn giường/hướng nhìn bị hoán đổi và một số diện tích mâu thuẫn; các trường đó để trống.
- **ID 12:** 2 tên Standard/Superior Suite cũ chưa có đối chiếu đổi tên sang danh mục mới; không gộp hoặc sửa ID.
- **ID 13:** 5 hạng chung cũ chưa có đối chiếu chính xác; giữ nguyên. Nguồn xác minh 7 tên bổ sung gồm 2 villa ba phòng ngủ; không tách gói vui chơi/ăn uống.
- **ID 14:** Tên cũ “PHÒNG BA PHÒNG NGỦ...” không khớp 5 hạng trong website; giữ nguyên, cần kiểm tra thủ công.
- **ID 15:** 4 hạng chung cũ ngoài Deluxe chưa khớp tên chính thức; giữ nguyên.
- **ID 16:** Hai bản ghi Ocean View One Bedroom Villa cũ giữ nguyên ID; không tạo thêm hạng này.
- **ID 18:** Giữ Family và tên cũ “4,137,116” chưa có nguồn đối chiếu. Không tự thay chúng thành hạng khác.
- **ID 19:** Chỉ thêm Two-Bedroom Chalet đã xác minh Booking.com. Deluxe Suite cũ chưa thể gán chắc với King Suite; chưa thêm các tên Penthouse của nguồn khác để tránh trùng.
- **ID 20:** Hai Superior Queen/Twin cũ cùng thuộc nhóm Superior trong nguồn; giữ ID riêng để bảo toàn liên kết.
- **ID 21:** Deluxe đã archived được giữ archived và không tạo bản ghi thay thế.
- **ID 43:** Hai Superior King/Twin cũ được giữ ID riêng. Một số dòng căn hộ khác có diện tích/mô tả không nhất quán, chưa nhập.
- **ID 44:** 8 hạng King xác minh từ Booking.com. King Accessible và Twin Guest cũ chưa được crawl hiện tại xác minh lại, được giữ nguyên.
- **ID 45:** Nguồn đầy đủ có 8 hạng, gồm Executive Suite và 3 nhóm villa. Villa ba phòng ngủ hướng vườn có diện tích mô tả 349–393 m² nhưng badge 350 m²; giữ diện tích NULL.
- **ID 48:** Nguồn chỉ xác minh 7 hạng có tên cụ thể; mục “Villas” là nhóm nhiều căn, không thêm như một hạng thứ 8.
- **ID 50:** Đã kiểm tra cả trang 1 và trang 2 website cơ sở: 6 hạng; không bịa thêm 3–4 hạng.
- **ID 54:** Gộp các dòng chỉ khác số khách của cùng hạng. Không thêm Family/The Level khi chưa xác định được phòng vật lý khác với các hạng đã có.
- **ID 56:** Chỉ thêm 4 suite có bố trí riêng rõ ràng. Chưa thêm các biến thể Classic/Premium/Junior hướng nhìn/tầng cao vì tên cũ còn chung, chưa xác minh ánh xạ để tránh trùng.
- **ID 58:** Không tách Club chỉ theo quyền lounge.
- **ID 60:** 1 Bedroom Suite cũ chưa khớp chắc với Residence; không thêm nhóm Residence đa bố trí như một loại mới.
- **ID 61:** Đã lấy đúng 10 hạng suite/villa trên IHG.

Không tự chỉnh giá/sức chứa/giường đã có dù khác nguồn: xung đột được lưu trong `hotel_room_type_evidence.issues`. Danh mục bổ sung giữ khoảng diện tích bằng `area_min_m2`/`area_max_m2`, sức chứa kèm nguyên điều kiện người lớn/trẻ em trong bằng chứng. Một số ảnh chỉ thể hiện phòng tắm/ban công/sân hiên trong gallery đúng hạng; không dùng ảnh ngẫu nhiên hoặc ảnh sự kiện. Ảnh nguồn công khai chưa đồng nghĩa có giấy phép tái sử dụng; trạng thái quyền được ghi trong bằng chứng ảnh.

## Kiểm tra và giới hạn

- Hai đợt ghi đều có backup mới, đã phục hồi sang server MySQL cô lập và đối chiếu đủ 48 bảng/view ở đợt đầu, 49 bảng/view ở đợt bổ sung.
- Nhập trên bản phục hồi, chạy lại không đổi dữ liệu; kiểm tra từ chối sai chi nhánh, bản ghi lặp, giá/tồn giả và alias ngoài phạm vi. Ghi chính và chạy lại command đều đạt; lần chạy lại tạo 0 bản ghi.
- Hash toàn bộ các bảng nghiệp vụ ngoài phạm vi, mọi RoomType cũ, ảnh/link cũ và trường danh mục không trống giữ nguyên trong từng đợt ghi. Booking, booking_room, payment, bảng giá, hotel và room không bị importer sửa. Riêng bảng `personal_access_tokens` có trạng thái sử dụng do truy cập Admin và được tách khỏi điều kiện bằng nhau ở lần ghi bổ sung; importer không ghi bảng này.
- Đọc API Customer qua controller cho đủ 40 khách sạn, có ngày 15–17/11/2026: danh sách phòng, sức chứa, khả dụng và báo giá cũ khớp trước/sau từng đợt nhập. Admin đọc được đủ 362 bản ghi danh mục. Báo giá cho loại mới không có phòng bị từ chối 404.
- `RoomPricingTest`: **5 tests / 45 assertions PASS** trên MySQL riêng.
- Bộ `HotelScopedRoomTypeTest` cũ: **14 tests / 103 assertions, 3 errors, 2 failures** do fixture thiếu bảng `booking_demo` và lỗi schema tiện ích cũ. Bộ này chưa áp dụng migration/dữ liệu đợt này; không sửa logic đặt phòng/giao diện để làm xanh bộ test. Kiểm tra hồi quy trên bản phục hồi có schema thực tế được nêu ở trên.
- HTTP thật trên localhost:8000: **40/40 API phòng HTTP 200**, không đưa loại mới chưa có giá ra bán. Chi tiết `storage/app/private/room-type-enrichment-20261031/http-report.json`.
- Sau đợt đầu có thay đổi phát sinh ngoài importer: `hotel_room_type` ID 157 (Regent, RoomType 64) đổi giường, có dòng `hotel_room_rate` ID 1 cho khách sạn 61 và bảng token thay đổi trạng thái sử dụng. Không đọc/in nội dung token. Đợt bổ sung lấy backup mới, giữ nguyên các thay đổi này; bằng chứng đối chiếu trong `post-import-drift.json`. Không phục hồi đè dữ liệu hoạt động mới để ép khớp snapshot cũ.

## Tệp và chạy lại

- Manifest dữ liệu không chứa credentials: [`database/data/verified-room-types-2026-10-31.json`](../database/data/verified-room-types-2026-10-31.json).
- Bảng đầy đủ/các tên cũ chưa khớp: [`validation/room-type-enrichment-2026-10-31.csv`](validation/room-type-enrichment-2026-10-31.csv).
- Schema dữ liệu bổ sung: migration `2026_10_31_000004_allow_verified_room_catalog_without_rates` chỉ cho phép thông tin chưa biết để NULL, thêm diện tích và bảng bằng chứng; không kích hoạt V2.
- Importer `tools/import-verified-room-types.php` yêu cầu backup đã phục hồi kiểm tra, manifest trùng với lần diễn tập và trạng thái DB đúng baseline. `--apply=<verified-backup.json>` tự trả kết quả không đổi nếu manifest và trạng thái đã khớp bản ghi chính; `--verify=<verified-backup.json>` chỉ xác minh.
- Bằng chứng HTML/hash, ảnh xem nhanh, backup và báo cáo trước/diễn tập/sau được lưu riêng trong `storage/app/private`; không chép credentials vào dữ liệu công khai hay Git. Không đụng Google Sheet.
