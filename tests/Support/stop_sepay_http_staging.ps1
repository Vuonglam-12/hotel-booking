param([Parameter(Mandatory=$true)][string]$Descriptor)

$ErrorActionPreference = 'Stop'
$workspace = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\..')).Path
$resolved = (Resolve-Path -LiteralPath $Descriptor).Path
$runtimeRoot = Join-Path $workspace 'storage\app\private\roomtype-v2-tests'
$descriptorOwned = $resolved.StartsWith($runtimeRoot + '\', [StringComparison]::OrdinalIgnoreCase)
if ((-not $descriptorOwned) -or (Split-Path -Leaf $resolved) -ne 'sepay-http-server.json') {
    throw 'Unsafe SePay HTTP staging descriptor.'
}

$settings = Get-Content -LiteralPath $resolved -Raw | ConvertFrom-Json
$runtime = [IO.Path]::GetFullPath([string]$settings.runtime)
$router = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot 'sepay_http_staging_router.php')).Path
$runtimeOwned = $runtime.StartsWith($runtimeRoot + '\', [StringComparison]::OrdinalIgnoreCase)
if ($settings.started_here -ne $true -or $settings.host -ne '127.0.0.1' -or (-not $runtimeOwned) -or $settings.router -ne $router) {
    throw 'Descriptor does not identify an owned loopback SePay staging server.'
}

$process = Get-CimInstance Win32_Process -Filter ('ProcessId=' + [int]$settings.pid)
if ($process) {
    $identityMatches = $process.ExecutablePath -eq $settings.binary
    $listenArgumentMatches = $process.CommandLine.Contains('-S 127.0.0.1:' + [int]$settings.port)
    $routerArgumentMatches = $process.CommandLine.Contains($router)
    if ((-not $identityMatches) -or (-not $listenArgumentMatches) -or (-not $routerArgumentMatches)) {
        throw 'HTTP process identity mismatch; refusing to stop it.'
    }
    Stop-Process -Id ([int]$settings.pid)
    $deadline = [DateTime]::UtcNow.AddSeconds(15)
    while (Get-Process -Id ([int]$settings.pid) -ErrorAction SilentlyContinue) {
        if ([DateTime]::UtcNow -gt $deadline) { throw 'Owned HTTP staging process has not stopped.' }
        Start-Sleep -Milliseconds 200
    }
}

$mysqlDescriptor = [string]$settings.mysql_descriptor
if (-not (Test-Path -LiteralPath $mysqlDescriptor -PathType Leaf)) {
    throw 'HTTP server stopped, but its owned MySQL descriptor is missing; MySQL was not touched.'
}
& (Join-Path $PSScriptRoot 'stop_roomtype_v2_test_server.ps1') -Descriptor $mysqlDescriptor
Write-Output 'SePay HTTP staging and its isolated MySQL server are stopped; private synthetic data is retained.'
