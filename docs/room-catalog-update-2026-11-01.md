# Sức chứa, tiện ích, trạng thái và giá tham khảo — 01/11/2026

**Cập nhật tiếp nối 04/11/2026:** người dùng đã cho phép tạo phòng quản lý và dùng giá thật tham khảo làm giá cơ bản dự án. Hiện có 1.119 phòng vật lý, 232/362 loại có giá cơ bản; các số 417 phòng và 234 loại chưa có tồn dưới đây là lịch sử ngày 01/11. [Báo cáo hiện tại](room-admin-update-2026-11-04.md).

Đã ghi DB chính: bật **234 loại** (tổng **362/362 loại chưa archived active**); điền **133 sức chứa** gồm **4 có nguồn chính thức** và **129 cấu hình DEMO**. Giữ mọi sức chứa đã có. Bổ sung **5134 mục tiện ích phòng**, **315 tiện ích gắn khách sạn**; giữ nguyên các mục cũ.

Giữ **40 khách sạn, 362 loại chưa archived, 30 loại archived và 417 phòng vật lý**; không tạo khách sạn/phòng/loại mới, không đổi giao diện hoặc logic đặt phòng. Các loại không có phòng vật lý vẫn không có tồn để đặt. 362/362 loại tiếp tục có ảnh.

## Giá thật đã lấy được và cách dùng

Có **7 báo giá trọn kỳ 15–17/11/2026 của Meliá Vinpearl Huế**, 2 người lớn, 1 phòng, VND, gồm thuế/phí. Nguồn Expedia đã hiển thị đúng ngày/khách và đúng từng hạng. Tổng giá gốc được lưu trong bằng chứng. Theo phạm vi DEMO, chia tổng 2 đêm cho 2 để lưu **giá bình quân tham khảo**, chỉ áp dụng đêm 15 và 16/11; tên bảng giá nói rõ kỳ và giá bình quân. Đây không phải bảng chi tiết giá từng đêm do Expedia trả về.

**Không thêm giá cơ bản cố định mới, không nhân giá theo tên hạng và không chép giá thấp nhất của khách sạn sang mọi phòng.** Giá cơ bản và các giá ngày/mùa cũ được giữ. Các nguồn còn lại chưa có báo giá đầy đủ đúng hạng/ngày/điều kiện; những giá chưa xác minh tiếp tục để trống.

| Hạng tại Meliá Vinpearl Huế | Tổng kỳ 2 đêm có thuế/phí | Bình quân DEMO mỗi đêm trong kỳ | Ăn sáng của gói nguồn |
|---|---:|---:|---|
| Deluxe Room | 3.690.270 ₫ | 1.845.135 ₫ | Chưa gồm; nguồn có lựa chọn trả thêm |
| Premium Room | 4.349.878 ₫ | 2.174.939 ₫ | Chưa gồm; nguồn có lựa chọn trả thêm |
| Suite | 9.349.190 ₫ | 4.674.595 ₫ | Chưa gồm; nguồn có lựa chọn trả thêm |
| Premium Room With View | 5.465.134 ₫ | 2.732.567 ₫ | Đã gồm |
| Suite, 2 Bedrooms | 14.629.184 ₫ | 7.314.592 ₫ | Chưa gồm; nguồn có lựa chọn trả thêm |
| Presidential Suite | 93.344.776 ₫ | 46.672.388 ₫ | Đã gồm |
| Deluxe Room, 2 Bedrooms | 8.856.656 ₫ | 4.428.328 ₫ | Đã gồm |

[Nguồn báo giá đúng kỳ](https://www.expedia.com.vn/Hue-Khach-San-Melia-Vinpearl-Hue.h29267758.Thong-tin-khach-san?startDate=2026-11-15&endDate=2026-11-17&d1=2026-11-15&d2=2026-11-17&adults=2&rooms=1). Kiểm tra nguồn 31/10/2026, ghi dữ liệu 01/11/2026. Điều kiện nguồn hiển thị hoàn tiền toàn bộ trước 12/11; chi tiết từng gói/ngày/khách và mã băm bằng chứng ở [manifest báo giá](../database/data/verified-room-rate-references-2026-11-01.json). Không nhập số phòng còn lại do OTA quảng bá thành tồn kho dự án.

## Kết quả từng khách sạn

Số loại trước/sau tính từ trước toàn bộ đợt bổ sung danh mục đến hiện tại, chỉ đếm bản ghi chưa archived; có cả tên cũ được giữ nguyên để bảo toàn liên kết. Riêng đợt cấu hình 01/11 không thêm loại mới. Nguồn trong bảng là nguồn đối chiếu danh mục; tiện ích và sức chứa DEMO được ghi riêng, không giả làm thông tin thực tế đã xác nhận. Các giá cũ được giữ nhưng không tự coi là giá thị trường vừa xác minh.

| Khách sạn | Số loại phòng trước/sau | Số phòng vật lý | Nguồn | Thông tin còn thiếu |
|---|---:|---:|---|---|
| MEANDER Sai Gon (ID 1) | 5 → 10 | 16 | [Danh mục](https://staymeander.com/meandersaigon/en/rooms) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| The Hanoi Signature Hotel (ID 2) | 3 → 9 | 16 | [Danh mục](https://thehanoisignaturehotel.com/rooms) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| La Siesta Classic Ma May (ID 3) | 4 → 10 | 16 | [Danh mục](https://lasiestahotels.com/mamay/rooms) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Hanoian Central Hotel & Spa (ID 4) | 3 → 7 | 16 | [Danh mục](https://www.hanoiancentralhotel.com/) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Hotel Majestic Saigon (ID 5) | 3 → 7 | 16 | [Danh mục](https://www.majesticsaigon.com/our-deluxes-suites/) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| La Siesta Premium Saigon Central (ID 6) | 4 → 8 | 16 | [Danh mục](https://lasiestahotels.com/saigoncentral/rooms-suites) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| La Siesta Premium Saigon (ID 7) | 3 → 7 | 16 | [Danh mục](https://lasiestahotels.com/saigon/rooms-suites) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Hotel Grand Saigon (ID 8) | 4 → 9 | 16 | [Danh mục](https://www.hotelgrandsaigon.com/rooms-suites/) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| InterContinental Danang Sun Peninsula (ID 9) | 5 → 15 | 16 | [Danh mục](https://www.danang.intercontinental.com/accommodation/) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Pullman Danang Beach Resort (ID 10) | 3 → 10 | 16 | [Danh mục](https://www.pullman-danang.com/room/) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Brilliant Hotel Danang (ID 11) | 5 → 15 | 16 | [Danh mục](https://brillianthotel.vn/en/rooms/) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| WorldHotels Long Beach Resort Phu Quoc (ID 12) | 2 → 12 | 16 | [Danh mục](https://worldhotelslongbeach.com/room/) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Vinpearl Resort & Spa Phú Quốc (ID 13) | 5 → 12 | 16 | [Danh mục](https://vinpearl.com/en/hotels/vinpearl-resort-spa-phu-quoc/rooms) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Brilliant Bay Nha Trang Hotel (ID 14) | 1 → 6 | 18 | [Danh mục](https://brilliantbaynhatrang.com/) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Sunrise Nha Trang Beach Hotel (ID 15) | 5 → 10 | 16 | [Danh mục](https://sunrisenhatrang.com.vn/rooms-suites/) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Four Seasons Resort The Nam Hai (ID 16) | 3 → 11 | 16 | [Danh mục](https://www.fourseasons.com/hoian/accommodations/) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Anantara Hoi An Resort (ID 17) | 4 → 6 | 16 | [Danh mục](https://www.anantara.com/en/hoi-an/rooms) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Dalat Palace Heritage Hotel (ID 18) | 5 → 11 | 16 | [Danh mục](https://www.dalatpalacehotel.com/en/roomssuites/) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Khách sạn Le Récit Boutique Đà Lạt (Le Recit Boutique Hotel de Dalat) (ID 19) | 3 → 4 | 16 | [Danh mục](https://www.booking.com/hotel/vn/le-recit-boutique-de-dalat.fr.html) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Azerai La Residence Hue (ID 20) | 3 → 11 | 17 | [Danh mục](https://azerai.com/azerai-la-residence-hue/accommodations/) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Pilgrimage Village Boutique Resort (ID 21) | 2 → 9 | 16 | [Danh mục](https://pilgrimagevillage.com/home) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| The Pearl Hoi An (ID 43) | 3 → 10 | 10 | [Danh mục](https://thepearlhoian.vn/en/hotel) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| NHAAN Resort & Spa Hội An, Tapestry Collection by Hilton (ID 44) | 2 → 10 | 4 | [Danh mục](https://www.booking.com/hotel/vn/nhaan-resort-spa-hoi-an-tapestry-collection-by-hilton.html) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Vinpearl Resort & Golf Nam Hoi An (ID 45) | 2 → 8 | 8 | [Danh mục](https://vinpearl.com/en/hotels/vinpearl-resort-golf-nam-hoi-an/rooms) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Pan Pacific Hanoi (ID 46) | 3 → 10 | 3 | [Danh mục](https://www.panpacific.com/en/hotels-and-resorts/pp-hanoi/rooms.html) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Silk Path Hotel Hanoi (ID 47) | 3 → 6 | 4 | [Danh mục](https://silkpathhotel.com/accommondationhanoi/) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Ana Mandara Villas Dalat Resort & Spa (ID 48) | 3 → 7 | 3 | [Danh mục](https://anamandara-resort.com/rooms-suites/) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Terracotta Hotel & Resort Dalat (ID 49) | 3 → 8 | 4 | [Danh mục](https://dalat.terracottaresort.com/phong-nghi/khach-san-17/) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Swiss-Belresort Tuyen Lam Dalat (ID 50) | 3 → 6 | 3 | [Danh mục](https://samtuyenlamhotel.com.vn/category-stay/accommodation/?lang=en) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Hyatt Regency Danang Resort and Spa (ID 51) | 3 → 10 | 4 | [Danh mục](https://www.hyatt.com/hyatt-regency/en-US/danhr-hyatt-regency-danang-resort-and-spa/rooms) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Novotel Danang Premier Han River (ID 52) | 3 → 10 | 3 | [Danh mục](https://all.accor.com/hotel/8287/index.en.shtml) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Silk Path Grand Hue Hotel & Spa (ID 53) | 3 → 8 | 4 | [Danh mục](https://silkpathhotel.com/slphueaccommonation/) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Meliá Vinpearl Hue (ID 54) | 3 → 7 | 3 | [Danh mục](https://www.expedia.com/Hue-Hotels-Melia-Vinpearl-Hue.h29267758.Hotel-Information); [7 báo giá](https://www.expedia.com.vn/Hue-Khach-San-Melia-Vinpearl-Hue.h29267758.Thong-tin-khach-san?startDate=2026-11-15&endDate=2026-11-17&d1=2026-11-15&d2=2026-11-17&adults=2&rooms=1) | Giá ngày ngoài kỳ đã xác minh; phân bổ tồn phòng |
| Senna Hue Hotel (ID 55) | 3 → 9 | 4 | [Danh mục](https://sennahue.com/) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| InterContinental Nha Trang (ID 56) | 3 → 7 | 3 | [Danh mục](https://nhatrang.intercontinental.com/rooms/) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Amiana Resort Nha Trang (ID 57) | 3 → 10 | 4 | [Danh mục](https://amianaresortsandhotels.com/amianaresortnhatrang/) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Sheraton Nha Trang Hotel & Spa (ID 58) | 2 → 9 | 3 | [Danh mục](https://www.marriott.com/en-us/hotels/nhasi-sheraton-nha-trang-hotel-and-spa/rooms/premium-rooms/) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Salinda Resort Phu Quoc Island (ID 59) | 3 → 8 | 4 | [Danh mục](https://salindaresort.com/accommodation/) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| InterContinental Phu Quoc Long Beach Resort (ID 60) | 3 → 10 | 3 | [Danh mục](https://phuquoc.intercontinental.com/resort-collection/) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |
| Regent Phu Quoc (ID 61) | 2 → 10 | 4 | [Danh mục](https://www.ihg.com/regent/hotels/us/en/phu-quoc/pqcrr/hoteldetail/rooms-and-suites) | Giá đúng hạng/ngày/điều kiện; phân bổ tồn phòng |

Chi tiết sức chứa/tiện ích thêm, giá và phần thông số thực tế chưa xác minh theo từng khách sạn: [CSV cập nhật](validation/room-catalog-update-2026-11-01.csv). Đối chiếu diện tích, giường, tên cũ và thông số còn thiếu nguồn: [báo cáo danh mục](room-type-enrichment-2026-10-31.md). Nguồn ảnh từng hạng: [báo cáo ảnh](room-type-photos-2026-10-31.md).

## Nguồn, kiểm tra và giới hạn

- [Cấu hình DEMO và các trường trước/sau](../database/data/demo-room-catalog-2026-10-31.json): sức chứa chỉ điền chỗ trống, tiện ích bổ sung theo giá dự án hoặc giá tham khảo xác minh được; không đổi dữ kiện cũ. 4 sức chứa mới lấy từ `HotelRoom.occupancy.maxValue` của [Cloudbeds chính thức MEANDER](https://hotels.cloudbeds.com/en/reservation/BscTin?currency=VND).
- Nguồn giá khác đã thử: Trip.com trả lỗi 430 ở API phòng; Booking.com không giữ lựa chọn ngày/báo giá; Agoda không trả trang báo giá; Cloudbeds lỗi ở dữ liệu giá; các link đặt trực tiếp đang trả 404. Expedia trả CAPTCHA khi tra nhiều cơ sở, nên dừng phiên tra đó và giữ các giá chưa có bằng chứng trống. Không giải CAPTCHA hoặc lách kiểm soát truy cập.
- Đã backup DB chính, khôi phục và đối chiếu 49 bảng/view trên MySQL cô lập, diễn tập toàn bộ thay đổi trước khi ghi. Hash chứng minh ảnh, phòng vật lý, booking/payment/snapshots và các bảng ngoài phạm vi không đổi. Giữ các giá/rate, sức chứa không trống, tiện ích khách sạn và bằng chứng cũ.
- Đọc khả dụng trước/sau cho 40 khách sạn khớp nhau. Giá kỳ 2 đêm qua cơ chế báo giá hiện có khớp tổng nguồn cho các hạng có phòng vật lý; không tạo tồn cho hạng mới chỉ để kiểm thử. Nhập lại tạo 0 thay đổi.
- HTTP đã kiểm tra 7 hạng có báo giá: 3 hạng có phòng vật lý trả 200, tổng kỳ khớp nguồn và ngày ngoài kỳ giữ giá cũ; 4 hạng không có phòng vật lý trả 404. Lần đọc lại đồng thời API của 40 khách sạn dừng do timeout, chưa xác nhận hoàn tất 40/40 ở đợt cập nhật này. Kết quả 40/40 trong báo cáo đợt ảnh là kết quả lịch sử. Không chạy thêm kiểm thử tự động theo yêu cầu dừng đã chốt.
- Dữ liệu thật chưa đủ: giá các hạng/ngày khác và phân bổ số phòng vật lý. Nhiều sức chứa/tiện ích mới là DEMO theo ủy quyền người dùng. Phân biệt này được lưu trong `hotel_room_type_evidence`, không sửa bằng chứng thực tế cũ.

## Tự kiểm tra trên website

1. Mở danh mục loại phòng ở Admin: các loại chưa lưu trữ đã active, có ảnh, sức chứa và tiện ích; các loại archived vẫn giữ nguyên.
2. Mở Meliá Vinpearl Huế, chọn 15–17/11/2026 và 2 người lớn: đối chiếu tổng 2 đêm của Deluxe Room, Premium Room và Suite với bảng trên. Chọn ngày ngoài kỳ để kiểm tra bảng giá tham khảo không bị kéo dài.
3. Thử các hạng không có phòng vật lý: không được tự xuất hiện tồn kho để đặt. Các booking cũ và chi tiết tiền đã lưu phải giữ nguyên.
