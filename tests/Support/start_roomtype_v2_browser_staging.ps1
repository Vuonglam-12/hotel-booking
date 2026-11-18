param([Parameter(Mandatory=$true)][string]$Descriptor)
$ErrorActionPreference='Stop'
$stagePath=(Resolve-Path -LiteralPath $Descriptor).Path
$stage=Get-Content -LiteralPath $stagePath -Raw | ConvertFrom-Json
$workspace=(Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\..')).Path
$runtime=[IO.Path]::GetFullPath($stage.runtime)
if (-not $runtime.StartsWith($workspace+'\storage\app\private\roomtype-v2-tests\',[StringComparison]::OrdinalIgnoreCase) -or $stage.synthetic_only -ne $true) { throw 'Owned synthetic staging runtime required' }
$listener=[Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback,0)
$listener.Start()
$port=$listener.LocalEndpoint.Port
$listener.Stop()
$env:AUREL_ROOMTYPE_V2_STAGING=$stagePath
$php=(Get-Command php).Source
$secretPath=$null
$oldEnabled=$env:SEPAY_WEBHOOK_ENABLED;$oldMode=$env:SEPAY_WEBHOOK_MODE;$oldAuth=$env:SEPAY_WEBHOOK_AUTH_METHOD;$oldSecret=$env:SEPAY_WEBHOOK_HMAC_SECRET;$oldAccount=$env:SEPAY_WEBHOOK_ACCOUNT_NUMBER
try {
    if ($stage.qr_banking_descriptor) {
        $qrPath=(Resolve-Path -LiteralPath $stage.qr_banking_descriptor).Path
        $qr=Get-Content -LiteralPath $qrPath -Raw | ConvertFrom-Json
        if ((Split-Path -Parent $qrPath) -ne $runtime -or $qr.synthetic_only -ne $true -or $qr.mode -ne 'sepay_test' -or $qr.database -ne $stage.database -or [DateTimeOffset]::Parse($qr.expires_at) -le [DateTimeOffset]::UtcNow) { throw 'Expired or mismatched QR staging artifact' }
        $bytes=New-Object byte[] 32;[Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
        $secret=([BitConverter]::ToString($bytes)).Replace('-','').ToLowerInvariant();$secretPath=Join-Path $runtime 'sepay-hmac.secret'
        [IO.File]::WriteAllText($secretPath,$secret,(New-Object Text.UTF8Encoding($false)))
        $env:SEPAY_WEBHOOK_ENABLED='true';$env:SEPAY_WEBHOOK_MODE='test';$env:SEPAY_WEBHOOK_AUTH_METHOD='hmac_sha256';$env:SEPAY_WEBHOOK_HMAC_SECRET=$secret;$env:SEPAY_WEBHOOK_ACCOUNT_NUMBER=$qr.banking_instructions.account_number
    }
    $http=Start-Process -FilePath $php -ArgumentList @('-S',('127.0.0.1:'+$port),'-t','public','tests/Support/roomtype_v2_staging_router.php') -WorkingDirectory $workspace -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtime 'staging-http.stdout.log') -RedirectStandardError (Join-Path $runtime 'staging-http.stderr.log')
} finally {
    $env:SEPAY_WEBHOOK_ENABLED=$oldEnabled;$env:SEPAY_WEBHOOK_MODE=$oldMode;$env:SEPAY_WEBHOOK_AUTH_METHOD=$oldAuth;$env:SEPAY_WEBHOOK_HMAC_SECRET=$oldSecret;$env:SEPAY_WEBHOOK_ACCOUNT_NUMBER=$oldAccount
}
$chrome='C:\Program Files\Google\Chrome\Application\chrome.exe'
if (-not (Test-Path -LiteralPath $chrome)) { throw 'Chrome executable missing; HTTP PID recorded only by caller' }
$profile=Join-Path $runtime 'browser-profile'
$browser=Start-Process -FilePath $chrome -ArgumentList @('--headless=new','--remote-debugging-port=0','--remote-debugging-address=127.0.0.1','--no-first-run','--no-default-browser-check','--disable-background-networking','--disable-sync','--disable-component-update',('--user-data-dir='+$profile),'about:blank') -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtime 'browser.stdout.log') -RedirectStandardError (Join-Path $runtime 'browser.stderr.log')
$result=Join-Path $runtime 'browser-staging.json'
@{http_pid=$http.Id; browser_pid=$browser.Id; origin=('http://127.0.0.1:'+$port); profile=$profile; stage_descriptor=$stagePath; runtime=$runtime; sepay_test_mode=([bool]$secretPath); sepay_hmac_secret_file=$secretPath} | ConvertTo-Json | Set-Content -LiteralPath $result -Encoding UTF8
$deadline=[DateTime]::UtcNow.AddSeconds(20)
while (-not (Test-Path -LiteralPath (Join-Path $profile 'DevToolsActivePort'))) {
    if ([DateTime]::UtcNow -gt $deadline) { throw ('Browser startup timeout; descriptor: '+$result) }
    Start-Sleep -Milliseconds 200
}
Write-Output $result
