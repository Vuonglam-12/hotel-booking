# SePay isolated HTTP staging

This runner creates an owned, loopback-only MySQL instance, initializes the
existing synthetic Booking V2 fixtures, and serves exactly
`POST /api/webhooks/sepay`. It does not load a production manifest, enable
Checkout, read or write `hotel_booking`, or expose the application's other
routes. The generated HMAC Secret Key is held only in the private test runtime
and the PHP server process environment.

## Start

From the repository root in PowerShell:

```powershell
.\tests\Support\start_sepay_http_staging.ps1 -TestAccountNumber 1033816978
```

The script prefers `127.0.0.1:8081`; if unavailable, it chooses an available
loopback port and prints the actual endpoint and private runtime descriptor.
The account number must be the matching **SePay Test mode** receiving account.
The script probes the endpoint without a signature and requires HTTP 401 (the
V2 and SePay guards passed) and verifies that `GET /admin` returns 404.

It also creates one pending synthetic Booking and Banking Payment. Copy its
printed transaction reference and exact VND amount into the SePay Test mode
simulator; do not send a live transfer. The reference and amount are written
into `sepay-staging.json` within the runtime folder.

## HMAC configuration

The generated HMAC key is never printed. Copy it directly to the clipboard
from the runtime path reported by the start script, then paste it into the
SePay Test mode webhook's HMAC-SHA256 Secret Key field:

```powershell
Get-Content -Raw "<runtime-folder>\sepay-hmac.secret" | Set-Clipboard
```

Set the webhook to incoming transfers and JSON, and use the tunnel's HTTPS URL
with `/api/webhooks/sepay`. Do not select Live mode, no authentication, or
OAuth 2.0. Avoid echoing or logging the clipboard value. Clear the clipboard
after saving the Dashboard configuration if the workstation is shared.

## Verify and stop

Use the actual loopback port printed by the start script:

```powershell
Invoke-WebRequest -UseBasicParsing -Method Post `
  -Uri "http://127.0.0.1:8081/api/webhooks/sepay" `
  -ContentType "application/json" -Body "{}"
```

Without SePay's HMAC headers this should return HTTP 401; it must not write a
payment event. Only POST to the exact webhook path is routed into Laravel.

Stop the HTTP server and its owned MySQL instance using the
`sepay-http-server.json` path printed by the start script:

```powershell
.\tests\Support\stop_sepay_http_staging.ps1 `
  -Descriptor "<runtime-folder>\sepay-http-server.json"
```

Stopping retains the private runtime and synthetic data for inspection. It
does not delete or modify any application database.

## One-off confirmation email (SMTP; requires separate approval)

The isolated SMTP runner reuses the existing paid synthetic Booking and its
single existing Invoice. It does not run the Booking effect worker, create an
Invoice, modify Payment/Booking state, or use the main `.env` SMTP credentials.
The message subject, HTML body, and attached PDF are marked
`THỬ NGHIỆM — KHÔNG CÓ GIÁ TRỊ THANH TOÁN`. A private one-time marker prevents
resending after an SMTP attempt, including an uncertain result.

Before authorizing an actual send, render-only preparation can be checked with:

```powershell
$attempt = .\tests\Support\send_sepay_staging_email.ps1 -Mode Prepare `
  -Descriptor "<runtime-folder>\sepay-staging.json"
$attemptId = [regex]::Match($attempt, 'attempt_id=([a-f0-9-]{36})').Groups[1].Value
if (-not $attemptId) { throw 'No independent SMTP attempt ID was prepared.' }
```

The returned UUID has its own marker under `smtp-attempts`; it is separate from
the previous `smtp-oneoff-email-attempt.json` uncertain marker. Test the exact
Booking/Invoice mailable through the local-only SMTP fixture before any Gmail
approval:

```powershell
.\tests\Support\send_sepay_staging_email.ps1 -Mode LocalTest `
  -Descriptor "<runtime-folder>\sepay-staging.json" -AttemptId $attemptId
```

An actual send is disabled unless the recipient is repeated as an exact
confirmation, the command carries an explicit one-message confirmation, and
the Gmail App Password is entered at a hidden local prompt. A separate
attempt UUID must first be prepared for that future send. The SMTP host is
pinned to Gmail (`smtp.gmail.com:587`, TLS); the sender address must be a
Gmail account and may be the same as the recipient:

```powershell
$recipient = "CONFIRMED_RECIPIENT@gmail.com"
.\tests\Support\send_sepay_staging_email.ps1 -Mode Send `
  -Descriptor "<runtime-folder>\sepay-staging.json" `
  -AttemptId $attemptId `
  -Recipient $recipient -ConfirmedRecipient $recipient `
  -SendConfirmation "SEND ONE EMAIL TO $recipient" `
  -SmtpUser "SENDER_ACCOUNT@gmail.com"
```

Do not run the `Send` command until the recipient and message are explicitly
approved. Enter a Gmail App Password only at the hidden prompt; do not put it
in command arguments, environment files, scripts, or chat. The runner reports
SMTP acceptance, not Inbox delivery. If it reports an uncertain result or a
one-time marker exists, it refuses to retry; check the provider before any
manual follow-up. No email is sent by `-Mode Prepare`.

The standalone AUTH checker performs TCP, EHLO, STARTTLS, EHLO, and AUTH LOGIN
only. It never issues `MAIL FROM`, `RCPT TO`, or `DATA`; the password is entered
at a hidden prompt:

```powershell
.\tests\Support\test_gmail_smtp_auth.ps1 `
  -SmtpHost smtp.gmail.com -Port 587 `
  -Username "SENDER_ACCOUNT@gmail.com"
```

Its local-fixture certificate bypass is accepted only for a literal loopback
IP and is for development tests; do not use it with a public SMTP host.
`-Mode LocalTest` sends only to a loopback SMTP fixture and marks its local
simulation result on the independent marker; it does not consume the future
Gmail send slot.

On an SMTP exception, the runner keeps the attempt `uncertain` and records
only a phase when the exception explicitly identifies it (`connection`,
`starttls`, `authentication`, or `send_data`), a valid SMTP reply code when
available, and the exception class. It never stores exception messages,
credentials, AUTH payloads, message contents, or recipient addresses in SMTP
diagnostics. An ambiguous failure is recorded as `unknown`; the one-time
marker remains in force and must not be reset to retry.
