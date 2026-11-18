<?php

if (!in_array($argc, [3, 4], true) || !ctype_digit($argv[1])) {
    fwrite(STDERR, "Invalid local SMTP fixture arguments.\n");
    exit(2);
}

$scenario = $argv[2];
$expectedAttemptId = $argv[3] ?? null;
$tlsFiles = [];
$contextOptions = [];
if ($scenario === 'auth-tls') {
    if (!function_exists('openssl_pkey_new')) {
        fwrite(STDERR, "OpenSSL is required for the local TLS fixture.\n");
        exit(2);
    }
    $keyOptions = ['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048];
    $opensslConfig = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras'
        . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';
    $opensslOptions = [];
    if (is_file($opensslConfig)) {
        $keyOptions['config'] = $opensslConfig;
        $opensslOptions['config'] = $opensslConfig;
    }
    $privateKey = openssl_pkey_new($keyOptions);
    $request = $privateKey === false
        ? false
        : openssl_csr_new(['commonName' => 'localhost'], $privateKey, $opensslOptions);
    $certificate = $request === false
        ? false
        : openssl_csr_sign($request, null, $privateKey, 1, $opensslOptions);
    $certificateExported = $certificate === false
        ? false
        : openssl_x509_export($certificate, $certificatePem);
    $privateKeyExported = $privateKey === false
        ? false
        : openssl_pkey_export($privateKey, $privateKeyPem, null, $opensslOptions);
    if ($certificateExported === false || $privateKeyExported === false) {
        fwrite(STDERR, "Could not create local TLS fixture certificate.\n");
        exit(2);
    }
    $certificatePath = tempnam(sys_get_temp_dir(), 'aurel-smtp-cert-');
    $keyPath = tempnam(sys_get_temp_dir(), 'aurel-smtp-key-');
    if ($certificatePath === false || $keyPath === false
        || file_put_contents($certificatePath, $certificatePem) === false
        || file_put_contents($keyPath, $privateKeyPem) === false) {
        fwrite(STDERR, "Could not persist local TLS fixture certificate.\n");
        exit(2);
    }
    chmod($keyPath, 0600);
    $tlsFiles = [$certificatePath, $keyPath];
    $contextOptions = ['ssl' => [
        'local_cert' => $certificatePath,
        'local_pk' => $keyPath,
        'verify_peer' => false,
    ]];
}
$context = stream_context_create($contextOptions);
$server = stream_socket_server(
    'tcp://127.0.0.1:' . (int) $argv[1],
    $errorCode,
    $errorMessage,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
    $context
);
if ($server === false) {
    fwrite(STDERR, "Could not bind local SMTP fixture.\n");
    foreach ($tlsFiles as $tlsFile) {
        @unlink($tlsFile);
    }
    exit(2);
}

fwrite(STDOUT, "READY\n");
fflush(STDOUT);
$client = @stream_socket_accept($server, 8);
if ($client === false) {
    fclose($server);
    foreach ($tlsFiles as $tlsFile) {
        @unlink($tlsFile);
    }
    exit(0);
}
stream_set_timeout($client, 3);
fwrite($client, "220 local-test.invalid ESMTP\r\n");
$tlsActive = false;

$readLine = static function () use ($client): ?string {
    $line = fgets($client);
    return $line === false ? null : rtrim($line, "\r\n");
};
$writeReply = static function (string $reply) use ($client): void {
    fwrite($client, $reply . "\r\n");
    fflush($client);
};

while (($line = $readLine()) !== null) {
    $command = strtoupper($line);
    if (str_starts_with($command, 'EHLO ') || str_starts_with($command, 'HELO ')) {
        if ($scenario === 'timeout') {
            usleep(900000);
            break;
        }
        if ($scenario === 'starttls' || ($scenario === 'auth-tls' && !$tlsActive)) {
            $writeReply("250-local-test.invalid\r\n250 STARTTLS");
            continue;
        }
        if ($scenario === 'auth' || ($scenario === 'auth-tls' && $tlsActive)) {
            $writeReply("250-local-test.invalid\r\n250-AUTH LOGIN\r\n250 HELP");
            continue;
        }
        $writeReply("250 local-test.invalid");
        continue;
    }

    if ($scenario === 'starttls' && $command === 'STARTTLS') {
        $writeReply('454 TLS not available in local fixture');
        continue;
    }
    if ($scenario === 'auth-tls' && $command === 'STARTTLS') {
        $writeReply('220 Begin TLS');
        stream_context_set_option($client, 'ssl', 'local_cert', $tlsFiles[0]);
        stream_context_set_option($client, 'ssl', 'local_pk', $tlsFiles[1]);
        stream_context_set_option($client, 'ssl', 'verify_peer', false);
        if (stream_socket_enable_crypto($client, true, STREAM_CRYPTO_METHOD_TLS_SERVER) !== true) {
            fwrite(STDOUT, "TLS_HANDSHAKE_FAILED\n");
            fflush(STDOUT);
            break;
        }
        $tlsActive = true;
        continue;
    }
    if (($scenario === 'auth' || ($scenario === 'auth-tls' && $tlsActive)) && $command === 'AUTH LOGIN') {
        $writeReply('334 VXNlcm5hbWU6');
        $readLine();
        $writeReply('334 UGFzc3dvcmQ6');
        $readLine();
        fwrite(STDOUT, "AUTH_EXCHANGE_COMPLETE\n");
        fflush(STDOUT);
        $writeReply('535 5.7.8 Authentication rejected by local fixture');
        continue;
    }
    if (in_array($scenario, ['auth', 'auth-tls'], true)
        && (str_starts_with($command, 'MAIL FROM:') || str_starts_with($command, 'RCPT TO:') || $command === 'DATA')) {
        fwrite(STDOUT, "UNEXPECTED_SEND_COMMAND\n");
        fflush(STDOUT);
        $writeReply('500 Email commands are disabled in the auth fixture');
        continue;
    }
    if ($command === 'RSET') {
        $writeReply('250 Reset');
        continue;
    }
    if (str_starts_with($command, 'MAIL FROM:')) {
        $writeReply('250 Sender accepted');
        continue;
    }
    if (str_starts_with($command, 'RCPT TO:')) {
        $writeReply('250 Recipient accepted');
        continue;
    }
    if ($command === 'DATA') {
        $writeReply('354 End data with <CR><LF>.<CR><LF>');
        $headers = [];
        $currentHeader = '';
        while (($headerLine = $readLine()) !== null && $headerLine !== '') {
            if (preg_match('/^[ \t]/', $headerLine) && $currentHeader !== '') {
                $currentHeader .= ' ' . trim($headerLine);
                continue;
            }
            if ($currentHeader !== '') {
                $headers[] = $currentHeader;
            }
            $currentHeader = $headerLine;
        }
        if ($currentHeader !== '') {
            $headers[] = $currentHeader;
        }
        do {
            $dataLine = $readLine();
        } while ($dataLine !== null && $dataLine !== '.');

        if ($scenario === 'data') {
            $writeReply('552 5.3.4 Message rejected by local fixture');
        } else {
            $writeReply('250 2.0.0 Queued by local fixture');
        }
        if ($expectedAttemptId !== null) {
            $headers = implode("\n", $headers);
            $decodedHeaders = preg_replace_callback(
                '/=\?UTF-8\?B\?([^?]+)\?=/i',
                static fn (array $match): string => base64_decode($match[1], true) ?: '',
                $headers
            );
            $subjectMatches = preg_match('/^Subject:.*ATTEMPT[ =]+' . preg_quote($expectedAttemptId, '/') . '/im', $decodedHeaders) === 1;
            $headerMatches = preg_match('/^X-AUREL-SMTP-Attempt:\s*' . preg_quote($expectedAttemptId, '/') . '\s*$/im', $headers) === 1;
            fwrite(STDOUT, $subjectMatches && $headerMatches
                ? 'CAPTURE_OK ' . $expectedAttemptId . PHP_EOL
                : 'CAPTURE_MISMATCH subject=' . (int) $subjectMatches . ' header=' . (int) $headerMatches . PHP_EOL);
            fflush(STDOUT);
            break;
        }
        continue;
    }
    if ($command === 'QUIT') {
        $writeReply('221 Bye');
        break;
    }

    $writeReply('500 Unsupported local fixture command');
}

fclose($client);
fclose($server);
foreach ($tlsFiles as $tlsFile) {
    @unlink($tlsFile);
}
