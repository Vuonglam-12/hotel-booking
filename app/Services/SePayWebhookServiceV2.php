<?php

namespace App\Services;

use App\Support\RoomTypeV2BookingAudit;
use App\Support\RoomTypeV2Inventory;
use App\Support\RoomTypeV2Writer;
use Carbon\Carbon;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class SePayWebhookServiceV2
{
    public function enabled(): bool
    {
        return config('services.sepay.webhook.enabled', false) === true;
    }

    public function assertOperational(): array
    {
        $configuration = config('services.sepay.webhook', []);
        if (!$this->enabled()) {
            throw new \LogicException('SePay webhook is disabled.');
        }
        if (($configuration['mode'] ?? 'test') !== 'test') {
            throw new \LogicException('Only SePay Test mode is accepted by this integration.');
        }
        if (!app()->environment('testing') || config('roomtype-v2.production_manifest') !== null) {
            throw new \LogicException('SePay Test mode may only write through the owned isolated MySQL test environment.');
        }
        foreach (['integration_complete', 'final_integration', 'delivery_safety'] as $flag) {
            if (config('roomtype-v2.' . $flag, false) !== true) {
                throw new \LogicException('The verified Booking V2 payment and delivery guards are not enabled.');
            }
        }
        if (!preg_match('/^[0-9]{4,30}$/D', $configuration['account_number'] ?? '')) {
            throw new \LogicException('Configure the exact SePay Test mode receiving account number.');
        }
        $method = $configuration['auth_method'] ?? null;
        if (!in_array($method, ['api_key', 'hmac_sha256'], true)) {
            throw new \LogicException('Select API Key or HMAC-SHA256 authentication in SePay and configure the matching method.');
        }
        $secret = $method === 'api_key' ? ($configuration['api_key'] ?? '') : ($configuration['hmac_secret'] ?? '');
        if (!is_string($secret) || $secret === '' || strlen($secret) > 512) {
            throw new \LogicException('A non-empty SePay webhook credential is required.');
        }
        return $configuration;
    }

    public function authenticate(Request $request): bool
    {
        $configuration = $this->assertOperational();
        if ($configuration['auth_method'] === 'api_key') {
            $provided = $request->header('Authorization', '');
            $expected = 'Apikey ' . $configuration['api_key'];
            return is_string($provided) && hash_equals($expected, $provided);
        }

        $signature = $request->header('X-SePay-Signature', '');
        $timestamp = $request->header('X-SePay-Timestamp', '');
        if (!is_string($signature) || !preg_match('/^sha256=[a-f0-9]{64}$/D', $signature)
            || !is_string($timestamp) || !ctype_digit($timestamp) || strlen($timestamp) > 12
            || abs(now()->timestamp - (int) $timestamp) > 300) {
            return false;
        }
        $expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $request->getContent(), $configuration['hmac_secret']);
        return hash_equals($expected, $signature);
    }

    public function receive(string $rawBody): array
    {
        $this->assertOperational();
        if ($rawBody === '' || strlen($rawBody) > 16384) {
            throw ValidationException::withMessages(['payload' => 'Webhook body is empty or exceeds the supported size.']);
        }
        try {
            $decoded = json_decode($rawBody, false, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ValidationException::withMessages(['payload' => 'Webhook body must be valid JSON.']);
        }
        if (!$decoded instanceof \stdClass) {
            throw ValidationException::withMessages(['payload' => 'Webhook body must be a JSON object.']);
        }

        $payload = get_object_vars($decoded);
        $event = $this->normalize($payload);
        $db = RoomTypeV2Inventory::open('mysql')->db;

        try {
            if ($event['payload']['transferType'] !== 'in') {
                return $this->recordUnmatched($db, $event, null, 'outgoing_ignored');
            }
            if ($event['payload']['accountNumber'] !== $this->assertOperational()['account_number']) {
                return $this->recordUnmatched($db, $event, null, 'account_mismatch_reconciliation');
            }

            $payments = $this->findPayments($db, $event['payload']);
            if ($payments->count() !== 1) {
                return $this->recordUnmatched(
                    $db,
                    $event,
                    $event['payload']['code'] ?: null,
                    $payments->isEmpty() ? 'reference_unmatched_reconciliation' : 'ambiguous_reference_reconciliation'
                );
            }

            $candidate = $payments->first();
            return RoomTypeV2Writer::run('mysql', (int) $candidate->booking_id, function ($session, $booking, $lines, $types) use ($candidate, $event) {
                $db = $session->db;
                $payment = $db->table('payment')->where('id', $candidate->id)->where('booking_id', $booking->id)->lockForUpdate()->first();
                if (!$payment || $payment->payment_method !== 'banking' || !$this->matchesReference($event['payload'], $payment->transaction_id)) {
                    return $this->insertEvent($db, $event, null, $event['payload']['code'] ?: null, 'reference_unmatched_reconciliation');
                }

                $outcome = $this->applyToPayment($session, $booking, $lines, $types, $payment, $event);
                return $outcome;
            });
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) !== 1062) {
                throw $e;
            }
            $existing = $db->table('sepay_webhook_event')->where('provider_transaction_id', $event['id'])->first();
            if (!$existing) {
                throw $e;
            }
            if (!hash_equals($existing->event_hash, $event['event_hash'])) {
                throw ValidationException::withMessages(['id' => 'Conflicting payload for an already-recorded SePay transaction ID.']);
            }
            return ['duplicate' => true, 'outcome' => $existing->outcome, 'payment_id' => $existing->payment_id];
        }
    }

    private function normalize(array $payload): array
    {
        $id = $payload['id'] ?? null;
        if (is_int($id) && $id > 0) {
            $id = (string) $id;
        } elseif (is_string($id) && strlen($id) <= 100 && ctype_digit($id)) {
            $id = ltrim($id, '0');
            if ($id === '') {
                $id = null;
            }
        }
        if (!is_string($id) || $id === ''
            || !is_string($payload['gateway'] ?? null) || trim($payload['gateway']) === '' || strlen($payload['gateway']) > 100
            || !is_string($payload['transactionDate'] ?? null)
            || !is_string($payload['accountNumber'] ?? null) || !preg_match('/^[0-9]{4,30}$/D', $payload['accountNumber'])
            || !(is_string($payload['subAccount'] ?? null) || ($payload['subAccount'] ?? null) === null)
            || strlen((string) ($payload['subAccount'] ?? '')) > 100
            || !(is_string($payload['code'] ?? null) || ($payload['code'] ?? null) === null)
            || strlen((string) ($payload['code'] ?? '')) > 100
            || !is_string($payload['content'] ?? null) || strlen($payload['content']) > 2048
            || !in_array($payload['transferType'] ?? null, ['in', 'out'], true)
            || !(is_string($payload['description'] ?? null) || ($payload['description'] ?? null) === null)
            || strlen((string) ($payload['description'] ?? '')) > 1000
            || !is_int($payload['transferAmount'] ?? null) || $payload['transferAmount'] < 1 || $payload['transferAmount'] > 9999999999
            || !is_int($payload['accumulated'] ?? null) || $payload['accumulated'] < 0
            || !(is_string($payload['referenceCode'] ?? null) || ($payload['referenceCode'] ?? null) === null)
            || strlen((string) ($payload['referenceCode'] ?? '')) > 100) {
            throw ValidationException::withMessages(['payload' => 'Webhook is missing required SePay fields or contains invalid field types.']);
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $payload['transactionDate'], new DateTimeZone('Asia/Ho_Chi_Minh'));
        $dateErrors = DateTimeImmutable::getLastErrors();
        if (!$date || ($dateErrors !== false && ($dateErrors['warning_count'] || $dateErrors['error_count']))
            || $date->format('Y-m-d H:i:s') !== $payload['transactionDate']) {
            throw ValidationException::withMessages(['transactionDate' => 'SePay transactionDate must use YYYY-MM-DD HH:mm:ss Vietnam local time.']);
        }
        $receivedAt = Carbon::instance($date)->utc();
        if ($receivedAt->isFuture()) {
            throw ValidationException::withMessages(['transactionDate' => 'SePay transactionDate cannot be in the future.']);
        }

        $normalized = [
            'id' => $id,
            'gateway' => $payload['gateway'],
            'transactionDate' => $payload['transactionDate'],
            'accountNumber' => $payload['accountNumber'],
            'subAccount' => $payload['subAccount'] ?? null,
            'code' => $payload['code'] ?? null,
            'content' => $payload['content'],
            'transferType' => $payload['transferType'],
            'description' => $payload['description'] ?? null,
            'transferAmount' => $payload['transferAmount'],
            'accumulated' => $payload['accumulated'],
            'referenceCode' => $payload['referenceCode'] ?? null,
        ];
        ksort($normalized);
        $serialized = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $amount = RoomTypeV2Inventory::money($payload['transferAmount'] * 100);

        return [
            'id' => $id,
            'event_hash' => hash('sha256', $serialized),
            'amount' => $amount,
            'received_at' => $receivedAt->format('Y-m-d H:i:s'),
            'payload' => $payload,
            'serialized_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ];
    }

    private function findPayments($db, array $payload)
    {
        $matches = collect();
        $code = $payload['code'];
        if (is_string($code) && $code !== '') {
            $payment = $db->table('payment')->where('payment_method', 'banking')->where('transaction_id', $code)->first();
            if ($payment) {
                $matches->put($payment->id, $payment);
            }
        }
        $contentPayments = $db->table('payment')->where('payment_method', 'banking')->whereNotNull('transaction_id')
            ->whereRaw('LOCATE(transaction_id, ?) > 0', [$payload['content']])->get();
        foreach ($contentPayments as $payment) {
            if ($this->matchesReference($payload, $payment->transaction_id)) {
                $matches->put($payment->id, $payment);
            }
        }
        return $matches->values();
    }

    private function matchesReference(array $payload, ?string $reference): bool
    {
        if (!is_string($reference) || $reference === '') {
            return false;
        }
        if (is_string($payload['code']) && $payload['code'] !== '' && strcasecmp($payload['code'], $reference) === 0) {
            return true;
        }
        return preg_match('/(?<![A-Za-z0-9_-])' . preg_quote($reference, '/') . '(?![A-Za-z0-9_-])/i', $payload['content']) === 1;
    }

    private function applyToPayment($session, object $booking, $lines, array $types, object $payment, array $event): array
    {
        $db = $session->db;
        $bookingStatus = $booking->status;
        $payload = $event['payload'];
        $expectedAmountMatches = $payment->amount === $booking->total_price
            && RoomTypeV2Inventory::cents($payment->amount) === RoomTypeV2Inventory::cents($event['amount']);
        $paymentCreated = Carbon::parse($payment->created_at, config('app.timezone'))->utc();
        $receivedBeforePayment = Carbon::parse($event['received_at'], 'UTC')->lt($paymentCreated);
        $outcome = !$expectedAmountMatches ? 'amount_mismatch_reconciliation'
            : ($receivedBeforePayment ? 'received_before_payment_reconciliation' : null);

        if ($outcome === null && $payment->payment_status === 'success') {
            $manual = $db->table('manual_payment_verification')->where('payment_id', $payment->id)->first();
            $outcome = $manual && $manual->bank_reference === strtoupper((string) ($payload['referenceCode'] ?? ''))
                && $manual->received_amount === $event['amount']
                ? 'already_verified_manually'
                : 'already_successful_reconciliation';
        } elseif ($outcome === null && !in_array($payment->payment_status, ['pending', 'failed', 'expired'], true)) {
            $outcome = 'payment_not_collectible_reconciliation';
        }

        $late = !RoomTypeV2Inventory::holding($booking->status)
            || ($booking->status === 'pending' && (!$booking->expires_at
                || Carbon::parse($booking->expires_at, config('app.timezone'))->lte(now())));
        $otherSuccess = null;
        if ($outcome === null && !$late) {
            $otherSuccess = $db->table('payment')->where('booking_id', $booking->id)->where('id', '!=', $payment->id)
                ->where('payment_status', 'success')->lockForUpdate()->first();
            if ($otherSuccess) {
                $outcome = 'duplicate_payment_reconciliation';
            }
        }
        if ($outcome === null && $late) {
            $outcome = 'late_reconciliation';
        }
        if ($outcome === null) {
            $outcome = 'success';
        }

        $successFunds = $expectedAmountMatches && !$receivedBeforePayment
            && in_array($payment->payment_status, ['pending', 'failed', 'expired'], true);
        if ($successFunds) {
            if ($outcome === 'success') {
                RoomTypeV2Writer::commitment($session, $booking, $lines, $types, false);
                if ($booking->status === 'pending') {
                    RoomTypeV2Writer::duplicate($db, $booking, 'confirmed');
                    $db->table('booking')->where('id', $booking->id)->update(['status' => 'confirmed', 'confirmed_at' => now()]);
                    RoomTypeV2BookingAudit::record($db, $booking->id, $booking->status, 'confirmed', 'provider', null, 'sepay_webhook_verified');
                    $bookingStatus = 'confirmed';
                }
            }
            $db->table('payment')->where('id', $payment->id)->update(['payment_status' => 'success', 'paid_at' => $event['received_at']]);
        }

        $this->insertReceipt($db, $payment, $event, $outcome);
        if ($outcome === 'success' && !$db->table('booking_effect')->where('event_key', 'payment-confirmed:' . $payment->id)->exists()) {
            $db->table('booking_effect')->insert([
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'event_key' => 'payment-confirmed:' . $payment->id,
            ]);
        }
        $this->insertEvent($db, $event, $payment->id, $payment->transaction_id, $outcome);
        return ['duplicate' => false, 'outcome' => $outcome, 'booking_status' => $bookingStatus, 'payment_id' => $payment->id];
    }

    private function insertReceipt($db, object $payment, array $event, string $outcome): void
    {
        $db->table('payment_gateway_receipt')->insert([
            'payment_id' => $payment->id,
            'provider' => 'sepay',
            'provider_transaction_id' => $event['id'],
            'merchant_ref' => $payment->transaction_id,
            'amount' => $event['amount'],
            'response_code' => '00',
            'transaction_status' => '00',
            'outcome' => $outcome,
            'payload' => $event['serialized_payload'],
            'event_hash' => $event['event_hash'],
        ]);
    }

    private function recordUnmatched($db, array $event, ?string $reference, string $outcome): array
    {
        return $db->transaction(function () use ($db, $event, $reference, $outcome) {
            $existing = $db->table('sepay_webhook_event')->where('provider_transaction_id', $event['id'])->lockForUpdate()->first();
            if ($existing) {
                if (!hash_equals($existing->event_hash, $event['event_hash'])) {
                    throw ValidationException::withMessages(['id' => 'Conflicting payload for an already-recorded SePay transaction ID.']);
                }
                return ['duplicate' => true, 'outcome' => $existing->outcome, 'payment_id' => $existing->payment_id];
            }
            return $this->insertEvent($db, $event, null, $reference, $outcome);
        }, 3);
    }

    private function insertEvent($db, array $event, ?int $paymentId, ?string $reference, string $outcome): array
    {
        $db->table('sepay_webhook_event')->insert([
            'provider_transaction_id' => $event['id'],
            'payment_id' => $paymentId,
            'merchant_ref' => $reference,
            'amount' => $event['amount'],
            'transfer_type' => $event['payload']['transferType'],
            'outcome' => $outcome,
            'payload' => $event['serialized_payload'],
            'event_hash' => $event['event_hash'],
            'received_at' => $event['received_at'],
        ]);
        return ['duplicate' => false, 'outcome' => $outcome, 'payment_id' => $paymentId];
    }
}
