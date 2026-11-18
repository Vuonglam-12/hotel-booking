<?php

namespace App\Support;

use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Symfony\Component\Mailer\Exception\TransportException;
use Throwable;

final class SmtpFailureDiagnostics
{
    public static function from(Throwable $exception): array
    {
        $messages = [];
        $smtpCode = null;
        $current = $exception;
        $depth = 0;

        while ($current !== null && $depth < 8) {
            $messages[] = $current->getMessage();
            if ($smtpCode === null && $current instanceof UnexpectedResponseException) {
                $code = $current->getCode();
                if ($code >= 200 && $code <= 599) {
                    $smtpCode = $code;
                }
            }
            $current = $current->getPrevious();
            $depth++;
        }

        $evidence = implode("\n", $messages);
        $debug = $exception instanceof TransportException ? $exception->getDebug() : '';
        [$stage, $evidenceCode] = self::classify($evidence, self::lastSmtpCommand($debug));
        if ($smtpCode === null) {
            $smtpCode = $evidenceCode;
        }
        if ($smtpCode === null && in_array($stage, ['authentication', 'send_data'], true)
            && $exception instanceof TransportException
            && $exception->getCode() >= 200 && $exception->getCode() <= 599) {
            $smtpCode = $exception->getCode();
        }

        return [
            'stage' => $stage,
            'smtp_code' => $smtpCode,
            'evidence' => $stage === 'unknown' ? null : $stage . '_failure',
            'exception_type' => get_class($exception),
        ];
    }

    private static function classify(string $evidence, ?string $lastCommand): array
    {
        if ($lastCommand === 'STARTTLS' || preg_match('/TLS handshake|TLS negotiation|Unable to connect with STARTTLS/i', $evidence)) {
            return ['starttls', self::extractSmtpCode($evidence)];
        }

        if (preg_match('/Failed to authenticate|authentication failed|authenticator .* returned|SMTP AUTH\b/i', $evidence)
            || $lastCommand === 'AUTH') {
            return ['authentication', self::extractSmtpCode($evidence)];
        }

        if (preg_match('/\b(?:SMTP\s+)?DATA(?:\s+command)?\s+(?:was\s+)?(?:rejected|failed|refused)|\bDATA command\b.*\b(?:\d{3}|reject|fail)/i', $evidence)
            || $lastCommand === 'DATA') {
            return ['send_data', self::extractSmtpCode($evidence)];
        }

        if (preg_match('/Connection could not be established|Failed to connect to|Connection refused|timed out/i', $evidence)
            && ($lastCommand === null || in_array($lastCommand, ['EHLO', 'HELO'], true))) {
            return ['connection', self::extractSmtpCode($evidence)];
        }

        return ['unknown', self::extractSmtpCode($evidence)];
    }

    private static function lastSmtpCommand(string $debug): ?string
    {
        if (!preg_match_all('/^>\s*(EHLO|HELO|STARTTLS|AUTH|MAIL\s+FROM:|RCPT\s+TO:|DATA|RSET|QUIT)\b[^\r\n]*/im', $debug, $matches)
            || $matches[1] === []) {
            return null;
        }

        return strtoupper(preg_replace('/\s+/', ' ', trim(end($matches[1]))));
    }

    private static function extractSmtpCode(string $evidence): ?int
    {
        if (preg_match('/(?:SMTP\s+(?:response|reply|error)\s+(?:code\s*)?|got\s+code\s+|returned\s+code\s+)([245]\d{2})\b/i', $evidence, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
}
