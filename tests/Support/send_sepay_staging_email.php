<?php

use App\Mail\BookingConfirmedV2;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\SmtpFailureDiagnostics;
use App\Support\RoomTypeV2TestDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;

$root = dirname(__DIR__, 2);
$runtimeRoot = realpath($root . '/storage/app/private/roomtype-v2-tests');
$descriptorPath = isset($argv[1]) ? realpath($argv[1]) : false;
$mode = $argv[2] ?? '';
$attemptId = strtolower($argv[3] ?? '');

if (!$runtimeRoot || !$descriptorPath || !str_starts_with($descriptorPath, $runtimeRoot . DIRECTORY_SEPARATOR)
    || basename($descriptorPath) !== 'sepay-staging.json' || !in_array($mode, ['prepare', 'local-test', 'send'], true)
    || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $attemptId)) {
    fwrite(STDERR, "Invalid isolated SePay staging descriptor or mode.\n");
    exit(2);
}

$descriptor = json_decode(file_get_contents($descriptorPath), true, 512, JSON_THROW_ON_ERROR);
$runtime = realpath($descriptor['runtime'] ?? '');
$settingsPath = realpath($descriptor['settings_file'] ?? '');
if (!$runtime || !str_starts_with($runtime, $runtimeRoot . DIRECTORY_SEPARATOR)
    || !$settingsPath || dirname($settingsPath) !== $runtime
    || ($descriptor['synthetic_only'] ?? false) !== true
    || ($descriptor['auth_method'] ?? '') !== 'hmac_sha256'
    || ($descriptor['database'] ?? '') !== 'aurel_roomtype_v2_test_c66440d55c61'
    || (int) ($descriptor['booking_id'] ?? 0) !== 1 || (int) ($descriptor['payment_id'] ?? 0) !== 1
    || ($descriptor['payment_reference'] ?? '') !== 'AURELSEPAYC4C54B92A009'
    || (int) ($descriptor['amount_vnd'] ?? 0) !== 200000) {
    fwrite(STDERR, "Staging descriptor does not match the approved synthetic fixture.\n");
    exit(2);
}

$settings = json_decode(file_get_contents($settingsPath), true, 512, JSON_THROW_ON_ERROR);
if (($settings['database'] ?? '') !== $descriptor['database']
    || !is_file($descriptor['mysql_descriptor'] ?? '')) {
    fwrite(STDERR, "Staging settings do not match the owned synthetic database.\n");
    exit(2);
}

$environment = [
    'AUREL_ROOMTYPE_V2_TEST_SETTINGS' => $settingsPath,
    'APP_ENV' => 'testing',
];
foreach ($environment as $key => $value) {
    putenv($key . '=' . $value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require $root . '/tests/Support/roomtype_v2_test_bootstrap.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (!app()->environment('testing')
    || config('roomtype-v2.production_manifest') !== null
    || config('roomtype-v2.staging_checkout') !== false
    || config('roomtype-v2.prepared_routes') !== false
    || config('roomtype-v2.integration_complete') !== false
    || config('roomtype-v2.final_integration') !== false
    || config('roomtype-v2.staging_mail') !== null) {
    throw new RuntimeException('Application test/Checkout/mail isolation guards are not in their expected state.');
}

$db = RoomTypeV2TestDatabase::connection('mysql');
$payment = Payment::with('booking')->findOrFail((int) $descriptor['payment_id']);
$booking = $payment->booking;
$reference = (string) $descriptor['payment_reference'];
$amount = (int) $descriptor['amount_vnd'];

if (!$booking || (int) $booking->id !== (int) $descriptor['booking_id']
    || $payment->payment_status !== 'success' || $payment->transaction_id !== $reference
    || (int) $payment->amount !== $amount || $booking->status !== 'confirmed') {
    throw new RuntimeException('Synthetic Payment/Booking are not in the expected paid and confirmed state.');
}

$invoiceQuery = Invoice::with('items')->where('booking_id', $booking->id);
if ($invoiceQuery->count() !== 1) {
    throw new RuntimeException('Expected exactly one existing synthetic invoice; refusing to proceed.');
}
$invoice = $invoiceQuery->firstOrFail();
$receiptCount = $db->table('payment_gateway_receipt')
    ->where('payment_id', $payment->id)->where('provider', 'sepay')
    ->where('outcome', 'success')->count();
$effectQuery = $db->table('booking_effect')
    ->where('booking_id', $booking->id)->where('payment_id', $payment->id);
if ($receiptCount !== 1 || $effectQuery->count() !== 1
    || $effectQuery->value('state') !== 'delivered'
    || (int) $effectQuery->value('invoice_id') !== (int) $invoice->id) {
    throw new RuntimeException('Existing successful receipt, delivered effect, and invoice do not match exactly once.');
}

$attemptDirectory = $runtime . DIRECTORY_SEPARATOR . 'smtp-attempts';
$attemptPath = $attemptDirectory . DIRECTORY_SEPARATOR . $attemptId . '.json';
$lockPath = $attemptPath . '.lock';

config(['aurel_email_test.warning' => true]);
$mailable = new BookingConfirmedV2($booking);
$html = $mailable->render();
if (!str_contains($html, 'THỬ NGHIỆM — KHÔNG CÓ GIÁ TRỊ THANH TOÁN')) {
    throw new RuntimeException('The rendered confirmation email is missing its mandatory test warning.');
}
if (count($mailable->rawAttachments) !== 1) {
    throw new RuntimeException('The existing invoice PDF was not attached exactly once.');
}

if ($mode === 'prepare') {
    if (!is_dir($attemptDirectory) && !mkdir($attemptDirectory, 0700, true) && !is_dir($attemptDirectory)) {
        throw new RuntimeException('Could not create the private SMTP attempt directory.');
    }
    $marker = [
        'state' => 'prepared',
        'attempt_id' => $attemptId,
        'created_at_utc' => gmdate('c'),
        'booking_id' => (int) $booking->id,
        'payment_id' => (int) $payment->id,
        'invoice_id' => (int) $invoice->id,
    ];
    $markerHandle = @fopen($attemptPath, 'x');
    if ($markerHandle === false) {
        fwrite(STDERR, "SMTP attempt preparation blocked: attempt ID already exists.\n");
        exit(4);
    }
    try {
        if (!flock($markerHandle, LOCK_EX)
            || fwrite($markerHandle, json_encode($marker, JSON_THROW_ON_ERROR) . PHP_EOL) === false
            || !fflush($markerHandle)) {
            throw new RuntimeException('Could not persist the independent SMTP attempt marker.');
        }
        if (function_exists('fsync')) {
            fsync($markerHandle);
        }
    } finally {
        flock($markerHandle, LOCK_UN);
        fclose($markerHandle);
    }
    fwrite(STDOUT, "PREPARED: attempt_id={$attemptId}; independent marker created; no SMTP transport used.\n");
    exit(0);
}

$markerData = is_file($attemptPath)
    ? json_decode(file_get_contents($attemptPath), true, 512, JSON_THROW_ON_ERROR)
    : null;
if (!is_array($markerData) || ($markerData['attempt_id'] ?? null) !== $attemptId
    || ($markerData['state'] ?? null) !== 'prepared') {
    fwrite(STDERR, "SMTP attempt blocked: no matching prepared marker or attempt already consumed.\n");
    exit(4);
}

$lockHandle = @fopen($lockPath, 'x');
if ($lockHandle === false) {
    fwrite(STDERR, "SMTP attempt blocked: another operation owns this attempt marker.\n");
    exit(4);
}

$fakeProcess = null;
$fakePipes = [];
$markerHandle = null;
$failureContext = 'attempt_setup';
$fixtureSummary = 'none';
try {
    $recipient = getenv('AUREL_STAGING_EMAIL_RECIPIENT');
    $smtpUser = getenv('AUREL_STAGING_SMTP_USER');
    $smtpPassword = getenv('AUREL_STAGING_SMTP_PASSWORD');
    if ($mode === 'local-test') {
        $failureContext = 'fixture_start';
        if (getenv('AUREL_STAGING_SMTP_PASSWORD') !== false || getenv('AUREL_STAGING_SMTP_USER') !== false
            || getenv('AUREL_STAGING_EMAIL_RECIPIENT') !== false) {
            throw new RuntimeException('Local SMTP test refuses Gmail credentials or recipient overrides.');
        }
        $listener = stream_socket_server('tcp://127.0.0.1:0', $socketErrorCode, $socketErrorMessage);
        if ($listener === false) {
            throw new RuntimeException('Could not reserve a loopback port for the local SMTP fixture.');
        }
        $address = stream_socket_get_name($listener, false);
        fclose($listener);
        $port = (int) substr(strrchr($address, ':'), 1);
        if ($port < 1024 || $port === 3306) {
            throw new RuntimeException('Local SMTP fixture selected an unsafe port.');
        }
        $command = [PHP_BINARY, $root . '/tests/Support/fake_smtp_server.php', (string) $port, 'success', $attemptId];
        $fakeProcess = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $fakePipes, $root);
        if (!is_resource($fakeProcess)) {
            throw new RuntimeException('Could not start the loopback SMTP fixture.');
        }
        stream_set_timeout($fakePipes[1], 5);
        if (fgets($fakePipes[1]) !== "READY\n") {
            throw new RuntimeException('Loopback SMTP fixture did not become ready.');
        }
        $recipient = 'smtp-capture@example.invalid';
        $smtpUser = 'aurel-local-test@example.invalid';
        $smtpPassword = null;
        $host = '127.0.0.1';
        $portConfig = $port;
        $mailer = 'aurel_oneoff_local_test';
    } else {
        if (!is_string($recipient) || !preg_match('/^[A-Z0-9._%+-]+@gmail\.com$/iD', $recipient)
            || !is_string($smtpUser) || !preg_match('/^[A-Z0-9._%+-]+@gmail\.com$/iD', $smtpUser)
            || !is_string($smtpPassword) || $smtpPassword === '') {
            throw new RuntimeException('A separately confirmed Gmail recipient, Gmail sending account, and locally prompted App Password are required.');
        }
        if ($smtpPassword !== preg_replace('/\s+/', '', $smtpPassword)) {
            throw new RuntimeException('Enter the Gmail App Password without whitespace.');
        }
        $host = 'smtp.gmail.com';
        $portConfig = 587;
        $mailer = 'aurel_oneoff_test';
    }

    config([
        'mail.mailers.' . $mailer => [
            'transport' => 'smtp',
            'scheme' => 'smtp',
            'host' => $host,
            'port' => $portConfig,
            'encryption' => $mode === 'local-test' ? null : 'tls',
            'username' => $smtpUser,
            'password' => $smtpPassword,
            'timeout' => $mode === 'local-test' ? 3 : 20,
            'local_domain' => 'aurel-attempt.invalid',
            'from' => ['address' => $smtpUser, 'name' => 'AUREL — Email thử nghiệm'],
            'to' => null,
            'reply_to' => null,
            'return_path' => null,
        ],
        'mail.from' => ['address' => $smtpUser, 'name' => 'AUREL — Email thử nghiệm'],
    ]);

    if ($mode === 'send') {
        $markerData['state'] = 'attempt_started';
        $markerData['started_at_utc'] = gmdate('c');
        $markerData['recipient_sha256'] = hash('sha256', strtolower($recipient));
        $markerHandle = @fopen($attemptPath, 'c+');
        if ($markerHandle === false || !flock($markerHandle, LOCK_EX)) {
            throw new RuntimeException('Could not lock independent attempt marker; refusing to send.');
        }
        ftruncate($markerHandle, 0);
        rewind($markerHandle);
        fwrite($markerHandle, json_encode($markerData, JSON_THROW_ON_ERROR) . PHP_EOL);
        fflush($markerHandle);
        if (function_exists('fsync')) {
            fsync($markerHandle);
        }
        flock($markerHandle, LOCK_UN);
        fclose($markerHandle);
        $markerHandle = null;
    }

    $mailable->withSymfonyMessage(function (Email $message) use ($recipient, $smtpUser, $attemptId): void {
        $to = $message->getTo();
        $from = $message->getFrom();
        if (count($to) !== 1 || strcasecmp($to[0]->getAddress(), $recipient) !== 0
            || count($from) !== 1 || strcasecmp($from[0]->getAddress(), $smtpUser) !== 0
            || $message->getCc() !== [] || $message->getBcc() !== []) {
            throw new RuntimeException('Resolved message recipients differ from the single confirmed recipient.');
        }
        $message->subject('THỬ NGHIỆM — KHÔNG CÓ GIÁ TRỊ THANH TOÁN | ATTEMPT ' . $attemptId . ' | ' . $message->getSubject());
        $message->getHeaders()->addTextHeader('X-AUREL-Test-Email', 'No payment value');
        $message->getHeaders()->addTextHeader('X-AUREL-SMTP-Attempt', $attemptId);
    });
    $failureContext = $mode === 'local-test' ? 'local_smtp_transport' : 'gmail_smtp_transport';
    $result = Mail::mailer($mailer)->to($recipient)->send($mailable);
    if (!$result) {
        throw new RuntimeException('SMTP transport did not return an accepted message.');
    }
    if ($mode === 'local-test') {
        $failureContext = 'fixture_capture';
        $fixtureOutput = stream_get_contents($fakePipes[1]);
        $fakeStatus = proc_close($fakeProcess);
        $fakeProcess = null;
        if (preg_match('/^(CAPTURE_OK [a-f0-9-]{36}|CAPTURE_MISMATCH subject=[01] header=[01])$/m', trim($fixtureOutput), $summary)) {
            $fixtureSummary = $summary[1];
        } else {
            $fixtureSummary = 'no_capture_summary';
        }
        if ($fakeStatus !== 0 || !str_contains($fixtureOutput, 'CAPTURE_OK ' . $attemptId)) {
            throw new RuntimeException('Local SMTP fixture did not verify the attempt Subject and header.');
        }
        $markerData['local_simulation'] = ['state' => 'passed', 'completed_at_utc' => gmdate('c')];
        file_put_contents($attemptPath, json_encode($markerData, JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);
        fwrite(STDOUT, "LOCAL_SMTP_TEST_PASSED: attempt_id={$attemptId}; no Internet transport used; Gmail marker remains prepared.\n");
    } else {
        $markerData['state'] = 'smtp_accepted';
        $markerData['accepted_at_utc'] = gmdate('c');
        file_put_contents($attemptPath, json_encode($markerData, JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);
        fwrite(STDOUT, "SMTP_ACCEPTED: Gmail SMTP accepted the one test message. Inbox delivery is not verified.\n");
    }
} catch (Throwable $exception) {
    if ($mode === 'send' && is_array($markerData)) {
        $diagnostic = SmtpFailureDiagnostics::from($exception);
        $markerData['state'] = 'uncertain';
        $markerData['smtp_diagnostic'] = $diagnostic;
        $markerData['updated_at_utc'] = gmdate('c');
        file_put_contents($attemptPath, json_encode($markerData, JSON_THROW_ON_ERROR) . PHP_EOL, LOCK_EX);
        fwrite(STDERR, 'SMTP_UNCERTAIN: attempt_id=' . $attemptId . '; stage=' . $diagnostic['stage']
            . '; smtp_code=' . ($diagnostic['smtp_code'] ?? 'none')
            . '; exception=' . $diagnostic['exception_type'] . "; no automatic retry is allowed.\n");
    } else {
        $diagnostic = SmtpFailureDiagnostics::from($exception);
        fwrite(STDERR, 'LOCAL_SMTP_TEST_FAILED: phase=' . $failureContext . '; stage=' . $diagnostic['stage']
            . '; smtp_code=' . ($diagnostic['smtp_code'] ?? 'none')
            . '; exception=' . $diagnostic['exception_type']
            . ($failureContext === 'fixture_capture' ? '; fixture=' . $fixtureSummary : '')
            . "; isolated attempt remains prepared; no Gmail send is permitted by this failure.\n");
    }
    if (is_resource($markerHandle)) {
        flock($markerHandle, LOCK_UN);
        fclose($markerHandle);
    }
    if (is_resource($fakeProcess)) {
        proc_terminate($fakeProcess);
        proc_close($fakeProcess);
    }
    if (is_resource($lockHandle)) {
        fclose($lockHandle);
    }
    @unlink($lockPath);
    exit(3);
}

if (is_resource($lockHandle)) {
    fclose($lockHandle);
}
@unlink($lockPath);
