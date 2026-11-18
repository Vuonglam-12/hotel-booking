<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Support\RoomTypeV2Integration;
use App\Support\RoomTypeV2TestDatabase;

/** Not registered in production config. Explicitly activated by isolated integration tests only. */
class RoomTypeV2PreparedProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (!config('roomtype-v2.prepared_routes',false)) return;
        $db=RoomTypeV2TestDatabase::connection('mysql');
        foreach (['payment_gateway_receipt','payment_gateway_unmatched_event','booking_effect','room_type_image','room_type_legacy_map'] as $table) {
            if (!$db->getSchemaBuilder()->hasTable($table)) throw new \RuntimeException('Prepared routing requires all schema prerequisites.');
        }
        if (!$db->getSchemaBuilder()->hasColumn('booking','active_duplicate_key')) throw new \RuntimeException('Active Booking constraint must be migrated first.');
        RoomTypeV2Integration::backend();
        if (config('roomtype-v2.integration_complete', false)) RoomTypeV2Integration::complete();
        if (config('roomtype-v2.final_integration', false)) RoomTypeV2Integration::finalize();
    }
}
