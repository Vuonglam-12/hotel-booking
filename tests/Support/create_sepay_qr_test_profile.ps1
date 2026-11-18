param([string]$Output = 'storage\app\private\sepay-test-qr-profile.json')
$ErrorActionPreference = 'Stop'
$workspace = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\..')).Path
$target = [IO.Path]::GetFullPath((Join-Path $workspace $Output))
$privateRoot = [IO.Path]::GetFullPath((Join-Path $workspace 'storage\app\private'))
if (-not $target.StartsWith($privateRoot + '\', [StringComparison]::OrdinalIgnoreCase)) { throw 'Profile must stay under storage/app/private.' }

Write-Host 'Copy every value from the SePay Test Mode dashboard. Do not use a Live account or a guessed value.'
$bankName = Read-Host 'Bank name shown by SePay Test Mode'
$bankCode = Read-Host 'Bank code shown by SePay Test Mode'
$accountName = Read-Host 'Account holder shown by SePay Test Mode'
$accountNumber = Read-Host 'Test Mode receiving account number'
$instructions = Read-Host 'Customer instruction (must explicitly say SePay Test Mode; no real transfer)'
$qrPayload = Read-Host 'Exact QR payload from Test Mode (leave blank to use a downloaded local PNG)'
$qrImage = ''
if ([string]::IsNullOrWhiteSpace($qrPayload)) {
    $qrImage = Read-Host 'Repo-relative PNG path downloaded from SePay Test Mode (must already exist under public/ or storage/app/public/)'
}

if (@($bankName,$bankCode,$accountName,$accountNumber,$instructions) | Where-Object { [string]::IsNullOrWhiteSpace($_) }) { throw 'All Dashboard fields are required.' }
if ([string]::IsNullOrWhiteSpace($qrPayload) -and [string]::IsNullOrWhiteSpace($qrImage)) { throw 'An exact QR payload or downloaded Test Mode QR image is required.' }
if ($accountNumber -notmatch '^[0-9]{4,30}$') { throw 'Test Mode account number must contain 4-30 digits.' }
if ($qrPayload.Length -gt 2048) { throw 'QR payload exceeds the application contract.' }
$confirmation = Read-Host 'Type VERIFIED SEPAY TEST MODE after checking bank, account holder, account number and QR all match'
if ($confirmation -cne 'VERIFIED SEPAY TEST MODE') { throw 'Profile was not marked verified.' }

$parent = Split-Path -Parent $target
if (-not (Test-Path -LiteralPath $parent)) { New-Item -ItemType Directory -Path $parent | Out-Null }
$banking = @{
    verified = $true
    bank_name = $bankName.Trim()
    bank_code = $bankCode.Trim()
    account_name = $accountName.Trim()
    account_number = $accountNumber.Trim()
    instructions = $instructions.Trim()
    transfer_reference = 'payment.transaction_id'
}
if (-not [string]::IsNullOrWhiteSpace($qrPayload)) { $banking.qr_payload = $qrPayload.Trim() }
else { $banking.qr_image_url = $qrImage.Trim() }
@{
    version = 1
    mode = 'sepay_test'
    dashboard_verified = $true
    qr_matches_account = $true
    verified_at = [DateTime]::UtcNow.ToString('yyyy-MM-ddTHH:mm:ss+00:00')
    banking_instructions = $banking
} | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath $target -Encoding UTF8

$acl = Get-Acl -LiteralPath $target
$acl.SetAccessRuleProtection($true, $false)
foreach ($rule in @($acl.Access)) { $acl.RemoveAccessRuleAll($rule) }
$currentUser = [Security.Principal.WindowsIdentity]::GetCurrent().User
$system = New-Object Security.Principal.SecurityIdentifier('S-1-5-18')
foreach ($identity in @($currentUser,$system)) {
    $rule = New-Object Security.AccessControl.FileSystemAccessRule -ArgumentList @(
        $identity,
        [Security.AccessControl.FileSystemRights]::FullControl,
        [Security.AccessControl.AccessControlType]::Allow
    )
    $acl.AddAccessRule($rule)
}
Set-Acl -LiteralPath $target -AclObject $acl
Write-Output ('Verified private profile: ' + $target)
