<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/** Intentionally absent from config/app.php. Register only in a separately approved deployment. */
class RoomTypeV2ProductionProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (config('roomtype-v2.production_manifest')===null) throw new \RuntimeException('Production V2 provider requires an approved deployment manifest.');
        $manifest=\App\Support\RoomTypeV2ProductionGuard::manifest();
        if (($manifest['session_timezone']??null)!=='+00:00' || ($manifest['historical_timestamps_verified']??false)!==true) throw new \RuntimeException('Review historical timestamps before changing the runtime timezone.');
        config(['database.connections.mysql.timezone'=>'+00:00']);
        \App\Support\RoomTypeV2ProductionGuard::connection('mysql');
        $this->app->booted(function () {
            // Register after all legacy route providers, regardless of provider ordering.
            config(['roomtype-v2.staging_checkout'=>false,'roomtype-v2.integration_complete'=>true,
                'roomtype-v2.final_integration'=>true,'roomtype-v2.delivery_safety'=>true]);
            \App\Support\RoomTypeV2Integration::acceptance();
        });
    }
}
