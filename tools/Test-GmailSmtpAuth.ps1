& {
    $ErrorActionPreference = 'Stop'
    $smtpServer = 'smtp.gmail.com'
    $smtpAccount = 'hahan8784@gmail.com'
    $state = @{ Stage = 'TCP'; Code = $null; Enhanced = ''; Reason = '' }
    $tcp = $tls = $reader = $writer = $secret = $null
    $passBytes = $saslBytes = $authChars = $null
    $secretPtr = [IntPtr]::Zero
    $crlf = [string]::Concat([char]13, [char]10)

    function Read-Reply {
        $state.Code = $null
        $state.Enhanced = ''
        $caps = @{ StartTls = $false; Plain = $false }
        for ($n = 0; $n -lt 100; $n++) {
            $line = $reader.ReadLine()
            if ($null -eq $line -or $line.Length -gt 4096 -or
                $line -notmatch '^\d{3}[ -]') {
                $state.Reason = 'Ket noi dong hoac phan hoi SMTP khong hop le.'
                throw 'CHECK_FAILED'
            }
            $code = [int]$line.Substring(0, 3)
            if ($null -ne $state.Code -and $state.Code -ne $code) { throw 'CHECK_FAILED' }
            $state.Code = $code
            $enhanced = [regex]::Match($line, '(?<!\d)[245]\.\d{1,3}\.\d{1,3}\b')
            if ($enhanced.Success) { $state.Enhanced = $enhanced.Value }
            if ($line -match '^250[ -]STARTTLS(?:\s|$)') { $caps.StartTls = $true }
            if ($line -match '^250[ -]AUTH[ =].*\bPLAIN\b') { $caps.Plain = $true }
            if ($line[3] -eq ' ') { return $caps }
        }
        throw 'CHECK_FAILED'
    }

    function Command([string]$text, [int]$expected) {
        if ($text -notin @('EHLO aurel.local', 'STARTTLS', 'AUTH PLAIN')) { throw 'COMMAND_NOT_ALLOWED' }
        $state.Code = $null
        $state.Enhanced = ''
        $writer.WriteLine($text)
        $writer.Flush()
        $reply = Read-Reply
        if ($state.Code -ne $expected) { throw 'SMTP_REJECTED' }
        return $reply
    }

    try {
        $tcp = [Net.Sockets.TcpClient]::new()
        $task = $tcp.ConnectAsync($smtpServer, 587)
        if (-not $task.Wait(15000)) {
            $state.Reason = 'Het thoi gian ket noi TCP.'
            throw 'CHECK_FAILED'
        }
        $task.GetAwaiter().GetResult()
        $tcp.ReceiveTimeout = $tcp.SendTimeout = 15000
        $stream = $tcp.GetStream()
        $reader = [IO.StreamReader]::new($stream, [Text.Encoding]::ASCII, $false, 1024, $true)
        $writer = [IO.StreamWriter]::new($stream, [Text.Encoding]::ASCII, 1024, $true)
        $writer.NewLine = $crlf

        $state.Stage = 'GREETING'
        $null = Read-Reply
        if ($state.Code -ne 220) { throw 'SMTP_REJECTED' }

        $state.Stage = 'EHLO'
        $caps = Command 'EHLO aurel.local' 250
        if (-not $caps.StartTls) {
            $state.Reason = 'May chu khong quang ba STARTTLS; dung truoc AUTH.'
            throw 'CHECK_FAILED'
        }
        $state.Stage = 'STARTTLS'
        $null = Command 'STARTTLS' 220
        $reader.Dispose()
        $writer.Dispose()
        $reader = $writer = $null

        $state.Stage = 'TLS'
        $state.Code = $null
        $state.Enhanced = ''
        $tls = [Net.Security.SslStream]::new($stream, $false)
        $tls.ReadTimeout = $tls.WriteTimeout = 15000
        $certs = [Security.Cryptography.X509Certificates.X509CertificateCollection]::new()
        $task = $tls.AuthenticateAsClientAsync(
            $smtpServer, $certs, [Security.Authentication.SslProtocols]::Tls12, $true)
        if (-not $task.Wait(15000)) {
            $state.Reason = 'Het thoi gian bat tay TLS.'
            throw 'CHECK_FAILED'
        }
        $task.GetAwaiter().GetResult()
        if (-not $tls.IsAuthenticated -or -not $tls.IsEncrypted) { throw 'CHECK_FAILED' }
        $reader = [IO.StreamReader]::new($tls, [Text.Encoding]::ASCII, $false, 1024, $true)
        $writer = [IO.StreamWriter]::new($tls, [Text.Encoding]::ASCII, 1024, $true)
        $writer.NewLine = $crlf

        $state.Stage = 'EHLO_TLS'
        $caps = Command 'EHLO aurel.local' 250
        if (-not $caps.Plain) {
            $state.Reason = 'May chu khong quang ba AUTH PLAIN sau TLS.'
            throw 'CHECK_FAILED'
        }

        $state.Stage = 'INPUT'
        $state.Code = $null
        $state.Enhanced = ''
        $state.Reason = 'App Password can 16 ky tu ASCII, khong tinh khoang trang.'
        $secret = Read-Host 'Nhap Gmail App Password (an)' -AsSecureString
        $secretPtr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret)
        $passBytes = [byte[]]::new(16)
        $count = 0
        for ($i = 0; $i -lt $secret.Length; $i++) {
            $ch = [Runtime.InteropServices.Marshal]::ReadInt16($secretPtr, 2 * $i)
            if ($ch -ge 0 -and [char]::IsWhiteSpace([char]$ch)) { continue }
            if ($count -ge 16 -or $ch -lt 33 -or $ch -gt 126) { throw 'INVALID_INPUT' }
            $passBytes[$count++] = [byte]$ch
        }
        if ($count -ne 16) { throw 'INVALID_INPUT' }

        $userBytes = [Text.Encoding]::ASCII.GetBytes($smtpAccount)
        $saslBytes = [byte[]]::new($userBytes.Length + 18)
        [Array]::Copy($userBytes, 0, $saslBytes, 1, $userBytes.Length)
        [Array]::Copy($passBytes, 0, $saslBytes, $userBytes.Length + 2, 16)
        $authChars = [char[]]::new((4 * [int][Math]::Ceiling($saslBytes.Length / 3.0)))
        $null = [Convert]::ToBase64CharArray($saslBytes, 0, $saslBytes.Length, $authChars, 0)

        $state.Stage = 'AUTH'
        $state.Reason = ''
        $null = Command 'AUTH PLAIN' 334
        $state.Code = $null
        $state.Enhanced = ''
        $writer.Write($authChars, 0, $authChars.Length)
        $writer.WriteLine()
        $writer.Flush()
        $null = Read-Reply
        if ($state.Code -ne 235) { throw 'SMTP_REJECTED' }
        Write-Host "AUTH PASS | SMTP 235 $($state.Enhanced)" -ForegroundColor Green
    }
    catch {
        $reason = $state.Reason
        if (-not $reason) {
            $reason = switch ($state.Code) {
                535 { 'Gmail khong chap nhan tai khoan/App Password.' }
                534 { 'Gmail yeu cau App Password hoac xac minh/chinh sach tai khoan.' }
                530 { 'Gmail yeu cau STARTTLS/xac thuc truoc.' }
                504 { 'Gmail khong chap nhan co che AUTH nay.' }
                454 { 'Loi xac thuc tam thoi; khong tu dong thu lai.' }
                421 { 'Gmail tam dong/gioi han ket noi; khong tu dong thu lai.' }
                default {
                    if ($state.Stage -eq 'TLS') { 'Bat tay TLS hoac xac minh chung chi that bai.' }
                    elseif ($state.Stage -eq 'TCP') { 'Loi DNS/TCP/timeout khi ket noi SMTP.' }
                    else { 'Phan hoi SMTP khong nhu mong doi, ket noi dong hoac timeout.' }
                }
            }
        }
        $codeText = if ($null -eq $state.Code) { 'N/A' } else { [string]$state.Code }
        $result = if ($state.Stage -eq 'AUTH') { 'AUTH FAIL' } else { 'CHECK FAIL' }
        Write-Host "$result | SMTP $codeText $($state.Enhanced) | $($state.Stage) | $reason" -ForegroundColor Red
    }
    finally {
        if ($null -ne $passBytes) { [Array]::Clear($passBytes, 0, $passBytes.Length) }
        if ($null -ne $saslBytes) { [Array]::Clear($saslBytes, 0, $saslBytes.Length) }
        if ($null -ne $authChars) { [Array]::Clear($authChars, 0, $authChars.Length) }
        if ($secretPtr -ne [IntPtr]::Zero) {
            [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($secretPtr)
        }
        foreach ($resource in @($secret, $writer, $reader, $tls, $tcp)) {
            if ($null -ne $resource) { try { $resource.Dispose() } catch {} }
        }
    }
}
