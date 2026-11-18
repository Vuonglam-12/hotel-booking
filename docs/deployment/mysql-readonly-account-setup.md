# Hotel-Booking — thiết lập tài khoản MySQL READ-ONLY riêng

Ngày: **25/10/2026**. Hướng dẫn cho **chủ dự án tự xem xét và thực hiện**; assistant chưa kết nối server, tạo user, cấp quyền hoặc thay đổi dữ liệu. Tham chiếu [báo cáo 5C.1](../catalog-readonly-phase5c1.md).

## 1. Công cụ đã kiểm tra offline

| Công cụ | Kết quả tại máy |
|---|---|
| Laragon | Có `C:\laragon\laragon.exe`; chưa mở ứng dụng hoặc đọc cấu hình kết nối |
| HeidiSQL | Có `C:\laragon\bin\heidisql\heidisql.exe`; chưa mở session hoặc đọc cấu hình/credential đã lưu |
| MySQL CLI, server executable, mysqldump, mysql_config_editor | Có tại `C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin`; file/version output xác nhận **8.0.30** |
| PHP CLI | Có tại `C:\laragon\bin\php\php-8.2.31-Win32-vs16-x64\php.exe`; không bootstrap Laravel |

Chỉ đọc metadata file, chạy `--version` và lọc trợ giúp CLI. Không khởi động MySQL, không kết nối database, không đọc `.env`, login file hoặc cấu hình HeidiSQL. Bản cài trên ổ đĩa chưa chứng minh phiên bản/cổng của server đang phục vụ `hotel_booking`.

**Điểm tương thích đã kiểm tra:** `mysql_config_editor` 8.0.30 không nhận `--no-defaults`; `mysql.exe` 8.0.30 tại máy không nhận `--no-login-paths`. Không dùng hai tùy chọn đó cho các chương trình tương ứng. Với mysql CLI, `--no-defaults` vẫn đọc login file; dùng `MYSQL_TEST_LOGIN_FILE` để chỉ định **file riêng mới** và tránh profile quản trị mặc định. [MySQL login-path](https://dev.mysql.com/doc/refman/8.0/en/mysql-config-editor.html).

## 2. Phạm vi quyền và điều kiện trước khi chủ dự án thao tác

Tài khoản đề xuất: **`'aurel_catalog_ro'@'127.0.0.1'`**, chỉ cho kết nối TCP loopback trên máy Laragon. Tài khoản mới nhận đúng **SELECT trên schema hotel_booking**, không `WITH GRANT OPTION`, role hoặc quyền toàn server. Không dùng tài khoản Laravel, không đổi tài khoản đang có, không dùng host `%`.

SELECT cấp ở mức schema có khả năng đọc mọi bảng trong schema đó. Phạm vi audit thực tế vẫn giới hạn location/attraction/ảnh/hotel/Cẩm nang/migrations và metadata FK; không đọc row tài khoản, Booking, Payment hoặc hóa đơn. Quyền đọc schema không phải quyền thực hiện các nghiệp vụ ứng dụng.

Chủ dự án xác nhận cổng/instance từ thông tin vận hành đã biết hoặc giao diện Laragon; không lấy password từ `.env`. Mẫu này dành cho **MySQL 8.0 local**, chưa áp dụng cho server khác hoặc truy cập từ xa. Nếu cần remote, phải có phương án host/IP và TLS được duyệt riêng, không tự mở rộng user thành `%`.

Chủ dự án kiểm tra chính sách log trước khi đưa mật khẩu vào SQL: server không chạy `--log-raw`; plugin audit/proxy/GUI/terminal session không ghi nguyên văn secret. MySQL thông thường che password của `CREATE USER ... IDENTIFIED BY`, nhưng cấu hình log raw hoặc công cụ trung gian có thể làm lộ. Nếu chưa xác nhận được đường đi của secret thì dừng phần tạo tài khoản. Không thay đổi cấu hình log toàn server theo hướng dẫn này. [Passwords and logging](https://dev.mysql.com/doc/refman/8.0/en/password-logging.html).

## 3. Chuẩn bị hai thư mục riêng ngoài Git — chủ dự án tự chạy

Hai thư mục có mục đích khác nhau: `mysql-owner-setup` chứa credential quản trị chỉ chủ dự án dùng; `mysql-readonly` chứa duy nhất credential RO có thể cấp cho phiên audit sau này. **Không cung cấp file quản trị cho assistant.** Không đặt secret dưới repository, `public`, `storage` hoặc các thư mục báo cáo.

Mở PowerShell riêng, không bật transcript/ghi hình session khi nhập secret. Ví dụ dưới đây tạo thư mục mới và giới hạn ACL cho Windows user hiện tại cùng SYSTEM; không thực thi trong phiên assistant này:

```powershell
$mysqlBin = 'C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin'
$mysqlExe = Join-Path $mysqlBin 'mysql.exe'
$editor = Join-Path $mysqlBin 'mysql_config_editor.exe'
$auditPort = [int](Read-Host 'Cong MySQL local da xac nhan')
if ($auditPort -lt 1 -or $auditPort -gt 65535) { throw 'Cong khong hop le.' }
$ownerUser = Read-Host 'Tai khoan quan tri MySQL cua chu du an'
$auditDir = Join-Path $env:LOCALAPPDATA 'AUREL\mysql-readonly'
$ownerDir = Join-Path $env:LOCALAPPDATA 'AUREL\mysql-owner-setup'
$auditSid = [Security.Principal.WindowsIdentity]::GetCurrent().User.Value
foreach ($directory in @($auditDir, $ownerDir)) {
    if (Test-Path -LiteralPath $directory) {
        throw 'Thu muc da ton tai: dung de kiem tra rieng, khong ghi de profile cu.'
    }
    if ([IO.Path]::GetFullPath($directory).StartsWith('C:\laragon\www\Hotel-Booking\', [StringComparison]::OrdinalIgnoreCase)) {
        throw 'Credential phai nam ngoai repository.'
    }
    New-Item -ItemType Directory -Path $directory -ErrorAction Stop | Out-Null
    & icacls.exe $directory /inheritance:r /grant:r ("*{0}:(OI)(CI)F" -f $auditSid) '*S-1-5-18:(OI)(CI)F'
    if ($LASTEXITCODE -ne 0) { throw 'Chua gioi han duoc ACL; khong luu secret.' }
}
```

Nếu thư mục đã có, kiểm tra ACL/ownership và chọn lại thư mục riêng thay vì chạy đoạn tạo mới mù. Không sửa ACL của cả user profile hoặc repository.

Lưu credential quản trị phục vụ đúng bước thiết lập này, password nhập tại prompt được che, không dùng `--password=...`:

```powershell
$ownerLoginFile = Join-Path $ownerDir 'owner-setup.mylogin.cnf'
$previousLoginFile = $env:MYSQL_TEST_LOGIN_FILE
try {
    $env:MYSQL_TEST_LOGIN_FILE = $ownerLoginFile
    & $editor set --login-path=aurel_owner_setup --host=127.0.0.1 --port=$auditPort --user=$ownerUser --password
    if ($LASTEXITCODE -ne 0) { throw 'Chua luu duoc profile quan tri.' }
} finally {
    $env:MYSQL_TEST_LOGIN_FILE = $previousLoginFile
}
```

`mysql_config_editor` tạo file login-path được **obfuscate**, không phải kho mã hóa mạnh chống quản trị viên máy. ACL và kiểm soát người truy cập vẫn cần thiết. Không dùng profile global có sẵn vì có thể chứa tài khoản root khác. [MySQL credential file](https://dev.mysql.com/doc/refman/8.0/en/mysql-config-editor.html).

## 4. SQL kiểm tra trước — chủ dự án dùng tài khoản quản trị

Mở connection quản trị qua file riêng để nhập **các câu SELECT/SHOW không có password**; lệnh này do chủ dự án tự chạy:

```powershell
if (-not (Test-Path -LiteralPath $ownerLoginFile -PathType Leaf)) { throw 'Thieu profile quan tri rieng; khong ket noi.' }
$previousLoginFile = $env:MYSQL_TEST_LOGIN_FILE
try {
    $env:MYSQL_TEST_LOGIN_FILE = $ownerLoginFile
    & $mysqlExe --no-defaults --login-path=aurel_owner_setup --protocol=TCP --host=127.0.0.1 --port=$auditPort --connect-timeout=5 --local-infile=0
} finally {
    $env:MYSQL_TEST_LOGIN_FILE = $previousLoginFile
}
```

Chạy riêng block **A1/A2** trong [mysql-readonly-account-verify.sql](mysql-readonly-account-verify.sql). Kỳ vọng server đúng instance/port, version MySQL 8.0, schema tồn tại, username mới **chưa tồn tại ở bất kỳ host nào**, `mandatory_roles` rỗng. Nếu đã có user cùng tên, dừng; không dùng `CREATE USER IF NOT EXISTS`, không tự REVOKE/đổi mật khẩu hoặc tái sử dụng quyền cũ.

Nếu `mandatory_roles` không rỗng, dừng để DBA phân tích toàn bộ quyền kế thừa trước. Không tắt mandatory role toàn server để tiện cho audit. Có thể xem quyền role bằng `SHOW GRANTS FOR user USING role...` với danh sách role thực do DBA xác nhận; `SHOW GRANTS FOR user` một mình chưa phải chứng minh đầy đủ khi có role. [SHOW GRANTS](https://dev.mysql.com/doc/refman/8.0/en/show-grants.html).

## 5. SQL tạo tài khoản — chỉ chủ dự án xem xét và thực hiện

**Đây là câu lệnh thay đổi tài khoản/quyền trên server. Assistant không thực thi.** Không chạm dữ liệu bảng ứng dụng. Tạo mật khẩu riêng trong password manager, không gửi vào chat/báo cáo. Thay placeholder trong **file SQL riêng có ACL ở thư mục owner** bằng trình soạn thảo riêng, không dán bản chứa password vào terminal, lịch sử query GUI hoặc repository.

```sql
CREATE USER 'aurel_catalog_ro'@'127.0.0.1'
    IDENTIFIED WITH caching_sha2_password BY 'REPLACE_WITH_PRIVATE_PASSWORD';
```

Chọn **đúng một** lệnh GRANT theo kết quả `@@GLOBAL.partial_revokes` vừa đọc. Dấu `_` có thể là wildcard ở mức schema khi partial_revokes OFF; backtick không tự làm nó thành literal. [Quy tắc GRANT của MySQL](https://dev.mysql.com/doc/refman/8.0/en/grant.html).

Nếu `partial_revokes = 0` / OFF:

```sql
GRANT SELECT ON `hotel\_booking`.* TO 'aurel_catalog_ro'@'127.0.0.1';
```

Nếu `partial_revokes = 1` / ON, schema được hiểu literal:

```sql
GRANT SELECT ON `hotel_booking`.* TO 'aurel_catalog_ro'@'127.0.0.1';
```

Giữ nguyên chế độ partial_revokes của server. Không cấp `ALL`, `FILE`, `PROCESS`, `EXECUTE`, `CREATE TEMPORARY TABLES`, `SHOW DATABASES` toàn server, `GRANT OPTION` hoặc role. Không GRANT lên `*.*`, `mysql.*` hoặc `information_schema.*`. Không cần `FLUSH PRIVILEGES` sau CREATE USER/GRANT.

**Cách tránh đưa password ra terminal:** chủ dự án lưu đúng CREATE USER và một GRANT đã chọn vào `create-audit-user.private.sql` dưới `$ownerDir`. Mật khẩu có dấu nháy/backslash phải được escape theo SQL mode thực tế; cách đơn giản là password manager tạo mật khẩu mạnh không chứa `'`, `\` hoặc ký tự điều khiển. Không dùng `BY RANDOM PASSWORD` vì câu đó trả secret trong result set. [CREATE USER](https://dev.mysql.com/doc/refman/8.0/en/create-user.html).

Chủ dự án có thể tự chạy đoạn PowerShell dưới đây sau khi đã xem xét file và điều kiện logging. SQL đi qua stdin; stdout/stderr được giữ trong bộ nhớ, không in ra khi lỗi. Không có password trong command line, không dùng tee/verbose hoặc query history. **Đoạn này có thể tạo user/cấp quyền nếu chủ dự án chạy; assistant chỉ cung cấp để xem xét.**

```powershell
$privateSqlPath = Join-Path $ownerDir 'create-audit-user.private.sql'
if (-not (Test-Path -LiteralPath $ownerLoginFile -PathType Leaf)) { throw 'Thieu profile quan tri rieng; khong ket noi.' }
$privateSql = [IO.File]::ReadAllText($privateSqlPath, [Text.Encoding]::UTF8)
if ($privateSql.Contains('REPLACE_WITH_PRIVATE_PASSWORD')) {
    throw 'Chua thay placeholder; dung thao tac.'
}
$startInfo = New-Object Diagnostics.ProcessStartInfo
$startInfo.FileName = $mysqlExe
$startInfo.Arguments = "--no-defaults --login-path=aurel_owner_setup --protocol=TCP --host=127.0.0.1 --port=$auditPort --batch --silent --skip-column-names --connect-timeout=5 --local-infile=0"
$startInfo.WorkingDirectory = $ownerDir
$startInfo.UseShellExecute = $false
$startInfo.CreateNoWindow = $true
$startInfo.RedirectStandardInput = $true
$startInfo.RedirectStandardOutput = $true
$startInfo.RedirectStandardError = $true
$startInfo.StandardInputEncoding = New-Object Text.UTF8Encoding($false)
$startInfo.EnvironmentVariables['MYSQL_TEST_LOGIN_FILE'] = $ownerLoginFile
$process = New-Object Diagnostics.Process
$process.StartInfo = $startInfo
try {
    if (-not $process.Start()) { throw 'Khong khoi chay duoc client.' }
    $outTask = $process.StandardOutput.ReadToEndAsync()
    $errTask = $process.StandardError.ReadToEndAsync()
    $process.StandardInput.WriteLine($privateSql)
    $process.StandardInput.Close()
    $process.WaitForExit()
    $privateOutput = $outTask.GetAwaiter().GetResult()
    $privateError = $errTask.GetAwaiter().GetResult()
    if ($process.ExitCode -ne 0) {
        throw 'Thiet lap that bai; dung, kiem tra trang thai user rieng. Khong in SQL/stderr chua secret.'
    }
    Write-Host 'Da thuc hien file do chu du an duyet. Tiep theo: kiem tra grants.'
} finally {
    $privateSql = $null; $privateOutput = $null; $privateError = $null
    $outTask = $null; $errTask = $null
    $process.Dispose()
}
```

Các biến string trong bộ nhớ không có bảo đảm secure erase. File SQL và login file quản trị chỉ do chủ dự án giữ; sau thiết lập xử lý chúng theo chính sách secret của dự án, không cấp cho phiên audit. Nếu CREATE USER thành công nhưng GRANT thất bại, user có thể đã được tạo: dừng và kiểm tra bằng admin, không chạy lại mù hoặc tự xóa/tạo lại.

## 6. Kiểm chứng quyền bằng thao tác chỉ đọc

Chủ dự án chạy block **B** trong file verify bằng admin. Kỳ vọng:

- SHOW GRANTS chỉ có `USAGE ON *.*` và một `SELECT ON hotel_booking.*` đúng literal/escaped theo chế độ; **không WITH GRANT OPTION**. USAGE là biểu diễn không có quyền toàn server, không phải quyền ghi.
- Không có role_edges cấp cho user này; không có proxy grant; mandatory_roles vẫn rỗng.
- Không có quyền `INSERT`, `UPDATE`, `DELETE`, `ALTER`, `DROP`, `CREATE`, `INDEX`, `TRIGGER`, `EVENT`, `EXECUTE`, `FILE` hoặc quyền quản trị/dynamic khác.

Không thử INSERT/UPDATE/DELETE/ALTER/DROP/CREATE trên production để chứng minh bị từ chối, kể cả câu có `WHERE 1=0` hoặc bọc transaction. Dùng grants và kiểm tra role/proxy/identity để đánh giá quyền; mọi điểm khác dự kiến đều cần dừng.

### Lưu file RO riêng, không in secret

Sau khi user đã được tạo, chủ dự án nhập **cùng password mới** ở prompt được che:

```powershell
$roLoginFile = Join-Path $auditDir 'catalog-ro.mylogin.cnf'
$previousLoginFile = $env:MYSQL_TEST_LOGIN_FILE
try {
    $env:MYSQL_TEST_LOGIN_FILE = $roLoginFile
    & $editor set --login-path=aurel_catalog_ro --host=127.0.0.1 --port=$auditPort --user=aurel_catalog_ro --password
    if ($LASTEXITCODE -ne 0) { throw 'Chua luu duoc credential RO.' }
} finally {
    $env:MYSQL_TEST_LOGIN_FILE = $previousLoginFile
}
```

File chứa duy nhất profile mới dành cho RO, nằm ngoài Git; không copy vào `.env`. Không dùng `Get-Content`, `type`, `--print-defaults`, dump biến/process environment hoặc log nội dung file. `mysql_config_editor print --login-path=aurel_catalog_ro` che password nếu chủ dự án cần xem riêng profile; không in `--all` để đọc các profile khác.

Chủ dự án mở phiên **RO** để chạy riêng block C1/C2/C3 và các lệnh C4 tương ứng với bảng đã xác nhận tồn tại:

```powershell
$previousLoginFile = $env:MYSQL_TEST_LOGIN_FILE
try {
    if (-not (Test-Path -LiteralPath $roLoginFile -PathType Leaf)) { throw 'Thieu file RO; khong ket noi.' }
    $env:MYSQL_TEST_LOGIN_FILE = $roLoginFile
    & $mysqlExe --no-defaults --login-path=aurel_catalog_ro --protocol=TCP --host=127.0.0.1 --port=$auditPort --user=aurel_catalog_ro --database=hotel_booking --connect-timeout=5 --local-infile=0
} finally {
    $env:MYSQL_TEST_LOGIN_FILE = $previousLoginFile
}
```

Kỳ vọng CURRENT_USER là `aurel_catalog_ro@127.0.0.1`, DATABASE là `hotel_booking`, CURRENT_ROLE là `NONE`; không có applicable/enabled roles. Kiểm tra lại SHOW GRANTS và bốn bảng privilege metadata trong block C2, không chỉ nhìn quyền trực tiếp. Gặp tài khoản/role/schema khác thì dừng.

### Metadata có thể đọc với SELECT

Quyền SELECT trên bảng cho phép xem metadata tương ứng trong `information_schema.TABLES`, `COLUMNS`, `STATISTICS`, `KEY_COLUMN_USAGE` và metadata FK liên quan; chỉ dùng các bảng metadata cần cho audit. `SHOW CREATE TABLE` của **base table** chỉ đọc định nghĩa, không thực thi CREATE. Không cần nâng quyền ghi để xem schema. [INFORMATION_SCHEMA và quyền](https://dev.mysql.com/doc/refman/8.0/en/information-schema-introduction.html), [SHOW CREATE TABLE](https://dev.mysql.com/doc/refman/8.0/en/show-create-table.html).

Không tự cấp quyền SHOW VIEW/PROCESS hoặc quyền đọc mysql.* khi một đối tượng không hiện. Nếu gặp view/metadata đặc biệt bị giới hạn, ghi PARTIAL và nhờ chủ dự án cung cấp định nghĩa đã được duyệt. Danh sách bảng không có attraction_image/guide_post có thể là kết quả audit; không tạo bảng để làm phép kiểm tra PASS.

## 7. Cấp phép cho assistant tiếp tục

Sau khi kiểm chứng, chỉ cung cấp **đường dẫn tuyệt đối của file RO**, profile `aurel_catalog_ro`, host/port/schema, UUID server đã xác nhận và phạm vi bảng được đọc. Không gửi mật khẩu, file SQL chứa password hoặc login file quản trị vào chat/Git. ACL phải cho đúng Windows identity của phiên audit truy cập; không mở Everyone để giải quyết quyền file.

Mẫu xác nhận bằng văn bản, điền giá trị thật nhưng không có secret:

> Tôi phê duyệt tiếp tục Phase 5C.1 READ-ONLY trên hotel_booking, host 127.0.0.1, port [đã xác nhận], server UUID [đã xác nhận]. Dùng account aurel_catalog_ro@127.0.0.1, login-path aurel_catalog_ro tại [đường dẫn RO ngoài Git]. Chỉ đọc bảng location, attraction, attraction_image, hotel, hotel_image, guide_category, guide_post, migrations và metadata liên quan. Không ghi dữ liệu, không migration/seed/import hoặc chuyển 5C.2. Tôi đã kiểm tra grants và không có role/proxy/quyền ghi.

Assistant chỉ tiếp tục sau xác nhận này; trước khi đọc dữ liệu sẽ đối chiếu lại identity/grants. Việc tạo file hoặc tài khoản chưa tự cấp phép phiên audit kết nối. Không dùng credential RO cho Laravel runtime hoặc triển khai.

## 8. Trạng thái cuối hướng dẫn

Kiểm tra offline tài liệu: **6 block PowerShell parse PASS**, không chạy các ví dụ; **23 câu SELECT/SHOW** trong file verify qua kiểm tra tĩnh; **15 hash source/test/migration/seeder** của 5B vẫn khớp. Không kiểm thử tạo account/GRANT trên bất kỳ database nào trong phiên này, không chạy lại test suite.

- **DONE:** kiểm tra công cụ offline, chuẩn bị SQL tạo user/cấp đúng SELECT, kiểm tra quyền bằng SELECT/SHOW, hướng dẫn secret file ngoài Git và ACL. Chỉ thêm tài liệu và SQL verify chưa chạy; không sửa ứng dụng hoặc dữ liệu.
- **PARTIAL:** cú pháp và khả năng client đã đối chiếu tài liệu MySQL/CLI; tài khoản, quyền metadata, ACL file mới và cơ chế log thực tế chưa được chủ dự án kiểm chứng trên server.
- **BLOCKED:** chưa có credential RO riêng và xác nhận cấp quyền kết nối cho assistant; chưa đọc schema/dữ liệu thật.
- **Tiến độ hiện tại:** **ước tính 80%**, giữ nguyên theo 5B/5C.1; nhóm dữ liệu thực/vận hành còn thiếu, không dùng số lượng tài liệu để tăng tiến độ.
- **Bước tiếp theo:** chủ dự án xem xét và tự thiết lập/kiểm chứng tài khoản, sau đó cấp phép rõ ràng kèm đường dẫn RO để hoàn tất Phase 5C.1. Chưa chuyển Phase 5C.2 hoặc triển khai.
