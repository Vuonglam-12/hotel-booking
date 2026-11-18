param([Parameter(Mandatory=$true)][string]$Descriptor)
$ErrorActionPreference = 'Stop'
$workspace = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\..')).Path
$resolved = (Resolve-Path -LiteralPath $Descriptor).Path
if (-not $resolved.StartsWith((Join-Path $workspace 'storage\app\private\roomtype-v2-tests\'), [StringComparison]::OrdinalIgnoreCase)) { throw 'Unsafe descriptor path' }
$settings = Get-Content -LiteralPath $resolved -Raw | ConvertFrom-Json
if (-not $settings.initialized_here -or $settings.port -eq 3306 -or $settings.host -ne '127.0.0.1') { throw 'Not an owned isolated server' }
$process = Get-CimInstance Win32_Process -Filter ('ProcessId=' + [int]$settings.pid)
if ($null -eq $process) { Write-Output 'Owned test server already stopped.'; exit }
if ($process.ExecutablePath -ne $settings.binary -or -not $process.CommandLine.Contains('--datadir=' + $settings.data)) { throw 'Process identity mismatch; not stopping' }
# No database/file deletion: preserve this private test server for inspection.
& php (Join-Path $PSScriptRoot 'shutdown_roomtype_v2_test_server.php') $resolved
if ($LASTEXITCODE -ne 0) { throw 'Graceful shutdown failed; server was not force-killed' }
$deadline = [DateTime]::UtcNow.AddSeconds(20)
while (Get-Process -Id ([int]$settings.pid) -ErrorAction SilentlyContinue) {
    if ([DateTime]::UtcNow -gt $deadline) { throw 'Owned server has not stopped yet' }
    Start-Sleep -Milliseconds 200
}
Write-Output 'Owned test server stopped; private test data retained.'
