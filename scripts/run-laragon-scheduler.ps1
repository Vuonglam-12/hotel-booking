param([Parameter(Mandatory = $true)][string]$PhpPath)
$ErrorActionPreference = 'Stop'
$projectDirectory = [System.IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
if (-not (Test-Path -LiteralPath (Join-Path $projectDirectory 'artisan') -PathType Leaf)) { throw 'Project artisan entrypoint is missing.' }
if (-not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) { throw 'Configured Laragon PHP executable is missing.' }
$mutexSuffix = [Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes($projectDirectory)).Replace('\', '_').Replace('/', '_')
$schedulerMutex = New-Object System.Threading.Mutex($false, ('Local\HotelBookingScheduler-' + $mutexSuffix))
$ownsSchedulerLock = $false
try {
    $ownsSchedulerLock = $schedulerMutex.WaitOne(0)
    if (-not $ownsSchedulerLock) { exit 0 }
    $env:HOTEL_BOOKING_SCHEDULER = '1'
    $schedulerLogDirectory = Join-Path $projectDirectory 'storage\logs'
    $schedulerProcess = Start-Process -FilePath $PhpPath -ArgumentList @('artisan', 'schedule:run', '--no-interaction') -WorkingDirectory $projectDirectory -WindowStyle Hidden -Wait -PassThru -RedirectStandardOutput (Join-Path $schedulerLogDirectory 'laragon-scheduler-output.log') -RedirectStandardError (Join-Path $schedulerLogDirectory 'laragon-scheduler-error.log')
    exit $schedulerProcess.ExitCode
} finally {
    if ($ownsSchedulerLock) { $schedulerMutex.ReleaseMutex() }
    $schedulerMutex.Dispose()
}
