Báo cáo hoàn thiện Giai đoạn 1 Profile AUREL — 19/10/2026

**DONE trong phạm vi code và kiểm thử cô lập được duyệt.** Hai điểm còn lại của đợt trước đã được xử lý: chuyển tiếp token không phân loại và chuẩn bị lưu ngày sinh. **PARTIAL về áp dụng database chính/production**, vì các thao tác này chưa được duyệt và chưa thực hiện. Không có hạng mục kỹ thuật BLOCKED.

| Hành động | Chính sách hiện tại | Trạng thái |
|---|---|---|
| Đăng xuất Customer | Thu hồi đúng token đang dùng, trong quan hệ token của Customer đó. Không thu hồi các phiên khác. Giữ cơ chế chờ `revoked: true`, xử lý mất mạng và bảo vệ phiên đăng nhập mới ở trình duyệt | DONE |
| Đổi mật khẩu | Sau khi kiểm tra mật khẩu hiện tại: cập nhật hash, thu hồi mọi phiên Customer khác và hủy credential reset cũ trong transaction. Giữ phiên đang thao tác và mọi phiên Admin hợp lệ | DONE |
| Reset mật khẩu | Tiêu thụ credential hợp lệ, đổi hash và thu hồi toàn bộ token Customer trong cùng transaction, kể cả Google, `customer_token` cũ, `auth_token`, token hết hạn và tên không biết. Không giữ ngoại lệ cho phiên Customer đang reset | DONE |
| Token Admin | Bảo vệ token đúng `admin_token`, thuộc Customer có role Admin và có quyền `admin` hoặc wildcard như middleware Admin hiện có. Chỉ mang tên/quyền Admin mà không thỏa cả ba điều kiện không được bảo vệ nhầm | DONE |
| Token mới | Đăng ký/email/điện thoại dùng `customer_password_token` + `customer, auth:password`. Google đã xác minh dùng `customer_google_token` + `customer, auth:google`. Cả hai hết hạn sau 7 ngày, không có quyền Admin; client không chọn được loại/quyền token | DONE |
| Token legacy | Không gán lại nguồn của `auth_token`. Đăng nhập mật khẩu chỉ xoay vòng những token mật khẩu đã biết (`customer_token` cũ và tên mới); đổi/reset xử lý toàn bộ phiên Customer theo chính sách trên | DONE |
| Google sau reset | Người dùng đăng nhập Google lại vào cùng Customer/email đã xác minh. Không xóa Customer, đổi email, gỡ liên kết hoặc tăng quyền Admin. Việc cấp token Google được đặt trong transaction có khóa Customer để đồng bộ với đổi/reset mật khẩu | DONE |
| Ngày sinh | Giữ trường trong Profile và Dashboard cũ. Khi chưa có cột, trường hiển thị nhưng tạm khóa, có thông báo và không gửi giá trị không thể lưu. Khi có cột, cho sửa/lưu/tải lại | DONE |
| Migration DOB | Chuẩn bị một thay đổi: thêm `customer.dob` kiểu DATE, nullable, không default ngày và không backfill. Chưa áp dụng vào `hotel_booking` | DONE code; PARTIAL áp dụng chính |

Đổi/reset không còn bỏ sót token Customer cũ chỉ vì không biết phương thức xác thực. Token được giới hạn theo cả Customer ID và loại chủ sở hữu của quan hệ Sanctum; không xóa token của Customer khác hoặc actor khác có cùng ID số. Không có lệnh dọn/thu hồi hàng loạt token khi triển khai hoặc migrate. Chính sách thu hồi chạy khi người dùng gọi endpoint tương ứng.

Trong trường hợp lỗi database ở bước thu hồi, transaction rollback cả đổi mật khẩu; credential reset không bị tiêu thụ và API không báo thành công. Các giới hạn OTP/reset, xác minh Google và SMTP hiện có được giữ nguyên; không sửa luồng gửi Email.

DOB dùng model cast `date:Y-m-d`, API trả `YYYY-MM-DD`, không chuyển ngày sinh qua múi giờ. Validation yêu cầu ngày tồn tại, đúng định dạng và không sau ngày hiện tại. Bỏ trường khỏi request giữ giá trị cũ; gửi rỗng/null xóa về NULL. Dữ liệu thiếu không được thay bằng ngày mặc định. Migration mới không âm thầm nhận một cột `dob` đã có: trường hợp trùng cột dừng để kiểm tra schema thay vì ghi đè hoặc bỏ qua rồi rollback nhầm cột có sẵn.

Kết quả kiểm thử của đợt hoàn thiện:

| Kiểm tra | Kết quả |
|---|---|
| SQLite `:memory:`: tài khoản, chuyển tiếp token, Google có chữ ký, DOB và hồi quy Admin | **53/53 test, 677 assertion** |
| MySQL riêng: thêm cột, giữ nguyên dữ liệu Customer, DOB API/validation, trước/sau migration, rollback/reapply, cột có sẵn, reset và phạm vi Admin | **7/7 test, 108 assertion** |
| Tổng hai cấu hình PHP | **60 lần chạy test, 785 assertion; không lỗi/failure/skipped** |
| Chrome với HTML/API synthetic | **47/47 kiểm tra**, không có lỗi script |
| Cú pháp PHP | 8/8 file PHP được kiểm tra |

Các ca chuyển tiếp bao gồm token mật khẩu mới/cũ, Google mới, nhiều `auth_token` không gán nguồn, tên lạ, token hết hạn, capabilities thiếu, token chỉ giả tên/quyền Admin, Admin wildcard hợp lệ, Customer khác và actor khác cùng ID. Kiểm thử xác nhận Google đăng nhập lại được sau reset vào đúng Customer cũ, không tăng quyền; credential có chữ ký sai không tạo token hoặc làm mất phiên hiện có. Chứng chỉ Google được fake cục bộ, phần xác minh chữ ký RS256 chạy thật bằng OpenSSL; không gọi Google thật.

Kiểm thử DOB gồm ngày nhuận, ngày không tồn tại, chuỗi ngày theo định dạng khác, timestamp, số/array, ngày tương lai, ngày hôm nay, bỏ qua trường, xóa rỗng/null, tải lại toàn bộ trang và lỗi lưu. Trình duyệt kiểm tra trường còn hiện trước migration, tự bật theo khả năng lưu, không tự điền ngày và không báo lưu thành công khi server từ chối. Hồi quy logout/XSS/sidebar/responsive trong harness cũ tiếp tục qua.

MySQL kiểm thử được tạo riêng trên loopback cổng **49309**, chỉ dùng các schema `aurel_roomtype_v2_test_*`. Tài khoản test không có quyền với database chính hoặc quyền toàn cục. Chỉ dựng schema tài khoản tối thiểu; không khởi tạo module tài chính. Server đã được shutdown và xác nhận không còn listener. SQLite chỉ nằm trong RAM; mail fake/array, session/cache array. Mọi API trong kiểm thử trình duyệt là synthetic và request ra ngoài bị chặn.

Đã so sánh SHA-256 trước/sau của **52 file** thuộc phạm vi Booking/Payment/SePay/VNPay/Invoice/Email và các `.env*`: không đổi. `CustomerPasswordRecovery.php` chỉ đổi lời gọi thu hồi token ở bước reset; không sửa phần gửi mail. Không truy cập, migrate hoặc thu hồi token tài khoản thật trên `hotel_booking`. Không commit, push hoặc triển khai production.

File thay đổi trong đợt này:

| Nhóm | File |
|---|---|
| Chính sách phiên | `app/Support/CustomerSessions.php`; `app/Http/Controllers/AuthController.php`; `app/Services/CustomerPasswordRecovery.php` |
| DOB model/UI | `app/Models/Customer.php`; `resources/views/user/profile/profile-section.blade.php`; `resources/views/user/profile/profile-script.blade.php`; `resources/views/Dashboard.blade.php` — chỉ phần DOB |
| Migration mới | `database/migrations/2026_10_19_000002_add_dob_to_customer_table.php` |
| Kiểm thử | `tests/Feature/CustomerAccountSecurityTest.php`; **mới** `tests/Feature/CustomerDobMigrationTest.php`; `tests/Feature/RoomTypeV2SecurityQualityTest.php` — chỉ cập nhật nhãn token Google; `tests/Frontend/customer-account-phase1.cjs`; `phpunit.customer-account.xml`; **mới** `phpunit.customer-account-transition-mysql.xml` |
| Báo cáo | `docs/customer-account-phase1-report.md` — thêm liên kết báo cáo mới; **mới** `docs/customer-account-phase1-completion-report.md` |

Bằng chứng nằm tại `storage/app/private/customer-account-phase1-completion/`: JUnit SQLite/MySQL, `test-results.json`, `browser-results.json`, `lint-results.json`, bản sao baseline và `completion.diff`, `protected-files.json`, cùng HTML trong `preview/`. Preview cần harness synthetic để hiển thị dữ liệu; không có route preview công khai hoặc tài khoản thật được đưa vào đây.

Trước khi áp dụng lên database chính cần phê duyệt riêng:

1. Cho phép kiểm tra read-only schema `customer` và lịch sử migration của `hotel_booking`. Nếu `dob` đã có, đối chiếu kiểu/nullable và chọn cách tiếp nhận migration riêng; không chạy mù hoặc tự sửa cột có sẵn.
2. Duyệt sao lưu và kế hoạch áp dụng **đúng một migration DOB nêu trên**; không chạy toàn bộ migration đang pending của dự án. Cột mới để NULL cho dữ liệu cũ, không thêm ngày sinh giả. `down()` xóa cột DOB nên không rollback sau khi có dữ liệu nếu chưa có phương án bảo toàn dữ liệu được duyệt.
3. Duyệt triển khai code và xác nhận sau áp dụng bằng tài khoản được phép. Không cần migration dữ liệu token và không chạy tác vụ thu hồi token thật hàng loạt. Việc sử dụng tài khoản thật để thử đổi/reset/đăng xuất phải được phê duyệt trước.

Chưa có thao tác nào trong ba bước trên được thực hiện. Không mở rộng sang Giai đoạn 2 hoặc các hệ thống 2FA/loyalty/voucher/lưu thẻ.
