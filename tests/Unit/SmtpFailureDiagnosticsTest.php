<?php

namespace Tests\Unit;

use App\Support\SmtpFailureDiagnostics;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;

class SmtpFailureDiagnosticsTest extends TestCase
{
    public function test_it_classifies_only_explicit_smtp_phases_and_codes(): void
    {
        $cases = [
            'connection' => [
                new TransportException('Connection could not be established with host smtp.gmail.com:587: Connection refused'),
                'connection',
                null,
            ],
            'starttls' => [
                new TransportException('Unable to connect with STARTTLS'),
                'starttls',
                null,
            ],
            'authentication' => [
                new TransportException('Failed to authenticate on SMTP server', 535),
                'authentication',
                535,
            ],
            'send data' => [
                new UnexpectedResponseException('SMTP DATA command rejected with code 552', 552),
                'send_data',
                552,
            ],
            'ambiguous' => [
                new TransportException('Connection to smtp.gmail.com:587 has been closed unexpectedly'),
                'unknown',
                null,
            ],
        ];

        foreach ($cases as $name => [$exception, $stage, $code]) {
            $diagnostic = SmtpFailureDiagnostics::from($exception);
            $this->assertSame($stage, $diagnostic['stage'], $name);
            $this->assertSame($code, $diagnostic['smtp_code'], $name);
        }
    }

    public function test_diagnostic_never_contains_exception_credentials_auth_payload_or_message_body(): void
    {
        $secrets = [
            'smtp-user-private@gmail.com',
            'APP_PASSWORD_FAKE_1234',
            'AUTH_PLAIN_FAKE_BASE64_TOKEN',
            'Bearer fake-oauth-access-token',
            'private booking email body',
        ];
        $exception = new TransportException(
            'Failed to authenticate on SMTP server with username "' . $secrets[0]
            . '" using AUTH payload "' . $secrets[2] . '"; password="' . $secrets[1]
            . '"; token="' . $secrets[3] . '"; DATA "' . $secrets[4] . '"',
            535
        );
        $authPayload = base64_encode($secrets[0] . "\0" . $secrets[0] . "\0" . $secrets[1]);
        $exception->appendDebug("> STARTTLS\n< 220 Ready to start TLS\n> EHLO staging.invalid\n> AUTH PLAIN "
            . $authPayload . "\n< 535 Rejected\n");
        $serialized = json_encode(SmtpFailureDiagnostics::from($exception), JSON_THROW_ON_ERROR);

        $this->assertSame('authentication', json_decode($serialized, true, 512, JSON_THROW_ON_ERROR)['stage']);
        foreach ($secrets as $secret) {
            $this->assertStringNotContainsString($secret, $serialized);
        }
        $this->assertStringNotContainsString($authPayload, $serialized);
        $this->assertStringNotContainsString('password', strtolower($serialized));
        $this->assertStringNotContainsString('payload', strtolower($serialized));
    }

    public function test_smtp_reply_code_is_taken_only_from_a_typed_smtp_response_exception(): void
    {
        $exception = new TransportException('Generic error', 535);

        $this->assertNull(SmtpFailureDiagnostics::from($exception)['smtp_code']);
    }
}
