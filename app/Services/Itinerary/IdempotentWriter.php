<?php

namespace App\Services\Itinerary;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

final class IdempotentWriter
{
    public function replay(Customer $customer, string $key, string $operation, array $payload): ?array
    {
        $previous = DB::table('itinerary_write_request')->where('customer_id',$customer->id)->where('request_key',$key)->first();
        if (!$previous) return null;
        $hash=hash('sha256',json_encode($this->canonical($payload),JSON_THROW_ON_ERROR));
        abort_unless($previous->operation===$operation && hash_equals($previous->payload_hash,$hash),409,'Idempotency-Key đã được dùng cho yêu cầu khác.');
        return ['status'=>$previous->response_status,'body'=>json_decode($previous->response_body??'null',true),'replayed'=>true];
    }

    public function run(Customer $customer, string $key, string $operation, array $payload, callable $write): array
    {
        $hash = hash('sha256', json_encode($this->canonical($payload), JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($customer, $key, $operation, $hash, $write) {
            // Serializes even the first use of a key. The UNIQUE constraint is a
            // second defense. All writes acquire Customer before Itinerary locks.
            Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $previous = DB::table('itinerary_write_request')->where('customer_id', $customer->id)->where('request_key', $key)->first();
            if ($previous) {
                abort_unless($previous->operation === $operation && hash_equals($previous->payload_hash, $hash), 409, 'Idempotency-Key đã được dùng cho yêu cầu khác.');

                return ['status' => $previous->response_status, 'body' => json_decode($previous->response_body ?? 'null', true), 'replayed' => true];
            }
            $result = $write();
            DB::table('itinerary_write_request')->insert([
                'customer_id' => $customer->id, 'request_key' => $key, 'operation' => $operation, 'payload_hash' => $hash,
                'itinerary_id' => $result['itinerary_id'] ?? null, 'response_status' => $result['status'],
                'response_body' => json_encode($result['body'], JSON_THROW_ON_ERROR),
            ]);

            return [...$result, 'replayed' => false];
        }, 3);
    }

    private function canonical(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as &$entry) {
            if (is_array($entry)) {
                $entry = $this->canonical($entry);
            }
        }

        return $value;
    }
}
