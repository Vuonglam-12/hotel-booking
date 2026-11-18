<?php

namespace Tests\Unit;

use App\Support\SmtpFailureDiagnostics;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;

class SmtpFailureDiagnosticsLocalTransportTest extends TestCase
{
    private array $servers = [];

    protected function tearDown(): void
    {
        foreach ($this->servers as [$process, $pipes]) {
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
        }

        parent::tearDown();
    }

    public function test_local_smtp_accepts_a_message_without_using_the_internet(): void
    {
        [$port] = $this->startServer('success');
        $result = $this->transport($port)->send($this->message());

        $this->assertInstanceOf(SentMessage::class, $result);
    }

    public function test_local_connection_refusal_is_classified_as_connection_failure(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        $this->assertNotFalse($socket);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $port = (int) substr(strrchr($address, ':'), 1);

        $diagnostic = $this->diagnose(fn () => $this->transport($port)->send($this->message()));

        $this->assertSame('connection', $diagnostic['stage']);
        $this->assertNull($diagnostic['smtp_code']);
    }

    public function test_starttls_refusal_records_its_smtp_reply_code(): void
    {
        [$port] = $this->startServer('starttls');
        $diagnostic = $this->diagnose(fn () => $this->transport($port)->send($this->message()));

        $this->assertSame('starttls', $diagnostic['stage']);
        $this->assertSame(454, $diagnostic['smtp_code']);
    }

    public function test_rejected_local_credentials_are_classified_without_being_recorded(): void
    {
        [$port] = $this->startServer('auth');
        $transport = $this->transport($port);
        $transport->setUsername('fake-smtp-user@example.invalid');
        $transport->setPassword('FAKE_PASSWORD_DO_NOT_STORE_9182');
        $diagnostic = $this->diagnose(fn () => $transport->send($this->message()));
        $serialized = json_encode($diagnostic, JSON_THROW_ON_ERROR);

        $this->assertSame('authentication', $diagnostic['stage']);
        $this->assertSame(535, $diagnostic['smtp_code']);
        $this->assertStringNotContainsString('fake-smtp-user', $serialized);
        $this->assertStringNotContainsString('FAKE_PASSWORD', $serialized);
    }

    public function test_local_smtp_timeout_is_classified_from_the_ehlo_exchange(): void
    {
        [$port] = $this->startServer('timeout');
        $transport = $this->transport($port, 0.2);
        $diagnostic = $this->diagnose(fn () => $transport->send($this->message()));

        $this->assertSame('connection', $diagnostic['stage']);
        $this->assertNull($diagnostic['smtp_code']);
    }

    public function test_message_data_rejection_records_code_without_leaking_message_content(): void
    {
        [$port] = $this->startServer('data');
        $body = 'FAKE_PRIVATE_BOOKING_BODY_7183';
        $diagnostic = $this->diagnose(fn () => $this->transport($port)->send($this->message($body)));
        $serialized = json_encode($diagnostic, JSON_THROW_ON_ERROR);

        $this->assertSame('send_data', $diagnostic['stage']);
        $this->assertSame(552, $diagnostic['smtp_code']);
        $this->assertStringNotContainsString($body, $serialized);
        $this->assertStringNotContainsString('local-test.invalid', $serialized);
    }

    private function diagnose(callable $send): array
    {
        try {
            $send();
            $this->fail('Expected the local SMTP fixture to reject or interrupt the operation.');
        } catch (TransportException $exception) {
            return SmtpFailureDiagnostics::from($exception);
        }

        throw new \LogicException('Unreachable');
    }

    private function transport(int $port, float $timeout = 2.0): EsmtpTransport
    {
        $transport = new EsmtpTransport('127.0.0.1', $port, false);
        $transport->getStream()->setTimeout($timeout);

        return $transport;
    }

    private function message(string $body = 'Local SMTP test body'): Email
    {
        return (new Email())
            ->from('sender@example.invalid')
            ->to('recipient@example.invalid')
            ->subject('Local SMTP diagnostic test')
            ->text($body);
    }

    private function startServer(string $scenario): array
    {
        $reservation = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        $this->assertNotFalse($reservation);
        $address = stream_socket_get_name($reservation, false);
        fclose($reservation);
        $port = (int) substr(strrchr($address, ':'), 1);

        $command = [PHP_BINARY, __DIR__ . '/../Support/fake_smtp_server.php', (string) $port, $scenario];
        $pipes = [];
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, dirname(__DIR__, 2));
        $this->assertIsResource($process);
        $this->servers[] = [$process, $pipes];
        stream_set_timeout($pipes[1], 5);
        $ready = fgets($pipes[1]);
        $this->assertSame("READY\n", $ready, 'Local SMTP fixture did not start.');

        return [$port, $process, $pipes];
    }
}
