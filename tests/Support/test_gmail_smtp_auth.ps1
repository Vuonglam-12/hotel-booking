param(
    [string]$SmtpHost = 'smtp.gmail.com',
    [ValidateRange(1,65535)][int]$Port = 587,
    [string]$Username = 'hahan8784@gmail.com',
    [switch]$AllowInsecureLocalTestCertificate
)

$ErrorActionPreference = 'Stop'
$phase = 'TCP'
$smtpCode = $null
$enhancedCode = $null
$client = $null
$network = $null
$ssl = $null
$reader = $null
$writer = $null
$securePassword = $null
$passwordPointer = [IntPtr]::Zero
$password = $null
$passwordBytes = $null
$passwordBase64 = $null
$usernameBase64 = $null
$reply = $null

if (-not $Username -or $Username -notmatch '^[A-Za-z0-9._%+-]+@gmail\.com$') {
    throw 'Username must be a Gmail address.'
}
if ($AllowInsecureLocalTestCertificate) {
    $parsedAddress = $null
    if (-not [System.Net.IPAddress]::TryParse($SmtpHost, [ref]$parsedAddress) -or
        -not [System.Net.IPAddress]::IsLoopback($parsedAddress)) {
        throw 'The test certificate bypass is restricted to a literal loopback IP address.'
    }
} elseif ($SmtpHost -cne 'smtp.gmail.com' -or $Port -ne 587) {
    throw 'Only smtp.gmail.com:587 is allowed outside the explicit loopback-fixture mode.'
}

function Read-SmtpReply {
    param([System.IO.StreamReader]$Reader)

    $lines = [System.Collections.Generic.List[string]]::new()
    do {
        $line = $Reader.ReadLine()
        if ($null -eq $line -or $line -notmatch '^(\d{3})([ -])') {
            throw 'Invalid SMTP reply.'
        }
        $code = [int]$Matches[1]
        $separator = $Matches[2]
        $lines.Add($line)
    } while ($separator -eq '-')

    return [pscustomobject]@{ Code = $code; Lines = $lines.ToArray() }
}

function Save-SmtpStatus {
    param($Reply)

    $script:smtpCode = $Reply.Code
    $script:enhancedCode = $null
    foreach ($line in $Reply.Lines) {
        if ($line -match '\b([245]\.\d{1,3}\.\d{1,3})\b') {
            $script:enhancedCode = $Matches[1]
            break
        }
    }
}

try {
    $client = [System.Net.Sockets.TcpClient]::new()
    $client.Connect($SmtpHost, $Port)
    $network = $client.GetStream()
    $network.ReadTimeout = 15000
    $network.WriteTimeout = 15000

    $reader = [System.IO.StreamReader]::new(
        $network, [Text.Encoding]::ASCII, $false, 1024, $true
    )
    $writer = [System.IO.StreamWriter]::new(
        $network, [Text.Encoding]::ASCII, 1024, $true
    )
    $writer.NewLine = "`r`n"
    $writer.AutoFlush = $true

    $reply = Read-SmtpReply $reader
    Save-SmtpStatus $reply
    if ($smtpCode -ne 220) { throw 'SMTP greeting rejected.' }

    $phase = 'EHLO'
    $writer.WriteLine('EHLO aurel-staging.invalid')
    $reply = Read-SmtpReply $reader
    Save-SmtpStatus $reply
    if ($smtpCode -ne 250) { throw 'EHLO rejected.' }
    if (-not ($reply.Lines | Where-Object { $_ -match '^250[- ]STARTTLS(?:\s|$)' })) {
        throw 'STARTTLS was not advertised.'
    }

    $phase = 'STARTTLS'
    $writer.WriteLine('STARTTLS')
    $reply = Read-SmtpReply $reader
    Save-SmtpStatus $reply
    if ($smtpCode -ne 220) { throw 'STARTTLS rejected.' }
    $reader.Dispose()
    $writer.Dispose()

    if ($AllowInsecureLocalTestCertificate) {
        $validationCallback = [System.Net.Security.RemoteCertificateValidationCallback]{
            param($sender, $certificate, $chain, $policyErrors)
            return $true
        }
        $ssl = [System.Net.Security.SslStream]::new($network, $false, $validationCallback)
    } else {
        $ssl = [System.Net.Security.SslStream]::new($network, $false)
    }
    $ssl.AuthenticateAsClient($SmtpHost)
    $reader = [System.IO.StreamReader]::new(
        $ssl, [Text.Encoding]::ASCII, $false, 1024, $true
    )
    $writer = [System.IO.StreamWriter]::new(
        $ssl, [Text.Encoding]::ASCII, 1024, $true
    )
    $writer.NewLine = "`r`n"
    $writer.AutoFlush = $true

    $phase = 'EHLO_TLS'
    $writer.WriteLine('EHLO aurel-staging.invalid')
    $reply = Read-SmtpReply $reader
    Save-SmtpStatus $reply
    if ($smtpCode -ne 250) { throw 'TLS EHLO rejected.' }
    $authCapability = $reply.Lines |
        Where-Object { $_ -match '^250[- ]AUTH(?:\s|=)' } |
        Select-Object -First 1
    if (-not $authCapability -or $authCapability -notmatch '\bLOGIN\b') {
        $phase = 'AUTH_CAPABILITY'
        throw 'AUTH LOGIN was not advertised over TLS.'
    }

    $securePassword = Read-Host 'Enter Gmail App Password (input is hidden)' -AsSecureString
    $passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
    $password = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
    if (-not $password -or $password -match '\s') {
        $phase = 'AUTH_INPUT'
        $smtpCode = $null
        $enhancedCode = $null
        throw 'App Password input is invalid.'
    }

    $phase = 'AUTH_COMMAND'
    $writer.WriteLine('AUTH LOGIN')
    $reply = Read-SmtpReply $reader
    Save-SmtpStatus $reply
    if ($smtpCode -ne 334) { throw 'AUTH challenge rejected.' }

    $phase = 'AUTH_USERNAME'
    $usernameBase64 = [Convert]::ToBase64String(
        [Text.Encoding]::ASCII.GetBytes($Username)
    )
    $writer.WriteLine($usernameBase64)
    $reply = Read-SmtpReply $reader
    Save-SmtpStatus $reply
    if ($smtpCode -ne 334) { throw 'Username challenge rejected.' }

    $phase = 'AUTH_PASSWORD'
    $passwordBytes = [Text.Encoding]::ASCII.GetBytes($password)
    $passwordBase64 = [Convert]::ToBase64String($passwordBytes)
    $writer.WriteLine($passwordBase64)
    $reply = Read-SmtpReply $reader
    Save-SmtpStatus $reply
    if ($smtpCode -eq 235) {
        'AUTH PASS: Gmail SMTP returned 235. No email was sent.'
    } else {
        throw 'SMTP rejected AUTH.'
    }
} catch {
    $codeText = if ($null -eq $smtpCode) { 'none' } else { [string]$smtpCode }
    $enhancedText = if ($enhancedCode) { $enhancedCode } else { 'none' }
    "AUTH FAIL: phase=$phase; SMTP=$codeText; enhanced_status=$enhancedText. Raw server response was suppressed; no email was sent."
} finally {
    if ($passwordBytes) {
        [Array]::Clear($passwordBytes, 0, $passwordBytes.Length)
    }
    $password = $null
    $passwordBase64 = $null
    $usernameBase64 = $null
    $reply = $null
    if ($passwordPointer -ne [IntPtr]::Zero) {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
    }
    if ($securePassword) { $securePassword.Dispose() }
    if ($reader) { $reader.Dispose() }
    if ($writer) { $writer.Dispose() }
    if ($ssl) { $ssl.Dispose() }
    elseif ($network) { $network.Dispose() }
    if ($client) { $client.Dispose() }
}
