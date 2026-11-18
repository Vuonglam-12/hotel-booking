# Giờ hoạt động theo ngày và đồng bộ 15 phút

Đã bổ sung code đọc toàn tab địa điểm, lịch giờ theo ID, bộ đối chiếu backend và UI chọn ngày/giờ. Không đổi hoặc xóa cột cũ trong `Dia_diem`. Service Account tiếp tục dùng scope `spreadsheets.readonly`; không có lệnh ghi Sheet, không nâng quyền Viewer.

## Thêm tab Gio_hoat_dong

Người có quyền sửa Sheet tạo tab `Gio_hoat_dong`. Có thể cấu hình tên khác bằng `GOOGLE_SHEETS_HOURS_TAB`. Reader kiểm tra danh sách tab bằng API chỉ đọc trước khi đọc giờ. Tab chưa tồn tại thì báo `hours_tab_state: missing` và giữ lịch giờ đã đồng bộ trước đó; lỗi API khi đọc tab thì hủy toàn bộ lượt đồng bộ, giữ snapshot trước.

Header đề xuất, có thể đổi thứ tự:

```csv
place_id,rule_type,weekday,date,state,opens_at,closes_at,end_day_offset,timezone,data_quality,source_url,checked_at,notes
```

| Cột | Ý nghĩa |
| --- | --- |
| place_id | ID đúng của địa điểm/chi nhánh trong Dia_diem, ví dụ DL001; không phải ID DB |
| rule_type | `weekly` hoặc `exception` |
| weekday | Thứ Hai=1 … Chủ nhật=7; chỉ dùng cho weekly |
| date | `YYYY-MM-DD`; chỉ dùng cho exception |
| state | `open`, `closed`, `24h`, `unknown` |
| opens_at / closes_at | `HH:MM`; chỉ dùng khi open |
| end_day_offset | `0` cùng ngày; `1` kết thúc ngày hôm sau; bắt buộc với open |
| timezone | Múi giờ IANA; các điểm hiện tại dùng `Asia/Ho_Chi_Minh` |
| data_quality | `reference` khi nguồn giờ đủ rõ, `unclear` khi mơ hồ/mâu thuẫn |
| source_url / checked_at / notes | Nguồn, ngày đọc nguồn, ghi chú; không phải xác nhận mở cửa thực tế |

Mỗi ca là một dòng. Ví dụ hai ca thứ Hai dùng hai dòng cùng place_id, weekly, weekday=1: 08:00–12:00 và 14:00–18:00. `closed` nghỉ trọn ngày; `24h` mở 00:00 đến 00:00 hôm sau theo lịch tham khảo; hai trạng thái này để trống opens_at/closes_at. `unknown` hoặc không có dòng cho thứ/ngày đó nghĩa là chưa rõ giờ, không phải nghỉ cả ngày.

Trong Sheets, đặt date dạng văn bản hoặc định dạng `yyyy-mm-dd`, giờ dạng `hh:mm` hoặc văn bản HH:MM. Giờ rõ dạng `8:00` cũng được chuẩn hóa thành `08:00`; các diễn đạt như “sáng đến tối”, “tùy hôm” hoặc hai giờ mâu thuẫn không được suy thành ca hoạt động.

Ca 22:00–02:00 phải có `end_day_offset=1`. Giờ kết thúc lúc nửa đêm ghi 00:00 và offset=1. Không tự suy ra offset từ giờ kết thúc nhỏ hơn giờ bắt đầu. Ngoại lệ ngày cụ thể thay toàn bộ lịch tuần của ngày đó, bao gồm phần ca qua đêm từ hôm trước. `closed` cũng áp dụng toàn bộ ngày địa phương; không khai báo ngày kế tiếp nghỉ cả ngày nếu muốn giữ ca đêm kéo dài vào ngày đó. Ngoại lệ có thể mở lại một Chủ nhật thường nghỉ, gồm nhiều ca riêng. Ca chồng nhau hoặc open/closed mâu thuẫn được đánh dấu chưa rõ cho địa điểm, không tạo giờ đã xác minh.

Mẫu riêng: [Gio_hoat_dong.csv](../examples/google-sheets/Gio_hoat_dong.csv). **Mọi dòng DEMO_PLACE_001 là minh họa**, không phải giờ của địa điểm thật, không được dùng nguyên mẫu để gán cho DL001. Có thể chỉ sao chép header; điền ID/giờ thật sau khi đọc nguồn. Toàn bộ tab chỉ có ID không tồn tại sẽ làm lượt đồng bộ thất bại thay vì xóa lịch giờ trước. Tab chỉ có header có nghĩa là xóa lịch giờ cấu trúc của nguồn; địa điểm và các trường khác vẫn giữ nguyên. Không phân tích `Giờ hoạt động tham khảo` dạng chữ để điền tab này tự động.

```powershell
php artisan migrate --path=database/migrations/2026_10_31_000002_add_source_place_opening_schedule.php --force
php artisan places:sync-sheet --dry-run
php artisan places:sync-sheet
```

Migration chỉ thêm JSON `opening_schedule` vào bảng nguồn. ID nguồn và khóa `(source, external_id)` giữ nguyên. Đồng bộ giờ cập nhật đúng bản ghi, không tạo địa điểm trùng, không xóa giá/điện thoại/địa chỉ. Chạy lại không đổi dữ liệu thì không ghi lại các dòng. Dòng giờ sai có báo số dòng và lý do; lỗi API/header/nguồn làm giữ toàn bộ snapshot thành công gần nhất.

## UI và đối chiếu lịch trình

Theo yêu cầu giao diện mới nhất, đã bỏ ô **Ngày bắt đầu ghé (để đối chiếu giờ hoạt động)** khỏi bảng lịch. Bộ đối chiếu dùng ngày đi sẵn có trong bản ghi hoặc luồng hội thoại/đề xuất, chỉ đổi lịch khi khách Áp dụng. Nếu chưa có ngày thật, không dùng ngày neo nội bộ của lịch sơ bộ để kết luận giờ ghé phù hợp. Thẻ nguồn giữ thao tác đối chiếu **Ngày ghé** trong phần xổ xuống khi có dữ liệu giờ; **Thêm vào lịch** vẫn mở hộp chỉnh hoạt động, cho chọn ngày thứ mấy, giờ bắt đầu/kết thúc và **Kết thúc vào ngày hôm sau**.

Thẻ lịch trình, gợi ý địa điểm và timeline chat dùng chung `public/vendor/aurel-ai/hours.js`: một dòng đồng hồ ngay trên Maps, mũi tên mở lịch từng thứ. Nguồn, ngày kiểm tra nguồn, ngoại lệ, giờ dạng chữ, thông tin đối chiếu ngày ghé và lưu ý giờ tham khảo ở trong phần xổ xuống; cảnh báo giờ ghé ngắn nằm sau Maps. Thiếu toàn bộ dữ liệu chỉ hiện “Chưa có thông tin giờ hoạt động”, trung tính, không tạo lịch giả để có dropdown.

`OpeningHours::display()` tính trạng thái theo `CarbonImmutable::now()` chuyển sang múi giờ IANA của lịch nguồn. Dùng cùng quy tắc ca/ngoại lệ/qua đêm của bộ đối chiếu, nhưng không dùng giờ ghé hay ngày trong lịch trình để suy ra trạng thái hiện tại. Đủ dữ liệu mới hiện “Đang mở cửa · Đóng cửa vào …” hoặc “Đang đóng cửa · Mở cửa vào …”; giờ dạng chữ/mơ hồ hoặc chưa có quy tắc cho hôm nay chỉ hiện “Giờ hoạt động: …”. Lịch 24h và ca qua đêm giữ đúng ý nghĩa tham khảo. Ngày `checked_at` được ghi “Ngày kiểm tra nguồn”; không lấy ngày chạy đồng bộ để giả làm ngày quán cập nhật giờ.

Khi đang xem lịch, giao diện làm mới dữ liệu giờ mỗi phút. Dòng trạng thái lịch sử quá hai phút không còn được dùng như trạng thái hiện tại: chuyển về nhãn chưa xác nhận, giữ lịch tuần trong dropdown. Nguồn thiếu giờ hoặc bị ngừng gợi ý không được chuyển thành trạng thái mở cửa giả.

Backend đối chiếu toàn bộ khoảng ghé với các ca liên tục, kể cả phần sang ngày kế tiếp. Khoảng 11:00–15:00 không phù hợp ca 08:00–12:00 / 14:00–18:00, dù giờ đến nằm trong ca. Kết thúc đúng giờ đóng ca được chấp nhận. Khoảng ghé vắt qua ngoại lệ nghỉ của ngày hôm sau bị cảnh báo. Múi giờ lấy từ lịch nguồn, không lấy múi giờ máy trình duyệt.

Đổi ngày, giờ, số thứ tự, thêm/xóa, Áp dụng AI hoặc Hoàn tác đều yêu cầu đối chiếu lại qua endpoint chỉ đọc `POST /api/v2/itineraries/planner/check-hours`. UI bỏ phản hồi cũ nếu khách đã chỉnh tiếp. Khi Lưu/mở lại/PDF, resource backend tính lại từ lịch giờ nguồn hiện tại. Cảnh báo và gợi ý không tự sửa giờ hoặc ghi DB. Khách quyết định đổi giờ/địa điểm. Nguồn chưa có giờ rõ hiển thị “Chưa có thông tin giờ hoạt động”; giờ dạng chữ vẫn hiện với nhãn tham khảo.

Khi tạo lịch có ngày thật, backend loại điểm chắc chắn không có ca phù hợp khỏi ứng viên của ngày, hoặc đề xuất điểm thay thế còn phù hợp. Có thể chèn khoảng nghỉ để chờ ca sau nếu toàn bộ thời lượng ghé vẫn vừa khung chuyến đi; không ép một lượt ghé qua giờ nghỉ. Nếu không có phương án phù hợp, trả lỗi thật để khách đổi ngày/điểm. Với điểm Geoapify có danh tính tên/địa chỉ khớp duy nhất một dòng nguồn, lịch giờ cấu trúc được gắn để đối chiếu; không lấy tọa độ thiếu của Sheet để tính tuyến. Không để Groq tự tạo giờ mở cửa.

Kết quả đối chiếu giờ ghé và dòng trạng thái theo đồng hồ đều dựa trên **lịch tham khảo**, không phải xác nhận thực tế cơ sở đang mở. Dòng trạng thái hiện tại là bổ sung trình bày được người dùng yêu cầu rõ; không biến kết quả kiểm tra một lịch ghé tương lai thành trạng thái hiện tại. Ngày đọc nguồn và thời gian job chạy không phải ngày cơ sở vừa cập nhật.

## Vận hành scheduler

Trong `app/Console/Kernel.php` đã khai báo `places:sync-sheet` mỗi 15 phút (`*/15 * * * *`), `withoutOverlapping(10)` và log `storage/logs/place-sheet-sync.log`. Command còn có khóa nguồn 300 giây để chặn lệnh thủ công chạy chồng. Mỗi report có `checked_at` và `dry_run` để phân biệt lượt đọc kiểm tra với lượt đồng bộ.

**Khai báo lịch không tạo tiến trình chạy lịch.** Trong lần làm này không cài Windows Task Scheduler, cron hoặc khởi động service nền; chưa xác minh tiến trình scheduler thật trên máy. `php artisan schedule:list` chỉ chứng minh cấu hình đã đăng ký, không chứng minh job đã chạy tự động. Đã thử chạy command trực tiếp và dry-run; không chạy toàn bộ scheduler trên dữ liệu thật vì còn các job cũ trong ứng dụng.

### Laragon/Windows

Để chạy khi phát triển, mở terminal ở thư mục project và giữ tiến trình sau hoạt động:

```powershell
Set-Location C:\laragon\www\Hotel-Booking
php artisan schedule:work
```

Đóng terminal/dừng tiến trình thì lịch dừng. Để chạy độc lập terminal, tạo Windows Task Scheduler task bằng tài khoản có quyền đọc credentials:

- Program: đường dẫn PHP thật, hiện là `C:\laragon\bin\php\php-8.2.31-Win32-vs16-x64\php.exe`.
- Arguments: `artisan schedule:run`.
- Start in: `C:\laragon\www\Hotel-Booking`.
- Trigger: chạy hằng ngày, lặp mỗi 1 phút, không giới hạn thời gian lặp; đặt không tạo instance mới nếu lượt trước chưa xong.
- Kiểm tra task Enabled, Last Run Time, Last Run Result, quyền filesystem của tài khoản chạy và log trên sau mốc 00/15/30/45.

Chỉ dùng một cơ chế (schedule:work hoặc task mỗi phút) trên máy này. Scheduler chạy cả các job cũ đã đăng ký. Không đổi scope hoặc quyền Sheet để chạy scheduler.

### Production

Tạo cron mỗi phút bằng user ứng dụng, thay đường dẫn theo server thật:

```cron
* * * * * cd /var/www/angel && /usr/bin/php artisan schedule:run >> /var/www/angel/storage/logs/scheduler-run.log 2>&1
```

Chỉ đặt cron trên một host; nếu nhiều host, dùng cache atomic lock dùng chung (ví dụ Redis) và thiết kế một scheduler duy nhất. Bảo đảm thư mục storage/cache ghi được và credentials vẫn nằm ngoài webroot. Sau deploy chạy `php artisan config:clear` (hoặc cache config theo quy trình deploy), `php artisan schedule:list`, kiểm tra log thực sau giờ đến hạn. Cần log `dry_run:false` có checked_at mới để xác minh hoạt động, không chỉ nhìn thấy biểu thức cron.

## Groq HTTP 400

Log cũ ngày 31/10/2026 chỉ còn `status:400, code:groq_unavailable`. File chẩn đoán trạng thái đã bị lượt sau ghi đè thành HTTP 200; không còn provider error code, request ID hoặc response lỗi gốc để phân biệt lỗi JSON, tham số, context hay trường hợp khác. **Chưa đủ bằng chứng xác định nguyên nhân và chưa tuyên bố đã sửa lỗi 400.** Lượt 200 sau đó không phải bằng chứng xử lý xong lượt 400.

Luồng Groq không thêm retry mù cho HTTP 400. Đã bổ sung metadata an toàn khi lỗi: status, mã lỗi nội bộ, provider error code trong allowlist, request ID có định dạng kiểm tra, model, max_tokens và response_format. Không log Authorization/API key, prompt đầy đủ, error message hoặc failed_generation. Bài kiểm thử chứng minh chỉ gọi một lần khi nhận 400 và dữ liệu nhạy cảm không vào log. Khi tái diễn, có thể dùng metadata mới để điều tra nguyên nhân cụ thể; chỉ sửa sau khi có bằng chứng.

## Kiểm chứng và giới hạn

Sheet thật hiện vẫn có 50 địa điểm hợp lệ; tab Gio_hoat_dong chưa tồn tại, dry-run báo missing và không làm mất dữ liệu. Bản ghi thứ 51, sửa bản ghi, chạy lại không trùng, API lỗi, khóa chạy chồng và cập nhật giờ được kiểm tra trong DB/HTTP fixture cô lập, không sửa Sheet thật và không đưa bản ghi mẫu vào nguồn thật. Chưa xác minh thêm/sửa dòng mới trực tiếp trên Sheet vì quyền hiện tại chỉ đọc.

Bộ hồi quy hiện đạt 174 bài backend. Các ca chuyên biệt gồm nghỉ Chủ nhật, ngoại lệ nghỉ/mở lại, nhiều ca, khoảng ghé vắt qua giờ nghỉ, phần qua đêm sang ngày sau, 24 giờ, thiếu giờ, cập nhật lịch nguồn, giữ nguyên snapshot khi lỗi và Groq 400 không retry. Kiểm tra Chrome gồm 83 thao tác, trong đó xác nhận cảnh báo không tự sửa giờ khách, đổi ngày/thứ tự kiểm tra lại đúng hoạt động và ngày chọn trên thẻ nằm ngoài lịch không ghi đè lịch hiện tại.

Chrome kiểm tra thay đổi ngày, giờ, thứ tự, ngoại lệ và ca qua đêm bằng chính engine PHP qua máy chủ fixture, không dùng một bản tính giờ riêng trong JavaScript. Chưa xác minh giờ hoạt động của cơ sở ngoài thực tế, chưa kiểm tra tự chạy liên tục 15 phút trên Windows/production và chưa xác định nguyên nhân lượt Groq 400 cũ.
