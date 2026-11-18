# Yêu cầu lịch trình đã chốt

## Cách tiếp nhận thay đổi

Yêu cầu mới bổ sung vào yêu cầu cũ, trừ khi người dùng nói rõ thay đổi/bỏ. Chỉ sửa phần liên quan, kiểm tra code trước và kiểm tra hồi quy sau. Giữ các lỗi đã sửa, không làm lại hoặc tự xóa chức năng. Có mâu thuẫn thật phải nêu rõ và hỏi trước. Quy tắc áp dụng lâu dài ở [AGENTS.md](../AGENTS.md).

## Các phần tiếp tục giữ

- Trang lịch trình ở trong khung tài khoản có sidebar/top bar, thương hiệu Angel, logo thật `public/image/Logo thương hiệu.png`. Top bar/sidebar #f8f8f8, vùng nội dung trắng.
- Hai bước: thông tin chuyến đi gọn (điểm đến DB, số ngày, số người, ngân sách tùy chọn, nhu cầu lưu trú), sau đó lịch trình/trợ lý AI với tỷ lệ laptop 60/40 và bố cục điện thoại phù hợp.
- Khách tự thêm/sửa/xóa/đổi giờ/ngày/thứ tự, hoàn tác, quay lại thông tin, tạo lịch mới, lưu, mở lịch đã lưu, tải PDF. Danh sách lịch có icon tải lại vòng tròn cạnh nút đóng. Giữ API/lịch cũ.
- AI chỉ đề xuất; khách Áp dụng/Bỏ qua. Đổi số ngày trong đề xuất toàn lịch được áp dụng đồng bộ; chỉnh riêng ngày/buổi giữ các phần còn lại. Đề xuất cũ không ghi đè chỉnh sửa mới. Giữ trạng thái đang tạo và lỗi thật, không giả lập AI thành công.
- Ảnh thật đúng địa điểm, có nguồn/quyền dùng, bấm xem lớn; ảnh còn thiếu tải bổ sung, giữ qua lưu/mở lại. Không thay ảnh bằng “Tìm ảnh địa điểm”. Thiếu ảnh phù hợp vẫn giữ thiếu, không dùng ảnh nơi khác. Mô tả thân thiện, không tự bịa đặc điểm hay giá.
- Liên kết địa điểm dùng Google Maps; Sheet dùng chính maps_url của bản ghi đã kiểm tra. Khách sạn xuống dòng theo danh sách, link xem khách sạn màu xanh, giữ ảnh phòng đã sửa.
- Dùng Groq, MySQL, Geoapify và các dịch vụ hiện có. Sheet chỉ đọc, đồng bộ theo nguồn/ID, không trùng, giữ dữ liệu thành công gần nhất khi nguồn lỗi, lịch 15 phút chống chồng. Không gợi ý địa điểm bị đóng vĩnh viễn/vô hiệu hóa. Không bịa điện thoại, tọa độ, giá, giờ hay ảnh.
- Lịch trải nghiệm tăng tham quan/ăn uống/vui chơi, không lấp cả buổi bằng nghỉ tự chèn; vẫn chừa ăn và di chuyển. Có ngân sách xét cả nhóm/toàn chuyến, chỉ dùng giá có nguồn, vượt nhẹ phải báo; thiếu giá không phải miễn phí. Không có ngân sách vẫn xếp sáng/chiều khi đủ dữ liệu. Tránh điểm trùng/cùng cụm, không tăng số lượng giả. Giờ ghé phải phù hợp ca tham khảo, thời lượng theo trải nghiệm; không mặc định mọi điểm một giờ hoặc chuyển điểm một phút không có cơ sở.

## Giờ hoạt động — cập nhật giao diện mới nhất

- Dòng có icon đồng hồ nằm ngay trên hàng Google Maps. Khi đủ dữ liệu: trạng thái hiện tại gọn, ví dụ “Đang mở cửa · Đóng cửa vào 22:00”, có mũi tên mở chi tiết.
- Trạng thái hiện tại tính bằng thời điểm hiện tại và múi giờ địa điểm, hoàn toàn tách với kết quả kiểm tra giờ ghé. Đây là suy ra từ lịch tham khảo, không xác nhận hoạt động thực tế.
- Có lịch nhưng chưa xác định hiện tại: “Giờ hoạt động: …”. Không có dữ liệu: chỉ “Chưa có thông tin giờ hoạt động”, trung tính; không bịa giờ để giống mẫu.
- Phần xổ xuống có lịch từng thứ, ngoại lệ ngày, nguồn, ngày kiểm tra/cập nhật được nguồn cung cấp và lưu ý giờ tham khảo. Không hiện đoạn giải thích dài thường trực ở mỗi thẻ.
- Bỏ ô “Ngày bắt đầu ghé (để đối chiếu giờ hoạt động)” trong bảng lịch trình theo yêu cầu rõ của người dùng. Giữ ngày đi đã có trong dữ liệu/luồng hội thoại để đối chiếu; không dùng ngày neo nội bộ của lịch sơ bộ như ngày đi thật.
- Giữ backend đối chiếu toàn khoảng bắt đầu–kết thúc, nhiều ca, nghỉ, 24 giờ, qua đêm, ngoại lệ ưu tiên tuần, trạng thái thiếu/mơ hồ khác nghỉ. Giữ kiểm tra sau khi đổi giờ/ngày/thứ tự; chỉ cảnh báo/gợi ý, không tự ghi đè lịch khách.

Chi tiết kỹ thuật: [trình soạn lịch](itinerary-two-step-editor.md), [giờ hoạt động](place-opening-hours.md), [nguồn Sheet](google-sheet-place-source.md).

## Bổ sung đã chốt ngày 31/10/2026 — đặt phòng DEMO

- Áp dụng vào luồng website DEMO hiện tại; không tự bật V2 hoặc thanh toán tiền thật. Giữ QR Banking, OTP, Google Login và cách chọn loại phòng/số lượng, không bắt chọn số phòng vật lý.
- Giá ngày cụ thể ưu tiên giá mùa, sau đó giá cơ bản. Cấm các khoảng giá cùng ưu tiên chồng nhau. Tính từng đêm, không tính ngày trả phòng; báo giá thống nhất và lưu bất biến chi tiết từng đêm trong đơn. Bảng giá mới không đổi đơn cũ.
- Thời gian giữ DEMO đổi từ 15 thành **10 phút**. Scheduler phải tự hủy đơn quá hạn và giải phóng phòng kể cả không có truy cập; không cài lịch trùng và báo rõ bằng chứng chạy thực tế.
- Giữ 1–10 phòng/lượt. Mặc định mỗi tài khoản tối đa **3 đơn chờ thanh toán / tổng 10 phòng**, có cấu hình; không tính đơn hủy/hết hạn và phải chống vượt hạn mức khi gửi đồng thời.
- Đơn hủy/hết hạn không được khôi phục; muốn đặt lại phải tạo đơn mới và kiểm tra tồn. Chỉ ghi thanh toán từ kết quả hợp lệ; DEMO tiếp tục công cụ giả lập của Admin. Hoàn thành cần đã thanh toán và tới giờ trả phòng của khách sạn vào ngày trả phòng.
- Thông báo lặp xử lý một lần; cùng mã thông báo nhưng đổi số tiền phải từ chối. Tiền đến sau hủy/hết hạn được lưu đối soát, không khôi phục đơn. Admin xem/ghi kết quả, giữ lịch sử; ghi nhận hoàn tiền phải có mã tham chiếu và tệp bằng chứng được lưu riêng tư.
- Kiểm thử dùng DB riêng, không phát sinh thanh toán thật, không reset/xóa dữ liệu hiện có. Chi tiết triển khai và vận hành: [bảo vệ booking DEMO](booking-demo-safeguards.md).

## Bổ sung đã chốt ngày 31/10/2026 — dữ liệu loại phòng khách sạn hiện có

- Chỉ bổ sung dữ liệu/ảnh cho đúng khách sạn và chi nhánh đang có; không thêm khách sạn, không đổi giao diện hay logic đặt phòng đã chốt.
- Phân biệt `room_type`/`hotel_room_type` (danh mục và thông tin riêng từng khách sạn) với `room` (phòng vật lý). Không suy ra tồn phòng từ số loại phòng, số giường hay tổng phòng quảng bá của cơ sở; thiếu dữ liệu phân bổ thì giữ nguyên tồn phòng.
- Khoảng 9–10 loại phòng chỉ là mục tiêu khi nguồn xác minh được. Giữ ít hơn khi thực tế/nguồn ít hơn; không tách ăn sáng, hủy miễn phí, số khách của gói giá hoặc quyền vào lounge đơn thuần thành loại phòng mới.
- Dùng website chính thức hoặc nguồn đặt phòng đáng tin cậy, đúng cơ sở. Lưu URL nguồn và ngày kiểm tra cho từng phần bổ sung; diện tích có khoảng thì giữ khoảng, không tự lấy trung bình. Thiếu hoặc mâu thuẫn dữ liệu thì để trống và ghi vấn đề; không suy ra sức chứa tối đa từ giường.
- Giá tham khảo cần ngày lưu trú và điều kiện rõ ràng; không dùng giá “từ…” làm giá cố định. Loại mới chưa có giá xác minh phải có `base_price = NULL`, trạng thái `inactive`, không tự tạo `room` hay bảng giá. Các giá/cấu hình DEMO cũ không được tự coi là giá/tồn kho thực tế đã xác minh.
- Giữ nguyên booking, ID/liên kết, gallery đã cấu hình, trạng thái lưu trữ và dữ liệu đúng. Chỉ điền trường trống, lưu riêng xung đột cần đối chiếu, không tự sửa sức chứa/giá cũ hoặc khôi phục hạng đã lưu trữ. Nhập lại không tạo trùng.
- Ảnh phải thuộc gallery chính hạng phòng hoặc được nguồn chú thích đúng hạng; kiểm tra nội dung, định dạng và file tải được. Không dùng ảnh ngẫu nhiên, ảnh sự kiện, trình tải hay ảnh khách sạn khác. Không khẳng định quyền tái sử dụng nếu nguồn chưa xác nhận.
- Kết thúc phải báo trước/sau số bản ghi danh mục, số phòng vật lý, nguồn và phần thiếu cho từng khách sạn; phân biệt phần đã ghi thực tế với phần chưa đủ bằng chứng. Báo cáo đợt này: [bổ sung loại phòng 31/10/2026](room-type-enrichment-2026-10-31.md).

## Bổ sung đã chốt ngày 31/10/2026 — hiển thị giá ngày/mùa

- Đối chiếu giá bằng đúng khách sạn, loại phòng, ngày lưu trú và bản ghi thực tế; không tự sửa giá kinh doanh để làm khớp giao diện. Khi Admin đổi bảng giá, tab khách phải tải lại báo giá phù hợp, giữ chống phản hồi cũ và kiểm tra quote hash khi tạo đơn.
- Admin tách rõ giá cơ bản, form giá ngày/mùa và danh sách đã lưu; có thời gian áp dụng, mức giá, sửa/xóa và link mở đúng phòng/ngày phía khách. Giữ form metadata, ảnh và các chức năng cũ.
- Khách có khối mở rộng “Chi tiết giá”: mỗi đêm, đơn giá × số phòng, thành tiền; tiền phòng và tổng thanh toán rõ, số tiền căn phải, không tràn điện thoại. Giữ nguyên yêu cầu thêm. Hiện tên loại giá dễ hiểu, không đưa mã nội bộ vào giao diện.
- Quyết định cũ ngày 31/10 về giá ngày/mùa thay thế giá cơ bản được người dùng thay đổi rõ ràng ngày 01/11/2026: các khoản nhập mới là **phụ thu cộng thêm**, theo quy tắc bổ sung dưới đây. Các khoản phí/giảm giá khác vẫn cần dữ liệu thực tế, không tự tạo phí.
- Kiểm chứng trên trình duyệt Admin/khách bằng dữ liệu thật ở chế độ chỉ đọc, kèm ảnh sau sửa; báo cáo [đối chiếu giá ngày/mùa](room-pricing-review-2026-10-31.md).
- Bổ sung kiểm thử ghi trên DB test riêng: lưu/sửa/xóa giá ngày và giá mùa qua Admin, chặn chồng chéo khi tạo/sửa, ưu tiên giá ngày và tab khách cập nhật đúng sau từng thao tác. Giữ nguyên code sản phẩm và giá DB chính; gửi ảnh hiển thị trực tiếp trong cuộc trò chuyện, không chỉ dẫn đường dẫn báo cáo.

## Bổ sung đã chốt ngày 01/11/2026 — phụ thu ngày/mùa

- Giữ giá cơ bản. Mỗi đêm = giá cơ bản + một khoản phụ thu: ngày cụ thể ưu tiên mùa, không cộng dồn; không có quy tắc thì phụ thu bằng 0. Giữ chặn khoảng cùng ưu tiên chồng chéo và loại trừ đêm trả phòng.
- Admin dùng nhãn “Phụ thu ngày/mùa (₫/phòng/đêm)” và giải thích “Cộng thêm vào giá cơ bản”; giữ bố cục/editor hiện có. Khách thấy giá cơ bản, phụ thu, số phòng, thành tiền từng đêm; checkout/hóa đơn dùng cùng giá đã lưu.
- Báo giá chưa đặt phải tải lại khi phụ thu đổi và yêu cầu khách xác nhận lại; đơn đã tạo giữ nguyên chi tiết/tiền đã lưu, không tính lại theo bảng giá mới.
- Chỉ xác nhận bản ghi `hotel_room_rate.id=1`, Regent Phu Quoc (`61`), Ocean View Suite (`64`), ngày 31/10/2026, `100000.00` là phụ thu. Giá cơ bản đối chiếu thực tế vẫn `6500000.00`, không cần khôi phục. Bản ghi cũ khác giữ ý nghĩa giá thay thế đến khi xác định; không chuyển hàng loạt hoặc đoán giá gốc. Khi chuyển qua Admin phải nhập số phụ thu và xác nhận rõ ràng.
- Theo yêu cầu mới nhất, dừng chạy thêm kiểm thử tự động; hoàn tất sửa và cung cấp danh sách để người dùng tự kiểm tra. Báo đúng phạm vi đã kiểm chứng và phần chưa chạy.

## Bổ sung đã chốt ngày 31/10/2026 — ảnh loại phòng

- Người dùng yêu cầu mỗi loại phòng còn thiếu có một ảnh là đủ; tiếp tục giữ gallery cũ. Gắn ảnh theo loại phòng trong từng khách sạn để các phòng vật lý tương ứng dùng chung; không tạo ảnh hay tồn kho giả cho từng số phòng.
- Được lấy ảnh phòng công khai từ website khách sạn và các nền tảng đặt phòng như Agoda, Trip.com, Traveloka… khi xác minh đúng cơ sở/chi nhánh và đúng loại phòng. Dự án dùng trình diễn cho thầy cô, không rao bán. Tiếp tục lưu URL trang, URL ảnh và ngày kiểm tra; không dùng ảnh ngẫu nhiên hoặc ảnh khách sạn khác.
- Đợt ảnh đã bổ sung 125 ảnh vào các gallery trống, giữ giá/tồn/booking/giao diện và trạng thái các loại chưa có giá. Báo cáo, nguồn và kiểm tra: [ảnh loại phòng 31/10/2026](room-type-photos-2026-10-31.md).

## Bổ sung đã chốt ngày 31/10/2026 — sức chứa, tiện ích và giá thật

- Người dùng cho phép tham khảo rồi đặt cấu hình sức chứa/tiện ích phù hợp cho DEMO, không cần giống khách sạn ngoài đời hoàn toàn. Phần bổ sung giả lập phải được ghi rõ là cấu hình DEMO trong bằng chứng và báo cáo; không đổi các dữ kiện đã xác minh thành thông tin tự đặt.
- Người dùng chốt riêng **giá phải lấy giá thật**. Tiếp tục cần đúng khách sạn/hạng, ngày lưu trú và điều kiện giá; không tự nhân giá theo tên hạng/số phòng ngủ, không coi giá thấp nhất của khách sạn là giá mọi hạng. Chưa xác minh được thì giữ trống. Mốc đối chiếu đợt này: 15–17/11/2026, 2 người lớn/1 phòng, ưu tiên VND; chỉ ghi giá ngày/mùa trong phạm vi nguồn chứng minh.
- Yêu cầu bật trạng thái áp dụng các loại chưa lưu trữ; các loại archived, phòng vật lý nghỉ quản lý/đang có booking và liên kết cũ vẫn được giữ. Bật danh mục không tự tạo tồn kho: loại chưa có phòng vật lý chưa thể đặt qua logic hiện tại. Không thay UI/logic, không thêm khách sạn hoặc phòng vật lý, không ghi đè giá ngày/mùa và giá trong booking cũ.

## Áp dụng ngày 01/11/2026 — danh mục DEMO và báo giá theo kỳ

- Đã bật 234 loại, hiện 362/362 loại chưa archived active; giữ 30 loại archived. Điền 133 sức chứa còn thiếu: 4 từ metadata Cloudbeds chính thức của MEANDER, 129 cấu hình DEMO. Bổ sung 5.134 mục tiện ích phòng và 315 tiện ích gắn khách sạn; các mục cũ được giữ.
- Giá xác minh đợt này là **7 tổng báo giá trọn kỳ 15–17/11/2026 của Meliá Vinpearl Huế**, 2 người lớn/1 phòng, VND gồm thuế/phí. Nguồn được kiểm tra 31/10/2026. Lưu nguyên tổng nguồn, điều kiện và URL/hash trong bằng chứng; phân bổ tổng/2 thành **giá bình quân tham khảo cho DEMO**, chỉ đêm 15 và 16/11. Label nêu rõ bình quân/kỳ/khách/thuế. Không mô tả phân bổ này là bảng giá riêng từng đêm của OTA, không biến thành giá cơ bản áp mọi ngày.
- Không điền giá tự đặt cho các hạng chưa có bằng chứng. Giữ giá cơ bản và bảng giá cũ. Số phòng vật lý vẫn 417; 234 loại chưa có phòng vật lý vẫn chưa có tồn để đặt dù active. Giữ ảnh, booking/payment/snapshots, giao diện và logic.
- Kết quả, nguồn và phần còn thiếu từng khách sạn: [cập nhật danh mục 01/11/2026](room-catalog-update-2026-11-01.md).

## Bổ sung đã chốt ngày 02/11/2026 — thông báo trong form đặt phòng

- Mỗi trạng thái phải nói cụ thể khách còn thiếu/sai gì và cách xử lý: ngày nhận/trả phòng riêng biệt, ngày đã qua/thứ tự ngày/kỳ tối đa, loại phòng, số khách, sức chứa, số lượng phòng, yêu cầu thêm. Hiện hướng dẫn sát trường, không chỉ một câu chung chung.
- Có danh sách mục cần sửa bấm tới đúng ô; nêu lý do nút tiếp tục chưa dùng được. Thông báo cập nhật khi sửa thông tin; phân biệt đang kiểm tra tồn, đang lấy giá, hết/thiếu phòng, giá đổi cần xác nhận, cần đăng nhập, sẵn sàng, đang tạo đơn và đã tạo/chuyển trang.
- Lỗi máy chủ hiển thị lý do hữu ích bằng tiếng Việt, giữ chi tiết giới hạn giữ phòng/đơn trùng; phân biệt phiên hết hạn, gửi quá nhanh, lỗi mạng hoặc chưa xác định kết quả tạo đơn. Có liên kết đăng nhập lại/lịch sử khi phù hợp. Không tự gửi lại POST khi chưa rõ kết quả, không hiện lỗi kỹ thuật nội bộ.
- Giữ luồng DEMO, công thức phụ thu, snapshot, giới hạn, QR Banking, OTP, Google Login và bố cục cũ. Không ghi giá/booking DB chính để kiểm tra giao diện. Tiếp tục không chạy bộ kiểm thử tự động theo yêu cầu trước; rà code, cú pháp và ghi danh sách tự kiểm tra.

## Bổ sung đã chốt ngày 02/11/2026 — giá, tổng phòng và thông báo Admin

- Tiếp tục cập nhật giá thật cho các loại phòng đã bổ sung, lưu nguồn/ngày/điều kiện; giữ giá và các đơn đã có, không suy ra giá của hạng khác.
- Người dùng cho phép tạo phòng quản lý cho các loại mới còn 0 phòng, không cần chèn nhãn DEMO/giả lập vào giao diện. Đây là số lượng cấu hình cho dự án, không xác nhận tồn kho thực tế của khách sạn; lưu căn cứ cấu hình nội bộ và giữ nguyên mọi phòng/booking/liên kết cũ.
- Admin phải thông báo từng trường thiếu/sai, ví dụ “Giá phòng chưa nhập.”; hiển thị đồng thời tất cả lỗi, đánh dấu dưới từng ô và đưa con trỏ tới ô đầu tiên cần sửa. Áp dụng cả trang chi tiết khách sạn và form quản lý loại phòng hiện có, giữ chức năng ảnh và phụ thu.
- Bảng danh mục hiển thị rõ giá chưa nhập, loại chưa có phòng quản lý và trạng thái chưa sẵn sàng thay vì chỉ hiện ký hiệu tiền hoặc khẳng định đang bán khi còn thiếu cấu hình.

### Quyết định tiếp nối về giá cơ bản — xác nhận trong cuộc trò chuyện, tiếp tục ngày 04/11/2026

- Người dùng chọn rõ “Dùng giá thật tham khảo làm giá cơ bản dự án”. Với loại còn trống giá cơ bản, được dùng mức giá thật của đúng hạng tại kỳ tham khảo (hoặc bình quân tổng giá kỳ có thuế/phí đã lưu) để cấu hình giá cơ bản; lưu ngày lưu trú, số khách, điều kiện, URL và ngày kiểm tra nguồn. Đây là giá quản lý dự án được người dùng chọn, không khẳng định OTA bán cùng giá mọi ngày.
- Quyết định này thay thế giới hạn trước đó chỉ lưu giá theo kỳ và để giá cơ bản trống dù đã có báo giá thật của hạng đó. Các quy tắc về giá thật, không suy ra giá hạng khác, giữ giá cũ/booking/ảnh và không thêm khách sạn vẫn áp dụng.
- Phòng quản lý bổ sung ngày 02/11: mỗi loại đang 0 phòng nhận 3 phòng, tổng 702 phòng mới/234 loại. Phòng chưa có giá giữ NULL và chưa sẵn sàng nhận đặt; khi có giá xác minh thì chỉ cập nhật các phòng mới tương ứng, bảo toàn phòng và booking cũ.

### Kết quả đã ghi DB ngày 04/11/2026

- Đã điền 104 giá cơ bản còn NULL của 30 khách sạn từ báo giá khớp hạng, cập nhật giá và trạng thái sẵn sàng cho 312 phòng mới tương ứng. Giữ 128 giá cơ bản cũ và các bảng giá/phụ thu/booking đã lưu.
- Hiện 232/362 loại có giá cơ bản; 130 loại còn thiếu nguồn giá khớp, tương ứng 390 phòng mới chưa sẵn sàng. Không có loại chưa lưu trữ nào còn 0 phòng quản lý. Tổng 1.119 phòng vật lý, 1.034 đang quản lý; 85 phòng nghỉ quản lý cũ được giữ.
- Đã thay thông báo chung của Admin bằng lỗi theo từng trường, danh sách bấm tới ô, đánh dấu và cập nhật khi sửa; áp dụng trang chi tiết và modal loại phòng. Rà cú pháp PHP/JavaScript, đối chiếu dữ liệu khi ghi và backup; tiếp tục không chạy bộ kiểm thử tự động theo yêu cầu đã chốt.
- Chi tiết từng khách sạn, từng loại và phần chưa đủ bằng chứng: [giá, tổng phòng và thông báo Admin 04/11/2026](room-admin-update-2026-11-04.md).

## Bổ sung đã chốt ngày 02/11/2026 — căn chỉnh chi tiết giá khách

- Trong bảng từng đêm, bỏ dòng giá cơ bản lặp lại và dòng “Không phụ thu”/“Phụ thu +0”. Giữ một đơn giá áp dụng, số phòng và thành tiền, căn cùng hàng với đêm lưu trú.
- Chỉ hiện ghi chú phụ thu ngày/mùa và số tiền khi phụ thu thực tế lớn hơn 0; không tự suy ra phụ thu từ quy tắc giá thay thế cũ. Ghi chú tổng quát về phụ thu cũng chỉ xuất hiện khi kỳ lưu trú có phụ thu.
- Cập nhật này thay thế yêu cầu trình bày giá cơ bản riêng trong dòng giá khách trước đó; giữ nguyên công thức, dữ liệu giá/snapshot, checkout/hóa đơn và các thông báo đã sửa.

## Bổ sung đã chốt ngày 04/11/2026 — logic Báo cáo doanh thu Admin

- Nối đúng route đang dùng `/api/admin/revenue` với một service báo cáo chung. Bộ lọc Hôm nay/7 ngày/30 ngày/Tháng này/Năm nay/Tùy chỉnh phải thực sự lọc KPI, biểu đồ, Top 5 khách sạn, loại phòng, dịch vụ, phương thức và giao dịch theo cùng kỳ. Kỳ tùy chỉnh tính cả hai đầu, tối đa 366 ngày, báo rõ ngày thiếu/sai.
- Doanh thu ghi nhận theo ngày thanh toán, múi giờ Việt Nam; chỉ tính thanh toán thành công, số tiền khớp DB, đơn đã xác nhận/hoàn thành; một đơn ghi nhận một lần. Không cộng đơn chờ/thất bại/hủy, thông báo lặp hoặc tiền đến muộn của đơn đã hủy.
- Giữ mặc định doanh thu thanh toán thực tế. Có lựa chọn riêng xem Thanh toán DEMO cho luồng giả lập đang dùng; không nhập số DEMO vào doanh thu thực tế, không bật cổng tiền thật.
- Tổng giao dịch đếm bản ghi thanh toán đủ điều kiện; tổng khách sạn đếm tất cả khách sạn có doanh thu, không dùng số hàng Top 5. Bảng giao dịch lấy giao dịch thực, có mã đơn/khách/số tiền/phương thức/trạng thái/thời điểm và phân trang; không dựng bảng từ tổng theo khách sạn.
- Tiền phòng theo loại dùng giá từng đêm đã lưu, fallback snapshot dòng đơn cũ; không dùng bảng giá hiện tại để tính lại. Phụ thu ngày/mùa đã nằm trong tiền phòng. Dịch vụ thêm chỉ đọc dữ liệu có sẵn; thiếu cơ chế/dữ liệu phải nói rõ, không tự tạo phí hoặc doanh thu.
- Giữ bố cục Admin hiện tại; thêm trạng thái đang tải/lỗi/rỗng/tải lại, chống phản hồi cũ ghi đè bộ lọc mới; số tiền không bị làm tròn thành triệu trước khi vẽ biểu đồ. Giữ dữ liệu tháng cho phần khách hàng đang dùng API cũ.
- Đối chiếu DB chính chỉ đọc, không tạo đơn/thanh toán hoặc sửa dữ liệu để làm báo cáo có số. Tiếp tục không chạy bộ test tự động theo yêu cầu trước; cung cấp danh sách để người dùng tự kiểm tra.

## Bổ sung đã chốt ngày 04/11/2026 — Sheet là nguồn địa điểm chính để tạo lịch Đà Lạt

- Người dùng yêu cầu thay nguồn tra địa điểm Geoapify đang không dùng được ở Đà Lạt bằng Google Sheet. Luồng tạo lịch tại địa phương cấu hình nguồn Sheet là **Google Sheets → đồng bộ MySQL → backend chọn địa điểm → Groq → backend kiểm tra → lịch trình**. Giữ nguồn và chức năng cũ ở các địa phương khác.
- Tạo lịch và tư vấn tại Đà Lạt dùng các hàng hợp lệ, đang được phép gợi ý, đúng nguồn/địa phương trong `travel_source_place`. Lấy toàn bộ danh mục để chọn ứng viên, độc lập với giới hạn 12 thẻ tư vấn. Không tự quay lại tra ranh giới/địa điểm Geoapify khi nguồn Sheet thiếu hoặc không có điểm phù hợp; báo rõ vấn đề nguồn. Groq chỉ được chọn ID backend đã cung cấp.
- Giữ nguyên URL Maps, địa chỉ, thời lượng ghé, giá tham khảo và thông tin giờ từ nguồn qua đề xuất/lưu/mở lại. Không suy ra tọa độ, ảnh, giá vé cả nhóm hoặc giờ hoạt động khi Sheet chưa có dữ liệu rõ. Giữ bộ đối chiếu giờ, cảnh báo ngân sách, lọc điểm đóng/vô hiệu hóa, chỉnh sửa thủ công, Áp dụng/Bỏ qua, hoàn tác và PDF.
- Các điểm hiện thiếu tọa độ vẫn được xếp lịch. Tạm chừa **30 phút giữa các điểm** như khoảng đệm lập kế hoạch, ghi rõ chưa xác minh thời gian/quãng đường thực tế; không gắn nhãn thời gian Geoapify hoặc trạng thái đã kiểm tra tuyến. Mở đúng liên kết Maps từng điểm; chưa có tọa độ thì không vẽ tuyến giả. Không ghi nguồn Sheet thành ranh giới hành chính đã xác minh.
- Giữ thời lượng ghé từ nguồn, cân đối các điểm với quỹ giờ, bữa ăn và khoảng đệm; báo khi phải cân đối lại. Khi có yêu cầu/sở thích/ăn uống riêng, không tự thay bằng điểm ngoài lựa chọn AI chỉ để đủ giờ. Rút gọn dữ liệu gửi Groq và giữ đại diện tham quan, ăn uống, cà phê, vui chơi để phù hợp giới hạn yêu cầu; dữ liệu đầy đủ vẫn nằm trong MySQL/snapshot.
- Kiểm tra kết nối/tạo đề xuất thực tế chỉ đọc, không lưu lịch thử hoặc ghi DB/Sheet. Tiếp tục không chạy bộ kiểm thử tự động. Chi tiết triển khai, bằng chứng và danh sách kiểm tra thủ công: [nguồn địa điểm Google Sheet](google-sheet-place-source.md).

## Bổ sung đã chốt ngày 04/11/2026 — Phú Quốc cũng dùng nguồn Sheet

- Theo yêu cầu tiếp nối, bỏ tra địa điểm/ranh giới Geoapify khi tư vấn và tạo lịch Phú Quốc; dùng **tab Phú Quốc → MySQL → Groq → lịch trình**, đồng thời giữ luồng Sheet của Đà Lạt. Các địa phương ngoài hai nguồn này giữ nguyên cách xử lý cũ.
- Hai nguồn có ánh xạ tab/Location và khóa nguồn riêng. Tra Location bằng tên trong DB, không đoán ID. Kiểm tra tiền tố ID `DL`/`PQ`, lọc theo cả nguồn và Location cho việc tạo lịch, tư vấn, nạp lại thẻ, Maps và giờ hoạt động; không trộn điểm giữa hai địa phương.
- Tab Đà Lạt đã được đổi từ `Dia_diem` thành `Đà Lạt` trên Sheet. Đọc được tên mới nhưng giữ khóa nguồn `Dia_diem`, các ID/liên kết/snapshot cũ. Không chép dữ liệu Đà Lạt sang Phú Quốc hoặc sửa Sheet để kết nối.
- Cho phép gợi ý tham khảo với đúng nhãn nguồn mới “Có trong nguồn công khai; cần kiểm tra hoạt động”; nhãn này không xác minh đang hoạt động/mở cửa. Các dòng “Cần đối chiếu hoạt động trước sử dụng”, trạng thái lạ, đóng cửa hoặc bị vô hiệu hóa tiếp tục không được tự bật.
- Đồng bộ cả hai nguồn bằng lịch 15 phút hiện có, giữ chống chồng, không trùng ID. Đọc và kiểm tra tất cả nguồn trước khi ghi; ghi trong một transaction để lỗi một nguồn không cập nhật dở nguồn khác. Lấy dữ liệu thật từ Sheet vào MySQL là công việc đã được yêu cầu; kiểm tra tạo lịch vẫn không lưu lịch thử hoặc ghi Sheet.
- Bản chụp đồng bộ ngày 04/11: Phú Quốc có 80 dòng, 50 được phép gợi ý; Đà Lạt có 82 dòng, 50 được phép gợi ý. Các dòng còn chờ đối chiếu vẫn giữ trong nguồn và bị lọc. Nguồn đã thay đổi số dòng trong lúc thực hiện; giữ các thay đổi đúng theo Sheet, không khôi phục dữ liệu cũ đè lên nguồn mới. Tiếp tục giữ quy tắc giờ/giá/ảnh thiếu, khoảng đệm chuyển điểm chưa xác minh, Áp dụng/Bỏ qua, sửa thủ công, hoàn tác, PDF và không chạy bộ test tự động.

## Bổ sung đã chốt ngày 04/11/2026 — sửa lỗi tạo lịch từ 3 ngày

- Người dùng kiểm tra Đà Lạt: lịch 1 ngày tạo được nhưng từ 3 ngày gặp “AI trả lịch chưa hợp lệ”. Phải sửa cả luồng nhiều ngày và giữ luồng 1 ngày, không giảm số ngày người dùng chọn hoặc bỏ bước kiểm tra dữ liệu để báo thành công.
- Đã tái hiện lỗi thật: Groq trả HTTP 200, JSON hợp lệ nhưng `days` có 5 phần tử cho yêu cầu 3 ngày, trong đó 2 phần tử thiếu ngày/hoạt động. Với GPT-OSS 20B/120B, dùng schema chặt có đúng khóa từng ngày yêu cầu rồi chuẩn hóa về định dạng lịch hiện có. Các model khác giữ JSON object cùng kiểm tra backend. Không tự thêm hoạt động cho ngày thiếu hoặc bỏ qua phần tử lạ để giả lập lịch hợp lệ.
- Mỗi phần yêu cầu AI của nguồn Sheet tối đa 3 ngày để phù hợp giới hạn kích thước yêu cầu. Ghép đủ toàn bộ lịch; giữ danh sách loại trừ địa điểm và chi phí đã tính qua các phần. Không mất ngày, trùng điểm, tính ngân sách lại từ đầu hoặc ghi lịch dở nếu một phần thất bại. Giữ cách sửa riêng ngày/buổi, Áp dụng/Bỏ qua, hoàn tác, lưu/mở lại và PDF.
- Kiểm tra cấu trúc/ngày/ID/kiểu số/điểm trùng vẫn bắt buộc. Khi AI trả sai, yêu cầu sửa đúng các trường lỗi tối đa một lần; vẫn báo lỗi thật nếu chưa đạt. Ghi mã trường và metadata an toàn để truy nguyên, không ghi prompt, phản hồi thô, nội dung suy luận hoặc credentials.
- Số điểm ưu tiên không được khiến lịch có trải nghiệm dài từ nguồn bị loại vô lý: nguồn Sheet được phép ít điểm hơn khi thời lượng ghé và các khoảng chừa đã chiếm ít nhất 75% quỹ giờ. Giữ mức tối thiểu đã yêu cầu rõ ràng và chặn lịch quá ít điểm/thời lượng ngắn. Không cắt thời lượng nguồn hay bịa thời gian tuyến đi để làm vừa lịch.
- Nếu Groq trả 429 kèm `Retry-After` không quá 45 giây, chờ theo header và thử lại tối đa một lần trong phần đang tạo, giữ kiểm tra hủy/hết thời gian. Hạn mức không phục hồi hoặc thời gian chờ quá dài vẫn báo lỗi quota; không tự đổi model, nâng gói hoặc tạo lịch giả.
- Tiếp tục không chạy bộ test tự động và không lưu lịch thử/ghi DB hay Sheet để kiểm tra. Bằng chứng tái hiện, xác nhận Groq thật và danh sách tự kiểm tra: [sửa lịch nhiều ngày 04/11/2026](itinerary-multi-day-fix-2026-11-04.md).
