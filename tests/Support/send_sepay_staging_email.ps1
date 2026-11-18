param(
    [Parameter(Mandatory=$true)][ValidateSet('Prepare','LocalTest','Send')][string]$Mode,
    [Parameter(Mandatory=$true)][string]$Descriptor,
    [string]$AttemptId,
    [string]$Recipient,
    [string]$ConfirmedRecipient,
    [string]$SendConfirmation,
    [string]$SmtpUser
)

$ErrorActionPreference = 'Stop'
$workspace = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\..')).Path
$runtimeRoot = (Resolve-Path -LiteralPath (Join-Path $workspace 'storage\app\private\roomtype-v2-tests')).Path
$descriptorPath = (Resolve-Path -LiteralPath $Descriptor).Path
if (-not $descriptorPath.StartsWith($runtimeRoot + '\', [StringComparison]::OrdinalIgnoreCase) -or
    (Split-Path -Leaf $descriptorPath) -ne 'sepay-staging.json') {
    throw 'Descriptor must be an owned synthetic SePay staging descriptor.'
}

$php = (Get-Command php -ErrorAction Stop).Source
$runner = Join-Path $PSScriptRoot 'send_sepay_staging_email.php'
$password = $null
$securePassword = $null
$passwordPointer = [IntPtr]::Zero

try {
    if ($Mode -eq 'Prepare') {
        if (-not $AttemptId) { $AttemptId = [Guid]::NewGuid().ToString().ToLowerInvariant() }
        if ($AttemptId -notmatch '^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$') {
            throw 'AttemptId must be a canonical version-4 UUID.'
        }
        & $php $runner $descriptorPath 'prepare' $AttemptId
        if ($LASTEXITCODE -ne 0) { throw "Staging email preflight failed with exit code $LASTEXITCODE." }
        return
    }

    if (-not $AttemptId -or $AttemptId -notmatch '^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$') {
        throw 'A canonical version-4 AttemptId from the Prepare result is required.'
    }

    if ($Mode -eq 'LocalTest') {
        if ($Recipient -or $ConfirmedRecipient -or $SmtpUser -or $SendConfirmation) {
            throw 'LocalTest does not accept Gmail identity or confirmation parameters.'
        }
        & $php $runner $descriptorPath 'local-test' $AttemptId
        if ($LASTEXITCODE -ne 0) { throw "Local SMTP integration test failed with exit code $LASTEXITCODE." }
        return
    }

    if (-not $Recipient -or $Recipient -notmatch '^[A-Za-z0-9._%+-]+@gmail\.com$' -or
        $ConfirmedRecipient -cne $Recipient -or
        $SendConfirmation -cne "SEND ONE EMAIL TO $Recipient" -or
        -not $SmtpUser -or $SmtpUser -notmatch '^[A-Za-z0-9._%+-]+@gmail\.com$') {
        throw 'Send requires a Gmail recipient, separately confirmed recipient, Gmail sender, and exact one-email confirmation.'
    }

    $securePassword = Read-Host 'Enter the sender Gmail App Password (input is hidden)' -AsSecureString
    $passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
    $password = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
    if (-not $password -or $password -match '\s') {
        throw 'A non-empty Gmail App Password without whitespace is required.'
    }

    $env:AUREL_STAGING_EMAIL_RECIPIENT = $Recipient
    $env:AUREL_STAGING_SMTP_USER = $SmtpUser
    $env:AUREL_STAGING_SMTP_PASSWORD = $password
    & $php $runner $descriptorPath 'send' $AttemptId
    if ($LASTEXITCODE -ne 0) { throw "One-off SMTP runner returned exit code $LASTEXITCODE; do not retry automatically." }
} finally {
    Remove-Item Env:AUREL_STAGING_EMAIL_RECIPIENT -ErrorAction SilentlyContinue
    Remove-Item Env:AUREL_STAGING_SMTP_USER -ErrorAction SilentlyContinue
    Remove-Item Env:AUREL_STAGING_SMTP_PASSWORD -ErrorAction SilentlyContinue
    if ($passwordPointer -ne [IntPtr]::Zero) {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
    }
    if ($securePassword) { $securePassword.Dispose() }
    $password = $null
}
