param([string]$SettingsFile = '', [int]$HttpPort = 0)
$ErrorActionPreference='Stop'
$demoRoot=(Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$runningFile=Join-Path $demoRoot 'storage\logs\booking-demo-running.json'
if (Test-Path -LiteralPath $runningFile) {
    $existing=Get-Content -LiteralPath $runningFile -Raw | ConvertFrom-Json
    $existingProcess=Get-CimInstance Win32_Process -Filter ('ProcessId='+[int]$existing.http_pid)
    if ($existingProcess -and $existingProcess.CommandLine.Contains((Join-Path $PSScriptRoot 'booking-demo-router.php')) -and (-not $SettingsFile -or [IO.Path]::GetFullPath($SettingsFile) -eq $existing.settings_file) -and ($HttpPort -eq 0 -or $HttpPort -eq ([Uri]$existing.origin).Port)) {
        try { $existingHealth=Invoke-RestMethod -Uri ($existing.origin+'/__demo/health') -TimeoutSec 2 } catch { $existingHealth=$null }
        if ($existingHealth.demo -and $existingHealth.isolated) { Write-Output ($existing.origin+'/demo');Write-Output ('Access file: '+$existing.access_file);exit 0 }
    }
}
if ($HttpPort -eq 0) { $listener=[Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback,0);$listener.Start();$HttpPort=$listener.LocalEndpoint.Port;$listener.Stop() }
if ($HttpPort -lt 1024 -or $HttpPort -gt 65535 -or $HttpPort -eq 3306 -or $HttpPort -eq 8000) { throw 'Use a separate unprivileged HTTP port.' }
$portProbe=[Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback,$HttpPort)
try { $portProbe.Start() } finally { $portProbe.Stop() }
if (-not $SettingsFile) {
    $serverDescriptor=& (Join-Path $demoRoot 'tests\Support\start_roomtype_v2_test_server.ps1')
    $prepared=& php (Join-Path $demoRoot 'tests\Support\prepare_roomtype_v2_test_databases.php') $serverDescriptor | ConvertFrom-Json
    if ($LASTEXITCODE -ne 0) { throw 'Owned MySQL preparation failed.' }
    $SettingsFile=$prepared.settings_file
}
$SettingsFile=(Resolve-Path -LiteralPath $SettingsFile).Path
if (-not $SettingsFile.StartsWith((Join-Path $demoRoot 'storage\app\private\roomtype-v2-tests\'),[StringComparison]::OrdinalIgnoreCase)) { throw 'Owned settings path required.' }
$demoRuntime=Split-Path -Parent $SettingsFile
$demoOwner=[Security.Principal.WindowsIdentity]::GetCurrent().Name
& icacls.exe $demoRuntime /inheritance:r /grant:r ($demoOwner+':(OI)(CI)F') 'SYSTEM:(OI)(CI)F' | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'Cannot restrict access to the private DEMO runtime.' }
& php (Join-Path $PSScriptRoot 'prepare-booking-demo.php') $SettingsFile | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'DEMO schema preparation failed; no primary connection is used.' }
$php=(Get-Command php -ErrorAction Stop).Source
$previousDemoStage=$env:AUREL_BOOKING_DEMO_STAGE
try {
    $env:AUREL_BOOKING_DEMO_STAGE=Join-Path $demoRuntime 'staging-session.json'
    $http=Start-Process -FilePath $php -ArgumentList @('-S',('127.0.0.1:'+$HttpPort),'-t',(Join-Path $demoRoot 'public'),(Join-Path $PSScriptRoot 'booking-demo-router.php')) -WorkingDirectory $demoRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $demoRuntime 'demo-http.stdout.log') -RedirectStandardError (Join-Path $demoRuntime 'demo-http.stderr.log')
} finally { $env:AUREL_BOOKING_DEMO_STAGE=$previousDemoStage }
$info=@{origin=('http://127.0.0.1:'+$HttpPort);http_pid=$http.Id;http_started=$http.StartTime.ToUniversalTime().ToString('o');runtime=$demoRuntime;settings_file=$SettingsFile;stage_file=(Join-Path $demoRuntime 'staging-session.json');access_file=(Join-Path $demoRuntime 'demo-access.txt')}
$info | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $demoRuntime 'demo-running.json') -Encoding UTF8
$info | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $demoRoot 'storage\logs\booking-demo-running.json') -Encoding UTF8
$deadline=[DateTime]::UtcNow.AddSeconds(20)
do { try { $health=Invoke-RestMethod -Uri ($info.origin+'/__demo/health') -TimeoutSec 2; if ($health.demo -and $health.isolated) { Write-Output ($info.origin+'/demo');Write-Output ('Access file: '+$info.access_file);exit 0 } }catch{};Start-Sleep -Milliseconds 200 }while([DateTime]::UtcNow -lt $deadline)
throw ('DEMO HTTP did not become ready; inspect private logs in '+$demoRuntime)
