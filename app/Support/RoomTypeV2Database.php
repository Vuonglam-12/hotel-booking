<?php

namespace App\Support;

/** Two independent gates. A test descriptor can never authorize production access. */
final class RoomTypeV2Database
{
    public static function connection(string $name): \Illuminate\Database\Connection
    {
        if (config('roomtype-v2.production_manifest')===null) return RoomTypeV2TestDatabase::connection($name);
        foreach (['integration_complete','final_integration','delivery_safety'] as $flag) {
            if (config('roomtype-v2.'.$flag)!==true) throw new \RuntimeException('Production access requires the complete verified V2 lifecycle and delivery policy.');
        }
        return RoomTypeV2ProductionGuard::connection($name);
    }
}
