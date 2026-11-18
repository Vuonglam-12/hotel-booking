# Lịch trình AI hai bước

Trang `/itineraries` nằm trong `user.layouts.user`, cùng khung tài khoản với trang Thông tin cá nhân: menu bên trái, top bar “Không gian của bạn”, thông báo và chân trang tài khoản. Mục “Lịch trình AI” được đánh dấu đang mở. Giao diện AUREL thay form tạo lịch cũ bằng năm thông tin: điểm đến từ `DestinationService`/bảng `location`, số ngày, số người, ngân sách có thể bỏ trống và nhu cầu lưu trú. Luồng v2 cũ và API chatbot cũ vẫn được giữ.

Trang không dùng header/footer của khu vực tìm khách sạn. Menu tài khoản trên điện thoại dùng nút mở/đóng hiện có. Thanh bước gộp với nút mở lịch đã lưu; chat giữ menu Gợi ý thay cho nhóm thẻ gợi ý trùng, không lặp logo hay liên kết tài khoản của khung ngoài.

Theo phạm vi B1/B2 đã duyệt, hai partial `itineraries-section.blade.php` và `itineraries-script.blade.php` của form cũ đã được bỏ. Partial chat chỉ còn giao diện nhúng trong khung tài khoản; không còn nhánh `planner=false` và CSS bố cục `.aurel-ai-page` toàn màn hình. Các API đọc lịch cũ, dịch vụ Groq, xử lý chat, lịch sử và thao tác chỉnh sửa vẫn được giữ.

Kiểm tra sau dọn B1/B2: 30 kiểm tra trình duyệt của luồng hai bước đạt, bao gồm menu Gợi ý dùng đúng chuyến đi hiện tại. Bài kiểm tra ảnh khách sạn trong `hotel-booking-phase1.test.cjs` đã chuyển sang renderer chat đang dùng và đạt. Toàn bộ file kiểm tra lịch sử này còn 11 lỗi ở mô phỏng Booking/Admin và contract Hotel Detail; chạy đối chiếu với mã trước dọn cho cùng 11 lỗi, không có lỗi mới từ lần dọn này. Các báo cáo và bộ kiểm tra giao diện cũ dùng preview lịch sử được giữ để tham khảo; không thay thế bộ kiểm tra hai bước hiện tại.

Bước 2 dùng tỉ lệ 3:2 trên laptop, xếp dọc trên điện thoại. Thêm/sửa/xóa/đổi ngày giờ và đổi thứ tự hoạt động làm việc trên bản đang chỉnh. Hoàn tác giữ tối đa 60 thay đổi, kể cả sau khi lưu. Lịch cũ vẫn mở được ở chế độ xem. Lịch có thông tin trẻ em hoặc đặt chỗ chi tiết được giữ ở chế độ xem để tránh mất các dữ liệu mà form gọn không quản lý.

Đề xuất thay toàn bộ lịch có thể đổi số ngày, ví dụ từ 1 sang 4 ngày. Khung đề xuất nói rõ số ngày trước/sau; Áp dụng cập nhật đồng thời các ngày, hoạt động, phần tóm tắt và form thông tin. Bỏ qua giữ nguyên lịch; Hoàn tác khôi phục cả số ngày và hoạt động, kể cả sau khi lưu. Thay đổi chỉ ghi vào MySQL khi khách lưu. Đề xuất chỉnh riêng một ngày vẫn phải giữ số ngày chung; đề xuất khác điểm đến, thiếu/trùng ngày hoặc dựa trên lịch đã bị chỉnh tiếp đều không được ghi đè. Lựa chọn đi trong ngày vẫn giới hạn một ngày. Cách viết khoảng ngày `3 -> 4` và `3 → 4` dùng cùng quy tắc chọn bốn ngày của khoảng `3–4`.

Chữ nội dung lịch trình/chat được tăng lên 14px, thông tin phụ và nút tối thiểu 12px, dùng màu chữ đậm hơn; nút Áp dụng có chữ trắng trên nền xanh. Kiểm tra Chrome hiện có 71 mục đạt, gồm tương phản chữ, nhấn Áp dụng bằng chuột, đổi 1 → 4 ngày, Bỏ qua, Hoàn tác, Lưu/mở lại, xem ảnh lớn, tạo lịch mới, chỉnh buổi chiều và bố cục laptop 1100px/điện thoại 390px. Các phản hồi AI trong kiểm tra trình duyệt là fixture cục bộ, không xác nhận nhà cung cấp đang hoạt động trực tiếp.

Ảnh địa điểm dùng renderer chung `photos.js` cho lịch trình và chat. Bấm ảnh mở hộp xem lớn, giữ thông tin tác giả/nguồn/giấy phép; đóng bằng nút ×, Escape hoặc bấm nền ngoài, rồi trả focus về ảnh. Các ảnh trong snapshot được xuất qua resource. Ảnh khi Áp dụng đề xuất AI được lưu bằng `photo_reference` gồm `message_id`, `day_number`, `item_index`: máy chủ kiểm tra chủ cuộc trò chuyện, điểm đến, tên và địa chỉ trước khi chép metadata ảnh. Không nhận URL/metadata ảnh tùy ý từ payload khách. Đổi địa điểm ẩn ảnh cũ; Hoàn tác khôi phục ảnh phù hợp.

Thẻ thiếu ảnh tải bổ sung khi gần vùng đang xem, tối đa hai lượt song song, kể cả địa điểm sau ba ảnh đầu và lịch đã lưu. Endpoint `POST /planner/place-photo` chỉ nhận ID hoạt động thuộc lịch của khách hoặc tham chiếu đề xuất thuộc cuộc trò chuyện của khách; tên/địa chỉ khách gửi chỉ dùng kiểm tra khớp, không dùng làm nguồn tra cứu. Việc lấy ảnh chỉ bổ sung cache, không sửa giờ/ngày, revision hay hoạt động. Lưu đề xuất chưa tải xong ảnh vẫn giữ thông tin nguồn để mở lại có thể tải tiếp. Lỗi mạng có nút Thử lại; chưa có ảnh phù hợp hiển thị rõ, không còn nút “Tìm ảnh địa điểm” thay ảnh. Dữ liệu Sheet thiếu ảnh tiếp tục giữ thiếu, không suy diễn ảnh từ tên.

`resources/content/itinerary/place-photos.json` chứa ảnh đã đối chiếu riêng theo tên và tọa độ, không thay đổi prompt hoặc dữ liệu lịch cũ. Thương Bạc tại Huế dùng ảnh Commons của Lưu Ly, Public domain, được tải về `public/image/ai-places/thuong-bac-hue.jpg`; ghi năm đăng nguồn 2008, không nhận là ảnh hiện tại. Bộ lấy ảnh Wikidata/Commons tiếp tục yêu cầu tên/biệt danh và tọa độ khớp, P18 và giấy phép rõ; Public domain không có LicenseUrl được dẫn tới trang nguồn công bố quyền.

`ActivityCopy` chuyển đúng các câu mẫu nghỉ ngơi/ăn trưa cũ sang lời gợi ý tự nhiên ở giao diện, chat và PDF; không chạy cập nhật dữ liệu ghi chú đã lưu. Prompt tạo lịch nhận thêm mô tả có nguồn và yêu cầu lời gợi ý hướng tới người đi, không bịa tiện ích. Chỉ hiển thị dòng chi phí khi có con số đã nhập hoặc giá tham khảo có nguồn; chi phí bỏ trống vẫn là `null`, không đổi thành miễn phí. Khách vẫn sửa ghi chú và nhập chi phí trong hộp chỉnh hoạt động. Bộ backend đạt 149 bài/1283 assertions trên MySQL cô lập, gồm giữ ảnh qua Lưu/mở lại và chặn tham chiếu ảnh của tài khoản khác.

Nút Tạo lịch mới đặt cạnh Lịch trình của tôi, xác nhận khi có thay đổi chưa lưu và chỉ xóa trạng thái trình soạn thảo; lịch đã lưu không bị xóa. Thư viện dùng icon tải lại hình vòng tròn bên trái nút đóng; nút tải lại lấy mới danh sách và giữ hộp thoại mở, mỗi lần mở cũng tự tải danh sách mới. Khi tạo AI, form hiển thị khung chờ và thời gian đã chờ thực, khóa gửi trùng, dừng khi thành công hoặc lỗi; không mô phỏng phần trăm hay giai đoạn nhà cung cấp.

Liên kết địa điểm ở lịch trình và chat mở Google Maps theo tên/địa chỉ; metadata nguồn Geoapify/OpenStreetMap vẫn giữ để truy nguyên dữ liệu. Danh sách tên khách sạn có nguồn được tách thành từng dòng và các liên kết Xem khách sạn dùng màu xanh.

`PlaceAvailability` lọc điểm có tín hiệu đóng vĩnh viễn trước khi đưa vào Groq, danh sách tư vấn và lịch trình. Chỉ nhận trạng thái rõ như `CLOSED_PERMANENTLY` / `permanently_closed`; tạm đóng cửa, giờ nghỉ hoặc thiếu trạng thái không bị coi là đóng vĩnh viễn. Tín hiệu từ nhà cung cấp được lưu vào `itinerary_place_exclusion`, nên xóa cache hoặc làm mới dữ liệu không tự mở lại điểm đã chặn. Khi nguồn trả nhiều bản ghi trùng tên, tín hiệu đóng cửa vẫn được ghi nhận. Điểm chặn chỉ được gỡ sau khi xem xét lại, không tự gỡ vì lần tra cứu sau thiếu trạng thái.

Các báo cáo đã xem xét nằm trong `resources/content/itinerary/place-exclusions.json`. Mục Google Maps “Thành cổ Điện Hải” do người dùng báo ngày 30/10/2026 được chặn tại Đà Nẵng theo các tên tương ứng, trạng thái `reported_closed`; không kết luận toàn bộ khu di tích đóng cửa và không chặn Bảo tàng Đà Nẵng ở địa chỉ khác. Các tín hiệu có tọa độ được đối chiếu thêm trong phạm vi 150 m để tránh chặn chi nhánh khác trùng tên. Danh sách hạn chế được trả kèm API điểm đến, để lịch sử chat không đưa lại card/đề xuất cũ và nút Áp dụng từ chối điểm bị chặn. Máy chủ cũng từ chối phát lại đề xuất có điểm bị chặn. Lịch đã lưu không bị xóa hoạt động: resource trả `excluded_from_ai` để hiện cảnh báo và cho khách tự chỉnh.

Giới hạn: Geoapify/OpenStreetMap chưa cung cấp trạng thái Google Maps theo thời gian thực cho mọi địa điểm. Trạng thái thiếu vẫn là chưa biết, không được trình bày thành đã xác minh đang mở cửa. Liên kết Google Maps phục vụ xem địa điểm, không đồng nghĩa có kết nối Google Places để kiểm tra trạng thái kinh doanh.

Yêu cầu rõ như “Ngày 1 cho tôi thêm lịch buổi chiều đi” được nhận trong ngữ cảnh lịch hiện tại dù mô hình diễn đạt lại `action_quote`. Bộ kiểm tra tạo lịch chung vẫn yêu cầu khách thực sự ra lệnh; yêu cầu phủ định không được tự nâng thành thao tác. Đề xuất theo buổi trả `target_window`, tạo trong khung tương ứng, giữ hoạt động ngoài khung và các ngày khác. Áp dụng giữ ID/ảnh/chi phí của hoạt động ngoài khung; có thể Bỏ qua hoặc Hoàn tác. Bộ backend hiện đạt 149 bài/1283 assertions. Rà dữ liệu ảnh đã khôi phục 21 tệp gốc còn được tham chiếu; 128 loại phòng của 40 khách sạn đang hoạt động đều có ảnh, 592 URL ảnh trả HTTP 200. Báo cáo chi tiết ở thư mục private `itinerary-diagnostics`.

## Tận dụng dịch vụ

Các yêu cầu được giữ cộng dồn theo [AGENTS.md](../AGENTS.md) và [danh sách đã chốt](itinerary-approved-requirements.md). Cập nhật hiện tại không làm lại ảnh, Maps, chỉnh sửa, Lưu/PDF hoặc cơ chế Áp dụng/Bỏ qua.

`PlanningPolicy` bổ sung chính sách vào bộ tạo lịch hiện có: số điểm tùy độ dài ngày (ngày dài thường 4–6, giảm khi có trải nghiệm dài hoặc khách yêu cầu nhẹ hơn), thời lượng gợi ý theo loại điểm/thông tin danh mục, chừa bữa ăn và thời gian chuyển điểm. Không chèn “Thời gian tự do” lấp cả buổi. Thiếu điểm phù hợp phải báo phần còn trống thay vì bịa trải nghiệm. Quãng đường/thời gian tuyến vẫn lấy từ Geoapify; phần dự phòng 5 phút khi đi bộ/xe đạp, 10 phút ô tô và làm tròn 5 phút được đánh dấu là giả định xếp lịch, không phải số đo giao thông. Các điểm cùng ID/Wikidata hoặc tên tương đương và rất gần nhau được gộp; không dùng riêng khoảng cách để gộp quán ăn hay các địa điểm độc lập.

Ngân sách là của cả nhóm/toàn chuyến. Ứng viên gửi Groq có giá vé theo nhóm chỉ khi tính được từ nguồn hiện có; xếp hạng theo sở thích và mức phù hợp ngân sách mỗi ngày. Backend kiểm tra tổng phần giá có nguồn, có thể thay điểm đắt bằng điểm có giá rõ rẻ hơn, không coi điểm thiếu giá là miễn phí. Ngưỡng vượt nhẹ 10%; vượt hơn mà chưa có phương án phù hợp trả lỗi thật. Khi chia chuyến dài thành nhiều lượt, phần đã dùng ở các ngày trước vẫn tính vào tổng. Giá ăn uống/lưu trú/di chuyển còn thiếu được ghi rõ; UI báo phần vượt biết được, không tuyên bố ngân sách tổng đã đủ.

Giờ hoạt động nay dùng dòng đồng hồ gọn ngay trên Maps và dropdown lịch tuần/nguồn/ngày kiểm tra. Trạng thái hiện tại tính theo đồng hồ/múi giờ nguồn, tách khỏi cảnh báo giờ ghé. Bỏ ô Ngày bắt đầu ghé theo yêu cầu rõ; dùng ngày đi sẵn có. Chi tiết và giới hạn ở [giờ hoạt động](place-opening-hours.md).

- `PlannerService` lưu vào các bảng `itinerary`, `itinerary_item`, lịch ngày, snapshot và dòng chi phí hiện có; dùng `TripService`, `WriteGuard` và `IdempotentWriter` để kiểm tra chủ sở hữu, chống ghi đè và xử lý gửi lại.
- Tạo AI gọi `ConversationalPlanner`/`GenerationService` hiện có với Groq và dữ liệu danh mục có nguồn. Ngày nội bộ chỉ dùng để tính lịch; cờ `is_provisional` bảo đảm giao diện/PDF không trình bày ngày đó là ngày khởi hành do khách chọn.
- Chat dùng nguyên bộ giao diện/streaming, lịch sử, `TravelAssistantService`, `TravelDialogue` và tra cứu danh mục. `planner_context` bổ sung lịch khách đang sửa. Kế hoạch của lượt chat này luôn là sơ bộ, không được `commit()` vào lịch khách đang mở. Khách chọn Áp dụng hoặc Bỏ qua; đề xuất được tạo trước một chỉnh sửa mới không được ghi đè chỉnh sửa đó.
- Khi đổi địa điểm thủ công, thông tin nguồn, tọa độ và giá của điểm cũ không được trình bày là thông tin đã xác minh cho điểm mới. Chi phí khách nhập luôn được đánh dấu dự kiến.
- AI không khả dụng trả lỗi thật. Khách có thể chủ động tiếp tục lịch thủ công. Không có dữ liệu AI minh họa trong mã sản phẩm.

## API bổ sung

Các đường dẫn dưới `/api/v2/itineraries`, dùng cùng Sanctum và middleware v2:

| Phương thức | Đường dẫn | Chức năng |
| --- | --- | --- |
| POST | `/planner/generate` | Tạo bản nháp bằng dịch vụ AI hiện có |
| POST | `/planner/place-photo` | Đọc ảnh từ nguồn đã lưu của khách; chỉ cập nhật cache ảnh |
| POST | `/planner` | Lưu lịch thủ công mới |
| PUT | `/{id}/planner` | Lưu thông tin và hoạt động, bắt buộc `expected_revision` |
| GET | `/{id}/pdf` | Tải PDF thật qua DomPDF, kiểm tra chủ sở hữu |

Các yêu cầu ghi cần `Idempotency-Key`. Khi lỗi AI, không tạo lịch giả hoặc ghi lịch trống thay cho kết quả AI. Các lịch trình chưa lưu có cảnh báo khi rời trang. PDF lưu các chỉnh sửa đang mở trước khi tải.

## Cấu trúc và kiểm thử

Các migration bổ sung cho trình soạn thảo và danh sách điểm bị chặn được chạy theo đường dẫn cụ thể. Không chạy lại toàn bộ migration:

```powershell
php artisan migrate --path=database/migrations/2026_10_29_000001_add_provisional_itinerary_flag.php --force
php artisan migrate --path=database/migrations/2026_10_30_000001_create_itinerary_place_exclusions.php --force
```

Kiểm thử backend dùng server MySQL cô lập theo hướng dẫn `itinerary-phase1-api.md`, sau đó:

```powershell
php vendor/bin/phpunit -c phpunit.itinerary-planner.xml --no-progress
node tests/Frontend/itinerary-planner.browser.cjs
```

Suite backend xuất Blade đã biên dịch cho bài kiểm tra Chrome; Chrome dùng API fixture trong bộ nhớ và chặn mọi yêu cầu ngoài máy cục bộ. API/PDF được kiểm thử thực trên Laravel và MySQL cô lập. Kiểm thử nhà cung cấp dùng HTTP fake, không xác nhận độ sẵn sàng của Groq/Geoapify trực tiếp.

Kiểm tra cập nhật ngày 31/10/2026: **190 bài backend / 1549 assertions và 92 thao tác trình duyệt đạt**. Gồm chính sách lịch/ngân sách, trạng thái giờ theo thời điểm/múi giờ, ca qua đêm/ngoại lệ, thiếu dữ liệu, trạng thái lịch sử cũ, dòng đồng hồ sát Maps, dropdown, ngày đi từ đề xuất, giữ cảnh báo sau sửa giờ/ngày/thứ tự, ảnh thật bấm xem lớn, Áp dụng/Bỏ qua, hoàn tác, lưu/mở lại, PDF, lịch cũ và laptop/điện thoại. Lượt này không gọi Groq thật hoặc xác nhận giờ mở thực tế; không ghi dữ liệu kiểm thử vào DB/Sheet thật.
