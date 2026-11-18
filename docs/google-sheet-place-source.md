# Nguồn địa điểm Google Sheet

Laravel đọc Google Sheets bằng Service Account với scope duy nhất `spreadsheets.readonly`. Dùng HTTP client Laravel/Guzzle và OpenSSL có sẵn, không thêm dependency. File credentials nằm ngoài project; chỉ đường dẫn được cấu hình. Không lưu token vào DB/cache/log, không in thông tin khóa và không ghi lên Sheet.

## Cấu hình

Các biến được đọc qua `config/services.php` → `services.place_sheet`:

```dotenv
GOOGLE_SHEETS_SPREADSHEET_ID=1FC8cWrCfI9YoTynJ0qZ1NBtUQM_o7RH8dbz56h_hdt8
GOOGLE_SHEETS_DATA_TAB=Dia_diem
GOOGLE_SHEETS_GUIDE_TAB=Huong_dan
GOOGLE_SHEETS_HOURS_TAB=Gio_hoat_dong
GOOGLE_SHEETS_RANGE=A1:ZZ
GOOGLE_SHEETS_LOCATION_NAME="Đà Lạt"
GOOGLE_SHEETS_CREDENTIALS=C:/laragon/secrets/angel/gen-lang-client-0662546816-682d74389493.json
```

Reader hiện đọc **toàn tab** `Dia_diem`, không cố định ô A1, dòng 51 hay cột cuối. `GOOGLE_SHEETS_RANGE` được giữ để tương thích cấu hình cũ nhưng không giới hạn lần đọc đầy đủ này. `Huong_dan` luôn được đọc trước `Dia_diem`. API dùng `FORMATTED_VALUE` để giữ số điện thoại có số 0 đầu. Chỉ bỏ khoảng trắng khi chuẩn hóa điện thoại; giá trống là null, giá 0 được giữ. Header được đối chiếu theo tên, có thể đổi thứ tự cột. Hướng dẫn thiếu mục giới hạn nguồn/giá/Maps/tọa độ/điểm đến thì dừng đồng bộ.

## Chạy

```powershell
php artisan config:clear
php artisan places:sync-sheet --dry-run
php artisan migrate --path=database/migrations/2026_10_31_000001_create_travel_source_places.php --force
php artisan places:sync-sheet
php artisan places:sync-sheet
```

Dry-run có thể chạy trước khi tạo bảng và không ghi DB. Nguồn đồng bộ nằm trong bảng riêng `travel_source_place`; unique `(source, external_id)` và content hash giúp chạy lại không tạo trùng hoặc ghi lại dòng không đổi. Không sửa bảng `attraction` hoặc Location cũ. Tên Location phải khớp duy nhất trong DB, không hardcode ID. Cập nhật chỉ tác động đúng nguồn Sheet. Dòng đóng/vô hiệu hóa bị tắt gợi ý; dòng bị loại/xóa trong lần đọc đầy đủ thành công cũng bị vô hiệu hóa ở nguồn này, không xóa lịch sử. Trùng ID hoặc đọc Sheet lỗi thì không ghi; DB transaction bảo đảm giữ snapshot thành công trước đó.

Trạng thái hiện tại “Có thông tin hoạt động trên nguồn công khai” chỉ cho phép tham khảo. Các trạng thái lạ không tự coi là đang mở. Không lấy giờ nghỉ ngoài giờ/hằng tuần trong cột giờ để kết luận đóng vĩnh viễn. Không thêm trường openNow.

## Chatbot

`TravelCatalog` ưu tiên nguồn Sheet phù hợp cho địa phương, lọc nhóm ăn uống/cà phê/vui chơi/tham quan, nhu cầu ăn chay và xếp theo thẻ trước khi giới hạn tối đa 12 ứng viên. Chỉ gửi ID/tên/mô tả/địa chỉ và metadata nguồn cần thiết cho Groq. Các trường URL Maps, điện thoại, giá và tọa độ không được giao cho mô hình tự tạo. Groq phải trả ID thuộc tập đã gửi; ID lạ bị từ chối. Backend đọc lại bản ghi đang được phép gợi ý để trả thông tin và URL đúng hàng, kể cả khi phát lại yêu cầu. Lịch sử hội thoại được giữ; các card Sheet bị vô hiệu hóa được ẩn khi đọc lại.

Nút **Truy cập địa điểm** mở đúng `maps_url`, kiểm tra HTTPS, hostname và đường dẫn Maps; không tạo URL từ tên khi URL Sheet không hợp lệ. Cho phép Google Maps chính thức và link rút gọn Maps (`maps.app.goo.gl`, `goo.gl/maps/`). Không tự theo redirect để coi link rút gọn đã đúng ghim. Giao diện có nhãn link tìm theo tên/địa chỉ và lời nhắc xác nhận trước khi đi. Giá chỉ hiển thị khi có số nguồn, kèm đơn vị/phạm vi; số điện thoại chỉ là số công khai.

Tọa độ/ảnh thiếu được giữ null. Không bổ sung từ Geoapify, không tìm ảnh thay thế cho bản ghi Sheet, không đưa các dòng thiếu tọa độ vào tính tuyến chính xác. Luồng tạo lịch và các nguồn cũ vẫn giữ nguyên. Tab giờ theo ID được dùng để đối chiếu khoảng ghé; giờ dạng chữ vẫn được giữ riêng, không tự suy ra lịch cấu trúc. Xem [giờ hoạt động và scheduler](place-opening-hours.md) để thêm tab, chạy migration mới và vận hành lịch 15 phút.

## Kiểm chứng thật ngày 31/10/2026

- File credentials đúng đường dẫn đã tồn tại; không cần đổi tên hoặc sao chép.
- Đọc được 26 dòng `Huong_dan`, 51 dòng `Dia_diem` gồm header và 50 địa điểm.
- Dry-run: 50 hợp lệ, 0 bỏ qua; Location thật là Đà Lạt (ID 7 được tra từ DB).
- Đồng bộ đầu: thêm 50. Lần hai: thêm 0, sửa 0, không đổi 50.
- 29 dòng thiếu giá thấp, 50 dòng thiếu tọa độ và ảnh vẫn giữ null. 49 link tìm kiếm và 1 link rút gọn Maps được giữ nguyên, chỉ xác minh cấu trúc URL.
- Kiểm tra nhu cầu cà phê gửi 12 ứng viên Đà Lạt cho Groq; Groq thật trả 4 ID hợp lệ và các ID khớp URL Maps/điện thoại bản ghi gốc. Có một lượt Groq HTTP 400 trước lượt kiểm tra HTTP 200; không mô phỏng kết quả khi nhà cung cấp lỗi.

Kiểm thử hồi quy: 160 bài backend trên MySQL cô lập và 73 kiểm tra Chrome, gồm quyền đọc Sheet, thứ tự đọc hướng dẫn, null/0/điện thoại, ID giả, URL độc hại, đồng bộ lại, lỗi nguồn, dữ liệu nguồn cũ và lịch sử chat. Bài Chrome dùng fixture cục bộ và chặn kết nối ngoài.

Chưa xác minh tại chỗ/qua điện thoại, openNow, độ chính xác từng ghim, quyền sử dụng ảnh mới hay tuyến đường từ bộ dữ liệu thiếu tọa độ. Chưa đánh giá độ ổn định Groq dài hạn sau một lượt kiểm tra thành công.

## Nguồn chính cho lịch Đà Lạt — cập nhật 04/11/2026

Theo yêu cầu mới, `TravelCatalog::pool()` dùng nguồn Sheet đã đồng bộ trong MySQL làm nguồn địa điểm chính cho địa phương `GOOGLE_SHEETS_LOCATION_NAME` (hiện là Đà Lạt). Nhánh này chạy trước việc tra ranh giới/địa điểm Geoapify; không yêu cầu khóa Geoapify và không quay về nguồn Geoapify nếu thiếu dữ liệu Sheet. Các địa phương khác tiếp tục dùng luồng hiện có. Đồng bộ Sheet vẫn chỉ đọc và vẫn giữ snapshot thành công gần nhất khi nguồn lỗi.

`SheetPlaceCatalog::forPlanning()` đọc toàn bộ các hàng hợp lệ, đang bật, đúng nguồn/Location; nhánh tư vấn giữ giới hạn tối đa 12 thẻ. Backend chọn ứng viên theo sở thích, đồng thời giữ đại diện các nhóm ăn uống/cà phê/vui chơi, rồi gửi mô tả ngắn gọn cho Groq. Việc rút gọn này chỉ tác động yêu cầu gửi AI, không cắt dữ liệu nguồn hoặc snapshot. ID ngoài danh sách và điểm trùng tiếp tục bị từ chối.

Nguồn hiện có 50 địa điểm đang bật, 50 thời lượng ghé gợi ý và 0 cặp tọa độ. Lịch giữ các điểm thiếu tọa độ; mỗi chặng chỉ được gắn khoảng đệm sắp lịch 30 phút, `time_seconds`/`distance_metres` là null và trạng thái tuyến là chưa xác minh. Lưu ánh xạ nguồn dưới provider `google_sheet`, không ghi đè ánh xạ Geoapify hoặc xác nhận ranh giới hành chính. Khi quỹ giờ không đủ, cân đối lại điểm theo thời lượng nguồn và báo rõ; không rút ngắn thời lượng nguồn để làm vừa lịch.

Đề xuất và dữ liệu mở lại giữ `provider`, `source_place_id`, `maps_url`, `maps_link_type`, `price_reference` cùng các trường giờ hoạt động. Áp dụng đề xuất rồi lưu lấy lại metadata từ hàng nguồn thuộc đúng Location. Trường Maps của nguồn khác tiếp tục dùng cách xử lý cũ. Bản đồ ngày chưa có tọa độ hiển thị các liên kết Maps từng điểm thay vì gọi tra ranh giới hoặc vẽ tuyến.

Bằng chứng kiểm tra thực tế (không ghi DB/Sheet):

- Lấy đủ 50 địa điểm nguồn và 50 URL Maps hợp lệ khi tắt khóa Geoapify riêng trong tiến trình kiểm tra; tư vấn vẫn giới hạn 12 ứng viên.
- Groq thật trả HTTP 200; tạo được đề xuất 2 ngày, 2 người lớn, 15–16/11/2026: mỗi ngày 3 điểm, kết thúc lần lượt 15:45 và 16:45. Tất cả điểm thuộc Sheet, Maps và thời lượng khớp hàng nguồn; 4 chặng có nhãn khoảng đệm chưa xác minh. Không có yêu cầu HTTP nào gửi Geoapify.
- Đã phát hiện và xử lý yêu cầu Groq quá lớn (HTTP 413) bằng thu gọn ứng viên/mô tả, và lỗi vượt quỹ giờ bằng cân đối thời lượng nguồn. Đây là bằng chứng một lượt tạo đề xuất thành công, không khẳng định độ ổn định dài hạn của AI.
- Kiểm tra cú pháp PHP/JavaScript các tệp liên quan. Không chạy bộ test tự động, không tạo lịch thử trong DB; chưa xác nhận thao tác trình duyệt lưu/mở lại/PDF trong lần thay đổi này.

Danh sách tự kiểm tra:

1. Chọn Đà Lạt, tạo lịch 1–2 ngày và thử cả không nhập/có nhập ngân sách. Điểm phải lấy từ danh sách Sheet, không báo lỗi ranh giới Geoapify; chi phí thiếu vẫn phải được báo thiếu.
2. Yêu cầu đổi riêng ngày/buổi; kiểm tra các phần ngoài phạm vi vẫn giữ nguyên và đề xuất chỉ đổi lịch khi bấm Áp dụng. Thử Bỏ qua và hoàn tác.
3. Lưu rồi mở lại lịch, mở Maps và tải PDF; kiểm tra đúng tên/chi nhánh, URL Maps, giờ tham khảo và ghi chú tuyến chưa xác minh. Thử áp dụng đề xuất chat rồi lưu.
4. Khi có quyền sửa Sheet, người dùng có thể đánh dấu một điểm không hoạt động, chờ đồng bộ rồi kiểm tra điểm đó không còn được gợi ý; Codex tiếp tục không ghi Sheet.
5. Thử điểm đến khác Đà Lạt để kiểm tra nguồn và tính tuyến cũ, cùng các lịch đã lưu trước thay đổi.

## Bổ sung Phú Quốc — 04/11/2026

Phú Quốc dùng chung luồng Sheet chính với Đà Lạt, qua `SheetPlaceSources`. Các thao tác tư vấn/tạo lịch đi vào nhánh Sheet trước việc tra ranh giới hoặc địa điểm Geoapify; không tự quay lại nguồn Geoapify nếu Sheet thiếu dữ liệu. Nguồn giờ, nạp lại thẻ chat, ánh xạ snapshot và URL Maps đều được lọc theo khóa nguồn và Location tương ứng.

| Địa phương | Tab đọc thực tế | Hậu tố khóa nguồn ổn định | Dòng trong MySQL | Được phép gợi ý |
| --- | --- | --- | ---: | ---: |
| Đà Lạt | Đà Lạt | Dia_diem | 82 | 50 |
| Phú Quốc | Phú Quốc | Phú Quốc | 80 | 50 |

Đây là số liệu lượt đồng bộ 18:48 ngày 04/11/2026, không phải số cố định. Sheet thay đổi từ 50 lên 82/80 dòng trong quá trình thực hiện. Khóa cũ `Dia_diem` được giữ khi tên tab đổi thành `Đà Lạt`, nên 50 ID nguồn Đà Lạt ban đầu và các liên kết cũ không bị tạo lại. Một dòng tạm vắng ở lượt đọc trước đã được cập nhật lại khi nguồn đưa vào lại; các dòng chờ đối chiếu không bị xóa hay tự bật.

Giữ nguyên các biến cấu hình cũ. Nguồn Phú Quốc được bổ sung trong `services.place_sheet.additional_sources` với:

```dotenv
GOOGLE_SHEETS_PHU_QUOC_DATA_TAB="Phú Quốc"
# Chỉ đặt khi Phú Quốc nằm ở workbook khác; bỏ biến này để dùng workbook hiện tại.
# GOOGLE_SHEETS_PHU_QUOC_SPREADSHEET_ID=
```

Mỗi nguồn có `location_name`, tab dữ liệu, workbook, tab hướng dẫn/giờ và tiền tố ID rõ ràng. `DL` chỉ được đồng bộ vào nguồn Đà Lạt, `PQ` vào nguồn Phú Quốc; Location được tra theo tên thật. `Huong_dan` dùng chung hiện vẫn nhắc Đà Lạt ở dòng “Điểm đến trong DB”; ánh xạ Phú Quốc được xác định bằng cấu hình riêng và kiểm tra tiền tố, không suy đoán Location hoặc sửa hướng dẫn trên Sheet.

Nhãn “Có trong nguồn công khai; cần kiểm tra hoạt động” được nhận diện là đủ để gợi ý **tham khảo**, vẫn có thông báo cần kiểm tra lại và không có xác nhận đang mở cửa. Nhãn “Cần đối chiếu hoạt động trước sử dụng” chưa được phép gợi ý: 32 dòng Đà Lạt và 30 dòng Phú Quốc tiếp tục bị lọc. Cờ tắt rõ ràng và các trạng thái đóng cửa/vô hiệu hóa vẫn được giữ ưu tiên.

Lệnh `places:sync-sheet` và lịch 15 phút hiện có đọc cả hai nguồn. Lấy khóa chống chồng từng nguồn, đọc/kiểm tra cả hai trước khi ghi, sau đó dùng một transaction; lỗi API/tab/ID/Location không cập nhật dở nguồn còn lại. Báo cáo nhiều nguồn có `source_reports` và các bộ đếm tổng; dry-run vẫn không ghi dữ liệu địa điểm. Trước khi đồng bộ, đã sao lưu 50 bản ghi nguồn ban đầu ở `storage/app/private/backups/place-sheet-before-phu-quoc-20261104-184505.json`; không có credentials trong bản sao lưu này.

Bằng chứng xác nhận:

- Đã ghi nguồn Phú Quốc vào MySQL, giữ nguồn/ID Đà Lạt. Sau lượt cập nhật cuối: 162 dòng nguồn, 100 dòng được phép gợi ý; không tạo khách sạn, phòng, booking hoặc lịch trình thử.
- Nạp được danh mục tạo lịch của cả hai địa phương khi tắt khóa Geoapify riêng trong tiến trình kiểm tra. Toàn bộ ID đúng tiền tố của địa phương, không trộn dữ liệu.
- Groq thật trả HTTP 200 và lập đề xuất Phú Quốc 2 ngày cho 2 người lớn, 15–16/11/2026: ngày đầu 2 điểm đến 15:45, ngày sau 3 điểm đến 17:15. Tất cả địa điểm thuộc nguồn Phú Quốc; URL Maps và thời lượng khớp hàng nguồn. Không có yêu cầu HTTP nào gửi Geoapify trong lượt tạo đề xuất; không lưu lịch thử.
- Cả hai nguồn vẫn thiếu tọa độ và tab giờ cấu trúc; tiếp tục dùng khoảng đệm 30 phút ghi rõ chưa xác minh, giữ giờ dạng chữ và giá/ảnh thiếu. Không xác minh tình trạng hoạt động trực tiếp.
- Kiểm tra cú pháp PHP, rà nguồn và đối chiếu DB; không chạy bộ test tự động. Chưa xác nhận thao tác trình duyệt lưu/mở lại/PDF cho lần bổ sung Phú Quốc.
- Lượt dry-run sau đồng bộ vẫn đọc đủ 162 dòng và không có dòng cần thêm; phát hiện 3 dòng nội dung đã tiếp tục đổi trên Sheet (2 Đà Lạt, 1 Phú Quốc). Đây là thay đổi nguồn đang diễn ra, không phải trùng bản ghi; lượt đồng bộ kế tiếp sẽ cập nhật theo lịch hiện có. API xem giờ riêng từng địa điểm cũng nhận đúng cả hai nguồn theo Location.

Kiểm tra thủ công: tạo lịch/tư vấn Phú Quốc và mở Maps; lưu/mở lại, tải PDF; thử sửa riêng ngày/buổi rồi Áp dụng/Bỏ qua và hoàn tác; đối chiếu Đà Lạt sau khi đổi tên tab; chạy dry-run khi Sheet ổn định để kiểm tra không có thay đổi/trùng; kiểm tra một nguồn lỗi không làm ghi dở nguồn khác. Không bật các dòng còn chờ đối chiếu để làm đủ số điểm.
