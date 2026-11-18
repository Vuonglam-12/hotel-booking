param([string]$Descriptor = '')
$ErrorActionPreference='Stop'
$demoRoot=(Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
if (-not $Descriptor) { $Descriptor=Join-Path $demoRoot 'storage\logs\booking-demo-running.json' }
$info=Get-Content -LiteralPath $Descriptor -Raw | ConvertFrom-Json
$runtime=(Resolve-Path -LiteralPath $info.runtime).Path
if (-not $runtime.StartsWith((Join-Path $demoRoot 'storage\app\private\roomtype-v2-tests\'),[StringComparison]::OrdinalIgnoreCase)) { throw 'Owned demo descriptor required.' }
$running=Get-CimInstance Win32_Process -Filter ('ProcessId='+[int]$info.http_pid)
if ($running) {
    $process=Get-Process -Id ([int]$info.http_pid)
    if (-not $running.CommandLine.Contains((Join-Path $PSScriptRoot 'booking-demo-router.php')) -or $process.StartTime.ToUniversalTime().ToString('o') -ne $info.http_started) { throw 'HTTP process identity mismatch; not stopping.' }
    Stop-Process -Id ([int]$info.http_pid)
}
& (Join-Path $demoRoot 'tests\Support\stop_roomtype_v2_test_server.ps1') -Descriptor (Join-Path $runtime 'server.json')
Write-Output 'AUREL DEMO stopped; isolated data retained. Primary MySQL was not touched.'
