<?php

namespace App\Services\Itinerary;

use App\Models\Itinerary;
use Illuminate\Http\Exceptions\HttpResponseException;

final class WriteGuard
{
    public function legacyEndpoint(Itinerary $trip): void
    {
        $code = (int) ($trip->schema_version ?? 1) === 2 ? 'use_itinerary_v2_api' : 'legacy_itinerary_read_only';
        throw new HttpResponseException(response()->json(['message' => 'Lịch cũ chỉ được đọc; thay đổi lịch mới phải dùng API v2.', 'code' => $code], 409));
    }

    public function revision(Itinerary $trip, int $expected): void
    {
        abort_unless((int) $trip->schema_version === 2, 409, 'Lịch cũ chỉ được đọc.');
        abort_unless((int) $trip->revision === $expected, 409, 'Phiên bản lịch đã thay đổi. Hãy tải lại trước khi ghi.');
    }
}
