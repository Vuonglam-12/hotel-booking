<?php

namespace App\Services;

use App\Support\RoomTypeV2Inventory;

/** Separate, test-only module; no production binding or route uses this class. */
final class RoomAvailabilityServiceV2
{
    public function forType(string $connection, int $hotelId, int $typeId, string $checkIn, string $checkOut): array
    {
        $session = RoomTypeV2Inventory::open($connection);
        $session->hotel($hotelId);
        $type = $session->types($hotelId, [$typeId])[$typeId];
        return $session->inspect($type, $checkIn, $checkOut);
    }
}
