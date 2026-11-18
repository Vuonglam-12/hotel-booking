Báo cáo triển khai Profile/Tài khoản AUREL — Giai đoạn 1, ngày 19/10/2026

Đây là báo cáo của đợt triển khai đầu. Hai hạng mục PARTIAL về token cũ và ngày sinh đã được hoàn thiện về code sau phê duyệt tiếp theo; xem [báo cáo hoàn thiện Giai đoạn 1](customer-account-phase1-completion-report.md) để biết chính sách hiện tại, migration chờ áp dụng và kết quả kiểm thử mới. Các mô tả/kết quả bên dưới ghi nhận trạng thái của đợt đầu.

Đã sửa các điểm tích hợp nghiệp vụ và bảo mật trong phạm vi được duyệt. Trạng thái tổng thể: **PARTIAL**, do không thể phân loại nguồn của token `auth_token` cũ và chưa có phê duyệt bổ sung schema ngày sinh. Các sửa chữa còn lại đã hoàn tất và kiểm thử bằng dữ liệu synthetic.

| Hạng mục | Lỗi gốc | Cách xử lý | Trạng thái |
|---|---|---|---|
| Đăng xuất | Sidebar gọi sai endpoint; xóa phiên phía trình duyệt dù server chưa xác nhận | Gọi `POST /api/logout` bằng đúng token đã chụp; server trả `revoked: true` sau khi thu hồi. Lỗi mạng, timeout, HTTP lỗi hoặc thiếu xác nhận đều giữ token và báo chưa xác nhận. Chặn gửi trùng; phản hồi muộn không xóa phiên mới; giữ khóa Admin | DONE |
| Phiên mật khẩu | Email login thu hồi cả token Google; đăng ký/điện thoại dùng tên token chung; đổi/reset chưa có chính sách thu hồi thống nhất | Phiên mật khẩu mới dùng `customer_token`, quyền Customer và hạn 7 ngày. Login thay các phiên mật khẩu đã phân loại. Đổi mật khẩu giữ phiên hiện tại và thu hồi các phiên mật khẩu khác. Reset thu hồi toàn bộ phiên mật khẩu đã phân loại. Không đụng `admin_token` hoặc token Google/legacy chưa phân loại | PARTIAL với token cũ |
| OTP/reset | OTP lộ email tồn tại; thiếu hạn thử ở bước xác minh/reset; credential có thể bị dùng đua hoặc lại; lỗi SMTP có thể phân biệt tài khoản | Phản hồi yêu cầu thống nhất; cooldown 60 giây/email chuẩn hóa, dùng chung cho OTP/link. OTP: 5 lượt/10 phút, hiệu lực 10 phút. Reset token 64 ký tự: 5 lượt/60 phút, hiệu lực 60 phút. Khóa hàng, kiểm tra hash, tiêu thụ credential và đổi mật khẩu/thu hồi token trong transaction. Không nhận OTP thô ở bước reset. Thiếu bảng reset trả cùng 503 cho mọi email. Gửi qua transport hiện có sau response; lỗi gửi chỉ ghi log an toàn | DONE |
| Validation mật khẩu | Chính sách 6 ký tự không nhất quán; thiếu kiểm soát thử mật khẩu hiện tại | Đăng ký/đổi/reset tối thiểu 8, tối đa 72 ký tự; xác nhận mật khẩu ở đổi/reset; đổi mật khẩu phải khác mật khẩu hiện tại. Sai mật khẩu hiện tại tối đa 5 lần/15 phút. Chặn dùng token Admin để đổi mật khẩu qua Customer API | DONE |
| XSS AI/chat | Tag, phản hồi chat, loại hoạt động và một số metadata được ghép vào HTML chưa mã hóa | Chat và tag dùng DOM/textContent, giữ xuống dòng. Các phần HTML lịch trình còn lại mã hóa văn bản, chỉ nội suy ID đã kiểm tra số nguyên và thời gian đúng định dạng. Sửa cả widget chat trang chủ. Thêm validation cho cập nhật lịch trình/hoạt động và giữ hỗ trợ kiểu giờ/loại địa điểm legacy | DONE |
| Booking legacy/V2 | Chi tiết có hai dạng payload; payment hasOne có thể lấy lần thất bại trước lần thành công; phương thức lạ thành tiền mặt; nút Invoice suy từ trạng thái Booking | Nhận cả object legacy và `{booking}` V2. Legacy chọn Payment thành công hoặc lần gần nhất như V2. Hiển thị trạng thái Payment riêng; tiền thành công ở Booking đã hủy/hết hạn được cảnh báo cần đối soát. Phương thức thiếu/lạ là chưa xác định. Tải metadata Invoice thật; nút PDF dựa trên Invoice đã tồn tại, kể cả Booking đã hủy. Không tự điền 1 khách/1 đêm/0 tiền khi thiếu. Giới hạn phân trang legacy 1–100 | DONE |
| Invoice PDF | GET tải PDF legacy ghi lại `pdf_url` | Chỉ đọc và tải Invoice hiện có, không ghi dữ liệu hoặc tạo Invoice khi tải. Giữ kiểm tra Booking thuộc Customer | DONE |
| Ngày sinh | Schema/model trong mã nguồn chưa định nghĩa nơi lưu DOB; mass assignment bỏ qua nhưng API vẫn báo thành công | Kiểm tra metadata cột `dob` trên connection của Customer khi xử lý request. Nếu có: kiểm tra ngày hợp lệ, không ở tương lai và lưu rõ ràng. Nếu không: ẩn/disable trường; API từ chối DOB không rỗng trước khi lưu hồ sơ. Áp dụng cả Profile và Dashboard cũ | DONE lỗi báo thành công; PARTIAL khả năng nhập khi thiếu cột |
| Thành phần mô phỏng | Điểm/voucher, hạng Bạc, Visa/MoMo mẫu, hoạt động local giả, 2FA/thiết bị giả và tùy chọn không có backend | Gỡ phần hiển thị và script liên quan khỏi Profile/Bảo mật. Gỡ đánh giá AI 4.5, số người/ngày tạo tự suy diễn. Avatar, tên, email hiển thị, điện thoại, số Booking thật và đổi mật khẩu tiếp tục hoạt động | DONE |
| Sidebar/Cài đặt | Sai active trang mật khẩu, thiếu lối mở menu mobile và route Cài đặt | Giữ thứ tự đã duyệt: Thông tin cá nhân → Lịch sử đặt phòng → Lịch trình AI → Bảo mật tài khoản → Cài đặt → Đăng xuất. Thêm route Cài đặt với mô tả đúng các tùy chỉnh chưa hỗ trợ. Sửa active/aria-current và mở menu mobile. Chỉ sửa tối thiểu grid/wrap gây tràn màn hình hẹp | DONE |

Chính sách token cần được hiểu rõ: `auth_token` từng được phát hành cho cả Google, đăng ký và đăng nhập điện thoại. Bảng token không có thông tin đủ để phân biệt các nguồn cũ. Tự thu hồi nhóm này khi đổi/reset mật khẩu có thể làm mất phiên Google đang hoạt động, trái ràng buộc đã duyệt. Vì vậy nhóm này được giữ nguyên; người đang giữ một token mật khẩu cũ mang tên `auth_token` có thể vẫn sử dụng được sau đổi/reset. Phiên mật khẩu mới đã tách tên và có hạn sử dụng. Đăng xuất chủ động vẫn thu hồi đúng token Customer đang sử dụng, kể cả token cũ.

Đối với ngày sinh, không truy vấn database chính để khẳng định cột thực tế có hay không. Đã kiểm tra migrations/model trong nguồn và kiểm thử hai schema synthetic: có cột và không có cột. Cơ chế kiểm tra khả năng lưu lúc chạy hỗ trợ cả hai mà không migrate.

Kết quả kiểm thử cuối cùng:

| Nhóm | Kết quả |
|---|---|
| Tài khoản và hồi quy Admin bằng SQLite `:memory:` | 37/37 test, 435 assertion |
| Hợp đồng Booking legacy/V2 | 3/3 test |
| Bảo mật V2/Google có chữ ký, quyền sở hữu, private response, OTP/reset | 14/14 test |
| Tích hợp Booking V2, PDF/mail trong bộ nhớ, VNPay callback, token Admin/Customer, concurrency/idempotency | 17/17 test |
| SePay: xác nhận một lần, callback trùng, không khôi phục Booking hủy/hết hạn/quá hạn | 3/3 test, 36 assertion |
| Tổng PHP sau khi tính mỗi test một lần bằng kết quả chạy cuối | **74/74 test, 902 assertion** |
| Chrome: logout lỗi mạng/server/thiếu ACK/phản hồi muộn, XSS, Booking payload, DOB, Sidebar, responsive | **41/41 kiểm tra**, không có lỗi script |
| Viewport | Profile, Bảo mật, Booking, Lịch trình, Cài đặt: 320, 390, 768, 1440 px; kiểm tra không tràn ngang |
| Kiểm tra cú pháp | 15 file PHP; 36 script inline từ 9 HTML render, gồm Login/Register/Reset |

Các lượt đầu phát hiện giả định kiểm thử cũ: ảnh demo nguồn không còn, số tác vụ scheduler đã thay đổi, và form Admin hiện hỗ trợ nhiều mô hình inventory. Đã sửa fixture dùng ảnh synthetic riêng, chỉ đếm hai tác vụ Booking và kiểm tra trường số phòng/giá cùng hợp đồng API. Các ca từng thất bại đã được chạy lại thành công. Không sửa scheduler, giao diện hoặc nghiệp vụ Admin để làm test qua.

SQLite dùng cấu hình riêng cưỡng chế `:memory:`. MySQL chạy trên hai instance do lần kiểm thử này tạo, bind loopback, cổng 54081 và 62804; tài khoản test chỉ có quyền trên schema `aurel_roomtype_v2_test_*`, không có quyền database chính/global. Đã shutdown cả hai và xác nhận không còn listener. Các ca callback chỉ xử lý dữ liệu synthetic; mail dùng fake/array, không có email thật, giao dịch thật hoặc cuộc gọi AI thật. Kiểm thử Chrome dùng HTML render và fetch synthetic, chặn request ra ngoài.

Không truy cập hoặc migrate `hotel_booking`; không sửa `.env`; không bật production Checkout; không reset/stash/commit/push. Không có migration mới, 2FA, loyalty, voucher hoặc hệ thống lưu thẻ. Không thay đổi module SePay/VNPay, luồng phát hành Invoice, SMTP hoặc cơ chế chống gửi trùng.

Danh sách file thay đổi trong Giai đoạn 1 (25 file chỉnh, 10 file mới, gồm báo cáo này):

| Nhóm | File |
|---|---|
| Auth/phiên | `app/Http/Controllers/AuthController.php`; `app/Models/Customer.php`; **mới** `app/Support/CustomerSessions.php`; **mới** `app/Services/CustomerPasswordRecovery.php` |
| Booking/Invoice | `app/Models/Booking.php`; `app/Services/BookingService.php`; `app/Services/BookingReadServiceV2.php`; `app/Http/Controllers/InvoiceController.php` |
| AI/backend | `app/Http/Controllers/ItineraryController.php` |
| Layout/route | `resources/views/user/layouts/user.blade.php`; `routes/web.php`; **mới** `resources/views/user/settings.blade.php` |
| Profile | `resources/views/user/profile/profile-section.blade.php`; `resources/views/user/profile/profile-script.blade.php` |
| Bảo mật | `resources/views/user/password.blade.php`; `resources/views/user/password/password-section.blade.php`; `resources/views/user/password/password-script.blade.php` |
| Booking/frontend | `resources/views/user/bookings/bookings-section.blade.php`; `resources/views/user/bookings/bookings-script.blade.php` |
| AI/frontend | `resources/views/user/itineraries/itineraries-section.blade.php`; `resources/views/user/itineraries/itineraries-script.blade.php`; `resources/views/partials/home/chatbot-script.blade.php` |
| Tích hợp cũ/Auth views | `resources/views/Dashboard.blade.php`; `resources/views/auth/Login.blade.php`; `resources/views/auth/Register.blade.php`; `resources/views/auth/reset_password.blade.php` |
| Kiểm thử mới | `phpunit.customer-account.xml`; `phpunit.customer-account-v2.xml`; `tests/Feature/CustomerAccountSecurityTest.php`; `tests/Feature/CustomerBookingContractTest.php`; `tests/Support/CustomerAccountTestAssets.php`; `tests/Frontend/customer-account-phase1.cjs` |
| Fixture/hồi quy hiện có | `tests/Support/RoomTypeV2FinalFixture.php`; `tests/Feature/RoomTypeV2IntegrationCompletionTest.php` |
| Báo cáo mới | `docs/customer-account-phase1-report.md` |

Bằng chứng nằm tại `storage/app/private/customer-account-phase1/`: `test-results.json` tổng hợp kết quả cuối theo từng test, `browser-results.json`, các file JUnit, `lint-results.json`, `js-syntax-results.json`, `changed-files.json`, `phase1.diff` và bản sao baseline trước sửa. `preview/` chứa HTML render cho kiểm thử; cần fetch synthetic như harness để xem nội dung tài khoản, không phải trang preview công khai hoặc dữ liệu tài khoản thật.

Những quyết định cần phê duyệt riêng:

1. Chính sách chuyển tiếp token `auth_token` cũ: chấp thuận một đợt yêu cầu đăng nhập lại, có thể bao gồm Google, hoặc cung cấp nguồn phân loại token đáng tin cậy. Chưa tự thu hồi nhóm này.
2. Nếu muốn cho nhập ngày sinh ở schema chưa có `dob`: duyệt migration và kế hoạch áp dụng riêng. Chưa thay đổi database chính.

Chưa triển khai Giai đoạn 2 hoặc thiết kế lại toàn bộ Profile. Không có công việc sửa chữa đang chạy nền.
