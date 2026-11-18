param(
    [Parameter(Mandatory=$true)][string]$TestAccountNumber,
    [string]$MySqlBase = 'C:\laragon\bin\mysql\mysql-8.0.30-winx64'
)

$ErrorActionPreference = 'Stop'
$workspace = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\..')).Path
$mysqlDescriptor = $null
$httpProcess = $null
$runtime = $null

function Get-FreeLoopbackPort([int]$PreferredPort) {
    $preferred = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, $PreferredPort)
    try {
        $preferred.Start()
        return $PreferredPort
    } catch {
        $listener = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, 0)
        $listener.Start()
        $port = $listener.LocalEndpoint.Port
        $listener.Stop()
        return $port
    } finally {
        $preferred.Stop()
    }
}

function Set-PrivateRuntimeAcl([string]$Path) {
    $acl = Get-Acl -LiteralPath $Path
    $acl.SetAccessRuleProtection($true, $false)
    foreach ($rule in @($acl.Access)) { $acl.RemoveAccessRuleAll($rule) }
    $inherit = [Security.AccessControl.InheritanceFlags]::ContainerInherit -bor [Security.AccessControl.InheritanceFlags]::ObjectInherit
    $currentUser = [Security.Principal.WindowsIdentity]::GetCurrent().User
    $system = New-Object Security.Principal.SecurityIdentifier('S-1-5-18')
    foreach ($identity in @($currentUser, $system)) {
        $rule = New-Object Security.AccessControl.FileSystemAccessRule -ArgumentList @(
            $identity,
            [Security.AccessControl.FileSystemRights]::FullControl,
            $inherit,
            [Security.AccessControl.PropagationFlags]::None,
            [Security.AccessControl.AccessControlType]::Allow
        )
        $acl.AddAccessRule($rule)
    }
    Set-Acl -LiteralPath $Path -AclObject $acl
}

function Invoke-PhpJson([string]$Php, [string[]]$Arguments) {
    $output = & $Php @Arguments
    if ($LASTEXITCODE -ne 0) { throw "PHP staging preparation failed with exit code $LASTEXITCODE." }
    return (($output | Out-String).Trim() | ConvertFrom-Json)
}

try {
    if (-not $TestAccountNumber -or $TestAccountNumber -notmatch '^[0-9]{4,30}$') {
        throw 'A numeric SePay Test mode account number is required.'
    }

    $php = (Get-Command php -ErrorAction Stop).Source
    $mysqlStart = Join-Path $PSScriptRoot 'start_roomtype_v2_test_server.ps1'
    $mysqlOutput = & $mysqlStart -MySqlBase $MySqlBase
    $mysqlDescriptor = ($mysqlOutput | Select-Object -Last 1).ToString().Trim()
    if (-not (Test-Path -LiteralPath $mysqlDescriptor -PathType Leaf)) {
        throw 'Isolated MySQL startup did not return an owned descriptor.'
    }
    $runtime = (Split-Path -Parent $mysqlDescriptor)

    $databaseSetup = Invoke-PhpJson $php @(
        (Join-Path $PSScriptRoot 'prepare_roomtype_v2_test_databases.php'),
        $mysqlDescriptor
    )
    $settingsPath = [string]$databaseSetup.settings_file
    if (-not (Test-Path -LiteralPath $settingsPath -PathType Leaf)) {
        throw 'Isolated test environment settings were not created.'
    }

    $stageSetup = Invoke-PhpJson $php @(
        (Join-Path $PSScriptRoot 'prepare_sepay_http_staging.php'),
        $settingsPath,
        $TestAccountNumber
    )
    $stagePath = [string]$stageSetup.staging_descriptor
    $stage = Get-Content -LiteralPath $stagePath -Raw | ConvertFrom-Json
    $runtime = [IO.Path]::GetFullPath([string]$stage.runtime)
    $ownedRuntimeRoot = Join-Path $workspace 'storage\app\private\roomtype-v2-tests'
    $runtimeOwned = $runtime.StartsWith($ownedRuntimeRoot + '\', [StringComparison]::OrdinalIgnoreCase)
    if ((-not $runtimeOwned) -or $stage.synthetic_only -ne $true -or $stage.auth_method -ne 'hmac_sha256') {
        throw 'Staging descriptor is outside the owned synthetic runtime.'
    }

    Set-PrivateRuntimeAcl $runtime
    $secretBytes = New-Object byte[] 32
    $random = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $random.GetBytes($secretBytes) } finally { $random.Dispose() }
    $secret = ([BitConverter]::ToString($secretBytes)).Replace('-', '').ToLowerInvariant()
    $secretPath = Join-Path $runtime 'sepay-hmac.secret'
    [IO.File]::WriteAllText($secretPath, $secret, (New-Object Text.UTF8Encoding($false)))

    $port = Get-FreeLoopbackPort 8081
    $router = Join-Path $PSScriptRoot 'sepay_http_staging_router.php'
    $stdout = Join-Path $runtime 'sepay-http.stdout.log'
    $stderr = Join-Path $runtime 'sepay-http.stderr.log'
    $httpDescriptor = Join-Path $runtime 'sepay-http-server.json'
    $oldDescriptor = $env:AUREL_SEPAY_STAGING_DESCRIPTOR
    $oldEnabled = $env:SEPAY_WEBHOOK_ENABLED
    $oldMode = $env:SEPAY_WEBHOOK_MODE
    $oldAuth = $env:SEPAY_WEBHOOK_AUTH_METHOD
    $oldSecret = $env:SEPAY_WEBHOOK_HMAC_SECRET
    try {
        $env:AUREL_SEPAY_STAGING_DESCRIPTOR = $stagePath
        $env:SEPAY_WEBHOOK_ENABLED = 'true'
        $env:SEPAY_WEBHOOK_MODE = 'test'
        $env:SEPAY_WEBHOOK_AUTH_METHOD = 'hmac_sha256'
        $env:SEPAY_WEBHOOK_HMAC_SECRET = $secret
        $httpProcess = Start-Process -FilePath $php `
            -ArgumentList @('-d', 'display_errors=0', '-d', 'log_errors=1', '-S', ('127.0.0.1:' + $port), $router) `
            -WorkingDirectory $workspace -WindowStyle Hidden -PassThru `
            -RedirectStandardOutput $stdout -RedirectStandardError $stderr
    } finally {
        $env:AUREL_SEPAY_STAGING_DESCRIPTOR = $oldDescriptor
        $env:SEPAY_WEBHOOK_ENABLED = $oldEnabled
        $env:SEPAY_WEBHOOK_MODE = $oldMode
        $env:SEPAY_WEBHOOK_AUTH_METHOD = $oldAuth
        $env:SEPAY_WEBHOOK_HMAC_SECRET = $oldSecret
    }

    $deadline = [DateTime]::UtcNow.AddSeconds(20)
    $ready = $false
    while ([DateTime]::UtcNow -lt $deadline) {
        if (Get-Process -Id $httpProcess.Id -ErrorAction SilentlyContinue) {
            $probe = [Net.Sockets.TcpClient]::new()
            try { $probe.Connect('127.0.0.1', $port); $ready = $true } catch { } finally { $probe.Dispose() }
            if ($ready) { break }
        } else {
            throw "HTTP staging server exited during startup; inspect $stderr."
        }
        Start-Sleep -Milliseconds 200
    }
    if (-not $ready) { throw 'HTTP staging listener did not become ready.' }

    $status = 0
    try {
        Invoke-WebRequest -UseBasicParsing -Method Post -Uri ('http://127.0.0.1:' + $port + '/api/webhooks/sepay') `
            -ContentType 'application/json' -Body '{}' -TimeoutSec 8 | Out-Null
    } catch {
        if ($_.Exception.Response) { $status = [int]$_.Exception.Response.StatusCode } else { throw }
    }
    if ($status -ne 401) { throw "Webhook guard probe expected HTTP 401 (valid guards, no signature); got $status." }

    $adminStatus = 0
    try {
        Invoke-WebRequest -UseBasicParsing -Uri ('http://127.0.0.1:' + $port + '/admin') -TimeoutSec 5 | Out-Null
    } catch {
        if ($_.Exception.Response) { $adminStatus = [int]$_.Exception.Response.StatusCode } else { throw }
    }
    if ($adminStatus -ne 404) { throw "Admin-route isolation probe expected HTTP 404; got $adminStatus." }

    @{
        version = 1
        host = '127.0.0.1'
        port = $port
        pid = $httpProcess.Id
        binary = $php
        router = $router
        runtime = $runtime
        stage_descriptor = $stagePath
        mysql_descriptor = $mysqlDescriptor
        secret_file = $secretPath
        started_here = $true
    } | ConvertTo-Json | Set-Content -LiteralPath $httpDescriptor -Encoding UTF8

    Write-Output ('HTTP staging descriptor: ' + $httpDescriptor)
    Write-Output ('Endpoint: POST http://127.0.0.1:' + $port + '/api/webhooks/sepay')
    Write-Output ('V2 and SePay Test/HMAC guards: operational (unauthenticated probe returned 401)')
    Write-Output ('Admin route isolation: verified (GET /admin returned 404)')
    Write-Output ('Synthetic Payment reference: ' + $stageSetup.payment_reference)
    Write-Output ('Synthetic amount: VND ' + $stageSetup.amount_vnd)
    Write-Output ('Test receiving account: ' + $stageSetup.test_account_number)
    Write-Output ('HMAC Secret Key remains only in the private runtime file; not displayed.')
} catch {
    if ($httpProcess -and (Get-Process -Id $httpProcess.Id -ErrorAction SilentlyContinue)) {
        Stop-Process -Id $httpProcess.Id
    }
    if ($mysqlDescriptor -and (Test-Path -LiteralPath $mysqlDescriptor -PathType Leaf)) {
        try {
            & (Join-Path $PSScriptRoot 'stop_roomtype_v2_test_server.ps1') -Descriptor $mysqlDescriptor | Out-Null
        } catch {
            Write-Warning ('Could not gracefully stop the owned MySQL test server: ' + $_.Exception.Message)
        }
    }
    throw
}
