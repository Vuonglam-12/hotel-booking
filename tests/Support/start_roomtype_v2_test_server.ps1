param([string]$MySqlBase = 'C:\laragon\bin\mysql\mysql-8.0.30-winx64')
$ErrorActionPreference = 'Stop'
$workspace = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\..')).Path
$runId = [Guid]::NewGuid().ToString('N').Substring(0, 12)
$runtime = Join-Path $workspace ('storage\app\private\roomtype-v2-tests\' + $runId)
if (-not [IO.Path]::GetFullPath($runtime).StartsWith($workspace + '\', [StringComparison]::OrdinalIgnoreCase)) { throw 'Unsafe runtime path' }
if (Test-Path -LiteralPath $runtime) { throw 'Runtime must be newly created' }
$binary = Join-Path $MySqlBase 'bin\mysqld.exe'
if (-not (Test-Path -LiteralPath $binary -PathType Leaf)) { throw 'MySQL binary missing' }
$listener = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, 0)
$listener.Start()
$testPort = $listener.LocalEndpoint.Port
$listener.Stop()
if ($testPort -eq 3306 -or $testPort -eq 33060) { throw 'Refusing application MySQL port' }
New-Item -ItemType Directory -Path $runtime | Out-Null
$data = Join-Path $runtime 'data'
$temporary = Join-Path $runtime 'tmp'
New-Item -ItemType Directory -Path $data,$temporary | Out-Null
$init = Start-Process -FilePath $binary -ArgumentList @('--no-defaults','--initialize-insecure',('--basedir=' + $MySqlBase),('--datadir=' + $data)) -WindowStyle Hidden -PassThru -Wait -RedirectStandardOutput (Join-Path $runtime 'initialize.stdout.log') -RedirectStandardError (Join-Path $runtime 'initialize.stderr.log')
if ($init.ExitCode -ne 0) { throw ('Isolated initialization failed; see ' + $runtime) }
$randomBytes = New-Object byte[] 24
[Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($randomBytes)
$controlPassword = ([BitConverter]::ToString($randomBytes)).Replace('-','').ToLowerInvariant()
$controlUser = 'aurel_v2_control_' + $runId
@{ username=$controlUser; password=$controlPassword } | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $runtime 'control-bootstrap.json') -Encoding UTF8
$initSql = Join-Path $runtime 'bootstrap-control.sql'
@("CREATE USER IF NOT EXISTS '$controlUser'@'127.0.0.1' IDENTIFIED BY '$controlPassword';", "GRANT ALL PRIVILEGES ON *.* TO '$controlUser'@'127.0.0.1' WITH GRANT OPTION;") | Set-Content -LiteralPath $initSql -Encoding ASCII
$server = Start-Process -FilePath $binary -ArgumentList @('--no-defaults',('--basedir=' + $MySqlBase),('--datadir=' + $data),('--tmpdir=' + $temporary),('--port=' + $testPort),'--bind-address=127.0.0.1','--mysqlx=0','--skip-log-bin','--skip-name-resolve','--persisted-globals-load=OFF','--innodb-buffer-pool-size=32M',('--init-file=' + $initSql),('--log-error=' + (Join-Path $runtime 'mysql-error.log'))) -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtime 'server.stdout.log') -RedirectStandardError (Join-Path $runtime 'server.stderr.log')
$descriptor = Join-Path $runtime 'server.json'
@{ run_id=$runId; host='127.0.0.1'; port=$testPort; pid=$server.Id; data=$data; runtime=$runtime; binary=$binary; initialized_here=$true } | ConvertTo-Json | Set-Content -LiteralPath $descriptor -Encoding UTF8
$ready = $false
$deadline = [DateTime]::UtcNow.AddSeconds(20)
while ([DateTime]::UtcNow -lt $deadline) {
    $server.Refresh()
    if ($server.HasExited) { throw ('Owned server exited; see ' + $runtime) }
    $probe = [Net.Sockets.TcpClient]::new()
    try { $probe.Connect('127.0.0.1', $testPort); $ready = $true } catch { } finally { $probe.Dispose() }
    if ($ready) { break }
    Start-Sleep -Milliseconds 200
}
if (-not $ready) { throw ('Owned server did not become ready; see ' + $runtime) }
Write-Output $descriptor
